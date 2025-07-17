<?php
session_start();
// DEBUG: Output session variables for troubleshooting
if (!isset($_SESSION['user_id']) && !isset($_SESSION['email'])) {
    echo '<pre>DEBUG SESSION: ' . print_r($_SESSION, true) . '</pre>';
}
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/db.php';
if (!isset($_SESSION['user_id']) && !isset($_SESSION['email'])) {
    header('Location: login.php');
    exit();
}


$student_id = $_SESSION['user_id'] ?? null;
if (!$student_id && isset($_SESSION['email'])) {
    // fallback: get user_id from email
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
    $stmt->bind_param('s', $_SESSION['email']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $student_id = $row['user_id'];
    }
}
if (!$student_id) {
    echo '<div style="color:red;text-align:center;margin-top:40px;">Student not found. Please contact the administrator.</div>';
    exit();
}


// Fetch student info (only name)
$stmt = $conn->prepare('SELECT first_name, last_name FROM users WHERE user_id = ?');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) {
    echo '<div style="color:red;text-align:center;margin-top:40px;">Student profile not found. Please contact the administrator.</div>';
    exit();
}
$full_name = $student['first_name'] . ' ' . $student['last_name'];

// Fetch subjects and grades for current year
$current_year = (int)date('Y');
$subjects = [];
$gpa = null;
$completed = 0;
$total = 0;

$sql = "SELECT sub.subject_name, r.grade as subject_grade FROM enrollments e
        JOIN classes c ON e.class_id = c.class_id
        JOIN subjects sub ON c.subject_id = sub.subject_id
        LEFT JOIN record r ON r.student_id = e.student_id AND r.subject_id = sub.subject_id AND r.school_year = ?
        WHERE e.student_id = ? AND c.school_year = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iii', $current_year, $student_id, $current_year);
$stmt->execute();
$result = $stmt->get_result();
$sum = 0;
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
    $total++;
    if (is_numeric($row['subject_grade'])) {
        $sum += $row['subject_grade'];
        $completed++;
    }
}
if ($completed > 0) {
    $gpa = round($sum / $completed, 2);
}

?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/studentDashboard.css">
</head>
<body>
    <div id="header-placeholder"></div>
    <main>
        <div class="leftContainer">
            <div class="profileSection">
                <img src="assets/profile.png" alt="Profile Picture" class="imageCircle">
            </div>
            <h1><?php echo htmlspecialchars($full_name); ?></h1>
        </div>
        <div class="rightContainer">
            <div id="basicInfo" class="basicInfo">
                <div style="display: flex; flex-direction: row">
                    <section>
                        <h5>GPA</h5>
                        <h1><?php echo $gpa !== null ? $gpa : 'N/A'; ?></h1>
                    </section>
                    <section>
                        <h5>Subjects Completed</h5>
                        <h1><?php echo $completed . '/' . $total; ?></h1>
                    </section>
                </div>
            </div>
            <div class="quickLinks">
                <a href="studentReport.php">Student Report</a>
                <a href="reportCard.php">Classroom</a>
                <a href="#">Clearance</a>
                <a href="studentSchedule.php">Schedule</a>
            </div>
            <div class="basicInfo" style="margin-top: 20px;">
                <h4>Current Subjects & Grades</h4>
                <table style="width:100%;margin-top:10px;">
                    <thead>
                        <tr><th>Subject</th><th>Grade</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $sub): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sub['subject_name']); ?></td>
                                <td><?php echo is_numeric($sub['subject_grade']) ? htmlspecialchars($sub['subject_grade']) : 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($subjects)): ?>
                            <tr><td colspan="2">No subjects found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
</body>
</html>
