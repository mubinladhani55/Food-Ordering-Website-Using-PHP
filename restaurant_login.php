<?php
session_start();

// Database Connection
$host = 'localhost';
$db_username = 'root';
$db_password = '';
$database = 'restaurant_db';

// Create connection
$conn = mysqli_connect($host, $db_username, $db_password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // Prepare and execute query using prepared statement
    $stmt = $conn->prepare("SELECT id, username, password, restaurant_name FROM restaurant_login WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['restaurant_id'] = $user['id'];
            $_SESSION['restaurant_name'] = $user['restaurant_name'];
            $_SESSION['restaurant_username'] = $user['username'];
            $_SESSION['restaurant_logged_in'] = true;

            // Determine which dashboard to redirect to based on restaurant name
            $restaurant_name = $user['restaurant_name'];
            $dashboard_page = '';
            
            if ($restaurant_name == 'Wok on Fire') {
                $dashboard_page = 'chinese_dashboard.php';
            } elseif ($restaurant_name == 'Pizza Hub') {
                $dashboard_page = 'pizza_dashboard.php';
            } elseif ($restaurant_name == 'Tandoor Hut') {
                $dashboard_page = 'tandoor_dashboard.php';
            } else {
                // Default fallback
                $dashboard_page = 'pizza_dashboard.php';
            }

            // Redirect to the appropriate dashboard
            header("Location: $dashboard_page");
            exit();
        } else {
            $_SESSION['login_error'] = "Invalid username or password";
            header("Location: restaurant_login.php");
            exit();
        }
    } else {
        $_SESSION['login_error'] = "Invalid username or password";
        header("Location: restaurant_login.php");
        exit();
    }

    $stmt->close();
}

// Check if there's a login error to display
$login_error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : null;
if (isset($_SESSION['login_error'])) {
    unset($_SESSION['login_error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Login - Foodie Express</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f4f4f4;
        }
        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 30px;
            width: 100%;
            max-width: 400px;
            margin: 80px auto;
        }
        .form-control:focus {
            border-color: #ff6b6b;
            box-shadow: 0 0 0 0.2rem rgba(255,107,107,0.25);
        }
        .btn-primary {
            background-color: #ff6b6b;
            border-color: #ff6b6b;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #ff5252;
            border-color: #ff5252;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
        }
    </style>
</head>
<body>
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
                            <li><a class="dropdown-item" href="chinese_menu.php?restaurant=WokOnFire">Wok on Fire</a></li>
                            <li><a class="dropdown-item" href="pizza_menu.php?restaurant=PizzaHub">Pizza Hub</a></li>
                            <li><a class="dropdown-item" href="tandoor_menu.php?restaurant=TandoorHut">Tandoor Hut</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Offers</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="loginDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> Login
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="login.php">Customer Login</a></li>
                            <li><a class="dropdown-item active" href="restaurant_login.php">Restaurant Login</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="login-container">
            <h2 class="text-center mb-4">
                <i class="fas fa-utensils me-2"></i>Restaurant Login
            </h2>
            
            <?php if ($login_error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $login_error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-floating mb-3 position-relative">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                    <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                </div>
                
                <div class="form-floating mb-3 position-relative">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                    <span class="password-toggle" onclick="togglePasswordVisibility('password')">
                        <i class="fas fa-eye" id="password-eye"></i>
                    </span>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember-me">
                    <label class="form-check-label" for="remember-me">Remember me</label>
                    <a href="restaurant_forgotpassword.php" class="float-end text-muted">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
                
                <div class="text-center mt-3">
                    <p>Don't have an account? <a href="restaurant_register.php">Create Account</a></p>
                </div>
            </form>
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
    <script>
        function togglePasswordVisibility(inputId) {
            const passwordInput = document.getElementById(inputId);
            const passwordEye = document.getElementById('password-eye');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordEye.classList.remove('fa-eye');
                passwordEye.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordEye.classList.remove('fa-eye-slash');
                passwordEye.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>

<?php
// Close the database connection
mysqli_close($conn);
?>