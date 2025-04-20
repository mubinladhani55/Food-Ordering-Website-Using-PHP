<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['restaurant_logged_in'])) {
    header("Location: restaurant_login.php");
    exit();
}

// Get restaurant information from session
$restaurant_id = $_SESSION['restaurant_id'];
$restaurant_name = $_SESSION['restaurant_name'];

// Database Connection
$host = 'localhost';
$db_username = 'root';
$db_password = '';
$database = 'restaurant_db';

$conn = mysqli_connect($host, $db_username, $db_password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Define pizza restaurant names
$pizzaRestaurants = ['PizzaHub', 'WorkOnFire', 'TandoorHut'];
$pizzaRestaurantsString = "'" . implode("','", $pizzaRestaurants) . "'";

// Handle order status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    
    $update_sql = "UPDATE restaurant_orders SET status = '$new_status' 
                  WHERE order_id = '$order_id' 
                  AND restaurant IN ($pizzaRestaurantsString)";
    
    if (mysqli_query($conn, $update_sql)) {
        $status_message = "Order status updated successfully!";
    } else {
        $error_message = "Error updating order status: " . mysqli_error($conn);
    }
}

// Handle settings update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_settings'])) {
    $_SESSION['items_per_page'] = $_POST['items_per_page'];
    $_SESSION['default_status'] = $_POST['default_status'];
    $_SESSION['refresh_rate'] = $_POST['refresh_rate'];
    $_SESSION['enable_notifications'] = isset($_POST['enable_notifications']) ? 1 : 0;
    $status_message = "Settings saved successfully!";
}

// Get settings from session or set defaults
$items_per_page = isset($_SESSION['items_per_page']) ? $_SESSION['items_per_page'] : 10;
$default_status = isset($_SESSION['default_status']) ? $_SESSION['default_status'] : 'all';
$refresh_rate = isset($_SESSION['refresh_rate']) ? $_SESSION['refresh_rate'] : 0;
$enable_notifications = isset($_SESSION['enable_notifications']) ? $_SESSION['enable_notifications'] : 1;

// Get order statistics
$total_orders_query = "SELECT COUNT(DISTINCT order_id) as count, SUM(price * quantity) as revenue 
                      FROM restaurant_orders 
                      WHERE restaurant IN ($pizzaRestaurantsString)";
$total_stats_result = mysqli_query($conn, $total_orders_query);
$total_stats = mysqli_fetch_assoc($total_stats_result);

$pending_orders_query = "SELECT COUNT(DISTINCT order_id) as count 
                        FROM restaurant_orders 
                        WHERE restaurant IN ($pizzaRestaurantsString) AND status = 'pending'";
$pending_stats_result = mysqli_query($conn, $pending_orders_query);
$pending_stats = mysqli_fetch_assoc($pending_stats_result);

$completed_orders_query = "SELECT COUNT(DISTINCT order_id) as count 
                          FROM restaurant_orders 
                          WHERE restaurant IN ($pizzaRestaurantsString) AND status = 'completed'";
$completed_stats_result = mysqli_query($conn, $completed_orders_query);
$completed_stats = mysqli_fetch_assoc($completed_stats_result);

$total_orders = $total_stats['count'] ?? 0;
$pending_orders = $pending_stats['count'] ?? 0;
$completed_orders = $completed_stats['count'] ?? 0;
$total_revenue = $total_stats['revenue'] ?? 0;

// Determine filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : $default_status;

// Build WHERE clause for orders query
$where_clause = "WHERE restaurant IN ($pizzaRestaurantsString)";
if ($status_filter != 'all') {
    $status_filter = mysqli_real_escape_string($conn, $status_filter);
    $where_clause .= " AND status = '$status_filter'";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT order_id) as total FROM restaurant_orders $where_clause";
$count_result = mysqli_query($conn, $count_sql);
$total_items = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_items / $items_per_page);

// Get distinct order IDs for the current page
$orders_sql = "SELECT DISTINCT order_id FROM restaurant_orders 
               $where_clause 
               ORDER BY created_at DESC 
               LIMIT $items_per_page OFFSET $offset";

$distinct_orders_result = mysqli_query($conn, $orders_sql);

// Function to get order details
function getOrderDetails($conn, $order_id, $pizzaRestaurantsString) {
    $order_id = mysqli_real_escape_string($conn, $order_id);
    $sql = "SELECT * FROM restaurant_orders 
           WHERE order_id = '$order_id' 
           AND restaurant IN ($pizzaRestaurantsString)
           ORDER BY id";
    $result = mysqli_query($conn, $sql);
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    return $items;
}

