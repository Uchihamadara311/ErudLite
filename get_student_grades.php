<?php
include 'includes/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['student_id']) || !isset($_GET['subject_id']) || !isset($_GET['year'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$student_id = $_GET['student_id'];
$subject_id = $_GET['subject_id'];
$year = $_GET['year'];

try {
    $query = "SELECT * FROM record WHERE student_id = ? AND subject_id = ? AND year = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("iis", $student_id, $subject_id, $year);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $grades = $result->fetch_assoc();
    
    header('Content-Type: application/json');
    echo json_encode($grades ? $grades : null);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
