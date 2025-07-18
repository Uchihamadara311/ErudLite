<?php
require_once 'includes/db.php';
header('Content-Type: application/json');

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$instructor_id = isset($_GET['instructor_id']) ? (int)$_GET['instructor_id'] : 0;

if (!$student_id || !$instructor_id) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT DISTINCT s.subject_id, s.subject_name, c.grade_level
        FROM enrollments e
        JOIN classes c ON e.class_id = c.class_id
        JOIN subjects s ON c.subject_id = s.subject_id
        WHERE e.student_id = ? AND c.school_year = ? AND c.instructor_id = ?
        ORDER BY c.grade_level, s.subject_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iii', $student_id, $year, $instructor_id);
$stmt->execute();
$result = $stmt->get_result();
$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
echo json_encode($subjects);
