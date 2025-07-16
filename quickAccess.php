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
    <div class="background-change">test</div>
    <main>
        <div class="choice-section">
            <a href="">
                <span>
                    <i class="fa-solid fa-award" style="font-size: 2em; margin-bottom: 5px;"></i>
                    <br>
                    Announcement<br>Notice
                </span>
            </a>
            <a href="">
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
            <a href="">
                <span>
                    <i class="icon fa-regular fa-square-check"></i>
                    <br>Subject<br>Clearance
                </span>
            </a>
        </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script src="js/hover-background.js"></script>
</body>
</html>