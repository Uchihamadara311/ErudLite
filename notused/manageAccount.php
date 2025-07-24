<?php
require_once 'includes/db.php';

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}
// Try to get user_id from session, or fetch by email if not set
$user_id = $_SESSION['user_id'] ?? null;
$user = null;
if ($user_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    // If user_id is not in session, set it for future use
    if ($user && !isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = $user['user_id'];
    }
} else if (isset($_SESSION['email'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if ($user) {
        $_SESSION['user_id'] = $user['user_id'];
        $user_id = $user['user_id'];
    }
}
// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    $fields = [
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name']),
        'email' => trim($_POST['email']),
        'address' => trim($_POST['address']),
        'nationality' => trim($_POST['nationality']),
        'gender' => trim($_POST['gender']),
        'contact_number' => trim($_POST['contact_number']),
        'emergency_contact' => trim($_POST['emergency_contact'])
    ];
    $password = $_POST['password'] ?? '';
    $sql = "UPDATE users SET first_name=?, last_name=?, email=?, address=?, nationality=?, gender=?, contact_number=?, emergency_contact=?";
    $params = [
        $fields['first_name'], $fields['last_name'], $fields['email'], $fields['address'], $fields['nationality'], $fields['gender'], $fields['contact_number'], $fields['emergency_contact']
    ];
    $types = "ssssssss";
    if (!empty($password)) {
        $sql .= ", password_hash=?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
        $types .= "s";
    }
    $sql .= " WHERE user_id=?";
    $params[] = $user_id;
    $types .= "i";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $success_message = "Account updated successfully.";
        // Refresh user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    } else {
        $error_message = "Failed to update account.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Account - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/adminManagement.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container" style="width: 100%">
        <h1 class="page-title">Manage Account</h1>
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php if ($user): ?>
        <form method="POST" class="form-section" style="max-width: 80%; margin: 0 auto;">
            <div class="form-group">
                <label class="form-label" for="first_name">First Name</label>
                <input class="form-input" name="first_name" id="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="last_name">Last Name</label>
                <input class="form-input" name="last_name" id="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input class="form-input" name="email" id="email" type="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="address">Address</label>
                <input class="form-input" name="address" id="address" value="<?php echo htmlspecialchars($user['address']); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="nationality">Nationality</label>
                <input class="form-input" name="nationality" id="nationality" value="<?php echo htmlspecialchars($user['nationality']); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="gender">Gender</label>
                <select class="form-select" name="gender" id="gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male" <?php if($user['gender']==='Male') echo 'selected'; ?>>Male</option>
                    <option value="Female" <?php if($user['gender']==='Female') echo 'selected'; ?>>Female</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="contact_number">Contact Number</label>
                <input class="form-input" name="contact_number" id="contact_number" value="<?php echo htmlspecialchars($user['contact_number']); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="emergency_contact">Emergency Contact</label>
                <input class="form-input" name="emergency_contact" id="emergency_contact" value="<?php echo htmlspecialchars($user['emergency_contact']); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">New Password (leave blank to keep current)</label>
                <input class="form-input" name="password" id="password" type="password" placeholder="Enter new password">
            </div>
            <button type="submit" class="submit-btn">Update Account</button>
        </form>
        <?php else: ?>
            <div class="message error">User not found.</div>
        <?php endif; ?>
    </div>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
</body>
</html>