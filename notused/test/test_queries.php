<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';

// Function to test a query and display results
function testQuery($conn, $description, $query) {
    echo "<h3>Testing: $description</h3>";
    echo "Query: " . htmlspecialchars($query) . "<br><br>";
    
    try {
        $result = $conn->query($query);
        if ($result) {
            echo "✅ Query successful<br>";
            $row = $result->fetch_assoc();
            if ($row) {
                echo "Sample row:<br><pre>";
                print_r($row);
                echo "</pre>";
            } else {
                echo "⚠️ No rows returned<br>";
            }
        } else {
            echo "❌ Query failed: " . $conn->error . "<br>";
        }
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "<br>";
    }
    echo "<hr>";
}

// Test database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Test 1: Basic Subject table
testQuery($conn, "Basic Subject Query", 
    "SELECT * FROM Subject LIMIT 1");

// Test 2: Subject with Clearance
testQuery($conn, "Subject with Clearance", 
    "SELECT s.*, c.Grade_Level 
     FROM Subject s 
     LEFT JOIN Clearance c ON s.Clearance_ID = c.Clearance_ID 
     LIMIT 1");

// Test 3: Basic Instructor Query
testQuery($conn, "Basic Instructor Query", 
    "SELECT * FROM Instructor LIMIT 1");

// Test 4: Instructor with Profile
testQuery($conn, "Instructor with Profile", 
    "SELECT i.*, p.* 
     FROM Instructor i
     JOIN Profile p ON i.Profile_ID = p.Profile_ID
     LIMIT 1");

// Test 5: Complete Instructor Query
testQuery($conn, "Complete Instructor Query", 
    "SELECT i.Instructor_ID, pb.Given_Name, pb.Last_Name, i.Specialization 
     FROM Instructor i
     JOIN Profile p ON i.Profile_ID = p.Profile_ID
     JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
     LIMIT 1");

// Test 6: Assigned Subject Basic Query
testQuery($conn, "Basic Assigned Subject Query", 
    "SELECT * FROM Assigned_Subject LIMIT 1");

// Test 7: Full Join Query (but step by step)
testQuery($conn, "Full Join Query", 
    "SELECT a.Instructor_ID, a.Subject_ID,
            pb.Given_Name, pb.Last_Name,
            s.Subject_Name, s.Description,
            c.Grade_Level, i.Specialization
     FROM Assigned_Subject a 
     JOIN Instructor i ON a.Instructor_ID = i.Instructor_ID
     JOIN Profile p ON i.Profile_ID = p.Profile_ID
     JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
     JOIN Subject s ON a.Subject_ID = s.Subject_ID
     LEFT JOIN Clearance c ON s.Clearance_ID = c.Clearance_ID
     LIMIT 1");

?>
