<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is a student
if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Student') {
    header("Location: quickAccess.php");
    exit();
}

// Get student ID
$student_id = $_SESSION['user_id'];
$current_year = (int)date('Y');

// Get student's current class
$class_sql = "SELECT c.class_id, c.grade_level, c.section, c.room 
              FROM enrollments e 
              JOIN classes c ON e.class_id = c.class_id 
              WHERE e.student_id = ? AND c.school_year = ?
              ORDER BY c.grade_level DESC LIMIT 1";
$stmt = $conn->prepare($class_sql);
$stmt->bind_param('ii', $student_id, $current_year);
$stmt->execute();
$class_result = $stmt->get_result();
$class_info = $class_result->fetch_assoc();

// Get student's schedule
$schedule_sql = "SELECT 
                    s.time,
                    s.day,
                    sub.subject_name,
                    c.room,
                    CONCAT(u.first_name, ' ', u.last_name) as instructor_name
                FROM schedule s
                JOIN subjects sub ON s.subject_id = sub.subject_id
                JOIN instructors i ON s.instructor_id = i.instructor_id
                JOIN users u ON i.instructor_id = u.user_id
                JOIN classes c ON s.class_id = c.class_id
                WHERE c.class_id = ? AND c.school_year = ?
                ORDER BY FIELD(s.day, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'), s.time";
$stmt = $conn->prepare($schedule_sql);
$stmt->bind_param('ii', $class_info['class_id'], $current_year);
$stmt->execute();
$schedule_result = $stmt->get_result();

// Group schedules by day
$schedules = [];
while ($row = $schedule_result->fetch_assoc()) {
    $schedules[$row['day']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Schedule - ErudLite</title>
    <link rel="stylesheet" href="css/studentSchedule.css">
    <link rel="stylesheet" href="css/essential.css">
    <style>
        .schedule-container {
            width: 95%;
            background-color: rgb(244, 244, 244);
            padding: 20px;
            border-radius: 10px;
            margin: 20px auto;
        }
        
        .day-header {
            background-color: #007bff;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .no-schedule {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        
        .class-info {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
        
        .print-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .print-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            background-color: #007bff;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .print-buttons button:hover {
            background-color: #0056b3;
        }
        
        @media print {
            .print-buttons {
                display: none;
            }
            
            .day-header {
                background-color: #eee !important;
                color: #333 !important;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div id="header-placeholder"></div>
    <main>
        <div class="topSchedule">
            <section style="text-align: center">
                <h1>Student Schedule</h1>
                <?php if ($class_info): ?>
                    <div class="class-info">
                        <h3>Grade <?php echo htmlspecialchars($class_info['grade_level']); ?> - 
                            Section <?php echo htmlspecialchars($class_info['section']); ?></h3>
                        <p>School Year: <?php echo $current_year; ?></p>
                    </div>
                <?php endif; ?>
            </section>
            <section class="print-buttons">
                <button onclick="window.print()">Print Schedule</button>
                <button onclick="downloadPDF()">Download PDF</button>
            </section>
        </div>
        
        <div class="schedule-container">
            <?php if (empty($schedules)): ?>
                <div class="no-schedule">
                    <p>No schedule available for this semester.</p>
                </div>
            <?php else: ?>
                <?php 
                $days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
                foreach ($days as $day): 
                ?>
                    <div class="day-header">
                        <h3><?php echo ucfirst(strtolower($day)); ?></h3>
                    </div>
                    <?php if (isset($schedules[$day])): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Subject</th>
                                    <th>Instructor</th>
                                    <th>Room</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules[$day] as $schedule): ?>
                                    <tr>
                                        <td><?php echo date('h:i A', strtotime($schedule['time'])); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['subject_name']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['instructor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['room']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-schedule">
                            <p>No classes scheduled for this day.</p>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            const element = document.querySelector('.schedule-container');
            const opt = {
                margin: 1,
                filename: 'student-schedule.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            
            html2pdf().from(element).set(opt).save();
        }
    </script>
</body>
</html>