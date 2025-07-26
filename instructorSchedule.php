<?php 
require_once 'includes/db.php';

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Ensure user is logged in and has instructor permissions
if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Instructor') {
    header("Location: index.php");
    exit();
}

// // Get instructor ID and profile from session
$instructor_email = $_SESSION['email'];
$instructor_sql = "SELECT i.Instructor_ID, pb.Given_Name, pb.Last_Name, r.Email 
                    FROM Instructor i 
                    JOIN Profile p ON i.Profile_ID = p.Profile_ID
                    JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                    JOIN Account a ON p.Profile_ID = a.Profile_ID
                    JOIN Role r ON a.Role_ID = r.Role_ID
                    WHERE r.Email = ?;";
$stmt = $conn->prepare($instructor_sql);
$stmt->bind_param("s", $instructor_email);
$stmt->execute();
$instructor_result = $stmt->get_result();
$instructor_data = $instructor_result->fetch_assoc();

if (!$instructor_data) {
    $_SESSION['error_message'] = "Instructor profile not found.";
    header("Location: index.php");
    exit();
}

$instructor_id = $instructor_data['Instructor_ID'];
$instructor_name = $instructor_data['Given_Name'] . ' ' . $instructor_data['Last_Name'];

// Initialize messages
$success_message = '';
$error_message = '';

// Retrieve messages from session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get current week (Monday to Friday)
$current_date = new DateTime();
$monday = clone $current_date;
$monday->modify('monday this week');

// Week navigation
if (isset($_GET['week_offset'])) {
    $week_offset = (int)$_GET['week_offset'];
    $monday->modify("$week_offset weeks");
} else {
    $week_offset = 0;
}

// Generate week dates
$week_dates = [];
for ($i = 0; $i < 5; $i++) {
    $date = clone $monday;
    $date->modify("+$i days");
    $week_dates[] = $date;
}

// // Get instructor's schedule for the week
$schedule_sql = "SELECT
                    s.Schedule_ID,
                    s.Class_ID,
                    sd.Day,
                    sd.Start_Time,
                    sd.End_Time,
                    sub.Subject_Name,
                    cl.Grade_Level,
                    cl.School_Year,
                    cr.Room,
                    cr.Section,
                    COUNT(DISTINCT e.Student_ID) as Student_Count
                FROM Schedule s
                JOIN schedule_details sd ON s.Schedule_ID = sd.Schedule_ID
                JOIN Subject sub ON s.Subject_ID = sub.Subject_ID
                JOIN Class c ON s.Class_ID = c.Class_ID
                JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                LEFT JOIN Enrollment e ON c.Class_ID = e.Class_ID AND e.Status = 'Active'
                WHERE s.Instructor_ID = ?
                GROUP BY s.Schedule_ID, s.Class_ID, sd.Day, sd.Start_Time, sd.End_Time,
                sub.Subject_Name, cl.Grade_Level, cl.School_Year, cr.Room, cr.Section;";
