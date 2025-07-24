<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test database connection
require_once 'includes/db.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "Database connection successful!<br>";
}

// Try a simple query
try {
    $result = $conn->query("SHOW TABLES");
    echo "<h3>Tables in database:</h3>";
    echo "<ul>";
    while ($row = $result->fetch_array()) {
        echo "<li>" . htmlspecialchars($row[0]) . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
