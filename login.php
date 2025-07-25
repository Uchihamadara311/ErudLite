<?php 
require 'includes/db.php';

// Redirect if already logged in
if(isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

$error_message = "";

// Handle login form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Get user information and verify credentials
    $stmt = $conn->prepare(
        "SELECT
            r.Password_Hash,
            r.Permissions,
            r.Role_ID,
            a.Login_ID,
            pb.Given_Name,
            pb.Profile_ID
        FROM Role AS r
        LEFT JOIN Account AS a ON r.Role_ID = a.Role_ID
        LEFT JOIN Profile p ON a.Profile_ID = p.Profile_ID
        LEFT JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
        WHERE r.Email = ?"
    );

    if ($stmt === false) {
        error_log("Failed to prepare login statement: " . $conn->error);
        $error_message = "System error occurred";
    } else {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if(password_verify($password, $user['Password_Hash'])) {
                // Set session variables
                $_SESSION['email'] = $email;
                $_SESSION['permissions'] = $user['Permissions'];
                $_SESSION['profile_id'] = $user['Profile_ID'];
                $_SESSION['name'] = $user['Given_Name'];
                $_SESSION['role_id'] = $user['Role_ID'];
                // Update login information
                $update_stmt = $conn->prepare(
                    "UPDATE Login_Info
                    SET
                        Status = 'Active',
                        Last_Login = COALESCE(Updated_At, NOW()),
                        Updated_At = NOW()
                    WHERE Login_ID = ?"
                );
                
                if ($update_stmt === false) {
                    error_log("Failed to prepare update statement: " . $conn->error);
                } else {
                    $update_stmt->bind_param("i", $user['Login_ID']);
                    
                    if (!$update_stmt->execute()) {
                        error_log("Failed to execute update statement: " . $update_stmt->error);
                    }
                    $update_stmt->close();
                }

                // Redirect after successful login
                header("Location: index.php");
                exit();
            } else {
                $error_message = "Invalid password";
            }
        } else {
            $error_message = "User not found";
        }
        $stmt->close();
    }
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap">
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