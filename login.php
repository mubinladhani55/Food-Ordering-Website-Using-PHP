<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Foodie Express - Login</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="CSS/styles.css">
  <link rel="stylesheet" href="CSS/login.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="login-bg">
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
            <a class="nav-link active" href="login.php">
              <i class="fas fa-user"></i> Login
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container">
    <div class="login-container">
      <div class="login-header">
        <i class="fas fa-utensils"></i>
        <h2>Welcome Back</h2>
        <p>Sign in to your Foodie Express account</p>
      </div>
      
      <?php
      session_start();
      
      // Initialize variables to retain form data
      $username = "";
      
      // Check if there's an error message
      if (isset($_SESSION['login_error'])) {
        echo '<div class="alert alert-danger">' . $_SESSION['login_error'] . '</div>';
        unset($_SESSION['login_error']);
      }
      
      // Check if there's a success message (for registration)
      if (isset($_SESSION['register_success'])) {
        echo '<div class="alert alert-success">' . $_SESSION['register_success'] . '</div>';
        unset($_SESSION['register_success']);
      }
      
      // Retrieve old form data if available
      if (isset($_SESSION['login_data'])) {
        $username = $_SESSION['login_data']['username'] ?? '';
      }
      
      // Handle form submission
      if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Save username in session (not password for security)
        $_SESSION['login_data'] = [
          'username' => $_POST['username'] ?? ''
        ];
        
        // Database connection
        $conn = new mysqli("localhost", "root", "", "restaurant_db");
        
        // Check connection
        if ($conn->connect_error) {
          die("Connection failed: " . $conn->connect_error);
        }
        
        // Get username and password
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        // Prepare SQL statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, username, password, fullname FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
          // User found
          $row = $result->fetch_assoc();
          
          // Verify password
          if (password_verify($password, $row['password'])) {
            // Password is correct, create session
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['fullname'] = $row['fullname'];
            
            // Clear login data from session
            unset($_SESSION['login_data']);
            
            // Redirect to home page
            header("Location: index.php");
            exit();
          } else {
            // Password is incorrect
            $_SESSION['login_error'] = "Invalid username or password";
            header("Location: login.php");
            exit();
          }
        } else {
          // User not found
          $_SESSION['login_error'] = "Invalid username or password";
          header("Location: login.php");
          exit();
        }
        
        $stmt->close();
        $conn->close();
      }
      ?>
      
      <form method="POST" action="login.php">
        <div class="form-floating">
          <input type="text" class="form-control" id="username" name="username" placeholder="Username" value="<?php echo htmlspecialchars($username); ?>" required>
          <label for="username"><i class="fas fa-user me-2"></i>Username</label>
        </div>
        
        <div class="form-floating">
          <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
          <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
          <span class="password-toggle" onclick="togglePasswordVisibility('password')">
            <i class="fas fa-eye" id="password-eye"></i>
          </span>
        </div>
        
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="rememberMe">
          <label class="form-check-label" for="rememberMe">
            Remember me
          </label>
          <a href="forgotpassword.php" class="float-end">Forgot password?</a>
        </div>
        
        <button type="submit" class="btn btn-primary btn-login">
          <i class="fas fa-sign-in-alt me-2"></i>Sign In
        </button>
      </form>
      
      <div class="login-footer">
        <p>Don't have an account? <a href="register.php">Create an account</a></p>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-dark text-white text-center py-3 mt-5">
    <p>&copy; 2025 Foodie Express. All rights reserved.</p>
    <div class="social-icons">
      <a href="#" class="text-white me-3"><i class="fab fa-facebook"></i></a>
      <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
      <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="JS/login.js"></script>
</body>
</html>