<?php
require_once 'includes/db.php';

// Check if user is logged in and is admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: quickAccess.php");
    exit();
}

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Function to delete a user
function deleteUser($conn, $user_id) {
    $conn->begin_transaction();
    try {
        // Delete from instructor and student tables first
        $conn->prepare("DELETE FROM instructors WHERE instructor_id = ?")->execute([$user_id]);
        $conn->prepare("DELETE FROM students WHERE student_id = ?")->execute([$user_id]);
        
        // Delete from users table
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $conn->commit();
            return "User deleted successfully!";
        } else {
            $conn->rollback();
            return "User not found or already deleted.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        return "Error deleting user: " . $e->getMessage();
    }
}

// Function to update a user
function updateUser($conn, $user_id, $userData) {
    $conn->begin_transaction();
    try {
        // Update user table
        if (!empty($userData['password'])) {
            $password_hash = password_hash($userData['password'], PASSWORD_DEFAULT);
            $sql = "UPDATE users SET first_name=?, last_name=?, email=?, address=?, nationality=?, gender=?, contact_number=?, emergency_contact=?, permissions=?, password_hash=? WHERE user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssssi", $userData['first_name'], $userData['last_name'], $userData['email'], $userData['address'], $userData['nationality'], $userData['gender'], $userData['contact_number'], $userData['emergency_contact'], $userData['permissions'], $password_hash, $user_id);
        } else {
            $sql = "UPDATE users SET first_name=?, last_name=?, email=?, address=?, nationality=?, gender=?, contact_number=?, emergency_contact=?, permissions=? WHERE user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssi", $userData['first_name'], $userData['last_name'], $userData['email'], $userData['address'], $userData['nationality'], $userData['gender'], $userData['contact_number'], $userData['emergency_contact'], $userData['permissions'], $user_id);
        }
        
        $stmt->execute();
        $user_updated = $stmt->affected_rows > 0;
        
        // Handle role changes
        $role_updated = false;
        
        // Handle instructor role
        if ($userData['permissions'] === 'Instructor') {
            $check = $conn->prepare("SELECT instructor_id FROM instructors WHERE instructor_id = ?");
            $check->bind_param("i", $user_id);
            $check->execute();
            
            if ($check->get_result()->num_rows > 0) {
                // Update existing instructor
                $update_inst = $conn->prepare("UPDATE instructors SET specialization = ? WHERE instructor_id = ?");
                $update_inst->bind_param("si", $userData['specialization'], $user_id);
                $role_updated = $update_inst->execute();
            } else {
                // Create new instructor
                $insert_inst = $conn->prepare("INSERT INTO instructors (instructor_id, hire_date, employ_status, specialization) VALUES (?, CURRENT_DATE, 'Active', ?)");
                $insert_inst->bind_param("is", $user_id, $userData['specialization']);
                $role_updated = $insert_inst->execute();
            }
        } else {
            // Delete instructor record if changing from instructor
            $delete_inst = $conn->prepare("DELETE FROM instructors WHERE instructor_id = ?");
            $delete_inst->bind_param("i", $user_id);
            $delete_inst->execute();
        }
        
        // Handle student role
        if ($userData['permissions'] === 'Student') {
            $check = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
            $check->bind_param("i", $user_id);
            $check->execute();
            
            if ($check->get_result()->num_rows == 0) {
                // Create new student
                $insert_std = $conn->prepare("INSERT INTO students (student_id) VALUES (?)");
                $insert_std->bind_param("i", $user_id);
                $role_updated = $insert_std->execute();
            }
        } else {
            // Delete student record if changing from student
            $delete_std = $conn->prepare("DELETE FROM students WHERE student_id = ?");
            $delete_std->bind_param("i", $user_id);
            $delete_std->execute();
        }
        
        if ($user_updated || $role_updated) {
            $conn->commit();
            return "User updated successfully!";
        } else {
            $conn->rollback();
            return "No changes were made.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        return "Error updating user: " . $e->getMessage();
    }
}

// Function to add a new user
function addUser($conn, $userData) {
    $conn->begin_transaction();
    try {
        // Insert into users table
        $password_hash = password_hash($userData['password'], PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (first_name, last_name, email, address, nationality, gender, contact_number, emergency_contact, permissions, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssss", $userData['first_name'], $userData['last_name'], $userData['email'], $userData['address'], $userData['nationality'], $userData['gender'], $userData['contact_number'], $userData['emergency_contact'], $userData['permissions'], $password_hash);
        
        if (!$stmt->execute()) {
            throw new Exception("Error adding user");
        }
        
        $user_id = $conn->insert_id;
        
        // Add to role-specific table
        if ($userData['permissions'] === 'Instructor') {
            $stmt2 = $conn->prepare("INSERT INTO instructors (instructor_id, hire_date, employ_status, specialization) VALUES (?, CURRENT_DATE, 'Active', ?)");
            $stmt2->bind_param("is", $user_id, $userData['specialization']);
            $stmt2->execute();
        } elseif ($userData['permissions'] === 'Student') {
            $stmt2 = $conn->prepare("INSERT INTO students (student_id) VALUES (?)");
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
        }
        
        $conn->commit();
        return "User registered successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        return "Error: " . $e->getMessage();
    }
}

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