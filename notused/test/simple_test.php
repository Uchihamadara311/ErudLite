<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';

// Test instructor query with joins
$test_query = "SELECT 
    i.Instructor_ID,
    pb.Given_Name,
    pb.Last_Name,
    i.Specialization
FROM Instructor i
JOIN Profile p ON i.Profile_ID = p.Profile_ID
JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
ORDER BY pb.Given_Name, pb.Last_Name";

try {
    $result = $conn->query($test_query);
    
    if ($result) {
        echo "<h2>Instructors List:</h2>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Specialization</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Instructor_ID']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Given_Name'] . ' ' . $row['Last_Name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Specialization']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "Query failed: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Test subject query
echo "<hr>";
$test_query = "SELECT s.Subject_ID, s.Subject_Name, s.Description, c.Grade_Level 
                  FROM Subject s
                  LEFT JOIN Clearance c ON s.Clearance_ID = c.Clearance_ID 
                  ORDER BY c.Grade_Level, s.Subject_Name";

try {
    $result = $conn->query($test_query);
    
    if ($result) {
        echo "<h2>Subjects List:</h2>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Subject</th><th>Description</th><th>Grade Level</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Subject_ID']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Subject_Name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Description']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Grade_Level']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "Query failed: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Test assigned subjects query
echo "<hr>";
$test_query = "SELECT 
    a.Instructor_ID,
    a.Subject_ID,
    pb.Given_Name,
    pb.Last_Name,
    s.Subject_Name,
    s.Description,
    c.Grade_Level,
    i.Specialization
FROM Assigned_Subject a
JOIN Instructor i ON a.Instructor_ID = i.Instructor_ID
JOIN Profile p ON i.Profile_ID = p.Profile_ID
JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
JOIN Subject s ON a.Subject_ID = s.Subject_ID
LEFT JOIN Clearance c ON s.Clearance_ID = c.Clearance_ID
ORDER BY pb.Given_Name, pb.Last_Name, s.Subject_Name";

try {
    $result = $conn->query($test_query);
    
    if ($result) {
        echo "<h2>Current Assignments:</h2>";
        echo "<table border='1'>";
        echo "<tr><th>Instructor</th><th>Subject</th><th>Grade Level</th><th>Description</th><th>Specialization</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Given_Name'] . ' ' . $row['Last_Name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Subject_Name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Grade_Level']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Description']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Specialization']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "Query failed: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