$stmt = $conn->prepare($schedule_sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$schedule_result = $stmt->get_result();

// Organize schedule by day and time
$weekly_schedule = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
foreach ($days as $day) {
    $weekly_schedule[$day] = [];
}

while ($schedule = $schedule_result->fetch_assoc()) {
    $day = $schedule['Day'];
    $weekly_schedule[$day][] = $schedule;
}

// Get summary statistics
$stats_sql = "SELECT 
                COUNT(DISTINCT s.Schedule_ID) as Total_Classes,
                COUNT(DISTINCT s.Subject_ID) as Total_Subjects,
                COUNT(DISTINCT s.Class_ID) as Total_Sections,
                SUM(TIMESTAMPDIFF(MINUTE, sd.Start_Time, sd.End_Time)) as Total_Minutes
              FROM Schedule s 
              JOIN schedule_details sd ON s.Schedule_ID = sd.Schedule_ID
              WHERE s.Instructor_ID = ?";
$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$total_hours = round($stats['Total_Minutes'] / 60, 1);

// Get subjects taught
$subjects_sql = "SELECT DISTINCT sub.Subject_Name,
                        COUNT(DISTINCT s.Class_ID) as Class_Count
                 FROM Schedule s
                 JOIN Subject sub ON s.Subject_ID = sub.Subject_ID
                 WHERE s.Instructor_ID = ?
                 GROUP BY sub.Subject_ID, sub.Subject_Name
                 ORDER BY sub.Subject_Name;";
$stmt = $conn->prepare($subjects_sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$subjects_result = $stmt->get_result();

// Time slots for the grid (7 AM to 5 PM)
$time_slots = [];
for ($hour = 7; $hour <= 17; $hour++) {
    $time_slots[] = sprintf("%02d:00", $hour);
}

// Get current day of week for highlighting
$current_day_name = date('l'); // Gets full day name (Monday, Tuesday, etc.)
$today_date = date('Y-m-d');

function formatTime($time) {
    return date('g:i A', strtotime($time));
}

function getClassDuration($start, $end) {
    $start_time = strtotime($start);
    $end_time = strtotime($end);
    $duration = ($end_time - $start_time) / 60; // in minutes
    return $duration;
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/adminLinks.css">
    <link rel="stylesheet" href="css/adminManagement.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .schedule-grid {
            display: grid;
            grid-template-columns: 80px repeat(5, 1fr);
            gap: 1px;
            background: #ddd;
            border-radius: 8px;
            overflow: hidden;
            margin: 20px 0;
            min-width: 700px;
            max-width: 100%;
        }
        
        .schedule-header {
            background: #2c3e50;
            color: white;
            padding: 15px 10px;
            text-align: center;
            font-weight: bold;
        }
        
        .schedule-header.current-day {
            background: #e74c3c;
            box-shadow: 0 0 10px rgba(231, 76, 60, 0.5);
        }
        
        .time-slot {
            background: #34495e;
            color: white;
            padding: 10px 5px;
            text-align: center;
            font-size: 12px;
            writing-mode: vertical-lr;
            text-orientation: mixed;
        }
        
        .schedule-cell {
            background: white;
            min-height: 80px;
            padding: 5px;
            position: relative;
        }
        
        .schedule-cell.current-day {
            background: #ffeaa7;
            border-left: 3px solid #e74c3c;
            border-right: 3px solid #e74c3c;
        }
        
        .class-block {
            background: #3498db;
            color: white;
            padding: 10px;
            border-radius: 6px;
            margin: 2px 0;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #2980b9;
        }
        
        .current-day .class-block {
            background: #e67e22;
            border-left: 4px solid #d35400;
            animation: pulse-glow 2s infinite;
        }
        
        @keyframes pulse-glow {
            0% { box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            50% { box-shadow: 0 4px 8px rgba(230, 126, 34, 0.3); }
            100% { box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        }
        
        .class-block:hover {
            background: #2980b9;
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.15);
        }
        
        .current-day .class-block:hover {
            background: #d35400;
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(211, 84, 0, 0.3);
        }
        
        .class-subject {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 13px;
        }
        
        .class-details {
            font-size: 11px;
            opacity: 0.95;
            line-height: 1.3;
        }
        
        .class-time {
            font-size: 10px;
            opacity: 0.8;
            margin-top: 3px;
        }
        
        .week-navigation {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .week-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .week-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .week-display {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #3498db;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .subjects-list {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .subject-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .subject-item:last-child {
            border-bottom: none;
        }
        
        .subject-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .class-count {
            background: #3498db;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
        }
        
        .date-display {
            font-size: 14px;
            color: #d7d7d7ff;
            margin-top: 5px;
        }
        
        .legend {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
        <div class="admin-back-btn-wrap admin-back-btn-upperleft">
            <a href="index.php" class="admin-back-btn"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        <h1 class="page-title">My Teaching Schedule</h1>
        <p style="text-align: center; color: #7f8c8d; margin-bottom: 30px;">
            <i class="fas fa-user-tie"></i> Welcome, <?php echo htmlspecialchars($instructor_name); ?>
        </p>
        
        <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Teaching Statistics -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-chart-bar"></i> Teaching Overview</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['Total_Classes']; ?></div>
                    <div class="stat-label">Total Classes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['Total_Subjects']; ?></div>
                    <div class="stat-label">Subjects Taught</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['Total_Sections']; ?></div>
                    <div class="stat-label">Class Sections</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_hours; ?>h</div>
                    <div class="stat-label">Weekly Hours</div>
                </div>
            </div>
        </section>
        
        <!-- Subjects Taught -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-book"></i> Subjects I Teach</h2>
            <div class="subjects-list">
                <?php while($subject = $subjects_result->fetch_assoc()): ?>
                    <div class="subject-item">
                        <div>
                            <div class="subject-name"><?php echo htmlspecialchars($subject['Subject_Name']); ?></div>
                        </div>
                        <div class="class-count"><?php echo $subject['Class_Count']; ?> class<?php echo $subject['Class_Count'] > 1 ? 'es' : ''; ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>
        
        <!-- Week Navigation -->
        <section class="frm-sectioon">
            <h2 class="form-title"><i class="fas fa-calendar-week"></i> Weekly Schedule</h2>
            <div class="week-navigation">
                <a href="?week_offset=<?php echo $week_offset - 1; ?>" class="week-btn">
                    <i class="fas fa-chevron-left"></i> Previous Week
                </a>
                <div>
                    <div class="week-display">
                        <?php echo $monday->format('M j'); ?> - <?php echo $week_dates[4]->format('M j, Y'); ?>
                    </div>
                    <div style="text-align: center; font-size: 14px; color: #7f8c8d;">
                        <?php if ($week_offset == 0): ?>
                            This Week
                        <?php elseif ($week_offset == 1): ?>
                            Next Week
                        <?php elseif ($week_offset == -1): ?>
                            Last Week
                        <?php else: ?>
                            <?php echo abs($week_offset); ?> week<?php echo abs($week_offset) > 1 ? 's' : ''; ?> <?php echo $week_offset > 0 ? 'ahead' : 'ago'; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="?week_offset=<?php echo $week_offset + 1; ?>" class="week-btn">
                    Next Week <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <!-- Instructions -->
            <div style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <p style="margin: 0; color: #7f8c8d; font-size: 14px;">
                    <i class="fas fa-info-circle"></i> Click on any class block to manage attendance for that specific class
                </p>
            </div>
            
            <!-- Schedule Grid -->
            <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <div class="schedule-grid">
                <!-- Header row -->
                <div class="schedule-header">Time</div>
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <?php 
                    $is_current_day = ($days[$i] === $current_day_name && 
                                     $week_dates[$i]->format('Y-m-d') === $today_date);
                    ?>
                    <div class="schedule-header <?php echo $is_current_day ? 'current-day' : ''; ?>">
                        <?php echo $days[$i]; ?>
                        <div class="date-display"><?php echo $week_dates[$i]->format('M j'); ?></div>
                        <?php if ($is_current_day): ?>
                            <div style="font-size: 12px; margin-top: 3px;">
                                <i class="fas fa-star"></i> Today
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
                
                <!-- Time slots and schedule -->
                <?php foreach ($time_slots as $time): ?>
                    <div class="time-slot"><?php echo date('g A', strtotime($time)); ?></div>
                    
                    <?php foreach ($days as $day): ?>
                        <?php 
                        $day_index = array_search($day, $days);
                        $is_current_day = ($day === $current_day_name && 
                                         $week_dates[$day_index]->format('Y-m-d') === $today_date);
                        ?>
                        <div class="schedule-cell <?php echo $is_current_day ? 'current-day' : ''; ?>">
                            <?php foreach ($weekly_schedule[$day] as $class): ?>
                                <?php 
                                $start_hour = (int)date('H', strtotime($class['Start_Time']));
                                $slot_hour = (int)date('H', strtotime($time));
                                $duration = getClassDuration($class['Start_Time'], $class['End_Time']);
                                
                                if ($start_hour == $slot_hour): ?>
                                    <div class="class-block" 
                                         title="<?php echo htmlspecialchars($class['Subject_Name']); ?> - Grade <?php echo $class['Grade_Level']; ?> Section <?php echo htmlspecialchars($class['Section']); ?>"
                                         onclick="showClassDetails(<?php echo $class['Class_ID']; ?>, '<?php echo $class['Start_Time']; ?>', '<?php echo $class['End_Time']; ?>')">
                                        <div class="class-subject"><?php echo htmlspecialchars($class['Subject_Name']); ?></div>
                                        <div class="class-details">
                                            Grade <?php echo $class['Grade_Level']; ?> - <?php echo htmlspecialchars($class['Section']); ?><br>
                                            Room <?php echo htmlspecialchars($class['Room']); ?>
                                        </div>
                                        <div class="class-time">
                                            <?php echo formatTime($class['Start_Time']); ?> - <?php echo formatTime($class['End_Time']); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
            </div>
        </section>
        
        <!-- Quick Actions -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-bolt"></i> Quick Actions</h2>
            <div style="padding: 20px;">
                <div class="button-group">
                    <a href="instructorRecord.php" class="submit-btn">
                        <i class="fas fa-chart-line"></i> Manage Grades
                    </a>
                    <a href="instructorAttendanceManagement.php" class="submit-btn">
                        <i class="fas fa-user-check"></i> Take Attendance
                    </a>
                    <a href="instructorSubjectClearance.php" class="submit-btn">
                        <i class="fas fa-clipboard-check"></i> Student Clearance
                    </a>
                    <a href="studentReport.php" class="submit-btn">
                        <i class="fas fa-file-alt"></i> Generate Reports
                    </a>
                    <button class="cancel-btn" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Schedule
                    </button>
                </div>
            </div>
        </section>
    </div>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script>
        function showClassDetails(classId, startTime, endTime) {
            // Redirect to the attendance management page with the specific class and time period pre-selected
            window.location.href = `instructorAttendanceManagement.php?class_id=${classId}&start_time=${startTime}&end_time=${endTime}`;
        }
        
        // Add some interactivity to the schedule
        document.querySelectorAll('.class-block').forEach(block => {
            block.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
            });
            
            block.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
        
        // Print styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                .admin-back-btn-wrap, .button-group, footer, header {
                    display: none !important;
                }
                .admin-container {
                    margin: 0 !important;
                    padding: 20px !important;
                }
                .schedule-grid {
                    font-size: 10px;
                }
                .class-block {
                    break-inside: avoid;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
