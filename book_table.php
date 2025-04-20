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

// Start session to check login status
session_start();

// Initialize user data variables
$user_name = '';
$user_email = '';

// If user is logged in, fetch their details from the database
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_query = $conn->prepare("SELECT fullname, email FROM users WHERE id = ?");
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $user_result = $user_query->get_result();
    
    if ($user_result && $user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        $user_name = $user_data['fullname'];
        $user_email = $user_data['email'];
    }
}

// Fetch restaurants from database - using only the columns that exist
$restaurantsQuery = "SELECT id, name FROM restaurants";
$restaurantsResult = $conn->query($restaurantsQuery);

// Create associative arrays for restaurant data
$restaurantDisplayNames = [];
$restaurantIds = [];

if ($restaurantsResult && $restaurantsResult->num_rows > 0) {
    while($row = $restaurantsResult->fetch_assoc()) {
        // Convert database name to display name (e.g., "WorkOnFire" to "Work on Fire")
        $displayName = preg_replace('/(?<!^)([A-Z])/', ' $1', $row['name']);
        
        $restaurantDisplayNames[$row['name']] = $displayName;
        $restaurantIds[$row['name']] = $row['id'];
    }
} else {
    // Fallback if no restaurants found
    $restaurantDisplayNames = [
        'WorkOnFire' => 'Work on Fire',
        'PizzaHub' => 'Pizza Hub',
        'TandoorHut' => 'Tandoor Hut'
    ];
}

// Get the restaurant from the URL parameter or default to first one
$restaurant = isset($_GET['restaurant']) ? $_GET['restaurant'] : array_key_first($restaurantDisplayNames);

// Get the display name of the current restaurant
$currentRestaurantName = $restaurantDisplayNames[$restaurant] ?? 'Restaurant';

// Check for availability AJAX request
if (isset($_GET['check_availability'])) {
    header('Content-Type: application/json');
    
    $table = $_GET['table'] ?? '';
    $date = $_GET['date'] ?? '';
    $start_time = $_GET['start_time'] ?? '';
    $end_time = $_GET['end_time'] ?? '';
    $restaurant = $_GET['restaurant'] ?? '';
    
    // Get the restaurant ID
    $restaurantId = $restaurantIds[$restaurant] ?? null;
    
    if ($restaurantId === null) {
        $idQuery = $conn->prepare("SELECT id FROM restaurants WHERE name = ?");
        $idQuery->bind_param("s", $restaurant);
        $idQuery->execute();
        $idResult = $idQuery->get_result();
        
        if ($idResult && $idResult->num_rows > 0) {
            $row = $idResult->fetch_assoc();
            $restaurantId = $row['id'];
        }
    }
    
    if (empty($table)) {
        echo json_encode(['available' => false, 'message' => 'Please select a table']);
        exit;
    }
    
    if (empty($date)) {
        echo json_encode(['available' => false, 'message' => 'Please select a date']);
        exit;
    }
    
    if (empty($start_time) || empty($end_time)) {
        echo json_encode(['available' => false, 'message' => 'Please select both start and end times']);
        exit;
    }
    
    if ($restaurantId === null) {
        echo json_encode(['available' => false, 'message' => 'Invalid restaurant']);
        exit;
    }
    
    $isBooked = isTableBooked($conn, $restaurantId, $table, $date, $start_time, $end_time);
    
    if ($isBooked) {
        echo json_encode(['available' => false, 'message' => 'Table ' . $table . ' is already booked for the selected date and time']);
    } else {
        echo json_encode(['available' => true, 'message' => 'Table ' . $table . ' is available']);
    }
    
    exit;
}

