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

// Get instructor ID from session
$instructor_email = $_SESSION['email'];
$instructor_sql = "SELECT i.Instructor_ID
                    FROM Instructor i 
                    JOIN Profile p ON i.Profile_ID = p.Profile_ID 
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

// Initialize messages
$success_message = '';
$error_message = '';

// Handle form submission for attendance
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_id = (int)$_POST['class_id'];
    $attendance_date = cleanInput($_POST['attendance_date']);
    $start_time = cleanInput($_POST['start_time']);
    $end_time = cleanInput($_POST['end_time']);
    $attendance_data = $_POST['attendance'] ?? [];
    
    // Verify instructor has access to this class
    $verify_sql = "SELECT c.Class_ID FROM Class c 
                  JOIN Schedule s ON c.Class_ID = s.Class_ID 
                  WHERE s.Instructor_ID = ? AND c.Class_ID = ?";
    $stmt = $conn->prepare($verify_sql);
    $stmt->bind_param("ii", $instructor_id, $class_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $_SESSION['error_message'] = "You don't have permission to manage attendance for this class.";
    } else {
        $success_count = 0;
        $error_count = 0;
        
        foreach ($attendance_data as $student_id => $status) {
            $student_id = (int)$student_id;
            $status = cleanInput($status);
            
            if (!in_array($status, ['Present', 'Absent', 'Late', 'Excused'])) {
                $error_count++;
                continue;
            }
            
            // Check if attendance record already exists
            $check_sql = "SELECT Attendance_ID FROM Attendance WHERE Student_ID = ? AND Class_ID = ? AND Date = ? AND Start_Time = ? AND End_Time = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("iisss", $student_id, $class_id, $attendance_date, $start_time, $end_time);
            $stmt->execute();
            $existing = $stmt->get_result();
            
            if ($existing->num_rows > 0) {
                // Update existing record
                $attendance_id = $existing->fetch_assoc()['Attendance_ID'];
                $sql = "UPDATE Attendance SET Status = ? WHERE Attendance_ID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $status, $attendance_id);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } else {
                // Insert new record
                $sql = "INSERT INTO Attendance (Student_ID, Class_ID, Date, Start_Time, End_Time, Status) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iissss", $student_id, $class_id, $attendance_date, $start_time, $end_time, $status);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($success_count > 0) {
            $_SESSION['success_message'] = "Attendance updated for $success_count student(s).";
        }
        if ($error_count > 0) {
            $_SESSION['error_message'] = "Failed to update attendance for $error_count student(s).";
        }
    }
    
    $redirect_url = "instructorAttendanceManagement.php";
    $params = [];
    if (!empty($class_id)) $params[] = "class_id=" . $class_id;
    if (!empty($attendance_date)) $params[] = "date=" . $attendance_date;
    if (!empty($start_time) && !empty($end_time)) {
        $params[] = "class_period=" . urlencode($start_time . '|' . $end_time);
    }
    
    if (!empty($params)) {
        $redirect_url .= "?" . implode("&", $params);
    }
    
    header("Location: " . $redirect_url);
    exit();
}

// Retrieve messages from session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get current date and time parameters
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_start_time = isset($_GET['start_time']) ? $_GET['start_time'] : '';
$selected_end_time = isset($_GET['end_time']) ? $_GET['end_time'] : '';

// Handle class_period selection (format: start_time|end_time)
if (isset($_GET['class_period']) && !empty($_GET['class_period'])) {
    $period_parts = explode('|', $_GET['class_period']);
    if (count($period_parts) == 2) {
        $selected_start_time = $period_parts[0];
        $selected_end_time = $period_parts[1];
    }
}

// Get instructor's classes with detailed time and subject information
$classes_sql = "SELECT DISTINCT c.Class_ID, cl.Grade_Level, cl.School_Year, cr.Room, cr.Section,
                       s.Subject_ID, sub.Subject_Name,
                       GROUP_CONCAT(DISTINCT CONCAT(sd.Day, ': ', sd.Start_Time, '-', sd.End_Time) ORDER BY sd.Day, sd.Start_Time SEPARATOR ' | ') as Schedule_Details,
                       GROUP_CONCAT(DISTINCT CONCAT(sd.Start_Time, '-', sd.End_Time) ORDER BY sd.Start_Time SEPARATOR ', ') as Time_Slots
                FROM Class c
                JOIN Schedule s ON c.Class_ID = s.Class_ID
                JOIN Subject sub ON s.Subject_ID = sub.Subject_ID
                JOIN schedule_details sd ON s.Schedule_ID = sd.Schedule_ID
                JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                WHERE s.Instructor_ID = ?
                GROUP BY c.Class_ID, cl.Grade_Level, cl.School_Year, cr.Room, cr.Section, s.Subject_ID, sub.Subject_Name
                ORDER BY cl.Grade_Level, cr.Room, sub.Subject_Name";
