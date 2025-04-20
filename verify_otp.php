<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

session_start();

// Check if registration ID is provided
if (!isset($_GET['reg_id']) || !isset($_SESSION['pending_registrations'][$_GET['reg_id']])) {
    header("Location: register.php");
    exit();
}

$registration_id = $_GET['reg_id'];
$registration_data = $_SESSION['pending_registrations'][$registration_id];
$email = $registration_data['email'];
$error_message = "";

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_otp = $_POST['otp'];
    
    // Check if OTP is correct and not expired
    if ($user_otp == $registration_data['otp']) {
        $current_time = date('Y-m-d H:i:s');
        
        if (strtotime($current_time) <= strtotime($registration_data['otp_expiry'])) {
            // Database connection
            $conn = new mysqli("localhost", "root", "", "restaurant_db");
            
            // Check connection
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            
            // Prepare SQL statement
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, username, password, email_verified, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
            $stmt->bind_param("ssss", 
                $registration_data['fullname'], 
                $registration_data['email'], 
                $registration_data['username'], 
                $registration_data['password']
            );
            
            // Execute the statement
            if ($stmt->execute()) {
                // Remove this specific registration from pending registrations
                unset($_SESSION['pending_registrations'][$registration_id]);
                
                // Set success message
                $_SESSION['register_success'] = "Account created successfully! You can now login.";
                header("Location: login.php");
                exit();
            } else {
                $error_message = "Error creating account: " . $stmt->error;
            }
            
            $stmt->close();
            $conn->close();
        } else {
            $error_message = "OTP has expired. Please request a new one.";
        }
    } else {
        $error_message = "Invalid OTP. Please try again.";
    }
}

// Resend OTP functionality
if (isset($_GET['resend']) && $_GET['resend'] == 'otp') {
    // Generate new OTP
    $new_otp = sprintf("%06d", mt_rand(1, 999999));
    $new_otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Resend email
    $mail = new PHPMailer(true);
    try {
        // SMTP configuration 
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mubinladhani5252@gmail.com';
        $mail->Password   = 'rjfs glpo byiw mswv';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('noreply@foodieexpress.com', 'Foodie Express');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'New Verification Code for Foodie Express';
        $mail->Body    = "
            <html>
            <body>
                <h2>Email Verification</h2>
                <p>Your new verification code is: <strong>$new_otp</strong></p>
                <p>This code will expire in 10 minutes.</p>
                <p>If you did not request this, please ignore this email.</p>
            </body>
            </html>
        ";

        $mail->send();
        
        // Update the OTP in pending registrations
        $_SESSION['pending_registrations'][$registration_id]['otp'] = $new_otp;
        $_SESSION['pending_registrations'][$registration_id]['otp_expiry'] = $new_otp_expiry;
        
        header("Location: verify_otp.php?reg_id={$registration_id}&resent=true");
        exit();
    } catch (Exception $e) {
        $error_message = "Failed to resend OTP. Please try again. Error: " . $mail->ErrorInfo;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Email - Foodie Express</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="CSS/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    .verify-container {
      max-width: 500px;
      margin: 60px auto;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
      background-color: #fff;
    }
    
    .verify-header {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .verify-header i {
      font-size: 3rem;
      color: #0d6efd;
      margin-bottom: 15px;
    }
  </style>
</head>
<body class="register-bg">
  <div class="container">
    <div class="verify-container">
      <div class="verify-header">
        <i class="fas fa-envelope-open-text"></i>
        <h2>Verify Your Email</h2>
        <p>We've sent a verification code to <?php echo htmlspecialchars($email); ?></p>
      </div>
      
      <?php
      if (!empty($error_message)) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($error_message) . '</div>';
      }
      
      if (isset($_GET['resent']) && $_GET['resent'] == 'true') {
        echo '<div class="alert alert-success">A new OTP has been sent to your email.</div>';
      }
      ?>
      
      <form method="POST" action="verify_otp.php?reg_id=<?php echo htmlspecialchars($registration_id); ?>">
        <div class="form-floating mb-3">
          <input type="text" class="form-control" id="otp" name="otp" placeholder="Enter 6-digit OTP" required maxlength="6" pattern="\d{6}">
          <label for="otp"><i class="fas fa-key me-2"></i>Enter Verification Code</label>
        </div>
        
        <button type="submit" class="btn btn-primary w-100 mb-3">
          <i class="fas fa-check-circle me-2"></i>Verify
        </button>
        
        <div class="text-center">
          <p>Didn't receive the code? 
            <a href="verify_otp.php?reg_id=<?php echo htmlspecialchars($registration_id); ?>&resend=otp" class="text-primary">Resend OTP</a>
          </p>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>