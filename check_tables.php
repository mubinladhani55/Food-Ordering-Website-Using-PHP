<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "restaurant_db";

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Get parameters
$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? '';
$restaurant = $_GET['restaurant'] ?? '';

// Validate input
if (empty($date) || empty($time) || empty($restaurant)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Get restaurant ID
$restaurantId = null;
$restaurantQuery = $conn->prepare("SELECT id FROM restaurants WHERE name = ?");
$restaurantQuery->bind_param("s", $restaurant);
$restaurantQuery->execute();
$restaurantResult = $restaurantQuery->get_result();

if ($restaurantResult && $restaurantResult->num_rows > 0) {
    $row = $restaurantResult->fetch_assoc();
    $restaurantId = $row['id'];
} else {
    // If restaurant doesn't exist in database, try to get ID from hardcoded values
    // This is a fallback for testing or when the database is not properly populated
    $restaurantMap = [
        'WorkOnFire' => 1,
        'PizzaHub' => 2,
        'TandoorHut' => 3
    ];
    
    if (isset($restaurantMap[$restaurant])) {
        $restaurantId = $restaurantMap[$restaurant];
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Restaurant not found']);
        exit;
    }
}

// Check which tables are booked - use straightforward query to avoid issues
$bookedTables = [];
$query = "SELECT table_numbers FROM table_bookings 
          WHERE restaurant = ? 
          AND booking_date = ? 
          AND booking_time = ? 
          AND status != 'cancelled'";

$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $restaurantId, $date, $time);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['table_numbers'])) {
            $tables = explode(',', $row['table_numbers']);
            foreach ($tables as $table) {
                $table = trim($table); // Remove any whitespace
                if (!empty($table) && !in_array($table, $bookedTables)) {
                    $bookedTables[] = $table;
                }
            }
        }
    }
}

// Add debugging information in response during development
$debug = [
    'query' => $query,
    'params' => [$restaurantId, $date, $time],
    'result_count' => $result ? $result->num_rows : 0
];

// Return results
header('Content-Type: application/json');
echo json_encode([
    'date' => $date,
    'time' => $time,
    'restaurant' => $restaurant,
    'restaurant_id' => $restaurantId,
    'booked_tables' => $bookedTables,
    'debug' => $debug
]);

// Close connection
$stmt->close();
$conn->close();
?>