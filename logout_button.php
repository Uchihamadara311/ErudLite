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
        <a href="includes/logout.php">Logout</a>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
</body>
</html>