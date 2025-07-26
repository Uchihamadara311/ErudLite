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
$student_sql = "SELECT s.Student_ID, pb.Given_Name, pb.Last_Name, cl.Grade_Level, cl.School_Year, cr.Section
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
$stmt = $conn->prepare($student_sql);
$stmt->bind_param("s", $student_email);
$stmt->execute();
$student_result = $stmt->get_result();
$student_data = $student_result->fetch_assoc();

if (!$student_data) {
    $_SESSION['error_message'] = "Student profile not found.";
    header("Location: index.php");
    exit();
}

$student_id = $student_data['Student_ID'];
$student_name = trim($student_data['Given_Name'] . ' ' . $student_data['Last_Name']);
$grade_level = $student_data['Grade_Level'];
$school_year = $student_data['School_Year'];
$section = $student_data['Section'];

// Get current semester/quarter
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

// Get student's grades for current school year
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

// Calculate overall GPA and prepare subjects data
$subjects_data = [];
$total_grades = 0;
$subject_count = 0;

while ($subject = $grades_result->fetch_assoc()) {
    $subjects_data[] = $subject;
    if ($subject['Average_Grade']) {
        $total_grades += $subject['Average_Grade'];
        $subject_count++;
    }
}

$overall_gpa = $subject_count > 0 ? round($total_grades / $subject_count, 2) : 0;

// Get attendance data for the current school year
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

// Calculate attendance percentage
$total_attendance_days = $attendance_data['Total_Days'] ?: 1;
$present_percentage = round(($attendance_data['Present_Count'] / $total_attendance_days) * 100, 1);

// Get recent attendance pattern (last 30 days)
$recent_attendance_sql = "SELECT a.Status, a.Date
                          FROM Attendance a
                          JOIN Class c ON a.Class_ID = c.Class_ID
                          JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                          WHERE a.Student_ID = ? AND cl.School_Year = ? 
                          AND a.Date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                          ORDER BY a.Date DESC
                          LIMIT 30";
$stmt = $conn->prepare($recent_attendance_sql);
$stmt->bind_param("is", $student_id, $school_year);
$stmt->execute();
$recent_attendance_result = $stmt->get_result();

$recent_attendance = [];
while ($attendance = $recent_attendance_result->fetch_assoc()) {
    $recent_attendance[] = $attendance;
}

