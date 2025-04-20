<?php
session_start();

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "restaurant_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the restaurant from the URL parameter
$restaurant = isset($_GET['restaurant']) ? $_GET['restaurant'] : 'PizzaHub';

// Map restaurant names to their display names
$restaurantDisplayNames = [
    'WorkOnFire' => 'Work on Fire',
    'PizzaHub' => 'PizzaHub',
    'TandoorHut' => 'Tandoor Hut'
];

// Get the display name of the current restaurant
$currentRestaurantName = $restaurantDisplayNames[$restaurant] ?? 'Restaurant';

// Function to update category item count
function updateCategoryItemCount($categoryId) {
    global $conn;
    $sql = "UPDATE pizza_category SET items_count = (
        SELECT COUNT(*) FROM pizza_menu_item 
        WHERE category_id = ?
    ) WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $categoryId, $categoryId);
    return $stmt->execute();
}

// Get menu items based on category
function getMenuItems($categoryId = null) {
    global $conn;
    
    if ($categoryId) {
        $sql = "SELECT m.*, c.name as category_name, c.id as category_id 
                FROM pizza_menu_item m 
                JOIN pizza_category c ON m.category_id = c.id 
                WHERE c.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $categoryId);
    } else {
        // If no category selected, get all items
        $sql = "SELECT m.*, c.name as category_name, c.id as category_id 
                FROM pizza_menu_item m 
                JOIN pizza_category c ON m.category_id = c.id";
        $stmt = $conn->prepare($sql);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get all categories with their items count
function getCategories() {
    global $conn;
    $sql = "SELECT c.*, (
                SELECT COUNT(*) 
                FROM pizza_menu_item 
                WHERE category_id = c.id
            ) as items_count 
            FROM pizza_category c";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to save an order to the database with user ID
function saveOrderItem($orderId, $restaurant, $itemId, $itemName, $quantity, $price) {
    global $conn;
    
    // Get user ID from session if logged in
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
    
    // Check if the item already exists in this order
    $checkSql = "SELECT id, quantity FROM restaurant_orders 
                WHERE order_id = ? AND restaurant = ? AND item_id = ? AND (user_id = ? OR (user_id IS NULL AND ? IS NULL))";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ssiii", $orderId, $restaurant, $itemId, $userId, $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        // Item exists, update quantity
        $row = $result->fetch_assoc();
        $newQuantity = $row['quantity'] + $quantity;
        
        // If quantity becomes 0 or negative, delete the item
        if ($newQuantity <= 0) {
            $deleteSql = "DELETE FROM restaurant_orders WHERE id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bind_param("i", $row['id']);
            return $deleteStmt->execute();
        } else {
            // Update quantity
            $updateSql = "UPDATE restaurant_orders SET quantity = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ii", $newQuantity, $row['id']);
            return $updateStmt->execute();
        }
    } else if ($quantity > 0) {
        // New item, insert with user ID
        $insertSql = "INSERT INTO restaurant_orders 
                      (order_id, restaurant, item_id, item_name, quantity, price, user_id) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("ssisidi", $orderId, $restaurant, $itemId, $itemName, $quantity, $price, $userId);
        return $insertStmt->execute();
    }
    
    return false;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'get_menu_items':
                $categoryId = isset($_POST['category_id']) ? $_POST['category_id'] : null;
                $menuItems = getMenuItems($categoryId);
                $response['success'] = true;
                $response['data'] = $menuItems;
                break;
                
            case 'add_item':
                $stmt = $conn->prepare("INSERT INTO pizza_menu_item (name, description, price, category_id, spice_level, image_path) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdiis", $_POST['name'], $_POST['description'], $_POST['price'], $_POST['category_id'], $_POST['spice_level'], $_POST['image_path']);
                
                if ($stmt->execute()) {
                    updateCategoryItemCount($_POST['category_id']);
                    $response['success'] = true;
                    $response['message'] = 'Item added successfully';
                }
                break;
                
            case 'edit_item':
                $stmt = $conn->prepare("UPDATE pizza_menu_item SET name=?, description=?, price=?, category_id=?, spice_level=?, image_path=? WHERE id=?");
                $stmt->bind_param("ssdissi", $_POST['name'], $_POST['description'], $_POST['price'], $_POST['category_id'], $_POST['spice_level'], $_POST['image_path'], $_POST['item_id']);
                
                if ($stmt->execute()) {
                    if (isset($_POST['old_category_id']) && $_POST['old_category_id'] != $_POST['category_id']) {
                        updateCategoryItemCount($_POST['old_category_id']);
                    }
                    updateCategoryItemCount($_POST['category_id']);
                    $response['success'] = true;
                    $response['message'] = 'Item updated successfully';
                }
                break;
                
            case 'delete_item':
                $stmt = $conn->prepare("DELETE FROM pizza_menu_item WHERE id=?");
                $stmt->bind_param("i", $_POST['item_id']);
                
                if ($stmt->execute()) {
                    updateCategoryItemCount($_POST['category_id']);
                    $response['success'] = true;
                    $response['message'] = 'Item deleted successfully';
                }
                break;
                
            case 'update_cart_item':
                $orderId = isset($_POST['order_id']) ? $_POST['order_id'] : uniqid('order_');
                $restaurant = "PizzaHub"; // Fixed restaurant name for Pizza
                $itemId = $_POST['item_id'];
                $itemName = $_POST['item_name'];
                $quantity = $_POST['quantity'];
                $price = $_POST['price'];
                
                if (saveOrderItem($orderId, $restaurant, $itemId, $itemName, $quantity, $price)) {
                    $response['success'] = true;
                    $response['message'] = 'Order updated successfully';
                    $response['order_id'] = $orderId;
                    $response['user_id'] = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                } else {
                    $response['message'] = 'Failed to update order';
                }
                break;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Initial page load
