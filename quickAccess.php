<?php
session_start();
require_once 'config/database.php';

// Get user data if logged in
$user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Access - ERUDLITE</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/quickAccess.css">
</head>
<body>
    <div class="topBar">
        <div class="title">ERUDLITE</div>
        <div class="navBar">
            <a href="quickAccess.php">Home</a>
            <a href="about.php">About</a>
            <a href="studentDashboard.php"><img src="assets/profile.png" alt="Profile" class="logo"></a>
        </div>
    </div>

    <main>
        <div>
            <a href="studentDashboard.php">
                <p>Student Dashboard</p>
            </a>
            <a href="reportCard.php">
                <p>Report Card</p>
            </a>
            <a href="schedule.php">
                <p>Schedule</p>
            </a>
            <a href="about.php">
                <p>About</p>
            </a>
        </div>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> ERUDLITE PMS</p>
    </footer>
</body>
</html>