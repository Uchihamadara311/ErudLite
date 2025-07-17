<?php
require_once 'includes/db.php';

// Start session only once (check db.php for session_start duplication)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: quickAccess.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = isset($_POST['operation']) ? $_POST['operation'] : 'add';
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

    if ($operation == 'delete' && $user_id > 0) {
        // Delete user
        $conn->begin_transaction();
        try {
            // Delete from role-specific tables first
            $sql = "DELETE FROM instructors WHERE instructor_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            $sql = "DELETE FROM students WHERE student_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Delete from users table
            $sql = "DELETE FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $conn->commit();
                    $success_message = "User deleted successfully!";
                } else {
                    $conn->rollback();
                    $error_message = "User not found or already deleted.";
                }
            } else {
                $conn->rollback();
                $error_message = "Error deleting user: " . $stmt->error;
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error deleting user: " . $e->getMessage();
        }
    } else if ($operation == 'edit' && $user_id > 0) {
        // Update existing user
        $first_name = trim(htmlspecialchars($_POST['first_name']));
        $last_name = trim(htmlspecialchars($_POST['last_name']));
        $email = trim(htmlspecialchars($_POST['email']));
        $address = trim(htmlspecialchars($_POST['address']));
        $nationality = trim(htmlspecialchars($_POST['nationality']));
        $gender = trim(htmlspecialchars($_POST['gender']));
        $contact_number = trim(htmlspecialchars($_POST['contact_number']));
        $emergency_contact = trim(htmlspecialchars($_POST['emergency_contact']));
        $permissions = trim(htmlspecialchars($_POST['permissions']));
        $specialization = !empty($_POST['specialization']) ? trim(htmlspecialchars($_POST['specialization'])) : '';
        
        $conn->begin_transaction();
        try {
            // Check if password should be updated
            $password = trim($_POST['password']);
            if (!empty($password)) {
                // Update user table with new password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, address = ?, nationality = ?, gender = ?, contact_number = ?, emergency_contact = ?, permissions = ?, password_hash = ? WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssssi", $first_name, $last_name, $email, $address, $nationality, $gender, $contact_number, $emergency_contact, $permissions, $password_hash, $user_id);
            } else {
                // Update user table without changing password
                $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, address = ?, nationality = ?, gender = ?, contact_number = ?, emergency_contact = ?, permissions = ? WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssssi", $first_name, $last_name, $email, $address, $nationality, $gender, $contact_number, $emergency_contact, $permissions, $user_id);
            }
            
            if ($stmt->execute()) {
                $user_updated = $stmt->affected_rows > 0;
                
                // Handle role-specific updates
                $role_updated = false;
                
                // Get current user permissions to check for role changes
                $current_role_sql = "SELECT permissions FROM users WHERE user_id = ?";
                $current_role_stmt = $conn->prepare($current_role_sql);
                $current_role_stmt->bind_param("i", $user_id);
                $current_role_stmt->execute();
                $current_role_result = $current_role_stmt->get_result();
                $current_role_row = $current_role_result->fetch_assoc();
                $current_role = $current_role_row['permissions'];
                
                // Handle instructor role
                if ($permissions === 'Instructor') {
                    // Check if instructor record exists
                    $check_instructor_sql = "SELECT instructor_id FROM instructors WHERE instructor_id = ?";
                    $check_instructor_stmt = $conn->prepare($check_instructor_sql);
                    $check_instructor_stmt->bind_param("i", $user_id);
                    $check_instructor_stmt->execute();
                    $instructor_exists = $check_instructor_stmt->get_result()->num_rows > 0;
                    
                    if ($instructor_exists) {
                        // Update existing instructor record
                        $sql2 = "UPDATE instructors SET specialization = ? WHERE instructor_id = ?";
                        $stmt2 = $conn->prepare($sql2);
                        $stmt2->bind_param("si", $specialization, $user_id);
                        if ($stmt2->execute()) {
                            $role_updated = true;
                        }
                    } else {
                        // Create new instructor record
                        $sql2 = "INSERT INTO instructors (instructor_id, hire_date, employ_status, specialization) VALUES (?, CURRENT_DATE, 'Active', ?)";
                        $stmt2 = $conn->prepare($sql2);
                        $stmt2->bind_param("is", $user_id, $specialization);
                        if ($stmt2->execute()) {
                            $role_updated = true;
                        }
                    }
                } else {
                    // If changing from instructor to another role, delete instructor record
                    if ($current_role === 'Instructor') {
                        $sql2 = "DELETE FROM instructors WHERE instructor_id = ?";
                        $stmt2 = $conn->prepare($sql2);
                        $stmt2->bind_param("i", $user_id);
                        if ($stmt2->execute()) {
                            $role_updated = true;
                        }
                    }
                }
                
                // Handle student role
                if ($permissions === 'Student') {
                    // Check if student record exists
                    $check_student_sql = "SELECT student_id FROM students WHERE student_id = ?";
                    $check_student_stmt = $conn->prepare($check_student_sql);
                    $check_student_stmt->bind_param("i", $user_id);
                    $check_student_stmt->execute();
                    $student_exists = $check_student_stmt->get_result()->num_rows > 0;
                    
                    if (!$student_exists) {
                        // Create new student record
                        $sql2 = "INSERT INTO students (student_id) VALUES (?)";
                        $stmt2 = $conn->prepare($sql2);
                        $stmt2->bind_param("i", $user_id);
                        if ($stmt2->execute()) {
                            $role_updated = true;
                        }
                    }
                } else {
                    // If changing from student to another role, delete student record
                    if ($current_role === 'Student') {
                        $sql2 = "DELETE FROM students WHERE student_id = ?";
                        $stmt2 = $conn->prepare($sql2);
                        $stmt2->bind_param("i", $user_id);
                        if ($stmt2->execute()) {
                            $role_updated = true;
                        }
                    }
                }
                
                // Check if any updates were made
                if ($user_updated || $role_updated) {
                    $conn->commit();
                    $success_message = "User updated successfully!";
                } else {
                    $conn->rollback();
                    $error_message = "No changes were made or user not found.";
                }
            } else {
                $conn->rollback();
                $error_message = "Error updating user: " . $stmt->error;
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error updating user: " . $e->getMessage();
        }
    } else {
        // Validate all required fields
        $required = [
            'first_name','last_name','email','address','nationality',
            'gender','contact_number','emergency_contact','permissions','password'
        ];
        $missing = [];
        foreach ($required as $field) {
            if (empty($_POST[$field])) $missing[] = $field;
        }
        if (!empty($missing)) {
            $error_message = "Missing required fields: " . implode(', ', $missing);
        } else {
        // Sanitize input
        $first_name = trim(htmlspecialchars($_POST['first_name']));
        $last_name = trim(htmlspecialchars($_POST['last_name']));
        $email = trim(htmlspecialchars($_POST['email']));
        $address = trim(htmlspecialchars($_POST['address']));
        $nationality = trim(htmlspecialchars($_POST['nationality']));
        $gender = trim(htmlspecialchars($_POST['gender']));
        $contact_number = trim(htmlspecialchars($_POST['contact_number']));
        $emergency_contact = trim(htmlspecialchars($_POST['emergency_contact']));
        $permissions = trim(htmlspecialchars($_POST['permissions']));
        $password = $_POST['password'];
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Optional
        $specialization = !empty($_POST['specialization']) ? trim(htmlspecialchars($_POST['specialization'])) : '';

        // Use transaction to ensure atomicity
        $conn->begin_transaction();
        try {
            // Insert user
            $sql = "INSERT INTO users (first_name, last_name, email, address, nationality, gender, contact_number, emergency_contact, permissions, password_hash)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparing user statement: " . $conn->error);
            }
            $stmt->bind_param(
                "ssssssssss",
                $first_name,
                $last_name,
                $email,
                $address,
                $nationality,
                $gender,
                $contact_number,
                $emergency_contact,
                $permissions,
                $password_hash
            );
            if (!$stmt->execute()) {
                throw new Exception("Error registering user: " . $stmt->error);
            }
            $user_id = $conn->insert_id;
            $stmt->close();

            // Insert into respective role table
            if ($permissions === 'Instructor') {
                $sql2 = "INSERT INTO instructors (instructor_id, hire_date, employ_status, specialization)
                         VALUES (?, CURRENT_DATE, 'Active', ?)";
                $stmt2 = $conn->prepare($sql2);
                if (!$stmt2) {
                    throw new Exception("Error preparing instructor statement: " . $conn->error);
                }
                $stmt2->bind_param("is", $user_id, $specialization);
                if (!$stmt2->execute()) {
                    throw new Exception("Error registering instructor: " . $stmt2->error);
                }
                $stmt2->close();
            } elseif ($permissions === 'Student') {
                $sql2 = "INSERT INTO students (student_id)
                         VALUES (?)";
                $stmt2 = $conn->prepare($sql2);
                if (!$stmt2) {
                    throw new Exception("Error preparing student statement: " . $conn->error);
                }
                $stmt2->bind_param("i", $user_id);
                if (!$stmt2->execute()) {
                    throw new Exception("Error registering student: " . $stmt2->error);
                }
                $stmt2->close();
            }

            $conn->commit();
            $success_message = "User registered successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = htmlspecialchars($e->getMessage());
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
    <link rel="stylesheet" href="css/adminManagement.css">
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
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
                <div class="search-container" style="width: 70%">
                    <input type="text" id="searchBar" class="form-input" placeholder="Search subjects..." style="width: 50%; margin-bottom: 10px;">
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
                            <th>Gender</th>
                            <th>Nationality</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.permissions, u.contact_number, u.gender, u.nationality, u.address, u.emergency_contact, i.specialization 
                                FROM users u 
                                LEFT JOIN instructors i ON u.user_id = i.instructor_id 
                                ORDER BY u.permissions, u.first_name ASC";
                        $result = $conn->query($sql);
                        
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
                                echo "<td>" . htmlspecialchars($row['gender']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['nationality']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='no-data'>No users found. Add your first user above!</td></tr>";
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