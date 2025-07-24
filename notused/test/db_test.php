<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Test</h1>";

try {
    $conn = new mysqli("localhost", "root", "", "erudlite");
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<p style='color: green'>Database connection successful!</p>";
    
    $test_query = "SELECT 1";
    if (!$conn->query($test_query)) {
        throw new Exception("Basic query failed: " . $conn->error);
    }
    
    echo "<p style='color: green'>Basic query successful!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
