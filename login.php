<?php require 'includes/db.php';

if(isset($_SESSION['email'])) {
    header("Location: quickAccess.php");
    exit();
}

?>

<?php
$error_message = "";
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $stmt = $conn->prepare("SELECT password_hash, permissions FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if($stmt->num_rows > 0) {
        $stmt->bind_result($password_hash, $permissions);
        $stmt->fetch();
        if(password_verify($password, $password_hash)) {
            $_SESSION['email'] = $email;
            $_SESSION['permissions'] = $permissions;
            header("Location: logout_button.php");
            exit();
        } else {
            $error_message = "Invalid password";
        }
    } else {
        $error_message = "User not found";
    }
    $stmt->close();
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
        <h1>Login</h1>
        <form method="POST">
            <?php if(!empty($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message)?></div>
            <?php endif;?>
            <label>USERNAME:</label>
            <input type="email" name="email" required>
            <label>PASSWORD:</label>
            <input type="password" name="password" required>
            <button type="submit">Login</button>
        </form>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
</body>
</html>