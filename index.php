<?php require 'includes/db.php';

if(!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMS</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/quickAccess.css">
   
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="background-change"></div>
    <main>
        <div class="choice-section">
            <?php if(isset($_SESSION['permissions']) && $_SESSION['permissions'] === 'Admin'): ?>
                <!-- Admin Options -->
                <a href="adminSubjectManagement.php">
                    <span>
                        <i class="fa-solid fa-book" style="font-size: 2em; margin-bottom: 5px;"></i>
                        <br>
                        Subject<br>Management
                    </span>
                </a>
                <a href="adminLinks.php">
                    <span>
                        <i class="icon fa-solid fa-users"></i>
                        <br>Admin<br>Dashboard
                    </span>
                </a>
                <a href="">
                    <span>
                        <i class="icon fa-solid fa-chart-line"></i>
                        <br>Reports<br>and Analytics
                    </span>
                </a>
                <a href="manageAccount.php">
                    <span>
                        <i class="icon fa-solid fa-cog"></i>
                        <br>Manage<br>Account
                    </span>
                </a>
            <?php elseif(isset($_SESSION['permissions']) && $_SESSION['permissions'] === 'Instructor'): ?>
                <!-- Instructor Options -->
                <a href="instructorAttendanceManagement.php">
                    <span>
                        <i class="fa-solid fa-award" style="font-size: 2em; margin-bottom: 5px;"></i>
                        <br>
                        Attendance<br>Management
                    </span>
                </a>
                <a href="instructorRecord.php">
                    <span>
                        <i class="icon fa-solid fa-chart-simple"></i>
                        <br>Grade<br>Management
                    </span>
                </a>
                <a href="instructorSchedule.php">
                    <span>
                        <i class="icon fa-solid fa-calendar-days"></i>
                        <br>Schedule<br>and Calendar
                    </span>
                </a>
                <a href="instructorSubjectClearance.php">
                    <span>
                        <i class="icon fa-regular fa-square-check"></i>
                        <br>Subject<br>Clearance
                    </span>
                </a>
            <?php else: ?>
                <!-- Student Options -->
                <a href="">
                    <span>
                        <i class="fa-solid fa-award" style="font-size: 2em; margin-bottom: 5px;"></i>
                        <br>
                        Announcement<br>Notice
                    </span>
                </a>
                <a href="studentReport.php">
                    <span>
                        <i class="icon fa-solid fa-chart-simple"></i>
                        <br>Student Report<br>Card
                    </span>
                </a>
                <a href="">
                    <span>
                        <i class="icon fa-solid fa-calendar-days"></i>
                        <br>Schedule<br>and Calendar
                    </span>
                </a>
                <a href="studentClearance.php">
                    <span>
                        <i class="icon fa-regular fa-square-check"></i>
                        <br>Subject<br>Clearance
                    </span>
                </a>
            <?php endif; ?>
        </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script src="js/hover-background.js"></script>
</body>
</html>