<?php
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management Links - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/quickAccess.css">
    <link rel="stylesheet" href="css/adminLinks.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <div id="header-placeholder"></div>
    <main>
        <div class="admin-links-container">
            <!-- <div class="admin-back-btn-wrap">
                <a href="index.php" class="admin-back-btn"><i class="fa fa-arrow-left"></i> Back to Quick Access</a>
            </div> -->
            <h1 class="page-title">Admin Management Pages</h1>
            <ul class="card-list">
                <li><a href="adminUserManagement.php" class="card card-link"><i class="fa-solid fa-users"></i><span>User Management</span></a></li>
                <li><a href="adminSubjectManagement.php" class="card card-link"><i class="fa-solid fa-book"></i><span>Subject Management</span></a></li>
                <li><a href="adminAssignSubjects.php" class="card card-link"><i class="fa-solid fa-chalkboard-user"></i><span>Assign Subjects</span></a></li>
                <li><a href="adminClasses.php" class="card card-link"><i class="fa-solid fa-school"></i><span>Classes Management</span></a></li>
                <li><a href="adminEnrollment.php" class="card card-link"><i class="fa-solid fa-user-graduate"></i><span>Enrollment Management</span></a></li>
                <li><a href="adminSchedule.php" class="card card-link"><i class="fa-solid fa-calendar-days"></i><span>Schedule Management</span></a></li>
                <li><a href="adminRecord.php" class="card card-link"><i class="fa-solid fa-file-lines"></i><span>Record Management</span></a></li>
            </ul>
        </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
</body>
</html>
