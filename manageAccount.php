<?php
require_once 'includes/db.php';

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}
// Try to get profile_id from session, or fetch by email if not set
$profile_id = $_SESSION['profile_id'] ?? null;
$user = null;

if ($profile_id) {
    // Get user data from multiple tables using Profile_ID
    $stmt = $conn->prepare("SELECT pb.Given_Name as first_name, pb.Last_Name as last_name, 
                                   l.Address as address, l.Nationality as nationality, 
                                   pb.Gender as gender, c.Contact_Number as contact_number, 
                                   c.Emergency_Contact as emergency_contact, r.Email as email
                            FROM Profile p
                            LEFT JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                            LEFT JOIN Location l ON p.Location_ID = l.Location_ID
                            LEFT JOIN Contacts c ON p.Contacts_ID = c.Contacts_ID
                            LEFT JOIN Account a ON p.Profile_ID = a.Profile_ID
                            LEFT JOIN Role r ON a.Role_ID = r.Role_ID
                            WHERE p.Profile_ID = ?");
    $stmt->bind_param("i", $profile_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // If profile_id is not in session, set it for future use
    if ($user && !isset($_SESSION['profile_id'])) {
        $_SESSION['profile_id'] = $profile_id;
    }
} else if (isset($_SESSION['email'])) {
    // Get Profile_ID from email and then fetch user data
    $stmt = $conn->prepare("SELECT a.Profile_ID FROM Role r 
                           JOIN Account a ON r.Role_ID = a.Role_ID 
                           WHERE r.Email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile_data = $result->fetch_assoc();
    
    if ($profile_data) {
        $profile_id = $profile_data['Profile_ID'];
        $_SESSION['profile_id'] = $profile_id;
        
        // Now get the full user data
        $stmt = $conn->prepare("SELECT pb.Given_Name as first_name, pb.Last_Name as last_name, 
                                       l.Address as address, l.Nationality as nationality, 
                                       pb.Gender as gender, c.Contact_Number as contact_number, 
                                       c.Emergency_Contact as emergency_contact, r.Email as email
                                FROM Profile p
                                LEFT JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                                LEFT JOIN Location l ON p.Location_ID = l.Location_ID
                                LEFT JOIN Contacts c ON p.Contacts_ID = c.Contacts_ID
                                LEFT JOIN Account a ON p.Profile_ID = a.Profile_ID
                                LEFT JOIN Role r ON a.Role_ID = r.Role_ID
                                WHERE p.Profile_ID = ?");
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    }
}
// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $profile_id) {
    $fields = [
        'given_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name']),
        'email' => trim($_POST['email']),
        'address' => trim($_POST['address']),
        'nationality' => trim($_POST['nationality']),
        'gender' => trim($_POST['gender']),
        'contact_number' => trim($_POST['contact_number']),
        'emergency_contact' => trim($_POST['emergency_contact'])
    ];
    
    $password = $_POST['password'] ?? '';
    
    // Update Profile_Bio table
    $bio_sql = "UPDATE Profile_Bio SET Given_Name = ?, Last_Name = ?, Gender = ? WHERE Profile_ID = ?";
    $stmt = $conn->prepare($bio_sql);
    $stmt->bind_param("sssi", $fields['given_name'], $fields['last_name'], $fields['gender'], $profile_id);
    $bio_updated = $stmt->execute();
    
    // Update Location table
    $location_sql = "UPDATE Location SET Address = ?, Nationality = ? WHERE Location_ID = (SELECT Location_ID FROM Profile WHERE Profile_ID = ?)";
    $stmt = $conn->prepare($location_sql);
    $stmt->bind_param("ssi", $fields['address'], $fields['nationality'], $profile_id);
    $location_updated = $stmt->execute();
    
    // Update Contacts table
    $contact_sql = "UPDATE Contacts SET Contact_Number = ?, Emergency_Contact = ? WHERE Contacts_ID = (SELECT Contacts_ID FROM Profile WHERE Profile_ID = ?)";
    $stmt = $conn->prepare($contact_sql);
    $stmt->bind_param("ssi", $fields['contact_number'], $fields['emergency_contact'], $profile_id);
    $contact_updated = $stmt->execute();
    
    // Update email in Role table if provided
    $role_updated = true;
    if (!empty($fields['email'])) {
        $role_sql = "UPDATE Role SET Email = ? WHERE Role_ID = (SELECT Role_ID FROM Account WHERE Profile_ID = ?)";
        $stmt = $conn->prepare($role_sql);
        $stmt->bind_param("si", $fields['email'], $profile_id);
        $role_updated = $stmt->execute();
        
        // Update session email if changed
        if ($role_updated) {
            $_SESSION['email'] = $fields['email'];
        }
    }
    
    // Update password in Role table if provided
    $account_updated = true;
    if (!empty($password)) {
        $password_sql = "UPDATE Role SET Password_Hash = ? WHERE Role_ID = (SELECT Role_ID FROM Account WHERE Profile_ID = ?)";
        $stmt = $conn->prepare($password_sql);
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bind_param("si", $password_hash, $profile_id);
        $account_updated = $stmt->execute();
    }
    
    // Check if any updates failed and provide specific error messages
    $failed_updates = [];
    if (!$bio_updated) $failed_updates[] = "Profile Bio";
    if (!$location_updated) $failed_updates[] = "Location";  
    if (!$contact_updated) $failed_updates[] = "Contacts";
    if (!$role_updated) $failed_updates[] = "Email";
    if (!$account_updated) $failed_updates[] = "Password";
    
    if (empty($failed_updates)) {
    if (empty($failed_updates)) {
        $success_message = "Account updated successfully.";
        // Refresh user data
        $stmt = $conn->prepare("SELECT pb.Given_Name as first_name, pb.Last_Name as last_name, 
                                       l.Address as address, l.Nationality as nationality, 
                                       pb.Gender as gender, c.Contact_Number as contact_number, 
                                       c.Emergency_Contact as emergency_contact, r.Email as email
                                FROM Profile p
                                LEFT JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                                LEFT JOIN Location l ON p.Location_ID = l.Location_ID
                                LEFT JOIN Contacts c ON p.Contacts_ID = c.Contacts_ID
                                LEFT JOIN Account a ON p.Profile_ID = a.Profile_ID
                                LEFT JOIN Role r ON a.Role_ID = r.Role_ID
                                WHERE p.Profile_ID = ?");
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    } else {
        $error_message = "Failed to update: " . implode(", ", $failed_updates) . ". Please try again.";
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
                <input class="form-input" name="first_name" id="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="last_name">Last Name</label>
                <input class="form-input" name="last_name" id="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input class="form-input" name="email" id="email" type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="address">Address</label>
                <input class="form-input" name="address" id="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="nationality">Nationality</label>
                <input class="form-input" name="nationality" id="nationality" value="<?php echo htmlspecialchars($user['nationality'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="gender">Gender</label>
                <select class="form-select" name="gender" id="gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male" <?php if(($user['gender'] ?? '') === 'Male') echo 'selected'; ?>>Male</option>
                    <option value="Female" <?php if(($user['gender'] ?? '') === 'Female') echo 'selected'; ?>>Female</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="contact_number">Contact Number</label>
                <input class="form-input" name="contact_number" id="contact_number" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="emergency_contact">Emergency Contact</label>
                <input class="form-input" name="emergency_contact" id="emergency_contact" value="<?php echo htmlspecialchars($user['emergency_contact'] ?? ''); ?>" required>
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