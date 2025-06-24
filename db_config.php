<?php
// db_config.php
// Configuration for connecting to your MySQL database

$servername = "localhost"; // Database server name, usually localhost
$username = "root"; // Your MySQL username
$password = ""; // Your MySQL password
$dbname = "safety_observations_db"; // The database name we created

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // Log the error instead of displaying it directly in production
    error_log("Database Connection failed: " . $conn->connect_error);
    die("Database connection failed. Please try again later."); // Generic error message for users
}

// Set character set to utf8mb4 for better emoji and special character support
$conn->set_charset("utf8mb4");
?>
