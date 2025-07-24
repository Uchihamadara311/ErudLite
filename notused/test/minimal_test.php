<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';

echo "<pre>";
echo "Testing database connection...\n";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "Connection successful!\n\n";

echo "Testing tables:\n";
$tables = [
    'assigned_subject' => "SELECT * FROM assigned_subject LIMIT 1",
    'instructor' => "SELECT * FROM instructor LIMIT 1",
    'subject' => "SELECT * FROM subject LIMIT 1",
    'profile' => "SELECT * FROM profile LIMIT 1",
    'profile_bio' => "SELECT * FROM profile_bio LIMIT 1",
    'clearance' => "SELECT * FROM clearance LIMIT 1"
];

foreach ($tables as $name => $query) {
    echo "\nTesting $name table:\n";
    try {
        $result = $conn->query($query);
        if (!$result) {
            echo "Error querying $name: " . $conn->error . "\n";
            continue;
        }
        
        $row = $result->fetch_assoc();
        if ($row) {
            echo "Found row in $name: " . print_r($row, true) . "\n";
        } else {
            echo "No rows found in $name\n";
        }
    } catch (Exception $e) {
        echo "Exception testing $name: " . $e->getMessage() . "\n";
    }
}

echo "\nTesting table case sensitivity:\n";
$variants = [
    'assigned_subject',
    'ASSIGNED_SUBJECT',
    'Assigned_Subject',
    'assignedsubject'
];

foreach ($variants as $table) {
    echo "\nTrying to query '$table':\n";
    $query = "SELECT * FROM $table LIMIT 1";
    $result = $conn->query($query);
    echo $result ? "Success" : "Failed: " . $conn->error . "\n";
}
?>
