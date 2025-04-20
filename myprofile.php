<?php
  // Start the session first thing in your script
  session_start();
  
  // Check if user is logged in
  if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
  }
  
  // Database connection
  $conn = new mysqli("localhost", "root", "", "restaurant_db");
  
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }
  
  // Get user information
  $user_id = $_SESSION['user_id'];
  $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();
  
  $stmt->close();
  
  // Handle profile update
  if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    
    $update_stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, address = ? WHERE id = ?");
    $update_stmt->bind_param("ssssi", $fullname, $email, $phone, $address, $user_id);
    
    if ($update_stmt->execute()) {
      $_SESSION['profile_success'] = "Profile updated successfully";
      $_SESSION['fullname'] = $fullname;
      header("Location: profile.php");
      exit();
    } else {
      $_SESSION['profile_error'] = "Error updating profile: " . $conn->error;
    }
    
    $update_stmt->close();
  }
  
  // Get user orders
  $orders_stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 5");
  $orders_stmt->bind_param("i", $user_id);
  $orders_stmt->execute();
  $orders_result = $orders_stmt->get_result();
  
  $orders_stmt->close();
  $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Foodie Express - My Profile</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="CSS/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    .profile-container {
      max-width: 800px;
      margin: 40px auto;
      padding: 0;
      background-color: #fff;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }
    
    .profile-header {
      background-color: #0d6efd;
      color: white;
      padding: 30px;
      text-align: center;
      position: relative;
    }
    
    .profile-pic {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      border: 5px solid rgba(255, 255, 255, 0.5);
      margin: 0 auto 15px;
      background-color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 3rem;
      color: #0d6efd;
    }
    
    .profile-tabs {
      background-color: #f8f9fa;
      padding: 0;
    }
    
    .nav-pills .nav-link {
      border-radius: 0;
      padding: 15px 20px;
      font-weight: 500;
    }
    
    .nav-pills .nav-link.active {
      background-color: #fff;
      color: #0d6efd;
    }
    
    .tab-content {
      padding: 30px;
    }
    
    .profile-info-item {
      margin-bottom: 20px;
    }
    
    .profile-info-item label {
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .order-card {
      margin-bottom: 20px;
      border-left: 4px solid #0d6efd;
    }
    
    .btn-edit-profile {
      padding: 8px 20px;
      font-weight: 500;
    }
    
    .profile-bg {
      background-color: #f8f9fa;
      min-height: 100vh;
      padding: 20px 0;
    }
    
    .logout-btn {
      position: absolute;
      top: 15px;
      right: 15px;
      background-color: rgba(255, 255, 255, 0.2);
      border: none;
      color: white;
      padding: 5px 15px;
      border-radius: 5px;
    }
    
    .logout-btn:hover {
      background-color: rgba(255, 255, 255, 0.3);
    }
  </style>
</head>
<body class="profile-bg">
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
            <a class="nav-link" href="#">Offers</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" id="cartLink">
              <i class="fas fa-shopping-cart"></i> Cart 
              <span class="badge bg-primary" id="cartCount">0</span>
            </a>
          </li>
          <?php if(isset($_SESSION['user_id'])): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle active" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
              <i class="fas fa-user"></i> <?php echo $_SESSION['fullname']; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item active" href="profile.php">My Profile</a></li>
              <li><a class="dropdown-item" href="my-orders.php">My Orders</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="logout.php">Logout</a></li>
            </ul>
          </li>
          <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="login.php">
              <i class="fas fa-user"></i> Login
            </a>
          </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container">
    <div class="profile-container">
      <div class="profile-header">
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        <div class="profile-pic">
          <i class="fas fa-user"></i>
        </div>
        <h2><?php echo $user['fullname']; ?></h2>
        <p>Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
      </div>
      
      <div class="profile-tabs">
        <ul class="nav nav-pills nav-fill" id="profileTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="profile-info-tab" data-bs-toggle="tab" data-bs-target="#profile-info" type="button" role="tab" aria-controls="profile-info" aria-selected="true">Profile Info</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="recent-orders-tab" data-bs-toggle="tab" data-bs-target="#recent-orders" type="button" role="tab" aria-controls="recent-orders" aria-selected="false">Recent Orders</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="edit-profile-tab" data-bs-toggle="tab" data-bs-target="#edit-profile" type="button" role="tab" aria-controls="edit-profile" aria-selected="false">Edit Profile</button>
          </li>
        </ul>
      </div>
      
      <div class="tab-content" id="profileTabContent">
        <!-- Profile Info Tab -->
        <div class="tab-pane fade show active" id="profile-info" role="tabpanel" aria-labelledby="profile-info-tab">
          <?php if(isset($_SESSION['profile_success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['profile_success']; unset($_SESSION['profile_success']); ?></div>
          <?php endif; ?>
          
          <div class="row">
            <div class="col-md-6">
              <div class="profile-info-item">
                <label>Full Name</label>
                <p class="form-control"><?php echo $user['fullname']; ?></p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="profile-info-item">
                <label>Username</label>
                <p class="form-control"><?php echo $user['username']; ?></p>
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="profile-info-item">
                <label>Email Address</label>
                <p class="form-control"><?php echo $user['email']; ?></p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="profile-info-item">
                <label>Phone Number</label>
                <p class="form-control"><?php echo !empty($user['phone']) ? $user['phone'] : 'Not provided'; ?></p>
              </div>
            </div>
          </div>
          
          <div class="profile-info-item">
            <label>Address</label>
            <p class="form-control"><?php echo !empty($user['address']) ? $user['address'] : 'Not provided'; ?></p>
          </div>
        </div>
        
        <!-- Recent Orders Tab -->
        <div class="tab-pane fade" id="recent-orders" role="tabpanel" aria-labelledby="recent-orders-tab">
          <?php if($orders_result->num_rows > 0): ?>
            <?php while($order = $orders_result->fetch_assoc()): ?>
              <div class="card order-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <span>Order #<?php echo $order['id']; ?></span>
                  <span class="badge bg-<?php 
                    if($order['status'] == 'Delivered') echo 'success';
                    elseif($order['status'] == 'Processing') echo 'warning';
                    elseif($order['status'] == 'Cancelled') echo 'danger';
                    else echo 'primary';
                  ?>"><?php echo $order['status']; ?></span>
                </div>
                <div class="card-body">
                  <h5 class="card-title"><?php echo $order['restaurant']; ?></h5>
                  <p class="card-text">
                    <strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?><br>
                    <strong>Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?><br>
                    <strong>Delivery Address:</strong> <?php echo $order['delivery_address']; ?>
                  </p>
                  <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                </div>
              </div>
            <?php endwhile; ?>
            <div class="text-center mt-3">
              <a href="my-orders.php" class="btn btn-outline-primary">View All Orders</a>
            </div>
          <?php else: ?>
            <div class="alert alert-info">
              You don't have any orders yet. <a href="index.php">Start ordering now!</a>
            </div>
          <?php endif; ?>
        </div>
        
        <!-- Edit Profile Tab -->
        <div class="tab-pane fade" id="edit-profile" role="tabpanel" aria-labelledby="edit-profile-tab">
          <?php if(isset($_SESSION['profile_error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['profile_error']; unset($_SESSION['profile_error']); ?></div>
          <?php endif; ?>
          
          <form method="POST" action="profile.php">
            <div class="row">
              <div class="col-md-6">
                <div class="form-floating mb-3">
                  <input type="text" class="form-control" id="fullname" name="fullname" placeholder="Full Name" value="<?php echo $user['fullname']; ?>" required>
                  <label for="fullname"><i class="fas fa-user me-2"></i>Full Name</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating mb-3">
                  <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" value="<?php echo $user['email']; ?>" required>
                  <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="form-floating mb-3">
                  <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone Number" value="<?php echo $user['phone'] ?? ''; ?>">
                  <label for="phone"><i class="fas fa-phone me-2"></i>Phone Number</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating mb-3">
                  <input type="text" class="form-control" id="username" value="<?php echo $user['username']; ?>" disabled>
                  <label for="username"><i class="fas fa-user-tag me-2"></i>Username (Cannot be changed)</label>
                </div>
              </div>
            </div>
            
            <div class="form-floating mb-3">
              <textarea class="form-control" id="address" name="address" placeholder="Address" style="height: 100px"><?php echo $user['address'] ?? ''; ?></textarea>
              <label for="address"><i class="fas fa-map-marker-alt me-2"></i>Delivery Address</label>
            </div>
            
            <button type="submit" name="update_profile" class="btn btn-primary btn-edit-profile">
              <i class="fas fa-save me-2"></i>Save Changes
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-dark text-white text-center py-3 mt-5">
    <p>&copy; 2023 Foodie Express. All rights reserved.</p>
    <div class="social-icons">
      <a href="#" class="text-white me-3"><i class="fab fa-facebook"></i></a>
      <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
      <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>