// Auto-refresh
$refresh_meta = $refresh_rate > 0 ? '<meta http-equiv="refresh" content="' . $refresh_rate . '">' : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo $refresh_meta; ?>
    <title>Pizza Dashboard - Foodie Express</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Your existing CSS styles here */
        body { background-color: #f8f9fa; }
        .dashboard-header { background-color: #fff; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        .stats-card { background-color: #fff; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 20px; height: 100%; transition: transform 0.3s ease; }
        .stats-card:hover { transform: translateY(-5px); }
        .order-card { background-color: #fff; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 20px; overflow: hidden; }
        .order-header { background-color: #f1f1f1; padding: 15px; border-bottom: 1px solid #ddd; }
        .order-items { padding: 15px; }
        .status-badge { font-size: 0.85rem; padding: 5px 10px; }
        .status-pending { background-color: #ffc107; color: #212529; }
        .status-processing { background-color: #17a2b8; color: white; }
        .status-completed { background-color: #28a745; color: white; }
        .status-cancelled { background-color: #dc3545; color: white; }
        .filter-btn { margin-right: 5px; margin-bottom: 5px; }
        .active-filter { background-color: #ff6b6b !important; border-color: #ff6b6b !important; }
        .pagination .page-item.active .page-link { background-color: #ff6b6b; border-color: #ff6b6b; }
        .pagination .page-link { color: #ff6b6b; }
        .btn-primary { background-color: #ff6b6b; border-color: #ff6b6b; }
        .btn-primary:hover { background-color: #ff5252; border-color: #ff5252; }
        .btn-outline-primary { color: #ff6b6b; border-color: #ff6b6b; }
        .btn-outline-primary:hover { background-color: #ff6b6b; border-color: #ff6b6b; color: white; }
        .navbar-brand { color: #ff6b6b !important; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="pizza_dashboard.php">
                <i class="fas fa-pizza-slice"></i> Pizza Dashboard - Foodie Express
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="pizza_dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pizza_menu.php">
                            <i class="fas fa-utensils me-1"></i> Pizza Menu
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user me-1"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="restaurant_logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-pizza-slice me-2"></i><?php echo htmlspecialchars($restaurant_name); ?> Pizza Dashboard</h2>
                    <p class="text-muted">Pizza orders summary and management</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#settingsModal">
                        <i class="fas fa-cog me-1"></i> Settings
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card text-center">
                    <i class="fas fa-pizza-slice fa-3x mb-3 text-primary"></i>
                    <h3><?php echo $total_orders; ?></h3>
                    <p class="text-muted mb-0">Total Pizza Orders</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card text-center">
                    <i class="fas fa-hourglass-half fa-3x mb-3 text-warning"></i>
                    <h3><?php echo $pending_orders; ?></h3>
                    <p class="text-muted mb-0">Pending Pizzas</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card text-center">
                    <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                    <h3><?php echo $completed_orders; ?></h3>
                    <p class="text-muted mb-0">Completed Pizzas</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card text-center">
                    <i class="fas fa-rupee-sign fa-3x mb-3 text-info"></i>
                    <h3>₹<?php echo number_format($total_revenue, 2); ?></h3>
                    <p class="text-muted mb-0">Pizza Revenue</p>
                </div>
            </div>
        </div>

        <!-- Status Messages -->
        <?php if (isset($status_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $status_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Orders Section -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h4 class="mb-0"><i class="fas fa-pizza-slice me-2"></i>Pizza Order Management</h4>
                    </div>
                    <div class="col-md-6 text-md-end mt-2 mt-md-0">
                        <div class="btn-group" role="group">
                            <a href="?status=all" class="btn btn-outline-primary filter-btn <?php echo $status_filter == 'all' ? 'active-filter' : ''; ?>">
                                All
                            </a>
                            <a href="?status=pending" class="btn btn-outline-primary filter-btn <?php echo $status_filter == 'pending' ? 'active-filter' : ''; ?>">
                                Pending
                            </a>
                            <a href="?status=processing" class="btn btn-outline-primary filter-btn <?php echo $status_filter == 'processing' ? 'active-filter' : ''; ?>">
                                Processing
                            </a>
                            <a href="?status=completed" class="btn btn-outline-primary filter-btn <?php echo $status_filter == 'completed' ? 'active-filter' : ''; ?>">
                                Completed
                            </a>
                            <a href="?status=cancelled" class="btn btn-outline-primary filter-btn <?php echo $status_filter == 'cancelled' ? 'active-filter' : ''; ?>">
                                Cancelled
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <?php if (mysqli_num_rows($distinct_orders_result) > 0): ?>
                    <?php while ($order_row = mysqli_fetch_assoc($distinct_orders_result)): ?>
                        <?php 
                            $order_id = $order_row['order_id'];
                            $order_items = getOrderDetails($conn, $order_id, $pizzaRestaurantsString);
                            
                            if (empty($order_items)) continue; // Skip if no items found
                            
                            $order_status = $order_items[0]['status'] ?? 'pending';
                            $order_total = 0;
                            foreach ($order_items as $item) {
                                $order_total += ($item['price'] * $item['quantity']);
                            }
                            $order_date = $order_items[0]['created_at'] ?? '';
                        ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <h5 class="mb-1">Pizza Order #<?php echo htmlspecialchars($order_id); ?></h5>
                                        <small class="text-muted"><?php echo date('F j, Y, g:i a', strtotime($order_date)); ?></small>
                                    </div>
                                    <div class="col-md-3 text-md-center my-2 my-md-0">
                                        <span class="badge status-badge status-<?php echo strtolower($order_status); ?>">
                                            <?php echo ucfirst($order_status); ?>
                                        </span>
                                    </div>
                                    <div class="col-md-2 text-md-end">
                                        <h6 class="mb-0">Total: ₹<?php echo number_format($order_total, 2); ?></h6>
                                    </div>
                                    <div class="col-md-3 text-md-end mt-2 mt-md-0">
                                        <button class="btn btn-sm btn-outline-primary me-1" type="button" data-bs-toggle="collapse" data-bs-target="#orderDetails<?php echo $order_id; ?>">
                                            <i class="fas fa-eye me-1"></i> Details
                                        </button>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatus<?php echo $order_id; ?>">
                                            <i class="fas fa-edit me-1"></i> Update
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="collapse" id="orderDetails<?php echo $order_id; ?>">
                                <div class="order-items">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Pizza</th>
                                                    <th>Price</th>
                                                    <th>Quantity</th>
                                                    <th>Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($order_items as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                        <td>₹<?php echo number_format($item['price'], 2); ?></td>
                                                        <td><?php echo $item['quantity']; ?></td>
                                                        <td>₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th colspan="3" class="text-end">Total:</th>
                                                    <th>₹<?php echo number_format($order_total, 2); ?></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Update Status Modal -->
                            <div class="modal fade" id="updateStatus<?php echo $order_id; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Update Pizza Order Status</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <div class="modal-body">
                                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">
                                                <div class="mb-3">
                                                    <label for="new_status" class="form-label">Status</label>
                                                    <select class="form-select" name="new_status" id="new_status" required>
                                                        <option value="pending" <?php echo $order_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="processing" <?php echo $order_status == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                        <option value="completed" <?php echo $order_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                        <option value="cancelled" <?php echo $order_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?status=<?php echo $status_filter; ?>&page=<?php echo $page-1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?status=<?php echo $status_filter; ?>&page=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?status=<?php echo $status_filter; ?>&page=<?php echo $page+1; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center py-5">
                        <img src="https://cdn-icons-png.flaticon.com/512/6978/6978255.png" alt="No Pizza Orders" style="width: 80px; opacity: 0.5;" class="mb-3">
                        <h4>No Pizza Orders Found</h4>
                        <p class="text-muted">There are no pizza orders matching your filter criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pizza Dashboard Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Items Per Page</label>
                            <select class="form-select" name="items_per_page">
                                <option value="10" <?php echo $items_per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $items_per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $items_per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $items_per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Default Order Status Filter</label>
                            <select class="form-select" name="default_status">
                                <option value="all" <?php echo $default_status == 'all' ? 'selected' : ''; ?>>All Orders</option>
                                <option value="pending" <?php echo $default_status == 'pending' ? 'selected' : ''; ?>>Pending Orders</option>
                                <option value="processing" <?php echo $default_status == 'processing' ? 'selected' : ''; ?>>Processing Orders</option>
                                <option value="completed" <?php echo $default_status == 'completed' ? 'selected' : ''; ?>>Completed Orders</option>
                                <option value="cancelled" <?php echo $default_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled Orders</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Refresh Rate (seconds)</label>
                            <select class="form-select" name="refresh_rate">
                                <option value="0" <?php echo $refresh_rate == 0 ? 'selected' : ''; ?>>Manual Only</option>
                                <option value="30" <?php echo $refresh_rate == 30 ? 'selected' : ''; ?>>30 seconds</option>
                                <option value="60" <?php echo $refresh_rate == 60 ? 'selected' : ''; ?>>1 minute</option>
                                <option value="300" <?php echo $refresh_rate == 300 ? 'selected' : ''; ?>>5 minutes</option>
                            </select>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="notificationToggle" name="enable_notifications" <?php echo $enable_notifications ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="notificationToggle">Enable Notifications</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-3 mt-5">
        <p>&copy; 2025 Foodie Express. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
mysqli_close($conn);
?>