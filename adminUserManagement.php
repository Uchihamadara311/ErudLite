<?php
require_once 'includes/db.php';
require_once 'includes/queries.php';

// Check if user is logged in and is admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: quickAccess.php");
    exit();
}
// All database functions have been moved to queries.php

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = $_POST['operation'] ?? 'add';
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($operation == 'delete' && $user_id > 0) {
        $result = deleteUser($conn, $user_id);
        if (strpos($result, 'successfully') !== false) {
            $success_message = $result;
        } else {
            $error_message = $result;
        }
    } elseif ($operation == 'edit' && $user_id > 0) {
        $userData = [
            'first_name' => cleanInput($_POST['first_name']),
            'last_name' => cleanInput($_POST['last_name']),
            'email' => cleanInput($_POST['email']),
            'address' => cleanInput($_POST['address']),
            'nationality' => cleanInput($_POST['nationality']),
            'gender' => cleanInput($_POST['gender']),
            'contact_number' => cleanInput($_POST['contact_number']),
            'emergency_contact' => cleanInput($_POST['emergency_contact']),
            'permissions' => cleanInput($_POST['permissions']),
            'password' => cleanInput($_POST['password']),
            'specialization' => cleanInput($_POST['specialization'] ?? '')
        ];
        
        $result = updateUser($conn, $user_id, $userData);
        if (strpos($result, 'successfully') !== false) {
            $success_message = $result;
        } else {
            $error_message = $result;
        }
    } else {
        // Add new user
        $required = ['first_name', 'last_name', 'email', 'address', 'nationality', 'gender', 'contact_number', 'emergency_contact', 'permissions', 'password'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($_POST[$field])) $missing[] = $field;
        }
        
        if (!empty($missing)) {
            $error_message = "Missing required fields: " . implode(', ', $missing);
        } else {
            $userData = [
                'first_name' => cleanInput($_POST['first_name']),
                'last_name' => cleanInput($_POST['last_name']),
                'email' => cleanInput($_POST['email']),
                'address' => cleanInput($_POST['address']),
                'nationality' => cleanInput($_POST['nationality']),
                'gender' => cleanInput($_POST['gender']),
                'contact_number' => cleanInput($_POST['contact_number']),
                'emergency_contact' => cleanInput($_POST['emergency_contact']),
                'permissions' => cleanInput($_POST['permissions']),
                'password' => $_POST['password'],
                'specialization' => cleanInput($_POST['specialization'] ?? '')
            ];
            
            $result = addUser($conn, $userData);
            if (strpos($result, 'successfully') !== false) {
                $success_message = $result;
            } else {
                $error_message = $result;
            }
        }
    }
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/adminLinks.css">
    <link rel="stylesheet" href="css/adminManagement.css">
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
        <div class="admin-back-btn-wrap admin-back-btn-upperleft">
            <a href="adminLinks.php" class="admin-back-btn"><i class="fa fa-arrow-left"></i> Back to Admin Dashboard</a>
        </div>
        <h1 class="page-title">User Management</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <section class="form-section">
            <h2 class="form-title" id="form-title">Add New User</h2>
            <form method="POST" action="adminUserManagement.php" id="user-form">
                <input type="hidden" id="operation" name="operation" value="add">
                <input type="hidden" id="user_id" name="user_id" value="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name *</label>
                        <input class="form-input" name="first_name" id="first_name" placeholder="Enter first name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name *</label>
                        <input class="form-input" name="last_name" id="last_name" placeholder="Enter last name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email *</label>
                        <div class="autocomplete-container">
                            <input class="form-input" name="email" id="email" type="email" placeholder="Enter email address" required autocomplete="off">
                            <div class="autocomplete-suggestions" id="user-suggestions"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="contact_number">Contact Number *</label>
                        <input class="form-input" name="contact_number" id="contact_number" placeholder="Enter contact number" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="address">Address *</label>
                        <input class="form-input" name="address" id="address" placeholder="Enter address" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="nationality">Nationality *</label>
                        <input class="form-input" name="nationality" id="nationality" placeholder="Enter nationality" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="gender">Gender *</label>
                        <select class="form-select" name="gender" id="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="emergency_contact">Emergency Contact *</label>
                        <input class="form-input" name="emergency_contact" id="emergency_contact" placeholder="Enter emergency contact" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="permissions">Role *</label>
                        <select class="form-select" name="permissions" id="permissions" required>
                            <option value="">Select Role</option>
                            <option value="Admin">Admin</option>
                            <option value="Instructor">Instructor</option>
                            <option value="Student">Student</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password" id="password-label">Password *</label>
                        <input class="form-input" name="password" id="password" type="password" placeholder="Enter password" required>
                    </div>
                </div>
                
                <div id="instructorFields" class="form-group hidden">
                    <label class="form-label" for="specialization">Specialization</label>
                    <input class="form-input" name="specialization" id="specialization" placeholder="Enter specialization (optional)">
                </div>

                <button type="submit" class="submit-btn" id="submit-btn">Register User</button>
                <button type="button" class="cancel-btn" id="cancel-btn" style="display: none; margin-left: 10px;" onclick="resetForm()">Cancel</button>
                <button type="button" class="delete-btn" id="delete-btn" style="display: none; margin-left: 10px;" onclick="deleteCurrentItem()">Delete User</button>
            </form>
        </section>
        
        <section class="table-section">
            <div class="table-header">
                <span>Existing Users</span>
                <div class="search-container" style="width: 80%">
                    <input type="text" id="searchBar" class="form-input" placeholder="Search subjects..." style="width: 95%; margin-bottom: 10px;">
                    <i class="fas fa-search search-icon"></i>
                </div>
            </div>
            <div class="table-container">
                <table class="subjects-table" id="users-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Contact</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = getAllUsers($conn);
                        
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr class='clickable-row' onclick='editUser(" . 
                                     $row['user_id'] . ", {" .
                                     "name: \"" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "\", " .
                                     "email: \"" . htmlspecialchars($row['email']) . "\", " .
                                     "first_name: \"" . htmlspecialchars($row['first_name']) . "\", " .
                                     "last_name: \"" . htmlspecialchars($row['last_name']) . "\", " .
                                     "permissions: \"" . htmlspecialchars($row['permissions']) . "\", " .
                                     "contact_number: \"" . htmlspecialchars($row['contact_number']) . "\", " .
                                     "gender: \"" . htmlspecialchars($row['gender']) . "\", " .
                                     "nationality: \"" . htmlspecialchars($row['nationality']) . "\", " .
                                     "address: \"" . htmlspecialchars($row['address']) . "\", " .
                                     "emergency_contact: \"" . htmlspecialchars($row['emergency_contact']) . "\", " .
                                     "specialization: \"" . htmlspecialchars($row['specialization'] ?: '') . "\"" .
                                     "})'>";
                                echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                echo "<td><span class='grade-badge'>" . htmlspecialchars($row['permissions']) . "</span></td>";
                                echo "<td>" . htmlspecialchars($row['contact_number']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='no-data'>No users found. Add your first user above!</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script src="js/adminManage.js"></script>
</body>
</html>