<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Foodie Express - My Orders</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="CSS/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    .orders-container {
      max-width: 900px;
      margin: 40px auto;
      padding: 30px;
      background-color: #fff;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    }
    
    .orders-header {
      margin-bottom: 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .orders-bg {
      background-color: #f8f9fa;
      min-height: 100vh;
      padding: 20px 0;
    }
    
    .order-card {
      margin-bottom: 20px;
      border-left: 4px solid #0d6efd;
    }
    
    .status-filter {
      margin-bottom: 20px;
    }
    
    .status-delivered {
      color: #198754;
    }
    
    .status-processing {
      color: #ffc107;
    }
    
    .status-cancelled {
      color: #dc3545;
    }
    
    .status-pending {
      color: #0d6efd;
    }
    
    .order-item {
      margin-bottom: 8px;
      display: flex;
      justify-content: space-between;
    }
    
    .pagination-container {
      margin-top: 30px;
    }
    
    .no-orders {
      text-align: center;
      padding: 40px 20px;
    }
    
    .no-orders i {
      font-size: 4rem;
      color: #6c757d;
      margin-bottom: 20px;
    }
  </style>
</head>
<body class="orders-bg">
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
      <a class="navbar-brand" href="index.php">
        <i class="fas fa-utensils"></i> Foodie Express
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="index.php">Home</a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
              Restaurants
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="chinese_menu.php?restaurant=WorkOnFire">Work on Fire</a></li>
              <li><a class="dropdown-item" href="chinese_menu.php?restaurant=PizzaHub">Pizza Hub</a></li>
              <li><a class="dropdown-item" href="chinese_menu.php?restaurant=TandoorHut">Tandoor Hut</a></li>
            </ul>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="cart.php">
              <i class="fas fa-shopping-cart"></i> Cart
              <?php 
                session_start();
                if(isset($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
                  echo '<span class="badge bg-danger">'.count($_SESSION['cart']).'</span>';
                }
              ?>
            </a>
          </li>
          <?php if(isset($_SESSION['user_id'])): ?>
            <li class="nav-item">
              <a class="nav-link active" href="myorders.php">My Orders</a>
            </li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                <i class="fas fa-user"></i> <?php echo $_SESSION['username']; ?>
              </a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
              </ul>
            </li>
          <?php else: ?>
            <li class="nav-item">
              <a class="nav-link" href="login.php">Login</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="register.php">Register</a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="container orders-container">
    <div class="orders-header">
      <h2><i class="fas fa-list-alt"></i> My Orders</h2>
      <a href="index.php" class="btn btn-outline-primary">
        <i class="fas fa-utensils"></i> Order More Food
      </a>
    </div>

    <!-- Status Filter -->
    <div class="status-filter">
      <div class="btn-group" role="group">
        <a href="myorders.php" class="btn <?php echo !isset($_GET['status']) ? 'btn-primary' : 'btn-outline-primary'; ?>">All Orders</a>
        <a href="myorders.php?status=pending" class="btn <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'btn-primary' : 'btn-outline-primary'; ?>">Pending</a>
        <a href="myorders.php?status=processing" class="btn <?php echo isset($_GET['status']) && $_GET['status'] == 'processing' ? 'btn-primary' : 'btn-outline-primary'; ?>">Processing</a>
        <a href="myorders.php?status=delivered" class="btn <?php echo isset($_GET['status']) && $_GET['status'] == 'delivered' ? 'btn-primary' : 'btn-outline-primary'; ?>">Delivered</a>
        <a href="myorders.php?status=cancelled" class="btn <?php echo isset($_GET['status']) && $_GET['status'] == 'cancelled' ? 'btn-primary' : 'btn-outline-primary'; ?>">Cancelled</a>
      </div>
    </div>

    <?php
    // Database connection
    require_once 'config.php';

    // Check if user is logged in
    if(!isset($_SESSION['user_id'])) {
      header('Location: login.php');
      exit;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Initialize query
    $query = "SELECT o.*, r.name as restaurant_name 
              FROM orders o 
              JOIN restaurants r ON o.restaurant_id = r.id 
              WHERE o.user_id = ?";
    
    // Add status filter if set
    $params = [$user_id];
    if(isset($_GET['status'])) {
      $status = $_GET['status'];
      $query .= " AND o.status = ?";
      $params[] = $status;
    }
    
    // Add ordering
    $query .= " ORDER BY o.order_date DESC";
    
    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 5;
    $offset = ($page - 1) * $limit;
    
    $countQuery = str_replace("o.*, r.name as restaurant_name", "COUNT(*) as total", $query);
    $stmt = $conn->prepare($countQuery);
    $stmt->execute($params);
    $totalRows = $stmt->fetch()['total'];
    $totalPages = ceil($totalRows / $limit);
    
    // Add pagination to query
    $query .= " LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $limit;
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if(count($orders) > 0):
    ?>
      <!-- Orders List -->
      <?php foreach($orders as $order): ?>
        <div class="card order-card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <strong>Order #<?php echo $order['id']; ?></strong> - 
              <?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?>
            </div>
            <div>
              <?php
                $statusClass = "";
                switch($order['status']) {
                  case 'delivered':
                    $statusClass = "status-delivered";
                    $statusIcon = "fa-check-circle";
                    break;
                  case 'processing':
                    $statusClass = "status-processing";
                    $statusIcon = "fa-clock";
                    break;
                  case 'cancelled':
                    $statusClass = "status-cancelled";
                    $statusIcon = "fa-times-circle";
                    break;
                  default:
                    $statusClass = "status-pending";
                    $statusIcon = "fa-hourglass-half";
                }
              ?>
              <span class="<?php echo $statusClass; ?>">
                <i class="fas <?php echo $statusIcon; ?>"></i> 
                <?php echo ucfirst($order['status']); ?>
              </span>
            </div>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <i class="fas fa-store"></i> <strong>Restaurant:</strong> <?php echo $order['restaurant_name']; ?>
            </div>
            
            <h6>Order Items:</h6>
            <?php
              // Get order items
              $stmt = $conn->prepare("SELECT oi.*, m.name 
                                     FROM order_items oi 
                                     JOIN menu_items m ON oi.menu_item_id = m.id 
                                     WHERE oi.order_id = ?");
              $stmt->execute([$order['id']]);
              $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
              
              foreach($items as $item):
            ?>
              <div class="order-item">
                <div>
                  <?php echo $item['quantity']; ?> x <?php echo $item['name']; ?>
                </div>
                <div>
                  $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                </div>
              </div>
            <?php endforeach; ?>
            
            <hr>
            <div class="d-flex justify-content-between">
              <div>
                <strong>Delivery Address:</strong> <?php echo $order['delivery_address']; ?>
              </div>
              <div>
                <strong>Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?>
              </div>
            </div>
            
            <?php if($order['status'] == 'pending'): ?>
              <div class="mt-3">
                <a href="cancel_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this order?')">
                  <i class="fas fa-times"></i> Cancel Order
                </a>
              </div>
            <?php elseif($order['status'] == 'delivered'): ?>
              <div class="mt-3">
                <a href="review_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                  <i class="fas fa-star"></i> Leave a Review
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      
      <!-- Pagination -->
      <?php if($totalPages > 1): ?>
        <div class="pagination-container">
          <nav>
            <ul class="pagination justify-content-center">
              <?php if($page > 1): ?>
                <li class="page-item">
                  <a class="page-link" href="?<?php echo isset($_GET['status']) ? 'status='.$_GET['status'].'&' : ''; ?>page=<?php echo $page-1; ?>">Previous</a>
                </li>
              <?php endif; ?>
              
              <?php for($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                  <a class="page-link" href="?<?php echo isset($_GET['status']) ? 'status='.$_GET['status'].'&' : ''; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
              <?php endfor; ?>
              
              <?php if($page < $totalPages): ?>
                <li class="page-item">
                  <a class="page-link" href="?<?php echo isset($_GET['status']) ? 'status='.$_GET['status'].'&' : ''; ?>page=<?php echo $page+1; ?>">Next</a>
                </li>
              <?php endif; ?>
            </ul>
          </nav>
        </div>
      <?php endif; ?>
      
    <?php else: ?>
      <!-- No Orders Message -->
      <div class="no-orders">
        <i class="fas fa-shopping-bag"></i>
        <h4>No orders found</h4>
        <p class="text-muted">You haven't placed any orders yet.</p>
        <a href="index.php" class="btn btn-primary mt-3">Browse Restaurants</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Footer -->
  <footer class="bg-dark text-white py-4 mt-5">
    <div class="container">
      <div class="row">
        <div class="col-md-4">
          <h5><i class="fas fa-utensils"></i> Foodie Express</h5>
          <p>Delivering delicious food right to your doorstep. Enjoy the best restaurants in town from the comfort of your home.</p>
        </div>
        <div class="col-md-4">
          <h5>Quick Links</h5>
          <ul class="list-unstyled">
            <li><a href="index.php" class="text-white">Home</a></li>
            <li><a href="about.php" class="text-white">About Us</a></li>
            <li><a href="faq.php" class="text-white">FAQs</a></li>
            <li><a href="contact.php" class="text-white">Contact Us</a></li>
          </ul>
        </div>
        <div class="col-md-4">
          <h5>Contact Info</h5>
          <address>
            <i class="fas fa-map-marker-alt"></i> 123 Food Street, Cuisine City<br>
            <i class="fas fa-phone"></i> (555) 123-4567<br>
            <i class="fas fa-envelope"></i> info@foodieexpress.com
          </address>
          <div class="social-links">
            <a href="#" class="text-white me-2"><i class="fab fa-facebook-f"></i></a>
            <a href="#" class="text-white me-2"><i class="fab fa-twitter"></i></a>
            <a href="#" class="text-white me-2"><i class="fab fa-instagram"></i></a>
          </div>
        </div>
      </div>
      <div class="text-center mt-3">
        <p class="mb-0">&copy; 2023 Foodie Express. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>