<?php
require_once 'includes/db.php';
header('Content-Type: application/json');

$instructor_id = isset($_GET['instructor_id']) ? intval($_GET['instructor_id']) : 0;
$year = isset($_GET['year']) ? intval($_GET['year']) : 0;

if (!$instructor_id || !$year) {
    echo json_encode([]);
    exit;
}

echo json_encode($subjects);

$sql = "SELECT s.subject_id, s.subject_name, s.grade_level
        FROM assigned_subject a
        JOIN subjects s ON a.subject_id = s.subject_id
        WHERE a.instructor_id = ?
        ORDER BY s.subject_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $instructor_id);
$stmt->execute();
$result = $stmt->get_result();

$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
echo json_encode($subjects);
