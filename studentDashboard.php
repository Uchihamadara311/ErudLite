<?php 
require_once 'includes/db.php';

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Ensure user is logged in and has student permissions
if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Student') {
    header("Location: index.php");
    exit();
}

// Get student ID from session
$student_email = $_SESSION['email'];

// First, get basic student info (without enrollment requirement)
$basic_student_sql = "SELECT s.Student_ID, pb.Given_Name, pb.Last_Name
                      FROM Student s 
                      JOIN Profile p ON s.Profile_ID = p.Profile_ID 
                      JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                      JOIN Account a ON p.Profile_ID = a.Profile_ID
                      JOIN Role r ON a.Role_ID = r.Role_ID
                      WHERE r.Email = ?";
$stmt = $conn->prepare($basic_student_sql);
$stmt->bind_param("s", $student_email);
$stmt->execute();
$basic_result = $stmt->get_result();
$basic_student = $basic_result->fetch_assoc();

if (!$basic_student) {
    $_SESSION['error_message'] = "Student profile not found.";
    header("Location: index.php");
    exit();
}

$student_id = $basic_student['Student_ID'];
$student_name = trim($basic_student['Given_Name'] . ' ' . $basic_student['Last_Name']);

// Check if student has active enrollment
$enrollment_sql = "SELECT s.Student_ID, pb.Given_Name, pb.Last_Name, cl.Grade_Level, cl.School_Year, cr.Section
                   FROM Student s 
                   JOIN Profile p ON s.Profile_ID = p.Profile_ID 
                   JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                   JOIN Account a ON p.Profile_ID = a.Profile_ID
                   JOIN Role r ON a.Role_ID = r.Role_ID
                   JOIN Enrollment e ON s.Student_ID = e.Student_ID
                   JOIN Class c ON e.Class_ID = c.Class_ID
                   JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                   JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                   WHERE r.Email = ? AND e.Status = 'Active'
                   ORDER BY cl.School_Year DESC, cl.Grade_Level DESC
                   LIMIT 1";
$stmt = $conn->prepare($enrollment_sql);
$stmt->bind_param("s", $student_email);
$stmt->execute();
$enrollment_result = $stmt->get_result();
$student_data = $enrollment_result->fetch_assoc();

// Check enrollment status
$is_enrolled = ($student_data !== null);

if ($is_enrolled) {
    $grade_level = $student_data['Grade_Level'];
    $school_year = $student_data['School_Year'];
    $section = $student_data['Section'];
} else {
    // Default values for non-enrolled students
    $grade_level = 'Not Assigned';
    $school_year = 'Not Enrolled';
    $section = 'Not Assigned';
}

$student_id = $basic_student['Student_ID'];
$student_name = trim($basic_student['Given_Name'] . ' ' . $basic_student['Last_Name']);

// Initialize variables with default values
$overall_gpa = 0;
$total_subjects = 0;
$subjects_completed = 0;
$attendance_data = [
    'Present_Count' => 0,
    'Absent_Count' => 0,
    'Late_Count' => 0,
    'Excused_Count' => 0,
    'Total_Days' => 0
];
$attendance_percentage = 0;
$current_term = 'Not Available';
$upcoming_classes = [];

