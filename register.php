<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Start the session at the very beginning
session_start();

// Initialize an array to store pending registrations if it doesn't exist
if (!isset($_SESSION['pending_registrations'])) {
    $_SESSION['pending_registrations'] = [];
}

// Initialize variables to retain form data
$fullname = $email = $username = "";

// Check if there's an error message
$register_error = isset($_SESSION['register_error']) ? $_SESSION['register_error'] : "";
unset($_SESSION['register_error']);

// Function to generate OTP
function generateOTP($length = 6) {
    return sprintf("%0{$length}d", mt_rand(1, 10 ** $length - 1));
}

// Function to send OTP email
function sendOTPEmail($email, $otp) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mubinladhani5252@gmail.com'; // Your Gmail address
        $mail->Password   = 'rjfs glpo byiw mswv';   // Your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('noreply@foodieexpress.com', 'Foodie Express');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Verification Code for Foodie Express';
        $mail->Body    = "
            <html>
            <body>
                <h2>Email Verification</h2>
                <p>Your verification code is: <strong>$otp</strong></p>
                <p>This code will expire in 10 minutes.</p>
                <p>If you did not request this, please ignore this email.</p>
            </body>
            </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Send Error: " . $e->getMessage());
        return false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve form data
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate form data
    $errors = false;
    $error_messages = [];

    // [Keep existing validation logic from the previous code]
    // ... (all the previous validation checks remain the same)

    // If there are validation errors
    if ($errors) {
        $_SESSION['register_error'] = implode(", ", $error_messages);
        
        $_SESSION['form_data'] = [
            'fullname' => $fullname,
            'email' => $email,
            'username' => $username
        ];
        
        header("Location: register.php");
        exit();
    }

    // Database connection
    $conn = new mysqli("localhost", "root", "", "restaurant_db");
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Check if username or email already exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['register_error'] = "Username or email already exists";
        header("Location: register.php");
        exit();
    }
    
    $check_stmt->close();
    
    // Generate OTP
    $otp = generateOTP();
    $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Store registration details in pending registrations
    $registration_id = md5($email . time());
    $_SESSION['pending_registrations'][$registration_id] = [
        'fullname' => $fullname,
        'email' => $email,
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'otp' => $otp,
        'otp_expiry' => $otp_expiry
    ];
    
    // Send OTP email
    if (sendOTPEmail($email, $otp)) {
        // Redirect to OTP verification page with registration ID
        header("Location: verify_otp.php?reg_id=" . $registration_id);
        exit();
    } else {
        $_SESSION['register_error'] = "Unable to send verification email. Please try again.";
        header("Location: register.php");
        exit();
    }
}

// Retrieve old form data if available
$fullname = $_SESSION['form_data']['fullname'] ?? '';
$email = $_SESSION['form_data']['email'] ?? '';
$username = $_SESSION['form_data']['username'] ?? '';

