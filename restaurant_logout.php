<?php
session_start();

// Unset all restaurant-related session variables
unset($_SESSION['restaurant_id']);
unset($_SESSION['restaurant_name']);
unset($_SESSION['restaurant_username']);
unset($_SESSION['restaurant_logged_in']);

// Destroy the session
session_destroy();

// Redirect to the restaurant login page
header("Location: restaurant_login.php");
exit();
?>