$stmt = $conn->prepare($classes_sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$classes_result = $stmt->get_result();

// Get specific schedule details for selected class
$selected_class_schedules = [];
if (isset($_GET['class_id']) && !empty($_GET['class_id'])) {
    $selected_class_id = (int)$_GET['class_id'];
    
    $schedule_details_sql = "SELECT sd.Day, sd.Start_Time, sd.End_Time, sub.Subject_Name
                            FROM Schedule s
                            JOIN schedule_details sd ON s.Schedule_ID = sd.Schedule_ID
                            JOIN Subject sub ON s.Subject_ID = sub.Subject_ID
                            JOIN Class c ON s.Class_ID = c.Class_ID
                            WHERE s.Instructor_ID = ? AND c.Class_ID = ?
                            ORDER BY FIELD(sd.Day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), sd.Start_Time";
    $stmt = $conn->prepare($schedule_details_sql);
    $stmt->bind_param("ii", $instructor_id, $selected_class_id);
    $stmt->execute();
    $schedule_details_result = $stmt->get_result();
    
    while ($schedule = $schedule_details_result->fetch_assoc()) {
        $selected_class_schedules[] = $schedule;
    }
}

// Get students and attendance for selected class and date
$students_data = [];
$attendance_stats = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0];
$selected_subject_name = '';

