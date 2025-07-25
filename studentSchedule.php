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
    $error_message = "Student profile not found or not enrolled.";
    $student_id = null;
} else {
    $student_id = $student_data['Student_ID'];
    $student_name = trim($student_data['Given_Name'] . ' ' . $student_data['Last_Name']);
    $grade_level = $student_data['Grade_Level'];
    $school_year = $student_data['School_Year'];
    $section = $student_data['Section'];
}

// Get student's schedule
$schedule_data = [];
if ($student_id) {
    $schedule_sql = "SELECT DISTINCT sub.Subject_Name, sd.Day, sd.Start_Time, sd.End_Time,
                            CONCAT(pb.Given_Name, ' ', pb.Last_Name) as Instructor_Name,
                            cr.Room, cl.School_Year, cl.Grade_Level
                     FROM Schedule s
                     JOIN schedule_details sd ON s.Schedule_ID = sd.Schedule_ID
                     JOIN Subject sub ON s.Subject_ID = sub.Subject_ID
                     JOIN Instructor i ON s.Instructor_ID = i.Instructor_ID
                     JOIN Profile p ON i.Profile_ID = p.Profile_ID
                     JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                     JOIN Class c ON s.Class_ID = c.Class_ID
                     JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                     JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                     JOIN Enrollment e ON c.Class_ID = e.Class_ID
                     WHERE e.Student_ID = ? AND e.Status = 'Active'
                     ORDER BY FIELD(sd.Day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), sd.Start_Time";
    $stmt = $conn->prepare($schedule_sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $schedule_result = $stmt->get_result();
    
    while ($schedule = $schedule_result->fetch_assoc()) {
        $schedule_data[] = $schedule;
    }
}

// Organize schedule by day
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$schedule_by_day = [];
foreach ($days as $day) {
    $schedule_by_day[$day] = [];
}

foreach ($schedule_data as $schedule) {
    $schedule_by_day[$schedule['Day']][] = $schedule;
}

?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Schedule - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/studentSchedule.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .schedule-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .schedule-header {
            background: linear-gradient(180deg, #465ab1ff 0%, #384a9aff 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .schedule-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }
        
        .student-info {
            margin: 15px 0;
            opacity: 0.9;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .action-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95em;
            font-weight: 500;
            min-width: 140px;
            justify-content: center;
            white-space: nowrap;
        }
        
        .action-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
        }
        
        .action-btn:active {
            transform: translateY(0);
        }
        
        .action-btn i {
            font-size: 1.1em;
        }
        
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .day-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .day-card:hover {
            transform: translateY(-5px);
        }
        
        .day-header {
            background: linear-gradient(180deg, #465ab1ff 0%, #384a9aff 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .schedule-item {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .schedule-item:hover {
            background: #e9ecef;
            border-left-color: #764ba2;
        }
        
        .subject-name {
            font-weight: bold;
            color: #333;
            font-size: 1.1em;
            margin-bottom: 5px;
        }
        
        .schedule-time {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .schedule-details {
            color: #666;
            font-size: 0.9em;
        }
        
        .no-schedule {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 20px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        
        .back-btn {
            background: linear-gradient(180deg, #465ab1ff 0%, #384a9aff 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .schedule-summary {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .schedule-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            
            .action-btn {
                width: 100%;
                max-width: 250px;
                padding: 15px 20px;
                font-size: 1em;
            }
            
            .schedule-header h1 {
                font-size: 2em;
            }
            
            .schedule-container {
                padding: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .action-btn {
                min-width: auto;
                padding: 12px 16px;
                font-size: 0.9em;
            }
            
            .schedule-header {
                padding: 20px 15px;
            }
            
            .schedule-header h1 {
                font-size: 1.8em;
            }
        }
        
        @media (min-width: 769px) and (max-width: 1024px) {
            .action-buttons {
                gap: 12px;
            }
            
            .action-btn {
                padding: 10px 16px;
                font-size: 0.9em;
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <div id="header-placeholder"></div>
    
    <div class="schedule-container">
        <a href="studentDashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php else: ?>
            <!-- Header Section -->
            <div class="schedule-header">
                <h1><i class="fas fa-calendar-alt"></i> My Class Schedule</h1>
                <div class="student-info">
                    <p><strong><?php echo htmlspecialchars($student_name); ?></strong></p>
                    <p>Grade <?php echo $grade_level; ?> - Section <?php echo htmlspecialchars($section); ?></p>
                    <p>School Year: <?php echo htmlspecialchars($school_year); ?></p>
                </div>
                
                <div class="action-buttons">
                    <button class="action-btn" onclick="downloadPDF()" title="Download schedule as PDF">
                        <i class="fas fa-download"></i>
                        <span>Download PDF</span>
                    </button>
                    <button class="action-btn" onclick="printSchedule()" title="Print current schedule">
                        <i class="fas fa-print"></i>
                        <span>Print Schedule</span>
                    </button>
                </div>
            </div>
            
            <!-- Schedule Summary -->
            <div class="schedule-summary">
                <h2><i class="fas fa-chart-bar"></i> Schedule Overview</h2>
                <div class="summary-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($schedule_data); ?></div>
                        <div class="stat-label">Total Classes</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_unique(array_column($schedule_data, 'Subject_Name'))); ?></div>
                        <div class="stat-label">Subjects</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_unique(array_column($schedule_data, 'Instructor_Name'))); ?></div>
                        <div class="stat-label">Instructors</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($schedule_by_day, function($day) { return !empty($day); })); ?></div>
                        <div class="stat-label">Active Days</div>
                    </div>
                </div>
            </div>
            
            <!-- Weekly Schedule Grid -->
            <h2 style="text-align: center; margin: 30px 0; color: #333;">
                <i class="fas fa-calendar-week"></i> Weekly Schedule
            </h2>
            
            <?php if (empty($schedule_data)): ?>
                <div class="day-card">
                    <div class="no-schedule">
                        <i class="fas fa-calendar-times" style="font-size: 3em; color: #ccc; margin-bottom: 15px;"></i>
                        <h3>No Schedule Available</h3>
                        <p>You don't have any classes scheduled yet. Please contact your academic advisor.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="schedule-grid">
                    <?php foreach ($days as $day): ?>
                        <div class="day-card">
                            <div class="day-header">
                                <i class="fas fa-calendar-day"></i>
                                <?php echo $day; ?>
                            </div>
                            
                            <?php if (empty($schedule_by_day[$day])): ?>
                                <div class="no-schedule">
                                    <i class="fas fa-coffee"></i>
                                    No classes scheduled
                                </div>
                            <?php else: ?>
                                <?php foreach ($schedule_by_day[$day] as $class): ?>
                                    <div class="schedule-item">
                                        <div class="subject-name">
                                            <i class="fas fa-book"></i>
                                            <?php echo htmlspecialchars($class['Subject_Name']); ?>
                                        </div>
                                        <div class="schedule-time">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('g:i A', strtotime($class['Start_Time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($class['End_Time'])); ?>
                                        </div>
                                        <div class="schedule-details">
                                            <div><i class="fas fa-user"></i> <?php echo htmlspecialchars($class['Instructor_Name']); ?></div>
                                            <div><i class="fas fa-map-marker-alt"></i> Room <?php echo htmlspecialchars($class['Room']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    
    <script>
        function downloadPDF() {
            // Create a new window for PDF generation
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Student Schedule - <?php echo htmlspecialchars($student_name ?? 'Unknown'); ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .student-info { margin: 20px 0; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                        th { background-color: #667eea; color: white; }
                        .day-section { margin: 20px 0; }
                        .day-title { background: #f0f0f0; padding: 10px; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Class Schedule</h1>
                        <div class="student-info">
                            <p><strong><?php echo htmlspecialchars($student_name ?? 'Unknown'); ?></strong></p>
                            <p>Grade <?php echo $grade_level ?? 'N/A'; ?> - Section <?php echo htmlspecialchars($section ?? 'N/A'); ?></p>
                            <p>School Year: <?php echo htmlspecialchars($school_year ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    
                    <?php foreach ($days as $day): ?>
                        <div class="day-section">
                            <div class="day-title"><?php echo $day; ?></div>
                            <?php if (!empty($schedule_by_day[$day])): ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Time</th>
                                            <th>Instructor</th>
                                            <th>Room</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($schedule_by_day[$day] as $class): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($class['Subject_Name']); ?></td>
                                                <td><?php echo date('g:i A', strtotime($class['Start_Time'])); ?> - <?php echo date('g:i A', strtotime($class['End_Time'])); ?></td>
                                                <td><?php echo htmlspecialchars($class['Instructor_Name']); ?></td>
                                                <td>Room <?php echo htmlspecialchars($class['Room']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="text-align: center; color: #666; font-style: italic;">No classes scheduled</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        function printSchedule() {
            window.print();
        }
        
        // Add loading animation
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.day-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>