<?php
session_start();

// Database Connection
$host = 'localhost';
$db_username = 'root';  // Default XAMPP MySQL username
$db_password = '';      // Default XAMPP MySQL password (empty)
$database = 'restaurant_db';

// Create connection
$conn = mysqli_connect($host, $db_username, $db_password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $restaurant = mysqli_real_escape_string($conn, $_POST['restaurant']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Secure password hashing
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Check if username already exists
    $check_query = "SELECT * FROM restaurant_login WHERE username = '$username'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $error = "Username already exists. Please choose another.";
    } else {
        // Insert new restaurant user
        $insert_query = "INSERT INTO restaurant_login (restaurant_name, username, password, email) 
                         VALUES ('$restaurant', '$username', '$password', '$email')";
        
        if (mysqli_query($conn, $insert_query)) {
            $_SESSION['registration_success'] = "Account created successfully! You can now log in.";
            header("Location: restaurant_login.php");
            exit();
        } else {
            $error = "Error creating account: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Create Account - Foodie Express</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f4f4f4;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Arial', sans-serif;
        }
        .create-account-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 30px;
            width: 100%;
            max-width: 500px;
            margin: 20px auto;
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
    <div class="container">
        <div class="create-account-container">
            <h2 class="text-center mb-4">
                <i class="fas fa-utensils me-2"></i> Restaurant Create Account
            </h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="restaurant" class="form-label">Select Restaurant</label>
                    <select class="form-select" id="restaurant" name="restaurant" required>
                        <option value="">Choose a Restaurant</option>
                        <option value="Wok on Fire">Wok on Fire</option>
                        <option value="Pizza Hub">Pizza Hub</option>
                        <option value="Tandoor Hut">Tandoor Hut</option>
                    </select>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" required 
                           minlength="4" maxlength="20" pattern="[A-Za-z0-9_]+" 
                           title="Username can only contain letters, numbers, and underscores">
                    <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email" name="email" required>
                    <label for="email"><i class="fas fa-envelope me-2"></i>Email</label>
                </div>
                
                <div class="form-floating mb-3 position-relative">
                    <input type="password" class="form-control" id="password" name="password" 
                           required minlength="8" 
                           pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" 
                           title="Must contain at least one number, one uppercase and lowercase letter, and be at least 8 characters long">
                    <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                    <span class="password-toggle" onclick="togglePasswordVisibility('password')">
                        <i class="fas fa-eye" id="password-eye"></i>
                    </span>
                </div>
                
                <div class="form-floating mb-3 position-relative">
                    <input type="password" class="form-control" id="confirm-password" 
                           required minlength="8" 
                           oninput="checkPasswordMatch()">
                    <label for="confirm-password"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                    <span class="password-toggle" onclick="togglePasswordVisibility('confirm-password')">
                        <i class="fas fa-eye" id="confirm-password-eye"></i>
                    </span>
                    <div id="password-error" class="text-danger mt-1"></div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
                
                <div class="text-center mt-3">
                    <p>Already have an account? <a href="restaurant_login.php">Login here</a></p>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function checkPasswordMatch() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm-password');
            const passwordError = document.getElementById('password-error');

            if (password.value !== confirmPassword.value) {
                passwordError.textContent = 'Passwords do not match';
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                passwordError.textContent = '';
                confirmPassword.setCustomValidity('');
            }
        }
        
        function togglePasswordVisibility(inputId) {
            const passwordInput = document.getElementById(inputId);
            const passwordEye = document.getElementById(inputId + '-eye');
            
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