$categories = getCategories();
$activeCategory = isset($_GET['category']) ? (int)$_GET['category'] : ($categories[0]['id'] ?? null);
$menuItems = getMenuItems($activeCategory);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentRestaurantName; ?> Menu</title>
    <link rel="stylesheet" href="CSS/pizza_menu.css">
    <style>
        .back-button {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background-color: #f8f9fa;
            color: #212529;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        .back-button:hover {
            background-color: #e9ecef;
        }
        .restaurant-info {
            margin-top: 10px;
            margin-bottom: 20px;
        }
        
        /* Cart Button Styles */
        .cart-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 1000;
            transition: transform 0.2s;
        }
        
        .cart-button:hover {
            transform: scale(1.1);
        }
        
        .cart-icon {
            font-size: 24px;
        }
        
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ff4d4d;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 2000;
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: var(--card-background);
            margin: 50px auto;
            padding: 20px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #777;
            transition: color 0.2s;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .modal-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        
        .empty-cart-message {
            text-align: center;
            padding: 30px;
            color: #777;
        }
        
        .search-book-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            width: 100%;
        }

        .search-bar {
            width: 70%;
            margin-bottom: 0;
        }

        .book-table-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.2s;
            text-align: center;
        }

        .book-table-btn:hover {
            opacity: 0.9;
        }
        
        .user-info {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="user-info">
            Logged in as User ID: <?php echo $_SESSION['user_id']; ?>
        </div>
    <?php endif; ?>
    
    <div class="header">
        <h1><?php echo $currentRestaurantName; ?> Menu</h1>
        <p>Authentic Indian Pizzas, Fresh Ingredients</p>
        <a href="index.php" class="back-button">Back to Home</a>
        <div class="restaurant-info">
            <p>You are viewing menu items from <?php echo $currentRestaurantName; ?></p>
        </div>
    </div>

    <div class="container">
        <div class="main-content">
            <div class="search-book-container">
                <input type="text" class="search-bar" placeholder="Search menu items..." id="searchInput">
                <a href="book_table.php?restaurant=<?php echo $restaurant; ?>" class="book-table-btn">Book a Table</a>
            </div>

            <div class="category-grid">
                <?php foreach ($categories as $category): ?>
                    <div class="category-card <?php echo $category['id'] === $activeCategory ? 'active' : ''; ?>"
                         data-category-id="<?php echo $category['id']; ?>">
                        <div class="category-icon"><?php echo $category['icon']; ?></div>
                        <h3><?php echo $category['name']; ?></h3>
                        <p><?php echo $category['items_count']; ?> Items</p>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2><?php echo $categories[array_search($activeCategory, array_column($categories, 'id'))]['name'] ?? 'All Items'; ?></h2>
            <div class="menu-grid" id="menuGrid"></div>
        </div>
    </div>
    
    <!-- Cart Button -->
    <div class="cart-button" id="cartButton">
        <div class="cart-icon">🛒</div>
        <div class="cart-badge" id="cartBadge">0</div>
    </div>
    
    <!-- Cart Modal -->
    <div class="modal" id="cartModal">
        <div class="modal-content">
            <span class="close-modal" id="closeModal">&times;</span>
            <h2 class="modal-title">Your Order</h2>
            <div id="modalCartItems"></div>
            <div class="cart-total" id="modalCartTotal">
                <div class="cart-total-row">
                    <span>Sub Total</span>
                    <span id="modalSubtotal">Rs0.00</span>
                </div>
                <div class="cart-total-row">
                    <span>Tax (18%)</span>
                    <span id="modalTax">Rs0.00</span>
                </div>
                <div class="cart-total-row">
                    <strong>Total Payment</strong>
                    <strong id="modalTotal">Rs0.00</strong>
                </div>
                <form id="orderForm" action="pizza_payment.php" method="POST">
                    <input type="hidden" name="order_id" id="orderIdInput" value="<?php echo isset($_SESSION['current_order_id']) ? $_SESSION['current_order_id'] : ''; ?>">
                    <input type="hidden" name="restaurant" value="<?php echo $restaurant; ?>">
                    <button type="submit" class="payment-button">Place Order</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const menuData = <?php echo json_encode($menuItems); ?>;
        const currentCategory = <?php echo $activeCategory ?: 'null'; ?>;
        const currentRestaurant = "<?php echo $restaurant; ?>";
        const currentRestaurantDisplay = "<?php echo $currentRestaurantName; ?>";
        const loggedInUserId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
    </script>
    <script src="JS/pizza_menu.js"></script>
</body>
</html>