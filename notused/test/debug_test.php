<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';

// Test database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Test three simple queries
echo "<h2>Test Query 1: Instructor Info</h2>";
$query1 = "SELECT i.Instructor_ID, pb.Given_Name, pb.Last_Name, i.Specialization 
           FROM Instructor i
           JOIN Profile p ON i.Profile_ID = p.Profile_ID
           JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
           LIMIT 1";
           
$result = $conn->query($query1);
if ($result) {
    print_r($result->fetch_assoc());
} else {
    echo "Error in Query 1: " . $conn->error;
}

echo "<hr><h2>Test Query 2: Subject Info</h2>";
$query2 = "SELECT s.Subject_ID, s.Subject_Name, c.Grade_Level 
           FROM Subject s
           LEFT JOIN Clearance c ON s.Clearance_ID = c.Clearance_ID
           LIMIT 1";
           
$result = $conn->query($query2);
if ($result) {
    print_r($result->fetch_assoc());
} else {
    echo "Error in Query 2: " . $conn->error;
}

echo "<hr><h2>Test Query 3: Assigned Subjects</h2>";
$query3 = "SELECT a.Instructor_ID, a.Subject_ID
           FROM Assigned_Subject a
           LIMIT 1";
           
$result = $conn->query($query3);
if ($result) {
    print_r($result->fetch_assoc());
} else {
    echo "Error in Query 3: " . $conn->error;
}

?>
