<?php
// Database Configuration
define('DB_HOST', 'localhost');      // Where your MySQL server is located
define('DB_USER', 'root');           // MySQL username
define('DB_PASS', '');               // MySQL password
define('DB_NAME', 'StudyManagementSystem');  // Your database name

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8");
?>