// Clear form data from session
unset($_SESSION['form_data']);
?> 
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Foodie Express - Create Account</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="CSS/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    .register-container {
      max-width: 500px;
      margin: 60px auto;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
      background-color: #fff;
    }
    
    .register-header {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .register-header i {
      font-size: 3rem;
      color: #0d6efd;
      margin-bottom: 15px;
    }
    
    .form-floating {
      margin-bottom: 20px;
      position: relative;
    }
    
    .btn-register {
      padding: 10px 20px;
      font-weight: 500;
      width: 100%;
    }
    
    .register-footer {
      text-align: center;
      margin-top: 20px;
    }
    
    .register-bg {
      background-color: #f8f9fa;
      min-height: 100vh;
      padding: 20px 0;
    }
    
    .alert {
      margin-bottom: 20px;
    }

    .password-toggle {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      z-index: 10;
    }

    .invalid-feedback {
      display: none;
      width: 100%;
      margin-top: 0.25rem;
      font-size: 0.875em;
      color: #dc3545;
      padding-bottom: 0.8rem;
    }

    .is-invalid ~ .invalid-feedback {
      display: block;
    }

    .is-invalid {
      border-color: #dc3545;
      padding-right: calc(1.5em + 0.75rem);
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right calc(0.375em + 0.1875rem) center;
      background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
  </style>
</head>
<body class="register-bg">
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
    <div class="register-container">
      <div class="register-header">
        <i class="fas fa-user-plus"></i>
        <h2>Create Account</h2>
        <p>Join Foodie Express to start ordering from your favorite restaurants</p>
      </div>
      
      <?php
      // Display error message if it exists
      if (!empty($register_error)) {
        echo '<div class="alert alert-danger">' . $register_error . '</div>';
      }
      ?>
      
      <form method="POST" action="register.php" id="registerForm" novalidate>
        <div class="form-floating">
          <input type="text" class="form-control" id="fullname" name="fullname" placeholder="Full Name" value="<?php echo htmlspecialchars($fullname); ?>" required>
          <label for="fullname"><i class="fas fa-user me-2"></i>Full Name</label>
          <div class="invalid-feedback" id="fullname-feedback">
            Full name must not exceed 15 characters and both first and last name should start with capital letters.
          </div>
        </div>
        
        <div class="form-floating">
          <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($email); ?>" required>
          <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
          <div class="invalid-feedback" id="email-feedback">
            Please enter a valid email address.
          </div>
        </div>
        
        <div class="form-floating">
          <input type="text" class="form-control" id="username" name="username" placeholder="Username" value="<?php echo htmlspecialchars($username); ?>" required>
          <label for="username"><i class="fas fa-user-tag me-2"></i>Username</label>
          <div class="invalid-feedback" id="username-feedback">
            Username must not exceed 8 characters and must contain at least 2 numbers.
          </div>
        </div>
        
        <div class="form-floating">
          <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
          <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
          <span class="password-toggle" onclick="togglePasswordVisibility('password')">
            <i class="fas fa-eye" id="password-eye"></i>
          </span>
          <div class="invalid-feedback" id="password-feedback">
            Password must be at least 6 characters long and should not contain special characters.
          </div>
        </div>
        
        <div class="form-floating">
          <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
          <label for="confirm_password"><i class="fas fa-lock me-2"></i>Confirm Password</label>
          <span class="password-toggle" onclick="togglePasswordVisibility('confirm_password')">
            <i class="fas fa-eye" id="confirm_password-eye"></i>
          </span>
          <div class="invalid-feedback" id="confirm-password-feedback">
            Passwords do not match.
          </div>
        </div>
        
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="termsCheck" required>
          <label class="form-check-label" for="termsCheck">
            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
          </label>
          <div class="invalid-feedback" id="terms-feedback">
            You must agree to the terms and conditions.
          </div>
        </div>
        
        <button type="submit" class="btn btn-primary btn-register">
          <i class="fas fa-user-plus me-2"></i>Create Account
        </button>
      </form>
      
      <div class="register-footer">
        <p>Already have an account? <a href="login.php">Sign in</a></p>
      </div>
    </div>
  </div>
  
  <!-- Terms Modal -->
  <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <h6>1. Acceptance of Terms</h6>
          <p>By accessing and using the Foodie Express service, you agree to be bound by these Terms and Conditions.</p>
          
          <h6>2. Registration</h6>
          <p>You must provide accurate and complete information when creating an account. You are responsible for maintaining the confidentiality of your account information.</p>
          
          <h6>3. Privacy Policy</h6>
          <p>Your use of the service is also governed by our Privacy Policy, which is incorporated by reference.</p>
          
          <h6>4. User Conduct</h6>
          <p>You agree not to use the service for any unlawful purpose or in any manner that could damage or impair the service.</p>
          
          <h6>5. Ordering</h6>
          <p>All orders placed through the service are subject to acceptance by the respective restaurants. Prices and availability may change without notice.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Agree</button>
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
  
  <script>
    // Function to toggle password visibility
    function togglePasswordVisibility(fieldId) {
      const passwordField = document.getElementById(fieldId);
      const eyeIcon = document.getElementById(`${fieldId}-eye`);
      
      if (passwordField.type === "password") {
        passwordField.type = "text";
        eyeIcon.classList.remove("fa-eye");
        eyeIcon.classList.add("fa-eye-slash");
      } else {
        passwordField.type = "password";
        eyeIcon.classList.remove("fa-eye-slash");
        eyeIcon.classList.add("fa-eye");
      }
    }
    
    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('registerForm');
      
      form.addEventListener('submit', function(event) {
        let valid = true;
        
        // Validate full name
        const fullname = document.getElementById('fullname');
        const fullnameValue = fullname.value.trim();
        
        if (fullnameValue.length > 15) {
          setInvalid(fullname, 'Full name must not exceed 15 characters.');
          valid = false;
        } else if (fullnameValue.includes(' ')) {
          // Check if first and last name start with capital letters
          const nameParts = fullnameValue.split(' ');
          if (!/^[A-Z]/.test(nameParts[0]) || (nameParts.length > 1 && !/^[A-Z]/.test(nameParts[1]))) {
            setInvalid(fullname, 'First and last name should start with capital letters.');
            valid = false;
          } else {
            setValid(fullname);
          }
        } else if (!/^[A-Z]/.test(fullnameValue)) {
          setInvalid(fullname, 'Name should start with a capital letter.');
          valid = false;
        } else {
          setValid(fullname);
        }
        
        // Validate email
        const email = document.getElementById('email');
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (!emailPattern.test(email.value.trim())) {
          setInvalid(email, 'Please enter a valid email address.');
          valid = false;
        } else {
          setValid(email);
        }
        
        // Validate username
        const username = document.getElementById('username');
        const usernameValue = username.value.trim();
        
        if (usernameValue.length > 8) {
          setInvalid(username, 'Username must not exceed 8 characters.');
          valid = false;
        } else if (!/.*[0-9].*[0-9]/.test(usernameValue)) {
          setInvalid(username, 'Username must contain at least 2 numbers.');
          valid = false;
        } else {
          setValid(username);
        }
        
        // Validate password
        const password = document.getElementById('password');
        const passwordValue = password.value;
        
        if (passwordValue.length < 6) {
          setInvalid(password, 'Password must be at least 6 characters long.');
          valid = false;
        } else if (/[^\w\s]/.test(passwordValue)) {
          setInvalid(password, 'Password should not contain special characters.');
          valid = false;
        } else {
          setValid(password);
        }
        
        // Validate confirm password
        const confirmPassword = document.getElementById('confirm_password');
        
        if (confirmPassword.value !== passwordValue) {
          setInvalid(confirmPassword, 'Passwords do not match.');
          valid = false;
        } else {
          setValid(confirmPassword);
        }
        
        // Validate terms checkbox
        const termsCheck = document.getElementById('termsCheck');
        
        if (!termsCheck.checked) {
          document.getElementById('terms-feedback').style.display = 'block';
          valid = false;
        } else {
          document.getElementById('terms-feedback').style.display = 'none';
        }
        
        // Prevent form submission if validation fails
        if (!valid) {
          event.preventDefault();
        }
      });
      
      // Helper functions for validation feedback
      function setInvalid(element, message) {
        element.classList.add('is-invalid');
        element.classList.remove('is-valid');
        const feedbackElement = document.getElementById(`${element.id}-feedback`);
        if (feedbackElement) {
          feedbackElement.textContent = message;
        }
      }
      
      function setValid(element) {
        element.classList.remove('is-invalid');
        element.classList.add('is-valid');
      }
    });
  </script>
</body>
</html>