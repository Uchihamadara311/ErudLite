<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMS</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/studentDashboard.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap">
</head>
<body>
    <div id="header-placeholder"></div>
    <main>
        <div class="leftContainer">
            <div class="profileSection">
                <img src="assets/profile.png" alt="Profile Picture" class="imageCircle">
            </div>
            <h1>John Doe</h1>
            <p>GRADE 5 (Class A)</p>
        </div>
        <div class="rightContainer">
            <div id="basicInfo" class="basicInfo" style="cursor:pointer;">
                <div style="display: flex; flex-direction: row">
                    <section>
                        <h5>GPA</h5>
                        <h1>90.50</h1>
                    </section>
                    <section>
                        <h5>Subjects Completed</h5>
                        <h1>7/11</h1>
                    </section>
                </div>
                <p>SHOW MORE</p>
            </div>
            <div class="quickLinks">
                <a href="studentReport.php">Student Report</a>
                <a href="reportCard.php">Classroom</a>
                <a href="">Clearance</a>
                <a href="studentSchedule.php">Schedule</a>
            </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script src="../js/studentDashboard.js"></script>
</body>
</html>