// Process form submission
$booking_success = false;
$booking_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $date = $_POST['date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $guests = $_POST['guests'] ?? 1;
    $restaurant = $_POST['restaurant'] ?? 'WorkOnFire'; // Get selected restaurant from the form
    $special_requests = $_POST['special_requests'] ?? '';
    $table_number = $_POST['table_number'] ?? '';
    
    // Get the restaurant ID for the selected restaurant
    // First try from our array of IDs
    $restaurantId = $restaurantIds[$restaurant] ?? null;
    
    // If not found in the array, try to fetch it directly from the database
    if ($restaurantId === null) {
        $idQuery = $conn->prepare("SELECT id FROM restaurants WHERE name = ?");
        $idQuery->bind_param("s", $restaurant);
        $idQuery->execute();
        $idResult = $idQuery->get_result();
        
        if ($idResult && $idResult->num_rows > 0) {
            $row = $idResult->fetch_assoc();
            $restaurantId = $row['id'];
        }
    }
    
    // Validate form data
    if (empty($name) || empty($email) ||  empty($date) || empty($start_time) || empty($end_time) || $restaurantId === null || empty($table_number)) {
        $booking_error = "Please fill in all required fields and select a table.";
    } else {
        // Check if the selected table is already booked for the selected date and time range
        $checkQuery = $conn->prepare("SELECT table_numbers FROM table_bookings WHERE restaurant = ? AND booking_date = ? AND 
                                     ((booking_start_time <= ? AND booking_end_time > ?) OR
                                      (booking_start_time < ? AND booking_end_time >= ?) OR
                                      (booking_start_time >= ? AND booking_end_time <= ?))
                                     AND status != 'cancelled'");
        $checkQuery->bind_param("isssssss", $restaurantId, $date, $end_time, $start_time, $start_time, $end_time, $start_time, $end_time);
        $checkQuery->execute();
        $checkResult = $checkQuery->get_result();
        
        $tableAlreadyBooked = false;
        if ($checkResult && $checkResult->num_rows > 0) {
            while ($row = $checkResult->fetch_assoc()) {
                $bookedTables = explode(',', $row['table_numbers']);
                if (in_array($table_number, $bookedTables)) {
                    $tableAlreadyBooked = true;
                    break;
                }
            }
        }
        
        if ($tableAlreadyBooked) {
            $booking_error = "Table " . $table_number . " is already booked for the selected date and time range. Please choose a different table or time.";
        } else {
            // Get the user ID if logged in
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            // Save booking to database using restaurant id
            $stmt = $conn->prepare("INSERT INTO table_bookings (restaurant, name, email, booking_date, booking_start_time, booking_end_time, guests, special_requests, table_numbers, user_id, status, created_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->bind_param("isssssisii", $restaurantId, $name, $email,  $date, $start_time, $end_time, $guests, $special_requests, $table_number, $user_id);
            
            if ($stmt->execute()) {
                $booking_success = true;
            } else {
                $booking_error = "Error saving booking: " . $conn->error;
            }
        }
    }
}

// Function to check if a table is booked for a specific date and time range
function isTableBooked($conn, $restaurantId, $table, $date, $start_time, $end_time) {
    $query = $conn->prepare("SELECT table_numbers FROM table_bookings WHERE restaurant = ? AND booking_date = ? AND 
                             ((booking_start_time <= ? AND booking_end_time > ?) OR
                              (booking_start_time < ? AND booking_end_time >= ?) OR
                              (booking_start_time >= ? AND booking_end_time <= ?))
                             AND status != 'cancelled'");
    $query->bind_param("isssssss", $restaurantId, $date, $end_time, $start_time, $start_time, $end_time, $start_time, $end_time);
    $query->execute();
    $result = $query->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $bookedTables = explode(',', $row['table_numbers']);
            if (in_array($table, $bookedTables)) {
                return true;
            }
        }
    }
    return false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Table at <?php echo $currentRestaurantName; ?></title>
    <link rel="stylesheet" href="CSS/chinese_menu.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Additional styles for the booking form */
        .booking-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-full-width {
            grid-column: span 2;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--card-background);
        }
        
        .form-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            cursor: pointer;
            transition: background-color 0.2s;
            font-weight: bold;
            font-size: 16px;
        }
        
        .form-submit:hover {
            opacity: 0.9;
        }
        
        .back-button {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background-color: #f8f9fa;
            color: #212529;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .back-button:hover {
            background-color: #e9ecef;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .restaurant-info {
            margin-bottom: 20px;
        }
        
        .restaurant-info h3 {
            margin-bottom: 10px;
        }
        
        .restaurant-info p {
            margin-bottom: 5px;
        }
        
        .restaurant-hours {
            margin-top: 10px;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
            text-align: center;
        }
        
        .modal-content h3 {
            margin-top: 0;
            color: #155724;
        }
        
        .modal-content p {
            margin-bottom: 20px;
        }
        
        .modal-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            cursor: pointer;
            transition: background-color 0.2s;
            font-weight: bold;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }
        
        .modal-button:hover {
            opacity: 0.9;
        }
        
        /* Status indicator */
        .login-status {
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 8px;
            background-color: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-info {
            font-weight: bold;
        }
        
        .login-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            text-decoration: none;
            font-size: 14px;
        }
        
        /* Time selection container */
        .time-selection-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .availability-message {
            margin-top: 10px;
            padding: 10px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Book a Table</h1>
        <p>Reserve Your Perfect Dining Experience</p>
        <a href="chinese_menu.php?restaurant=<?php echo $restaurant; ?>" class="back-button">Back to Menu</a>
    </div>

    <div class="booking-container">
        <!-- Login Status -->
        <div class="login-status">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-info">
                    Welcome, <?php echo htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username']); ?>
                </div>
                <a href="logout.php" class="login-button">Logout</a>
            <?php else: ?>
                <div class="user-info">
                    You are not logged in
                </div>
                <a href="login.php" class="login-button">Login</a>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($booking_error)): ?>
            <div class="alert alert-error">
                <p><?php echo $booking_error; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="restaurant-info">
            <h3>Restaurant Information</h3>
            <p><strong>Address:</strong> 123 Main Street, Cityville</p>
            <div class="restaurant-hours">
                <p><strong>Hours:</strong></p>
                <p>Monday - Thursday: 11:00 AM - 10:00 PM</p>
                <p>Friday - Saturday: 11:00 AM - 11:00 PM</p>
                <p>Sunday: 12:00 PM - 9:00 PM</p>
            </div>
        </div>
        
        <form method="post" action="" id="booking-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user_name); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="guests">Number of Guests *</label>
                    <select id="guests" name="guests" required>
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                        <?php endfor; ?>
                        <option value="10+">More than 10</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date">Date *</label>
                    <input type="date" id="date" name="date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="table_number">Select Table *</label>
                    <select id="table_number" name="table_number" required>
                        <option value="">-- Select Table --</option>
                        <?php for ($i = 1; $i <= 15; $i++): ?>
                            <option value="<?php echo $i; ?>">Table <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group form-full-width">
                    <label>Reservation Time *</label>
                    <div class="time-selection-container">
                        <div>
                            <label for="start_time">Start Time</label>
                            <select id="start_time" name="start_time" required>
                                <option value="">-- Select Start Time --</option>
                                <option value="11:00">11:00 AM</option>
                                <option value="11:30">11:30 AM</option>
                                <option value="12:00">12:00 PM</option>
                                <option value="12:30">12:30 PM</option>
                                <option value="13:00">1:00 PM</option>
                                <option value="13:30">1:30 PM</option>
                                <option value="14:00">2:00 PM</option>
                                <option value="14:30">2:30 PM</option>
                                <option value="17:00">5:00 PM</option>
                                <option value="17:30">5:30 PM</option>
                                <option value="18:00">6:00 PM</option>
                                <option value="18:30">6:30 PM</option>
                                <option value="19:00">7:00 PM</option>
                                <option value="19:30">7:30 PM</option>
                                <option value="20:00">8:00 PM</option>
                                <option value="20:30">8:30 PM</option>
                            </select>
                        </div>
                        <div>
                            <label for="end_time">End Time</label>
                            <select id="end_time" name="end_time" required>
                                <option value="">-- Select End Time --</option>
                                <option value="12:00">12:00 PM</option>
                                <option value="12:30">12:30 PM</option>
                                <option value="13:00">1:00 PM</option>
                                <option value="13:30">1:30 PM</option>
                                <option value="14:00">2:00 PM</option>
                                <option value="14:30">2:30 PM</option>
                                <option value="15:00">3:00 PM</option>
                                <option value="18:00">6:00 PM</option>
                                <option value="18:30">6:30 PM</option>
                                <option value="19:00">7:00 PM</option>
                                <option value="19:30">7:30 PM</option>
                                <option value="20:00">8:00 PM</option>
                                <option value="20:30">8:30 PM</option>
                                <option value="21:00">9:00 PM</option>
                                <option value="21:30">9:30 PM</option>
                                <option value="22:00">10:00 PM</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="restaurant">Select Restaurant *</label>
                    <select id="restaurant" name="restaurant" required>
                        <?php foreach ($restaurantDisplayNames as $key => $name): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($key == $restaurant) ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group form-full-width">
                    <label for="special_requests">Special Requests</label>
                    <textarea id="special_requests" name="special_requests" rows="4"></textarea>
                </div>
            </div>
            
            <button type="submit" class="form-submit">Book Now</button>
        </form>
    </div>

    <!-- Success Modal -->
    <div id="success-modal" class="modal">
        <div class="modal-content">
            <h3>Booking Successful!</h3>
            <p>Your table has been reserved. We look forward to serving you.</p>
            <a href="index.php?restaurant=<?php echo $restaurant; ?>" class="modal-button">Return to Home Page</a>
        </div>
    </div>

    <script>
        // Set minimum date to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date').min = today;
            
            // Show modal if booking was successful
            <?php if ($booking_success): ?>
            document.getElementById('success-modal').style.display = 'block';
            <?php endif; ?>
            
            // Add event listener for start time to update end time options
            document.getElementById('start_time').addEventListener('change', updateEndTimeOptions);
            
            // Validate that end time is after start time
            document.getElementById('booking-form').addEventListener('submit', function(e) {
                const startTime = document.getElementById('start_time').value;
                const endTime = document.getElementById('end_time').value;
                
                if (startTime >= endTime) {
                    e.preventDefault();
                    alert('End time must be after start time');
                }
            });
        });
        
        // Function to update end time options based on selected start time
        function updateEndTimeOptions() {
            const startTime = document.getElementById('start_time').value;
            const endTimeSelect = document.getElementById('end_time');
            
            if (!startTime) {
                return;
            }
            
            // Clear all options
            while (endTimeSelect.options.length > 0) {
                endTimeSelect.remove(0);
            }
            
            // Add default option
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.text = '-- Select End Time --';
            endTimeSelect.add(defaultOption);
            
            // Get all available times
            const allTimes = [
                '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00',
                '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30', '21:00', '21:30', '22:00'
            ];
            
            // Add options for times that are after the selected start time
            for (let i = 0; i < allTimes.length; i++) {
                if (allTimes[i] > startTime) {
                    const option = document.createElement('option');
                    option.value = allTimes[i];
                    
                    // Format display time (convert 24h to 12h format)
                    let hour = parseInt(allTimes[i].split(':')[0]);
                    const minute = allTimes[i].split(':')[1];
                    const period = hour >= 12 ? 'PM' : 'AM';
                    hour = hour % 12 || 12;
                    
                    option.text = `${hour}:${minute} ${period}`;
                    endTimeSelect.add(option);
                }
            }
        }
        
        // jQuery for AJAX table availability check
        $(document).ready(function() {
            // Elements that trigger availability check
            const triggerElements = ['#table_number', '#date', '#start_time', '#end_time', '#restaurant'];
            
            // Add change event listeners to all relevant fields
            triggerElements.forEach(element => {
                $(element).change(function() {
                    checkTableAvailability();
                });
            });
            
            function checkTableAvailability() {
                const table = $('#table_number').val();
                const date = $('#date').val();
                const startTime = $('#start_time').val();
                const endTime = $('#end_time').val();
                const restaurant = $('#restaurant').val();
                
                // Don't check if any required field is empty
                if (!table || !date || !startTime || !endTime || !restaurant) {
                    return;
                }
                
                $.ajax({
                    url: 'book_table.php?check_availability=1',
                    method: 'GET',
                    data: {
                        table: table,
                        date: date,
                        start_time: startTime,
                        end_time: endTime,
                        restaurant: restaurant
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.available) {
                            // Table is available
                            showAvailabilityMessage(response.message, 'success');
                        } else {
                            // Table is booked
                            showAvailabilityMessage(response.message, 'error');
                        }
                    },
                    error: function() {
                        showAvailabilityMessage('Error checking table availability', 'error');
                    }
                });
            }
            
            function showAvailabilityMessage(message, type) {
                // Remove any existing availability messages
                $('.availability-message').remove();
                
                // Create new message element
                const messageElement = $('<div class="availability-message"></div>')
                    .text(message)
                    .addClass(type === 'error' ? 'alert-error' : 'alert-success')
                    .css('margin-top', '10px');
                
                // Insert after the table select
                $('#table_number').closest('.form-group').append(messageElement);
                
                // Auto-hide after 5 seconds
                setTimeout(function() {
                    messageElement.fadeOut(500, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        });
    </script>
</body>
</html>