<?php
session_start();
require_once 'includes/db.php';

// Ensure user is logged in and has admin permissions
if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: index.php");
    exit();
}

// Intitialize messages and variables
$success_message = "";
$error_message = "";


// Set profile_id from session
$profile_id = $_SESSION['profile_id'];

// Get Schedules
$schedule_sql = "SELECT cr.School_Year, cr.Grade_Level, s.Subject_Name, scdt.Start_Time, scdt.End_Time, scdt.Day
                 FROM Subject s
                 JOIN Clearance cr ON s.Clearance_ID = s.Clearance_ID
                 JOIN Schedule sc ON s.Subject_ID = sc.Subject_ID
                 JOIN schedule_details scdt ON sc.Schedule_ID = scdt.Schedule_ID
                 JOIN Class c ON cr.Clearance_ID = c.Clearance_ID
                 JOIN Enrollment en ON c.Class_ID = en.Class_ID
                 JOIN Student std ON en.Student_ID = std.Student_ID
                 WHERE std.Profile_ID = ?;";
$stmt = $conn->prepare($schedule_sql);
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$schedule_result = $stmt->get_result();
// if (!$schedule_result) {
//     $error_message = "Error fetching schedules: " . $conn->error;
// }


?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMS</title>
    <link rel="stylesheet" href="css/studentSchedule.css">
    <link rel="stylesheet" href="css/essential.css">
</head>
<body>
    <header id="header-placeholder"></header>
    <main style="border: 50px">
        <div class="topSchedule">
            <sectio style="text-align: center">
                <h1>Student Schedule</h1>
            </section>
            <section style="display: flex; justify-content: center; gap: 20px">
                <button>Download PDF</button>
                <button>Print Schedule</button>
            </section>
        </div>
        <div style="width: 99%; background-color: rgb(244, 244, 244); padding: 20px; border-radius: 10px; margin-top: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <?php echo "<p>" . $_SESSION['profile_id'] . "</p>";?>
            <table>
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Time</th>
                        <th>Room</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Mathematics</td>
                        <td>9:00 AM - 10:30 AM</td>
                        <td>Room 101</td>
                    </tr>
                    <tr>
                        <td>Science</td>
                        <td>10:45 AM - 12:15 PM</td>
                        <td>Room 102</td>
                    </tr>
                    <tr>
                        <td>History</td>
                        <td>1:00 PM - 2:30 PM</td>
                        <td>Room 103</td>
                    </tr>
                </tbody>
            </table>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
</body>
</html>