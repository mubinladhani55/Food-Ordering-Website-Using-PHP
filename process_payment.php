<?php
session_start();

header('Content-Type: application/json');

// Load Stripe PHP library
require_once 'vendor/autoload.php';

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "restaurant_db";

// Stripe API configuration - USE YOUR ACTUAL KEYS HERE
$stripeSecretKey = 'sk_test_51R7ZyLEd5cGIgDPDfo3DUrN3gUAyu8g41EFGBnHWf1qZId4UKnqasdPc6nWjrmg5SnumrAi2ll1V0BbSADcnuPX200iMfPtWnT';

// Initialize Stripe with the API key
\Stripe\Stripe::setApiKey($stripeSecretKey);

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]);
    exit;
}

// Check if this is a POST request with payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = isset($_POST['order_id']) ? $_POST['order_id'] : '';
    $restaurant = isset($_POST['restaurant']) ? $_POST['restaurant'] : '';
    $cardName = trim($_POST['card_name']);
    $address = trim($_POST['address']);
    $stripeToken = $_POST['stripeToken'];
    
    // Validate inputs
    if (empty($orderId) || empty($restaurant) || empty($cardName) || empty($address) || empty($stripeToken)) {
        echo json_encode(['success' => false, 'message' => "All fields are required."]);
        exit;
    }
    
    // Get user ID from session
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
    
    // Get order details
    $sql = "SELECT * FROM restaurant_orders 
            WHERE order_id = ? AND restaurant = ? AND status = 'pending'
            AND (user_id = ? OR (user_id IS NULL AND ? IS NULL))";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $orderId, $restaurant, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => "No pending order found."]);
        exit;
    }
    
    // Calculate order totals
    $orderItems = [];
    $subtotal = 0;
    
    while ($row = $result->fetch_assoc()) {
        $orderItems[] = $row;
        $subtotal += $row['price'] * $row['quantity'];
    }
    
    $tax = $subtotal * 0.10;
    $total = $subtotal + $tax;
    
    try {
        $orderNumber = 'ORD-' . strtoupper(substr(uniqid(), -6));
        
        if (strpos($stripeToken, 'tok_') === 0 || strpos($stripeToken, 'pm_') === 0) {
            // Real Stripe payment
            $amountInCents = round($total * 100);
            
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => 'inr',
                'payment_method' => $stripeToken,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'description' => "Order #" . $orderId . " from " . $restaurant,
                'metadata' => [
                    'order_id' => $orderId,
                    'restaurant' => $restaurant
                ]
            ]);
            
            $paymentId = $paymentIntent->id;
            $paymentStatus = $paymentIntent->status;
        } else {
            // Simulated payment
            $paymentId = $stripeToken;
            $paymentStatus = 'completed';
            
            $logFile = fopen("stripe_payment_logs.txt", "a");
            fwrite($logFile, date("Y-m-d H:i:s") . " - Simulated payment for Order ID: " . $orderId . " - Amount: ₹" . number_format($total, 2) . " - Token: " . $stripeToken . "\n");
            fclose($logFile);
        }
        
        // Update order status
        $updateSql = "UPDATE restaurant_orders SET status = 'completed', payment_id = ? WHERE order_id = ? AND status = 'pending'";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ss", $paymentId, $orderId);
        
        if ($updateStmt->execute()) {
            // Get user email if logged in
            $userEmail = '';
            if ($userId) {
                $emailSql = "SELECT email FROM users WHERE id = ?";
                $emailStmt = $conn->prepare($emailSql);
                $emailStmt->bind_param("i", $userId);
                $emailStmt->execute();
                $emailResult = $emailStmt->get_result();
                if ($emailResult->num_rows > 0) {
                    $userData = $emailResult->fetch_assoc();
                    $userEmail = $userData['email'];
                }
            }
            
            // Prepare order items as JSON
            $orderItemsJson = json_encode($orderItems);
            
            // Insert payment details
            $insertPaymentSql = "INSERT INTO payment_details (
                payment_id, order_id, user_id, email, restaurant, 
                cardholder_name, delivery_address, subtotal, tax, total, 
                payment_status, order_items, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $insertStmt = $conn->prepare($insertPaymentSql);
            $insertStmt->bind_param(
                "ssissssdddss",
                $paymentId,
                $orderId,
                $userId,
                $userEmail,
                $restaurant,
                $cardName,
                $address,
                $subtotal,
                $tax,
                $total,
                $paymentStatus,
                $orderItemsJson
            );
            
            if ($insertStmt->execute()) {
                // Clear session order ID
                unset($_SESSION['current_order_id']);
                
                // Return success response
                echo json_encode([
                    'success' => true, 
                    'message' => "Payment successful", 
                    'orderNumber' => $orderNumber
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => "Payment successful but failed to save payment details: " . $conn->error
                ]);
            }
        } else {
            echo json_encode([
                'success' => false, 
                'message' => "Payment successful but failed to update order status: " . $conn->error
            ]);
        }
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'message' => "Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => "Invalid request"]);
}