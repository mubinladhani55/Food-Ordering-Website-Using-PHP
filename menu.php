<?php
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

// Get menu items based on category
function getMenuItems($category) {
    global $conn;
    $sql = "SELECT * FROM menu_items WHERE category_id = (SELECT id FROM categories WHERE name = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get all categories
function getCategories() {
    global $conn;
    $sql = "SELECT * FROM categories";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

$categories = getCategories();
$activeCategory = isset($_GET['category']) ? $_GET['category'] : 'Lunch';
$menuItems = getMenuItems($activeCategory);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Menu System</title>
    <style>
        :root {
            --primary-color: #4285f4;
            --background-color: #f8f9fa;
            --card-background: #ffffff;
            --text-color: #333333;
            --border-color: #e0e0e0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--background-color);
        }

        .container {
            display: flex;
            padding: 20px;
            gap: 20px;
        }

        .main-content {
            flex: 1;
        }

        .search-bar {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--background-color);
            margin-bottom: 20px;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .category-card {
            background-color: var(--card-background);
            padding: 15px;
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.2s;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .category-card.active {
            background-color: var(--primary-color);
            color: white;
        }

        .category-card:hover {
            transform: translateY(-2px);
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .menu-item {
            background-color: var(--card-background);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            gap: 15px;
            border: 1px solid var(--border-color);
        }

        .menu-item img {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
        }

        .item-details {
            flex: 1;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .quantity-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
        }

        .quantity-input {
            width: 40px;
            text-align: center;
            border: none;
            background: transparent;
        }

        .cart-section {
            width: 300px;
            background-color: var(--card-background);
            padding: 20px;
            border-radius: 12px;
            position: sticky;
            top: 20px;
            height: calc(100vh - 40px);
            display: flex;
            flex-direction: column;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        .cart-item {
            display: flex;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .cart-item img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }

        .cart-total {
            border-top: 2px solid var(--border-color);
            padding-top: 20px;
            margin-top: auto;
        }

        .cart-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .payment-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            width: 100%;
            cursor: pointer;
            margin-top: 20px;
        }

        .category-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-content">
            <input type="text" class="search-bar" placeholder="Search Your Menu Here" id="searchInput">

            <div class="category-grid">
                <?php foreach ($categories as $category): ?>
                    <div class="category-card <?php echo $category['name'] === $activeCategory ? 'active' : ''; ?>" 
                         data-category="<?php echo $category['name']; ?>">
                        <div class="category-icon"><?php echo $category['icon']; ?></div>
                        <h3><?php echo $category['name']; ?></h3>
                        <p><?php echo $category['items_in_stock']; ?> Menu In Stock</p>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2><?php echo $activeCategory; ?> Menu</h2>
            <div class="menu-grid" id="menuGrid"></div>
        </div>

        <div class="cart-section">
            <h2>Invoice</h2>
            <div class="cart-items" id="cartItems"></div>
            <div class="cart-total">
                <div class="cart-total-row">
                    <span>Sub Total</span>
                    <span id="subtotal">₹0.00</span>
                </div>
                <div class="cart-total-row">
                    <span>Tax</span>
                    <span id="tax">₹0.00</span>
                </div>
                <div class="cart-total-row">
                    <strong>Total Payment</strong>
                    <strong id="total">₹0.00</strong>
                </div>
                <button class="payment-button">Credit Card</button>
            </div>
        </div>
    </div>

    <script>
        let cart = [];
        const TAX_RATE = 0.18; // 18% GST
        const menuData = <?php echo json_encode($menuItems); ?>;

        // Category Selection
        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('click', () => {
                const category = card.dataset.category;
                window.location.href = `?category=${category}`;
            });
        });

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            filterMenuItems(searchTerm);
        });

        function filterMenuItems(searchTerm) {
            const filteredItems = menuData.filter(item => 
                item.name.toLowerCase().includes(searchTerm) ||
                item.description.toLowerCase().includes(searchTerm)
            );
            displayMenuItems(filteredItems);
        }

        function updateCart(itemId, change) {
            const item = menuData.find(i => i.id === itemId);
            const cartItem = cart.find(i => i.id === itemId);

            if (cartItem) {
                cartItem.quantity += change;
                if (cartItem.quantity <= 0) {
                    cart = cart.filter(i => i.id !== itemId);
                }
            } else if (change > 0) {
                cart.push({
                    id: item.id,
                    name: item.name,
                    price: item.price,
                    image: item.image_path,
                    quantity: 1
                });
            }

            updateCartDisplay();
            displayMenuItems(menuData);
        }

        function updateCartDisplay() {
            const cartItems = document.getElementById('cartItems');
            cartItems.innerHTML = cart.map(item => `
                <div class="cart-item">
                    <img src="${item.image}" alt="${item.name}">
                    <div class="cart-item-details">
                        <h4>${item.name}</h4>
                        <p>₹${item.price} x ${item.quantity}</p>
                    </div>
                </div>
            `).join('');

            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const tax = subtotal * TAX_RATE;
            const total = subtotal + tax;

            document.getElementById('subtotal').textContent = `₹${subtotal.toFixed(2)}`;
            document.getElementById('tax').textContent = `₹${tax.toFixed(2)}`;
            document.getElementById('total').textContent = `₹${total.toFixed(2)}`;
        }

        function displayMenuItems(items) {
            const menuGrid = document.getElementById('menuGrid');
            menuGrid.innerHTML = items.map(item => `
                <div class="menu-item">
                    <img src="${item.image_path}" alt="${item.name}">
                    <div class="item-details">
                        <h3>${item.name}</h3>
                        <p>${item.description}</p>
                        <div class="price">₹${item.price}</div>
                        <div class="quantity-control">
                            <button class="quantity-btn minus" onclick="updateCart(${item.id}, -1)">-</button>
                            <input type="text" class="quantity-input" value="${cart.find(i => i.id === item.id)?.quantity || 0}" readonly>
                            <button class="quantity-btn plus" onclick="updateCart(${item.id}, 1)">+</button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Initial display
        displayMenuItems(menuData);
    </script>
</body>
</html>