// Simulate appeal count (you can create an appeals table if needed)
$appeal_count = 0; // This would come from an appeals table in a real system

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMS - Academic Statistics</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/academicStats.css">
    <style>
        .attendance-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin: 2px;
            display: inline-block;
            cursor: pointer;
        }
        
        .attendance-dot.present { background-color: #4CAF50; }
        .attendance-dot.absent { background-color: #F44336; }
        .attendance-dot.late { background-color: #FF9800; }
        .attendance-dot.excused { background-color: #9E9E9E; }
        .attendance-dot.no-data { background-color: #E0E0E0; }
        
        .attendance-calendar {
            margin: 10px 0;
        }
        
        .calendar-header {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .month-year {
            font-weight: bold;
            font-size: 14px;
            color: #333;
        }
        
        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            margin-bottom: 5px;
        }
        
        .weekday {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            color: #777;
            padding: 3px;
        }
        
        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            border-radius: 4px;
            cursor: pointer;
            position: relative;
            min-height: 25px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
        }
        
        .calendar-day.empty {
            background: transparent;
            border: none;
            cursor: default;
        }
        
        .calendar-day.weekend {
            background-color: #f0f0f0;
            color: #999;
        }
        
        .calendar-day.future {
            background-color: #fafafa;
            color: #ccc;
            cursor: default;
        }
        
        .calendar-day.today {
            border: 2px solid #007bff;
            font-weight: bold;
        }
        
        .calendar-day.present {
            background-color: #4CAF50;
            color: white;
        }
        
        .calendar-day.absent {
            background-color: #F44336;
            color: white;
        }
        
        .calendar-day.late {
            background-color: #FF9800;
            color: white;
        }
        
        .calendar-day.excused {
            background-color: #9E9E9E;
            color: white;
        }
        
        .calendar-day.no-data {
            background-color: #E0E0E0;
            color: #777;
        }
        
        .calendar-day:hover:not(.empty):not(.future) {
            transform: scale(1.1);
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .day-number {
            font-weight: bold;
        }
        
        .status-indicator {
            width: 4px;
            height: 4px;
            background-color: rgba(255,255,255,0.8);
            border-radius: 50%;
            margin-top: 1px;
        }
        
        .grade-bar {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .grade-bar:hover {
            transform: scale(1.05);
            filter: brightness(1.1);
        }
        
        .grades-chart {
            padding-top: 30px; /* Add some top padding to accommodate the moved bars */
        }
        
        .leftContainer {
            padding: 20px;
        }
        
        .leftContainer h1 {
            margin: 15px 0 5px 0;
            color: #333;
        }
        
        .leftContainer p {
            margin: 0;
            color: #777;
            font-weight: 500;
        }
        
        /* Back to Dashboard Button Styling */
        .backButton {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(180deg, #475cb6ff 0%, #3a4c9dff 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            border: none;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .backButton:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            background: linear-gradient(180deg, #465ab1ff 0%, #384a9aff 100%);
            color: white;
            text-decoration: none;
        }
        
        .backButton:active {
            transform: translateY(0px);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }
        
        .backButton i {
            font-size: 16px;
            transition: transform 0.3s ease;
        }
        
        .backButton:hover i {
            transform: translateX(-3px);
        }
        
        /* Responsive design for back button */
        @media (max-width: 768px) {
            .backButton {
                padding: 10px 16px;
                font-size: 13px;
                margin-bottom: 15px;
            }
            
            .backButton i {
                font-size: 14px;
            }
        }
    </style>

</head>
<body>
    <header id="header-placeholder"></header>
    
    <main>
        <div class="leftContainer">
            <a href="studentDashboard.php" class="backButton">
                <i class="fa fa-arrow-left" style="margin-right: 10px"></i>Back to Dashboard
            </a>
            <div class="profileSection">
                <img src="assets/profile.png" alt="Profile Picture" class="imageCircle">
            </div>
            <h1><?php echo htmlspecialchars($student_name); ?></h1>
            <p>GRADE <?php echo $grade_level; ?> (Class <?php echo htmlspecialchars($section); ?>)</p>
            <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 8px; color: #777;">
                <small><strong>School Year:</strong> <?php echo htmlspecialchars($school_year); ?></small><br>
                <small><strong>Current Term:</strong> <?php echo htmlspecialchars($current_term); ?></small>
            </div>
        </div>
        
        <div class="rightContainer">
            <div class="leftCards">
                <div class="dashboard-card GPA">
                    <h1 class="card-title">GPA</h1>
                    <div class="gpa-display" id="gpaDisplay"><?php echo $overall_gpa; ?></div>
                    <div style="font-size: 12px; color: #777; margin-top: 5px;">
                        Based on <?php echo $subject_count; ?> subjects
                    </div>
                </div>
                <div class="dashboard-card ATTENDANCE">
                    <h1 class="card-title">Attendance</h1>
                    <div class="attendance-calendar" id="attendanceCalendar">
                        <div class="calendar-header">
                            <div class="month-year"><?php echo date('F Y'); ?></div>
                        </div>
                        <div class="calendar-weekdays">
                            <div class="weekday">Sun</div>
                            <div class="weekday">Mon</div>
                            <div class="weekday">Tue</div>
                            <div class="weekday">Wed</div>
                            <div class="weekday">Thu</div>
                            <div class="weekday">Fri</div>
                            <div class="weekday">Sat</div>
                        </div>
                        <div class="calendar-days">
                            <?php 
                            // Get current month and year
                            $current_month = date('n');
                            $current_year = date('Y');
                            $first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
                            $days_in_month = date('t', $first_day_of_month);
                            $first_weekday = date('w', $first_day_of_month);
                            $today = date('j');
                            
                            // Create a map of dates to attendance status
                            $attendance_map = [];
                            foreach ($recent_attendance as $att) {
                                $attendance_map[$att['Date']] = strtolower($att['Status']);
                            }
                            
                            // Add empty cells for days before the first day of the month
                            for ($i = 0; $i < $first_weekday; $i++) {
                                echo "<div class='calendar-day empty'></div>";
                            }
                            
                            // Generate calendar days
                            for ($day = 1; $day <= $days_in_month; $day++) {
                                $date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
                                $day_of_week = date('w', mktime(0, 0, 0, $current_month, $day, $current_year));
                                $is_weekend = ($day_of_week == 0 || $day_of_week == 6);
                                $is_today = ($day == $today);
                                $is_future = strtotime($date) > time();
                                
                                $status = isset($attendance_map[$date]) ? $attendance_map[$date] : 'no-data';
                                
                                $class = 'calendar-day';
                                if ($is_today) $class .= ' today';
                                if ($is_weekend) $class .= ' weekend';
                                if ($is_future) $class .= ' future';
                                
                                if (!$is_weekend && !$is_future && $status !== 'no-data') {
                                    $class .= ' ' . $status;
                                } elseif (!$is_weekend && !$is_future) {
                                    $class .= ' no-data';
                                }
                                
                                $title = $date;
                                if (!$is_weekend && !$is_future) {
                                    $title .= ' - ' . ucfirst($status === 'no-data' ? 'No Record' : $status);
                                } elseif ($is_weekend) {
                                    $title .= ' - Weekend';
                                } elseif ($is_future) {
                                    $title .= ' - Future Date';
                                }
                                
                                echo "<div class='$class' title='$title' data-date='$date'>";
                                echo "<span class='day-number'>$day</span>";
                                if (!$is_weekend && !$is_future && $status !== 'no-data') {
                                    echo "<div class='status-indicator'></div>";
                                }
                                echo "</div>";
                            }
                            ?>
                        </div>
                    </div>
                    <div class="attendance-legend">
                        <div class="legend-item">
                            <div class="legend-dot present"></div>
                            <span>Present (<?php echo $attendance_data['Present_Count']; ?>)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot absent"></div>
                            <span>Absent (<?php echo $attendance_data['Absent_Count']; ?>)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot late"></div>
                            <span>Late (<?php echo $attendance_data['Late_Count']; ?>)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot excused"></div>
                            <span>Excused (<?php echo $attendance_data['Excused_Count']; ?>)</span>
                        </div>
                    </div>
                    <?php if ($appeal_count > 0): ?>
                    <div class="appeal-badge"><?php echo $appeal_count; ?> Appeal for Absence</div>
                    <?php endif; ?>
                    <div style="margin-top: 10px; font-size: 14px; color: #777;">
                        Attendance Rate: <strong><?php echo $present_percentage; ?>%</strong>
                    </div>
                </div>
                
                <div class="dashboard-card QUARTER">
                    <h1 class="card-title">Current Term</h1>
                    <div class="quarter-display"><?php echo htmlspecialchars($current_term); ?></div>
                </div>
            </div>
            
            <div class="rightCards">
                <div class="SUBJECT_GRADES">
                    <h2>Subject Grades</h2>
                    <p>Current Academic Performance</p>
                    <div class="grades-chart">
                        <?php 
                        if (!empty($subjects_data)) {
                            foreach ($subjects_data as $subject) {
                                $grade = round($subject['Average_Grade'], 1);
                                $height = ($grade / 100) * 100; // Convert to percentage for height
                                $subject_name = htmlspecialchars($subject['Subject_Name']);
                                
                                // Limit subject name length for display
                                $display_name = strlen($subject_name) > 8 ? substr($subject_name, 0, 8) . '...' : $subject_name;
                                
                                echo "<div class='grade-bar' style='height: {$height}%;' onclick='showGradeDetails(\"{$subject_name}\", {$grade})' title='{$subject_name}: {$grade}'>";
                                echo "<div class='grade-value'>{$grade}</div>";
                                echo "<div class='subject-label'>{$display_name}</div>";
                                echo "</div>";
                            }
                        } else {
                            echo "<div style='text-align: center; color: #777; padding: 20px;'>";
                            echo "<i class='fas fa-info-circle'></i><br>";
                            echo "No grades available for current school year";
                            echo "</div>";
                        }
                        ?>
                    </div>
                    <?php if (!empty($subjects_data)): ?>
                    <div style="margin-top: 70px; font-size: 12px; color: #777; text-align: center;">
                        Click on any bar to see detailed grade information
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <footer id="footer-placeholder"></footer>
    
    <script src="js/layout-loader.js"></script>
    <script>
        function showGradeDetails(subject, grade) {
            alert(`Subject: ${subject}\nCurrent Average: ${grade}\n\nClick on "View Statistics" in the Student Report for detailed grade breakdown.`);
        }
        
        // Update GPA display with animation
        document.addEventListener('DOMContentLoaded', function() {
            const gpaDisplay = document.getElementById('gpaDisplay');
            const targetGPA = parseFloat(gpaDisplay.textContent);
            let currentGPA = 0;
            const increment = targetGPA / 50; // Animation steps
            
            const animateGPA = setInterval(function() {
                currentGPA += increment;
                if (currentGPA >= targetGPA) {
                    currentGPA = targetGPA;
                    clearInterval(animateGPA);
                }
                gpaDisplay.textContent = currentGPA.toFixed(2);
            }, 20);
            
            // Add hover effects to attendance calendar days
            const calendarDays = document.querySelectorAll('.calendar-day:not(.empty):not(.future)');
            calendarDays.forEach(day => {
                day.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('weekend')) {
                        const dayNumber = this.querySelector('.day-number');
                        if (dayNumber) {
                            dayNumber.style.fontSize = '12px';
                        }
                    }
                });
                
                day.addEventListener('mouseleave', function() {
                    const dayNumber = this.querySelector('.day-number');
                    if (dayNumber) {
                        dayNumber.style.fontSize = '10px';
                    }
                });
                
                day.addEventListener('click', function() {
                    const date = this.getAttribute('data-date');
                    const title = this.getAttribute('title');
                    if (date && !this.classList.contains('weekend')) {
                        alert(`Date: ${date}\nStatus: ${title.split(' - ')[1]}`);
                    }
                });
            });
            
            // Add grade bar interactions
            const gradeBars = document.querySelectorAll('.grade-bar');
            gradeBars.forEach(bar => {
                bar.addEventListener('mouseenter', function() {
                    const gradeValue = this.querySelector('.grade-value');
                    if (gradeValue) {
                        gradeValue.style.fontSize = '14px';
                        gradeValue.style.fontWeight = 'bold';
                    }
                });
                
                bar.addEventListener('mouseleave', function() {
                    const gradeValue = this.querySelector('.grade-value');
                    if (gradeValue) {
                        gradeValue.style.fontSize = '12px';
                        gradeValue.style.fontWeight = 'normal';
                    }
                });
            });
        });
    </script>
</body>
</html>