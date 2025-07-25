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
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap">
</head>
<body>
    <header>
        <div class="topBar">
            <a class="logo-text" href="index.php">
                <img src="assets/logo.png" alt="PMS Logo" class="logo">
                <?php if(isset($_SESSION['permissions']) && $_SESSION['permissions'] === 'Admin'): ?>
                    <h3>ERUDLITE [ADMIN]</h3>
                <?php elseif(isset($_SESSION['permissions']) && $_SESSION['permissions'] === 'Instructor'): ?>
                    <h3>ERUDLITE [Instructor]</h3>
                <?php else: ?>
                    <h3>ERUDLITE</h3>
                <?php endif; ?>
            </a>
            <div class="navBar">
                <a href="index.php">Home</a>
                <a href="about.php">About</a>
                <div class="profile-dropdown">
                    <a href="#" class="profile-trigger">
                        <img src="assets/profile.png" alt="Profile" class="logo">
                        <i class="fas fa-chevron-down"></i>
                    </a>
                    <div class="dropdown-content">
                        <?php if(isset($_SESSION['permissions']) && $_SESSION['permissions'] === 'Student'): ?>
                            <a href="studentDashboard.php"><i class="fas fa-users"></i> Student Dashboard</a>
                        <?php endif; ?>
                        <a href="manageAccount.php"><i class="fas fa-user-cog"></i> Manage Account</a>
                        <a href="includes/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <footer style="z-index: 1000">
        <p>&copy Erudlite PMS 2025</p>
    </footer>
</body>
</html>