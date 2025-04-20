<?php
session_start();

// Load Stripe PHP library
require_once 'vendor/autoload.php';

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "restaurant_db";

// Stripe API configuration - USE YOUR ACTUAL KEYS HERE
$stripeSecretKey = 'sk_test_51R7ZyLEd5cGIgDPDfo3DUrN3gUAyu8g41EFGBnHWf1qZId4UKnqasdPc6nWjrmg5SnumrAi2ll1V0BbSADcnuPX200iMfPtWnT';
$stripePublishableKey = 'pk_test_51R7ZyLEd5cGIgDPD0Mm2DHhY3WFU6xyZkkS3yC9HtYQ1D7nlpNmTMCbiXt0jSBs4WenOnW7mkL8RNJBD42beXSXo005lDlBO6k';

// Initialize Stripe with the API key
\Stripe\Stripe::setApiKey($stripeSecretKey);

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get restaurant from URL parameter
$restaurant = isset($_GET['restaurant']) ? $_GET['restaurant'] : 'WorkOnFire';

// Map restaurant names to display names and database values
$restaurantMap = [
    'WorkOnFire' => ['display' => 'Work on Fire', 'db' => 'ChineseRestaurant'],
    'PizzaHub' => ['display' => 'Pizza Hub', 'db' => 'PizzaHub'],
    'TandoorHut' => ['display' => 'Tandoor Hut', 'db' => 'TandoorHut']
];

$currentRestaurantName = $restaurantMap[$restaurant]['display'] ?? 'Restaurant';
$restaurantDbValue = $restaurantMap[$restaurant]['db'] ?? 'ChineseRestaurant';

// Initialize variables
$orderId = isset($_POST['order_id']) ? $_POST['order_id'] : (isset($_GET['order_id']) ? $_GET['order_id'] : '');
$errorMessage = '';
$orderItems = [];
$subtotal = 0;
$tax = 0;
$total = 0;

// Get order details
if (!empty($orderId)) {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
    
    $sql = "SELECT * FROM restaurant_orders 
            WHERE order_id = ? AND restaurant = ? AND status = 'pending'
            AND (user_id = ? OR (user_id IS NULL AND ? IS NULL))";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $orderId, $restaurantDbValue, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orderItems[] = $row;
            $subtotal += $row['price'] * $row['quantity'];
        }
        $tax = $subtotal * 0.10;
        $total = $subtotal + $tax;
    } else {
        $errorMessage = "No pending items found in your order.";
    }
} else {
    $errorMessage = "No order ID provided.";
}

