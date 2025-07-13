<?php
require_once 'config/database.php';

// Create database and tables
$database = new Database();

try {
    $pdo = $database->connect();
    
    // Create tables
    if ($database->createTables()) {
        echo "<h2>✅ Database tables created successfully!</h2>";
        
        // Insert sample data
        insertSampleData($pdo);
        
        echo "<h3>🎉 Installation completed successfully!</h3>";
        echo "<p><a href='quickAccess.php'>Go to Quick Access</a></p>";
    } else {
        echo "<h2>❌ Failed to create tables</h2>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Installation failed: " . $e->getMessage() . "</h2>";
}

function insertSampleData($pdo) {
    // Sample students
    $students = [
        ['2024-001', 'Juan Dela Cruz', 'Grade 4', 'Mabini'],
        ['2024-002', 'Maria Santos', 'Grade 4', 'Mabini'],
        ['2024-003', 'Jose Rizal', 'Grade 5', 'Bonifacio'],
        ['2024-004', 'Ana Garcia', 'Grade 5', 'Bonifacio']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO students (student_id, student_name, grade_level, section) VALUES (?, ?, ?, ?)");
    foreach ($students as $student) {
        $stmt->execute($student);
    }
    
    // Sample subjects
    $subjects = [
        ['Filipino', 'Grade 4'],
        ['English', 'Grade 4'],
        ['Mathematics', 'Grade 4'],
        ['Science', 'Grade 4'],
        ['Araling Panlipunan (AP)', 'Grade 4'],
        ['MAPEH', 'Grade 4']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, grade_level) VALUES (?, ?)");
    foreach ($subjects as $subject) {
        $stmt->execute($subject);
    }
    
    echo "<p>✅ Sample data inserted successfully!</p>";
}
?>