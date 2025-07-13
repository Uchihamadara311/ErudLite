<?php
session_start();
require_once 'config/database.php';

// Get student data from database or session
$studentId = $_SESSION['student_id'] ?? '2024-001';

// Database connection
$database = new Database();
$pdo = $database->connect();

// Get student information
try {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               COALESCE(AVG(g.final_rating), 0) as overall_average,
               COUNT(DISTINCT rc.id) as total_reports
        FROM students s
        LEFT JOIN report_cards rc ON s.student_id = rc.student_id
        LEFT JOIN grades g ON rc.id = g.report_card_id
        WHERE s.student_id = ?
        GROUP BY s.id
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
    
    // If student not found, create default
    if (!$student) {
        $student = [
            'student_id' => $studentId,
            'student_name' => 'Juan Dela Cruz',
            'grade_level' => 'Grade 4',
            'section' => 'Mabini',
            'overall_average' => 87.5,
            'total_reports' => 0
        ];
    }
} catch (Exception $e) {
    // Default data if database error
    $student = [
        'student_id' => $studentId,
        'student_name' => 'Juan Dela Cruz',
        'grade_level' => 'Grade 4',
        'section' => 'Mabini',
        'overall_average' => 87.5,
        'total_reports' => 0
    ];
}

// Get recent grades
try {
    $stmt = $pdo->prepare("
        SELECT g.subject, g.final_rating, g.remarks, rc.school_year
        FROM grades g
        JOIN report_cards rc ON g.report_card_id = rc.id
        WHERE rc.student_id = ?
        ORDER BY rc.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $recentGrades = $stmt->fetchAll();
} catch (Exception $e) {
    $recentGrades = [];
}

// Calculate attendance (mock data for now)
$attendanceRate = 95; // In real app, calculate from attendance table

// Quick stats
$quickStats = [
    'average' => round($student['overall_average'], 1),
    'attendance' => $attendanceRate,
    'reports' => $student['total_reports'],
    'subjects' => count($recentGrades)
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ERUDLITE</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/studentDashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <div class="topBar">
        <div class="title">ERUDLITE</div>
        <div class="navBar">
            <a href="quickAccess.php">Home</a>
            <a href="about.php">About</a>
            <a href="studentDashboard.php"><img src="assets/profile.png" alt="Profile" class="logo"></a>
        </div>
    </div>

    <main>
        <div class="leftContainer">
            <div class="profileSection">
                <img src="assets/profile.png" alt="Profile" class="imageCircle">
            </div>
            <h1><?= htmlspecialchars($student['student_name']) ?></h1>
            <p><strong>ID:</strong> <?= htmlspecialchars($student['student_id']) ?></p>
            <p><strong>Grade:</strong> <?= htmlspecialchars($student['grade_level']) ?> - <?= htmlspecialchars($student['section']) ?></p>
            
            <div class="quickLinks">
                <a href="reportCard.php">
                    <i class="fas fa-file-alt"></i>
                    View Report Card
                </a>
                <a href="schedule.php">
                    <i class="fas fa-calendar"></i>
                    Class Schedule
                </a>
                <a href="grades.php">
                    <i class="fas fa-chart-line"></i>
                    View Grades
                </a>
                <a href="attendance.php">
                    <i class="fas fa-check-circle"></i>
                    Attendance
                </a>
                <a href="about.php">
                    <i class="fas fa-info-circle"></i>
                    About School
                </a>
            </div>
        </div>

        <div class="rightContainer">
            <div class="basicInfo">
                <h2><i class="fas fa-chart-bar"></i> Academic Overview</h2>
                
                <div class="stats-grid">
                    <section class="stat-card">
                        <i class="fas fa-percentage"></i>
                        <h3>Overall Average</h3>
                        <p class="stat-number"><?= $quickStats['average'] ?>%</p>
                        <span class="stat-label">
                            <?= $quickStats['average'] >= 90 ? 'Excellent' : 
                                ($quickStats['average'] >= 80 ? 'Good' : 'Needs Improvement') ?>
                        </span>
                    </section>
                    
                    <section class="stat-card">
                        <i class="fas fa-calendar-check"></i>
                        <h3>Attendance Rate</h3>
                        <p class="stat-number"><?= $quickStats['attendance'] ?>%</p>
                        <span class="stat-label">
                            <?= $quickStats['attendance'] >= 95 ? 'Excellent' : 
                                ($quickStats['attendance'] >= 90 ? 'Good' : 'Needs Improvement') ?>
                        </span>
                    </section>
                    
                    <section class="stat-card">
                        <i class="fas fa-file-text"></i>
                        <h3>Total Reports</h3>
                        <p class="stat-number"><?= $quickStats['reports'] ?></p>
                        <span class="stat-label">Report Cards</span>
                    </section>
                    
                    <section class="stat-card">
                        <i class="fas fa-book"></i>
                        <h3>Active Subjects</h3>
                        <p class="stat-number"><?= $quickStats['subjects'] ?></p>
                        <span class="stat-label">Enrolled</span>
                    </section>
                </div>
            </div>

            <?php if (!empty($recentGrades)): ?>
                <div class="recent-grades">
                    <h2><i class="fas fa-history"></i> Recent Grades</h2>
                    <div class="grades-list">
                        <?php foreach ($recentGrades as $grade): ?>
                            <div class="grade-item">
                                <div class="grade-subject">
                                    <strong><?= htmlspecialchars($grade['subject']) ?></strong>
                                    <span class="grade-year"><?= htmlspecialchars($grade['school_year']) ?></span>
                                </div>
                                <div class="grade-score">
                                    <span class="grade-number"><?= number_format($grade['final_rating'], 1) ?></span>
                                    <span class="grade-remarks <?= strtolower(str_replace(' ', '-', $grade['remarks'])) ?>">
                                        <?= htmlspecialchars($grade['remarks']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> ERUDLITE PMS</p>
    </footer>
</body>
</html>