if (isset($_GET['class_id']) && !empty($_GET['class_id'])) {
    $selected_class_id = (int)$_GET['class_id'];
    
    // Verify instructor has access to this class and get subject name
    $verify_sql = "SELECT c.Class_ID, sub.Subject_Name FROM Class c 
                  JOIN Schedule s ON c.Class_ID = s.Class_ID 
                  JOIN Subject sub ON s.Subject_ID = sub.Subject_ID
                  WHERE s.Instructor_ID = ? AND c.Class_ID = ?";
    $stmt = $conn->prepare($verify_sql);
    $stmt->bind_param("ii", $instructor_id, $selected_class_id);
    $stmt->execute();
    $verify_result = $stmt->get_result();
    if ($verify_result->num_rows > 0) {
        $class_info = $verify_result->fetch_assoc();
        $selected_subject_name = $class_info['Subject_Name'];
        
        $students_sql = "SELECT DISTINCT s.Student_ID, pb.Given_Name, pb.Last_Name,
                               a.Status as Attendance_Status
                        FROM Student s
                        JOIN Profile p ON s.Profile_ID = p.Profile_ID
                        JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                        JOIN Enrollment e ON s.Student_ID = e.Student_ID
                        LEFT JOIN Attendance a ON s.Student_ID = a.Student_ID 
                                               AND a.Class_ID = ? 
                                               AND a.Date = ? 
                                               AND (a.Start_Time = ? OR a.Start_Time IS NULL)
                                               AND (a.End_Time = ? OR a.End_Time IS NULL)
                        WHERE e.Class_ID = ? AND e.Status = 'Active'
                        ORDER BY pb.Last_Name, pb.Given_Name";
        $stmt = $conn->prepare($students_sql);
        $stmt->bind_param("isssi", $selected_class_id, $selected_date, $selected_start_time, $selected_end_time, $selected_class_id);
        $stmt->execute();
        $students_result = $stmt->get_result();
        
        while ($student = $students_result->fetch_assoc()) {
            $students_data[] = $student;
            if ($student['Attendance_Status']) {
                $attendance_stats[$student['Attendance_Status']]++;
            }
        }
    }
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/adminLinks.css">
    <link rel="stylesheet" href="css/adminManagement.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .attendance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 15px;
            max-width: 100%;
            margin: 20px 0;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .attendance-grid {
                grid-template-columns: repeat(auto-fit, minmax(70px, 1fr));
                gap: 10px;
            }
        }
        
        .attendance-dot {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            border: 3px solid transparent;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin: 0 auto;
        }
        
        .attendance-dot:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .attendance-dot.not-marked {
            background-color: #e0e0e0;
            color: #666;
            border-color: #ccc;
        }
        
        .attendance-dot.present {
            background-color: #4CAF50;
            border-color: #45a049;
        }
        
        .attendance-dot.absent {
            background-color: #F44336;
            border-color: #da190b;
        }
        
        .attendance-dot.late {
            background-color: #FF9800;
            border-color: #f57c00;
        }
        
        .attendance-dot.excused {
            background-color: #9E9E9E;
            border-color: #757575;
        }
        
        .attendance-dot.appeal {
            background-color: #8B0000;
            border: 3px solid #FFD700;
        }
        
        .attendance-legend {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
        }
        
        .legend-dot.not-marked {
            background-color: #e0e0e0;
            border: 2px solid #ccc;
        }
        
        .legend-dot.present {
            background-color: #4CAF50;
        }
        
        .legend-dot.absent {
            background-color: #F44336;
        }
        
        .legend-dot.late {
            background-color: #FF9800;
        }
        
        .legend-dot.excused {
            background-color: #9E9E9E;
        }
        
        .legend-dot.appeal {
            background-color: #8B0000;
            border: 2px solid #FFD700;
        }
        
        .attendance-stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 6px;
            border-left: 4px solid #ddd;
        }
        
        .stat-item.present {
            border-left-color: #4CAF50;
        }
        
        .stat-item.absent {
            border-left-color: #F44336;
        }
        
        .stat-item.late {
            border-left-color: #FF9800;
        }
        
        .stat-item.excused {
            border-left-color: #9E9E9E;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .attendance-controls {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .radio-group {
            display: flex;
            gap: 10px;
            align-items: center;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
        }
        
        .radio-group input[type="radio"] {
            margin-right: 5px;
        }
        
        .appeal-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #FF5722;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .message.info {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            color: #1976d2;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        
        .message.info i {
            color: #2196f3;
            margin-right: 8px;
        }
        
        /* Enhanced table styling for Detailed Attendance */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: center;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            color: #495057;
        }
        
        .data-table th:first-child {
            text-align: left;
            width: 40%;
        }
        
        .data-table th:not(:first-child) {
            width: 15%;
        }
        
        .data-table td {
            padding: 12px 15px;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .data-table td:first-child {
            text-align: left;
            font-weight: 500;
            color: #495057;
        }
        
        .data-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .data-table input[type="radio"] {
            transform: scale(1.2);
            margin: 0;
            cursor: pointer;
        }
        
        /* Radio button colors */
        .data-table td:nth-child(2) input[type="radio"]:checked {
            accent-color: #4CAF50;
        }
        
        .data-table td:nth-child(3) input[type="radio"]:checked {
            accent-color: #F44336;
        }
        
        .data-table td:nth-child(4) input[type="radio"]:checked {
            accent-color: #FF9800;
        }
        
        .data-table td:nth-child(5) input[type="radio"]:checked {
            accent-color: #9E9E9E;
        }
        
        /* Responsive table */
        @media (max-width: 768px) {
            .data-table {
                font-size: 14px;
            }
            
            .data-table th,
            .data-table td {
                padding: 8px 6px;
            }
            
            .data-table th:first-child,
            .data-table td:first-child {
                width: 35%;
            }
            
            .data-table th:not(:first-child),
            .data-table td:not(:first-child) {
                width: 16.25%;
            }
        }
    </style>
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
        <div class="admin-back-btn-wrap admin-back-btn-upperleft">
            <a href="index.php" class="admin-back-btn"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        <h1 class="page-title">
            Attendance Management System
            <?php if (!empty($selected_start_time) && !empty($selected_end_time)): ?>
                <div style="font-size: 16px; color: #666; margin-top: 5px;">
                    <i class="fas fa-clock"></i> <?php echo htmlspecialchars($selected_subject_name); ?> - Class Period: <?php echo $selected_start_time . ' - ' . $selected_end_time; ?>
                </div>
            <?php endif; ?>
        </h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($selected_start_time) && !empty($selected_end_time)): ?>
            <div class="message info" style="background-color: #e3f2fd; border-left-color: #2196f3;">
                <i class="fas fa-info-circle"></i> 
                <strong>Time-Specific Attendance:</strong> You are recording attendance for 
                <strong><?php echo htmlspecialchars($selected_subject_name); ?></strong> 
                from <?php echo $selected_start_time; ?> to <?php echo $selected_end_time; ?>. 
                This allows you to track attendance for multiple class periods throughout the day.
            </div>
        <?php endif; ?>
        
        <!-- Class and Date Selection -->
        <section class="form-section">
            <h2 class="form-title">
                <i class="fas fa-filter"></i> Select Class, Date and Time Period
                <?php if (!empty($selected_start_time) && !empty($selected_end_time)): ?>
                    <span style="font-size: 14px; color: #666; font-weight: normal;">
                        (<?php echo htmlspecialchars($selected_subject_name); ?>: <?php echo $selected_start_time . ' - ' . $selected_end_time; ?>)
                    </span>
                <?php endif; ?>
            </h2>
            <form method="GET" action="instructorAttendanceManagement.php" style="padding: 20px;">
                <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div class="form-group">
                        <label class="form-label" for="class_id"><i class="fas fa-school"></i> Class</label>
                        <select class="form-select" name="class_id" id="class_id" onchange="this.form.submit()" required>
                            <option value="">Select a Class</option>
                            <?php while($class = $classes_result->fetch_assoc()): ?>
                                <option value="<?php echo $class['Class_ID']; ?>" <?php echo (isset($_GET['class_id']) && $_GET['class_id'] == $class['Class_ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['Subject_Name']); ?> - Grade <?php echo $class['Grade_Level']; ?> - <?php echo htmlspecialchars($class['Section']); ?> (Room <?php echo htmlspecialchars($class['Room']); ?>) - <?php echo htmlspecialchars($class['Schedule_Details']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="date"><i class="fas fa-calendar"></i> Date</label>
                        <input type="date" class="form-select" name="date" id="date" value="<?php echo $selected_date; ?>" onchange="this.form.submit()" required>
                    </div>
                    
                    <?php if (!empty($selected_class_schedules)): ?>
                    <div class="form-group">
                        <label class="form-label" for="class_period"><i class="fas fa-clock"></i> Class Period</label>
                        <select class="form-select" name="class_period" id="class_period" onchange="updateTimeFields(this.value); this.form.submit();">
                            <option value="">Select Time Period</option>
                            <?php foreach ($selected_class_schedules as $schedule): ?>
                                <?php 
                                $period_value = $schedule['Start_Time'] . '|' . $schedule['End_Time'];
                                $is_selected = (!empty($selected_start_time) && !empty($selected_end_time) && 
                                              $selected_start_time == $schedule['Start_Time'] && 
                                              $selected_end_time == $schedule['End_Time']);
                                ?>
                                <option value="<?php echo htmlspecialchars($period_value); ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($schedule['Day'] . ' - ' . $schedule['Start_Time'] . ' to ' . $schedule['End_Time'] . ' (' . $schedule['Subject_Name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="start_time" id="hidden_start_time" value="<?php echo htmlspecialchars($selected_start_time); ?>">
                        <input type="hidden" name="end_time" id="hidden_end_time" value="<?php echo htmlspecialchars($selected_end_time); ?>">
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($selected_start_time) && !empty($selected_end_time)): ?>
                    <div class="form-group">
                        <label class="form-label" for="class_time"><i class="fas fa-clock"></i> Selected Period</label>
                        <input type="text" class="form-select" name="class_time" id="class_time" 
                               value="<?php echo htmlspecialchars($selected_start_time . ' - ' . $selected_end_time . ' (' . $selected_subject_name . ')'); ?>" readonly>
                    </div>
                    <?php elseif (empty($selected_class_schedules) && isset($_GET['class_id']) && !empty($_GET['class_id'])): ?>
                    <div class="form-group">
                        <label class="form-label" for="start_time"><i class="fas fa-clock"></i> Start Time</label>
                        <input type="time" class="form-select" name="start_time" id="start_time" value="<?php echo $selected_start_time; ?>" onchange="this.form.submit()">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="end_time"><i class="fas fa-clock"></i> End Time</label>
                        <input type="time" class="form-select" name="end_time" id="end_time" value="<?php echo $selected_end_time; ?>" onchange="this.form.submit()">
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </section>
        
        <?php if (!empty($students_data)): ?>
        <!-- Attendance Statistics -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-chart-pie"></i> Attendance Overview</h2>
            <div class="attendance-stats">
                <div class="stats-grid">
                    <div class="stat-item present">
                        <div class="stat-number"><?php echo $attendance_stats['Present']; ?></div>
                        <div>Present</div>
                    </div>
                    <div class="stat-item absent">
                        <div class="stat-number"><?php echo $attendance_stats['Absent']; ?></div>
                        <div>Absent</div>
                    </div>
                    <div class="stat-item late">
                        <div class="stat-number"><?php echo $attendance_stats['Late']; ?></div>
                        <div>Late</div>
                    </div>
                    <div class="stat-item excused">
                        <div class="stat-number"><?php echo $attendance_stats['Excused']; ?></div>
                        <div>Excused</div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Visual Attendance Grid -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-users"></i> Attendance Grid</h2>
            <div style="padding: 20px;">
                <div class="attendance-legend">
                    <div class="legend-item">
                        <div class="legend-dot not-marked"></div>
                        <span>Not Marked</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot present"></div>
                        <span>Present</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot absent"></div>
                        <span>Absent</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot late"></div>
                        <span>Late</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot excused"></div>
                        <span>Excused</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot appeal"></div>
                        <span>Rejected Appeals</span>
                    </div>
                </div>
                
                <div class="attendance-grid">
                    <?php 
                    $has_any_appeal = false; // Track if any student has appeals
                    foreach ($students_data as $index => $student): 
                        $status = $student['Attendance_Status'];
                        $status_class = $status ? strtolower($status) : 'not-marked';
                        $initials = strtoupper(substr($student['Given_Name'], 0, 1) . substr($student['Last_Name'], 0, 1));
                        
                        // Simulate some rejected appeals for demo (you can remove this logic)
                        $has_appeal = ($index == 2 || $index == 10); // Example: 3rd and 11th students have appeals
                        if ($has_appeal) $has_any_appeal = true;
                        
                        // Display status for tooltip
                        $display_status = $status ?: 'Not Marked';
                    ?>
                        <div class="attendance-dot <?php echo $status_class; ?>" 
                             title="<?php echo htmlspecialchars($student['Given_Name'] . ' ' . $student['Last_Name']); ?> - <?php echo $display_status; ?>"
                             onclick="toggleAttendance(this, <?php echo $student['Student_ID']; ?>)"
                             data-student-id="<?php echo $student['Student_ID']; ?>"
                             data-student-name="<?php echo htmlspecialchars($student['Given_Name'] . ' ' . $student['Last_Name']); ?>">
                            <?php echo $initials; ?>
                            <?php if ($has_appeal): ?>
                                <div class="appeal-badge" title="15 days of rejected appeals">15</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($has_any_appeal): ?>
                <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                    <strong><i class="fas fa-exclamation-triangle"></i> Appeal for Absence</strong>
                    <p>Some students have submitted appeals for their absence. Click on students with the appeal badge to review.</p>
                </div>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- Detailed Attendance Form -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-edit"></i> Detailed Attendance</h2>
            <form method="POST" action="instructorAttendanceManagement.php" style="padding: 20px;">
                <input type="hidden" name="class_id" value="<?php echo isset($_GET['class_id']) ? $_GET['class_id'] : ''; ?>">
                <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">
                <input type="hidden" name="start_time" value="<?php echo $selected_start_time; ?>">
                <input type="hidden" name="end_time" value="<?php echo $selected_end_time; ?>">
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-user"></i> Student</th>
                                <th><i class="fas fa-check-circle"></i> Present</th>
                                <th><i class="fas fa-times-circle"></i> Absent</th>
                                <th><i class="fas fa-clock"></i> Late</th>
                                <th><i class="fas fa-user-check"></i> Excused</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students_data as $student): ?>
                                <tr>
                                    <td><i class="fas fa-user"></i> <?php echo htmlspecialchars($student['Given_Name'] . ' ' . $student['Last_Name']); ?></td>
                                    <td>
                                        <input type="radio" name="attendance[<?php echo $student['Student_ID']; ?>]" value="Present" 
                                               <?php echo ($student['Attendance_Status'] == 'Present') ? 'checked' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="radio" name="attendance[<?php echo $student['Student_ID']; ?>]" value="Absent" 
                                               <?php echo ($student['Attendance_Status'] == 'Absent') ? 'checked' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="radio" name="attendance[<?php echo $student['Student_ID']; ?>]" value="Late" 
                                               <?php echo ($student['Attendance_Status'] == 'Late') ? 'checked' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="radio" name="attendance[<?php echo $student['Student_ID']; ?>]" value="Excused" 
                                               <?php echo ($student['Attendance_Status'] == 'Excused') ? 'checked' : ''; ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="button-group" style="margin-top: 20px;">
                    <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Save Attendance</button>
                    <button type="button" class="cancel-btn" onclick="markAllPresent()"><i class="fas fa-check"></i> Mark All Present</button>
                    <button type="button" class="cancel-btn" onclick="markAllAbsent()" style="background-color: #F44336;"><i class="fas fa-times"></i> Mark All Absent</button>
                </div>
            </form>
        </section>
        <?php endif; ?>
    </div>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script>
        function updateTimeFields(periodValue) {
            if (periodValue) {
                const parts = periodValue.split('|');
                if (parts.length === 2) {
                    document.getElementById('hidden_start_time').value = parts[0];
                    document.getElementById('hidden_end_time').value = parts[1];
                }
            } else {
                document.getElementById('hidden_start_time').value = '';
                document.getElementById('hidden_end_time').value = '';
            }
        }
        
        function toggleAttendance(element, studentId) {
            const currentClass = element.className;
            let newStatus = 'Present';
            
            if (currentClass.includes('not-marked')) {
                newStatus = 'Present';
                element.className = element.className.replace('not-marked', 'present');
            } else if (currentClass.includes('present')) {
                newStatus = 'Absent';
                element.className = element.className.replace('present', 'absent');
            } else if (currentClass.includes('absent')) {
                newStatus = 'Late';
                element.className = element.className.replace('absent', 'late');
            } else if (currentClass.includes('late')) {
                newStatus = 'Excused';
                element.className = element.className.replace('late', 'excused');
            } else if (currentClass.includes('excused')) {
                newStatus = 'Present';
                element.className = element.className.replace('excused', 'present');
            }
            
            // Update tooltip
            const studentName = element.getAttribute('data-student-name');
            element.title = `${studentName} - ${newStatus}`;
            
            // Update the corresponding radio button
            const radioButtons = document.querySelectorAll(`input[name="attendance[${studentId}]"]`);
            radioButtons.forEach(radio => {
                if (radio.value === newStatus) {
                    radio.checked = true;
                }
            });
            
            updateStats();
        }
        
        function updateStats() {
            const stats = { Present: 0, Absent: 0, Late: 0, Excused: 0 };
            
            document.querySelectorAll('.attendance-dot').forEach(dot => {
                if (dot.className.includes('present')) stats.Present++;
                else if (dot.className.includes('absent')) stats.Absent++;
                else if (dot.className.includes('late')) stats.Late++;
                else if (dot.className.includes('excused')) stats.Excused++;
            });
            
            // Update the stats display
            document.querySelector('.stat-item.present .stat-number').textContent = stats.Present;
            document.querySelector('.stat-item.absent .stat-number').textContent = stats.Absent;
            document.querySelector('.stat-item.late .stat-number').textContent = stats.Late;
            document.querySelector('.stat-item.excused .stat-number').textContent = stats.Excused;
        }
        
        function markAllPresent() {
            document.querySelectorAll('input[type="radio"][value="Present"]').forEach(radio => {
                radio.checked = true;
            });
            
            document.querySelectorAll('.attendance-dot').forEach(dot => {
                // Remove all status classes and add present
                dot.className = dot.className.replace(/\b(not-marked|absent|late|excused)\b/g, 'present');
                
                // Update tooltip
                const studentName = dot.getAttribute('data-student-name');
                dot.title = `${studentName} - Present`;
            });
            
            updateStats();
        }
        
        function markAllAbsent() {
            document.querySelectorAll('input[type="radio"][value="Absent"]').forEach(radio => {
                radio.checked = true;
            });
            
            document.querySelectorAll('.attendance-dot').forEach(dot => {
                // Remove all status classes and add absent
                dot.className = dot.className.replace(/\b(not-marked|present|late|excused)\b/g, 'absent');
                
                // Update tooltip
                const studentName = dot.getAttribute('data-student-name');
                dot.title = `${studentName} - Absent`;
            });
            
            updateStats();
        }
        
        // Sync radio buttons with grid when changed
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const studentId = this.name.match(/\d+/)[0];
                const status = this.value;
                const dot = document.querySelector(`.attendance-dot[data-student-id="${studentId}"]`);
                
                if (dot) {
                    // Remove all status classes and add the new one
                    dot.className = dot.className.replace(/\b(not-marked|present|absent|late|excused)\b/g, status.toLowerCase());
                    
                    // Update tooltip
                    const studentName = dot.getAttribute('data-student-name');
                    dot.title = `${studentName} - ${status}`;
                    
                    updateStats();
                }
            });
        });
        
        // Initialize stats on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateStats();
        });
    </script>
</body>
</html>
