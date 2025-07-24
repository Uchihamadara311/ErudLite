<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// First, test direct connection
echo "<h2>1. Testing Direct Database Connection</h2>";
try {
    $direct_conn = new mysqli("localhost", "root", "", "erudlite");
    if ($direct_conn->connect_error) {
        throw new Exception("Direct connection failed: " . $direct_conn->connect_error);
    }
    echo "<p style='color:green'>✓ Direct connection successful</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Then, test through includes/db.php
echo "<h2>2. Testing Connection Through db.php</h2>";
try {
    require_once 'includes/db.php';
    if (!isset($conn) || $conn === null) {
        throw new Exception("db.php did not create \$conn variable");
    }
    if ($conn->connect_error) {
        throw new Exception("Connection through db.php failed: " . $conn->connect_error);
    }
    echo "<p style='color:green'>✓ Connection through db.php successful</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test if assigned_subject table exists
echo "<h2>3. Testing assigned_subject Table</h2>";
try {
    $result = $conn->query("SHOW TABLES LIKE 'assigned_subject'");
    if (!$result) {
        throw new Exception("Error checking table: " . $conn->error);
    }
    if ($result->num_rows === 0) {
        throw new Exception("Table 'assigned_subject' does not exist");
    }
    echo "<p style='color:green'>✓ Table exists</p>";
    
    // Get table structure
    $structure = $conn->query("DESCRIBE assigned_subject");
    if (!$structure) {
        throw new Exception("Error getting table structure: " . $conn->error);
    }
    echo "<p>Table structure:</p><pre>";
    while ($col = $structure->fetch_assoc()) {
        print_r($col);
    }
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test a simple SELECT
echo "<h2>4. Testing Simple SELECT</h2>";
try {
    $result = $conn->query("SELECT * FROM assigned_subject LIMIT 1");
    if (!$result) {
        throw new Exception("Error querying: " . $conn->error);
    }
    if ($result->num_rows === 0) {
        echo "<p style='color:orange'>⚠ No rows found in table</p>";
    } else {
        $row = $result->fetch_assoc();
        echo "<p style='color:green'>✓ Found row:</p><pre>";
        print_r($row);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
