<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Foodie Express - Forgot Password</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="CSS/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    .forgot-password-container {
      max-width: 450px;
      margin: 80px auto;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
      background-color: #fff;
    }
    
    .forgot-password-header {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .forgot-password-header i {
      font-size: 3rem;
      color: #0d6efd;
      margin-bottom: 15px;
    }
    
    .form-floating {
      margin-bottom: 20px;
    }
    
    .btn-reset {
      padding: 10px 20px;
      font-weight: 500;
      width: 100%;
    }
    
    .forgot-password-footer {
      text-align: center;
      margin-top: 20px;
    }
    
    .forgot-password-bg {
      background-color: #f8f9fa;
      min-height: 100vh;
      padding: 20px 0;
    }
    
    .alert {
      margin-bottom: 20px;
    }
  </style>
</head>
<body class="forgot-password-bg">
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
          <li class="nav-item">
            <a class="nav-link" href="login.php">
              <i class="fas fa-user"></i> Login
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container">
    <div class="forgot-password-container">
      <div class="forgot-password-header">
        <i class="fas fa-key"></i>
        <h2>Forgot Password</h2>
        <p>Enter your email to reset your password</p>
      </div>
      
      <?php
      session_start();
      
      // Check if there's an error message
      if (isset($_SESSION['reset_error'])) {
        echo '<div class="alert alert-danger">' . $_SESSION['reset_error'] . '</div>';
        unset($_SESSION['reset_error']);
      }
      
      // Check if there's a success message
      if (isset($_SESSION['reset_success'])) {
        echo '<div class="alert alert-success">' . $_SESSION['reset_success'] . '</div>';
        unset($_SESSION['reset_success']);
      }
      
      // Handle form submission
      if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Database connection
        $conn = new mysqli("localhost", "root", "", "restaurant_db");
        
        // Check connection
        if ($conn->connect_error) {
          die("Connection failed: " . $conn->connect_error);
        }
        
        // Get email
        $email = $_POST['email'];
        
        // Check if email exists
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
          // Email found
          $row = $result->fetch_assoc();
          $user_id = $row['id'];
          
          // Generate reset token
          $reset_token = bin2hex(random_bytes(32));
          $token_expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));
          
          // Store token in database
          $token_stmt = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE id = ?");
          $token_stmt->bind_param("ssi", $reset_token, $token_expiry, $user_id);
          $token_stmt->execute();
          $token_stmt->close();
          
          // In a real application, send email with reset link
          // For demonstration, we'll just show a success message
          $_SESSION['reset_success'] = "Password reset link has been sent to your email. Please check your inbox.";
          header("Location: forgot-password.php");
          exit();
        } else {
          // Email not found
          $_SESSION['reset_error'] = "Email address not found";
          header("Location: forgot-password.php");
          exit();
        }
        
        $stmt->close();
        $conn->close();
      }
      ?>
      
      <form method="POST" action="forgot-password.php">
        <div class="form-floating">
          <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" required>
          <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
        </div>
        
        <button type="submit" class="btn btn-primary btn-reset">
          <i class="fas fa-paper-plane me-2"></i>Send Reset Link
        </button>
      </form>
      
      <div class="forgot-password-footer">
        <p>Remember your password? <a href="login.php">Back to Login</a></p>
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