// Process payment
$paymentSuccess = false;
$orderNumber = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $cardName = trim($_POST['card_name']);
    $address = trim($_POST['address']);
    $stripeToken = $_POST['stripeToken'];
    
    if (empty($cardName) || empty($address) || empty($stripeToken)) {
        $errorMessage = "All fields are required.";
    } else {
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
                    'description' => "Order #" . $orderId . " from " . $currentRestaurantName,
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
            $updateSql = "UPDATE restaurant_orders SET status = 'completed', payment_id = ? WHERE order_id = ? AND restaurant = ? AND status = 'pending'";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("sss", $paymentId, $orderId, $restaurantDbValue);
            
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
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $insertStmt = $conn->prepare($insertPaymentSql);
                $insertStmt->bind_param(
                    "ssissssdddss",
                    $paymentId,
                    $orderId,
                    $userId,
                    $userEmail,
                    $restaurantDbValue,
                    $cardName,
                    $address,
                    $subtotal,
                    $tax,
                    $total,
                    $paymentStatus,
                    $orderItemsJson
                );
                
                if ($insertStmt->execute()) {
                    $paymentSuccess = true;
                    unset($_SESSION['current_order_id']);
                } else {
                    $errorMessage = "Payment successful but failed to save payment details: " . $conn->error;
                }
            } else {
                $errorMessage = "Payment successful but failed to update order status: " . $conn->error;
            }
        } catch (\Stripe\Exception\CardException $e) {
            $errorMessage = "Card error: " . $e->getMessage();
        } catch (\Stripe\Exception\RateLimitException $e) {
            $errorMessage = "Too many requests. Please try again later.";
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            $errorMessage = "Invalid request: " . $e->getMessage();
        } catch (\Stripe\Exception\AuthenticationException $e) {
            $errorMessage = "Authentication error: " . $e->getMessage();
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            $errorMessage = "Network error: " . $e->getMessage();
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $errorMessage = "Stripe API error: " . $e->getMessage();
        } catch (\Exception $e) {
            $errorMessage = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - <?php echo htmlspecialchars($currentRestaurantName); ?></title>
    <script src="https://js.stripe.com/v3/"></script>
    <link rel="stylesheet" href="CSS/payment.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Checkout - <?php echo htmlspecialchars($currentRestaurantName); ?></h1>
            <p>Complete your payment to place your order</p>
        </div>
        
        <?php if ($paymentSuccess): ?>
            <div class="success-message">
                <h2>Payment Successful!</h2>
                <div class="order-number">Order #<?php echo htmlspecialchars($orderNumber); ?></div>
                <p>Thank you for your order. Your delicious food will be prepared shortly.</p>
                <a href="chinese_menu.php?restaurant=<?php echo htmlspecialchars($restaurant); ?>" class="submit-btn" style="margin-top: 20px;">Back to Menu</a>
            </div>
        <?php else: ?>
            <?php if (!empty($errorMessage)): ?>
                <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>
            
            <div class="payment-container">
                <!-- Order Summary Section -->
                <div class="order-summary">
                    <h2 class="section-title">Order Summary</h2>
                    
                    <?php if (empty($orderItems)): ?>
                        <p>No items in your order.</p>
                    <?php else: ?>
                        <?php foreach ($orderItems as $item): ?>
                            <div class="order-item">
                                <div class="item-details">
                                    <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?> × <?php echo htmlspecialchars($item['quantity']); ?></div>
                                    <div class="item-price">₹<?php echo number_format($item['price'], 2); ?> each</div>
                                </div>
                                <div class="item-total">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="order-total">
                            <div class="total-row">
                                <span>Subtotal:</span>
                                <span>₹<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="total-row">
                                <span>Tax (10%):</span>
                                <span>₹<?php echo number_format($tax, 2); ?></span>
                            </div>
                            <div class="total-row final">
                                <span>Total:</span>
                                <span>₹<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <a href="chinese_menu.php?restaurant=<?php echo htmlspecialchars($restaurant); ?>" style="display: inline-block; margin-top: 20px; color: var(--primary-color);">← Back to Menu</a>
                </div>
                
                <!-- Payment Form Section -->
                <div class="payment-form">
                    <h2 class="section-title">Payment Details</h2>
                    
                    <form id="payment-form" method="post" action="">
                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderId); ?>">
                        <input type="hidden" name="restaurant" value="<?php echo htmlspecialchars($restaurant); ?>">
                        <input type="hidden" name="stripeToken" id="stripeToken">
                        
                        <div class="form-group">
                            <label for="card_name">Cardholder Name</label>
                            <input type="text" id="card_name" name="card_name" placeholder="John Doe" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="card-element">Card Information</label>
                            <div id="card-element"></div>
                            <div id="card-errors" role="alert"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Delivery Address</label>
                            <textarea id="address" name="address" rows="3" placeholder="Enter your full delivery address" required></textarea>
                        </div>
                        
                        <button type="submit" name="submit_payment" id="submit-button" class="submit-btn">
                            Pay Now ₹<?php echo number_format($total, 2); ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Payment Success Popup -->
    <div id="success-popup" class="popup-overlay">
        <div class="popup-content">
            <div class="popup-icon">✓</div>
            <h2 class="popup-title">Payment Successful!</h2>
            <p>Your payment has been processed successfully. Your order is confirmed.</p>
            <button id="popup-close" class="submit-btn" style="margin-top: 20px;">Continue</button>
        </div>
    </div>
    
    <script>
        const stripe = Stripe('<?php echo $stripePublishableKey; ?>');
        const elements = stripe.elements();
        
        const cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#32325d',
                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                invalid: {
                    color: '#fa755a',
                    iconColor: '#fa755a'
                }
            }
        });
        
        cardElement.mount('#card-element');
        
        const form = document.getElementById('payment-form');
        const submitButton = document.getElementById('submit-button');
        const cardErrors = document.getElementById('card-errors');
        
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            
            submitButton.disabled = true;
            submitButton.textContent = 'Processing...';
            
            const {paymentMethod, error} = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
                billing_details: {
                    name: document.getElementById('card_name').value
                }
            });
            
            if (error) {
                cardErrors.textContent = error.message;
                submitButton.disabled = false;
                submitButton.textContent = 'Pay Now ₹<?php echo number_format($total, 2); ?>';
            } else {
                document.getElementById('stripeToken').value = paymentMethod.id;
                document.getElementById('success-popup').classList.add('show-popup');
            }
        });
        
        document.getElementById('popup-close').addEventListener('click', function() {
            document.getElementById('success-popup').classList.remove('show-popup');
            form.submit();
        });
    </script>
</body>
</html>