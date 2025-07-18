<?php
session_start();
require_once 'includes/db.php';
$student_id = $_SESSION['user_id'] ?? null;
if (!$student_id && isset($_SESSION['email'])) {
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
    $stmt->bind_param('s', $_SESSION['email']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $student_id = $row['user_id'];
    }
}
$full_name = '';
$class_info = '';
if ($student_id) {
    $stmt = $conn->prepare('SELECT first_name, last_name FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    if ($student) {
        $full_name = $student['first_name'] . ' ' . $student['last_name'];
    }
    // Fetch current year class (grade and section)
    $current_year = (int)date('Y');
    $sql = "SELECT c.grade_level, c.section FROM enrollments e JOIN classes c ON e.class_id = c.class_id WHERE e.student_id = ? AND c.school_year = ? ORDER BY c.grade_level DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $student_id, $current_year);
    $stmt->execute();
    $class = $stmt->get_result()->fetch_assoc();
    if ($class) {
        $class_info = 'GRADE ' . htmlspecialchars($class['grade_level']);
        if (!empty($class['section'])) {
            $class_info .= ' (' . htmlspecialchars($class['section']) . ')';
        }
    }
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMS</title>
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
            <h1><?php echo htmlspecialchars($full_name ?: 'Student'); ?></h1>
            <p><?php echo $class_info; ?></p>
        </div>
        <div class="rightContainer">
            <?php
            // GPA and Subjects Completed Calculation
            $gpa = null;
            $subjects_completed = 0;
            $subjects_total = 0;
            if ($student_id) {
                $current_year = (int)date('Y');
                // Get all records for this student and year
                $stmt = $conn->prepare('SELECT grade FROM record WHERE student_id = ? AND school_year = ?');
                $stmt->bind_param('ii', $student_id, $current_year);
                $stmt->execute();
                $result = $stmt->get_result();
                $sum = 0;
                $count = 0;
                $completed = 0;
                while ($row = $result->fetch_assoc()) {
                    if (is_numeric($row['grade'])) {
                        $sum += $row['grade'];
                        $count++;
                        if ($row['grade'] >= 75) {
                            $completed++;
                        }
                    }
                }
                if ($count > 0) {
                    $gpa = round($sum / $count, 2);
                    $subjects_completed = $completed;
                }
                // Get total subjects enrolled for this year
                $stmt = $conn->prepare('SELECT COUNT(*) as total FROM enrollments e JOIN classes c ON e.class_id = c.class_id WHERE e.student_id = ? AND c.school_year = ?');
                $stmt->bind_param('ii', $student_id, $current_year);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $subjects_total = (int)$row['total'];
                }
            }
            ?>
            <div id="basicInfo" class="basicInfo" style="cursor:pointer;">
                <div style="display: flex; flex-direction: row">
                    <section>
                        <h5>GPA</h5>
                        <h1><?php echo $gpa !== null ? htmlspecialchars($gpa) : '--'; ?></h1>
                    </section>
                    <section>
                        <h5>Subjects Completed</h5>
                        <h1><?php echo $subjects_completed . ($subjects_total ? '/' . $subjects_total : ''); ?></h1>
                    </section>
                </div>
                <p>SHOW MORE</p>
            </div>
            <div class="quickLinks">
                <a href="studentReport.php">Student Report</a>
                <a href="reportCard.php">Classroom</a>
                <a href="">Clearance</a>
                <a href="studentSchedule.php">Schedule</a>
            </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script src="../js/studentDashboard.js"></script>
</body>
</html>