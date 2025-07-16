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
            header("Location: quickAccess.php");
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
    <title>Login - ErudLite PMS</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <main>
        <div class="login-container">
            <div class="logo-section">
                <img src="assets/logo.png" alt="ErudLite Logo">
                <h2>ERUDLITE</h2>
            </div>
            
            <div class="login-header">
                <h1>Welcome Back</h1>
                <p>Please sign in to your account</p>
            </div>
            
            <form method="POST" class="login-form" autocomplete="on">
                <?php if(!empty($error_message)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message)?>
                    </div>
                <?php endif;?>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email" autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password" autocomplete="current-password">
                </div>
                
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>
        </div>
    </main>
</body>
</html>