// Only proceed with data queries if student is enrolled
if ($is_enrolled) {
    // Get student's current term
    $current_term_sql = "SELECT cl.Term FROM Record r
                         JOIN Record_Details rd ON r.Record_ID = rd.Record_ID
                         JOIN Clearance cl ON rd.Clearance_ID = cl.Clearance_ID
                         WHERE r.Student_ID = ? AND cl.School_Year = ?
                         ORDER BY rd.Record_Date DESC LIMIT 1";
    $stmt = $conn->prepare($current_term_sql);
    $stmt->bind_param("is", $student_id, $school_year);
    $stmt->execute();
    $current_term_result = $stmt->get_result();
    $current_term_data = $current_term_result->fetch_assoc();
    $current_term = $current_term_data ? $current_term_data['Term'] : '1st Semester';

    // Get student's grades and calculate GPA
    $grades_sql = "SELECT DISTINCT sub.Subject_Name, sub.Subject_ID,
                          AVG(rd.Grade) as Average_Grade,
                          COUNT(rd.Grade) as Grade_Count
                   FROM Record r
                   JOIN Record_Details rd ON r.Record_ID = rd.Record_ID
                   JOIN Clearance cl ON rd.Clearance_ID = cl.Clearance_ID
                   JOIN Subject sub ON r.Subject_ID = sub.Subject_ID
                   WHERE r.Student_ID = ? AND cl.School_Year = ?
                   GROUP BY sub.Subject_ID, sub.Subject_Name
                   ORDER BY sub.Subject_Name";
    $stmt = $conn->prepare($grades_sql);
    $stmt->bind_param("is", $student_id, $school_year);
    $stmt->execute();
    $grades_result = $stmt->get_result();

    $total_grades = 0;
    $subject_count = 0;

    while ($subject = $grades_result->fetch_assoc()) {
        $total_subjects++;
        if ($subject['Average_Grade']) {
            $total_grades += $subject['Average_Grade'];
            $subject_count++;
            if ($subject['Average_Grade'] >= 75) {
                $subjects_completed++;
            }
        }
    }

    $overall_gpa = $subject_count > 0 ? round($total_grades / $subject_count, 2) : 0;

    // Get attendance statistics
    $attendance_sql = "SELECT 
                        COUNT(CASE WHEN a.Status = 'Present' THEN 1 END) as Present_Count,
                        COUNT(CASE WHEN a.Status = 'Absent' THEN 1 END) as Absent_Count,
                        COUNT(CASE WHEN a.Status = 'Late' THEN 1 END) as Late_Count,
                        COUNT(CASE WHEN a.Status = 'Excused' THEN 1 END) as Excused_Count,
                        COUNT(*) as Total_Days
                       FROM Attendance a
                       JOIN Class c ON a.Class_ID = c.Class_ID
                       JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                       WHERE a.Student_ID = ? AND cl.School_Year = ?";
    $stmt = $conn->prepare($attendance_sql);
    $stmt->bind_param("is", $student_id, $school_year);
    $stmt->execute();
    $attendance_result = $stmt->get_result();
    $attendance_data = $attendance_result->fetch_assoc();

    $total_attendance_days = $attendance_data['Total_Days'] ?: 1;
    $attendance_percentage = round(($attendance_data['Present_Count'] / $total_attendance_days) * 100, 1);

    // Get upcoming schedule
    $upcoming_schedule_sql = "SELECT DISTINCT sub.Subject_Name, sd.Day, sd.Start_Time, sd.End_Time,
                                     CONCAT(pb.Given_Name, ' ', pb.Last_Name) as Instructor_Name,
                                     cr.Room
                              FROM Schedule s
                              JOIN schedule_details sd ON s.Schedule_ID = sd.Schedule_ID
                              JOIN Subject sub ON s.Subject_ID = sub.Subject_ID
                              JOIN Instructor i ON s.Instructor_ID = i.Instructor_ID
                              JOIN Profile p ON i.Profile_ID = p.Profile_ID
                              JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                              JOIN Class c ON s.Class_ID = c.Class_ID
                              JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                              JOIN Enrollment e ON c.Class_ID = e.Class_ID
                              WHERE e.Student_ID = ? AND e.Status = 'Active'
                              ORDER BY FIELD(sd.Day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), sd.Start_Time
                              LIMIT 5";
    $stmt = $conn->prepare($upcoming_schedule_sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $schedule_result = $stmt->get_result();

    while ($class = $schedule_result->fetch_assoc()) {
        $upcoming_classes[] = $class;
    }
}

?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/studentDashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .upcoming-classes {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .class-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease;
        }
        
        .class-item:hover {
            background-color: #f8f9fa;
        }
        
        .class-item:last-child {
            border-bottom: none;
        }
        
        .class-info h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .class-info p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }
        
        .class-time {
            text-align: right;
            color: #667eea;
            font-weight: bold;
        }
        
        .student-header {
            background: white;
            color: black;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .student-header h1 {
            margin: 15px 0 5px 0;
            font-size: 2.5em;
        }
        
        .student-header p {
            margin: 0;
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .student-details {
            margin-top: 15px;
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 30px 0;
        }
        
        .action-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            text-decoration: none;
            color: #333;
        }
        
        .action-card i {
            font-size: 3em;
            margin-bottom: 15px;
            color: #667eea;
        }
        
        .action-card h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .action-card p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }
        
        .not-enrolled-message {
            background: linear-gradient(180deg, #ff7575ff 0%, #b63030ff 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 4px 15px rgba(255, 118, 117, 0.3);
        }
        
        .not-enrolled-message h2 {
            margin: 0 0 15px 0;
            font-size: 2em;
        }
        
        .not-enrolled-message p {
            margin: 10px 0;
            font-size: 1.1em;
            opacity: 0.95;
        }
        
        .contact-info {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
        }
        
        .contact-info h3 {
            margin: 0 0 10px 0;
            color: white;
        }
        
        .contact-info p {
            margin: 5px 0;
            color: rgba(255,255,255,0.9);
        }
        
        .disabled-card {
            opacity: 0.5;
            pointer-events: none;
            cursor: not-allowed;
        }
        
        .disabled-card:hover {
            transform: none !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
        }
    </style>
</head>
<body>
    <div id="header-placeholder"></div>
    <main>
        <div class="leftContainer">
            <div class="student-header">
                <div class="profileSection">
                    <img src="assets/profile.png" alt="Profile Picture" class="imageCircle">
                </div>
                <h1><?php echo htmlspecialchars($student_name); ?></h1>
                <p>GRADE <?php echo $grade_level; ?> (Class <?php echo htmlspecialchars($section); ?>)</p>
                
                <?php if ($is_enrolled): ?>
                <div class="student-details">
                    <div>
                        <strong>School Year:</strong><br>
                        <?php echo htmlspecialchars($school_year); ?>
                    </div>
                    <div>
                        <strong>Current Term:</strong><br>
                        <?php echo htmlspecialchars($current_term); ?>
                    </div>
                    <div>
                        <strong>Attendance Rate:</strong><br>
                        <?php echo $attendance_percentage; ?>%
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="rightContainer">
            <?php if (!$is_enrolled): ?>
            <!-- Not Enrolled Message -->
            <div class="not-enrolled-message">
                <h2><i class="fas fa-exclamation-triangle"></i> Enrollment Required</h2>
                <p>You are not currently enrolled in any active classes for this academic year.</p>
                <p>Please contact the school administration to complete your enrollment process.</p>
                
                <div class="contact-info">
                    <h3><i class="fas fa-phone"></i> Contact Information</h3>
                    <p><strong>Registrar's Office:</strong> registrar@erudlite.edu</p>
                    <p><strong>Phone:</strong> (555) 123-4567</p>
                    <p><strong>Office Hours:</strong> Monday - Friday, 8:00 AM - 5:00 PM</p>
                </div>
                
                <p style="margin-top: 20px; font-size: 0.9em;">
                    <i class="fas fa-info-circle"></i> 
                    Once enrolled, you'll have access to your grades, schedule, and other academic features.
                </p>
            </div>
            <?php else: ?>
            <!-- Academic Statistics -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $overall_gpa; ?></div>
                    <div class="stat-label">Overall GPA</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $subjects_completed; ?>/<?php echo $total_subjects; ?></div>
                    <div class="stat-label">Subjects Passed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $attendance_data['Present_Count']; ?></div>
                    <div class="stat-label">Days Present</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $attendance_data['Absent_Count']; ?></div>
                    <div class="stat-label">Days Absent</div>
                </div>
            </div>

            <!-- Upcoming Classes -->
            <div class="upcoming-classes">
                <h2><i class="fas fa-calendar-alt"></i> Upcoming Classes</h2>
                <?php if (empty($upcoming_classes)): ?>
                    <p style="text-align: center; color: #666; margin: 20px 0;">No upcoming classes scheduled.</p>
                <?php else: ?>
                    <?php foreach ($upcoming_classes as $class): ?>
                        <div class="class-item">
                            <div class="class-info">
                                <h4><?php echo htmlspecialchars($class['Subject_Name']); ?></h4>
                                <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($class['Instructor_Name']); ?></p>
                                <p><i class="fas fa-map-marker-alt"></i> Room <?php echo htmlspecialchars($class['Room']); ?></p>
                            </div>
                            <div class="class-time">
                                <div><?php echo htmlspecialchars($class['Day']); ?></div>
                                <div><?php echo date('g:i A', strtotime($class['Start_Time'])); ?> - <?php echo date('g:i A', strtotime($class['End_Time'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="<?php echo $is_enrolled ? 'studentReport.php' : '#'; ?>" class="action-card <?php echo !$is_enrolled ? 'disabled-card' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <h3>Academic Report</h3>
                    <p><?php echo $is_enrolled ? 'View your grades and academic progress' : 'Available after enrollment'; ?></p>
                </a>
                <a href="<?php echo $is_enrolled ? 'studentAcademicStats.php' : '#'; ?>" class="action-card <?php echo !$is_enrolled ? 'disabled-card' : ''; ?>">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Academic Stats</h3>
                    <p><?php echo $is_enrolled ? 'Track attendance and performance' : 'Available after enrollment'; ?></p>
                </a>
                <a href="<?php echo $is_enrolled ? 'studentClearance.php' : '#'; ?>" class="action-card <?php echo !$is_enrolled ? 'disabled-card' : ''; ?>">
                    <i class="fas fa-clipboard-check"></i>
                    <h3>Clearance</h3>
                    <p><?php echo $is_enrolled ? 'Check your clearance status' : 'Available after enrollment'; ?></p>
                </a>
                <a href="<?php echo $is_enrolled ? 'studentSchedule.php' : '#'; ?>" class="action-card <?php echo !$is_enrolled ? 'disabled-card' : ''; ?>">
                    <i class="fas fa-clock"></i>
                    <h3>Schedule</h3>
                    <p><?php echo $is_enrolled ? 'View your class schedule' : 'Available after enrollment'; ?></p>
                </a>
            </div>
        </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script src="js/studentDashboard.js"></script>
    
    <script>
        // Enhanced dashboard interactivity
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($is_enrolled): ?>
            // Add smooth scrolling for better UX
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    document.querySelector(this.getAttribute('href')).scrollIntoView({
                        behavior: 'smooth'
                    });
                });
            });
            
            // Add loading animation for action cards
            const actionCards = document.querySelectorAll('.action-card:not(.disabled-card)');
            actionCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Add hover effects for stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
            
            // Auto-refresh dashboard every 5 minutes to keep data current
            setTimeout(function() {
                location.reload();
            }, 300000);
            <?php else: ?>
            // Handle disabled cards for non-enrolled students
            const disabledCards = document.querySelectorAll('.disabled-card');
            disabledCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    e.preventDefault();
                    alert('This feature is available after enrollment. Please contact the registrar\'s office to complete your enrollment.');
                });
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>