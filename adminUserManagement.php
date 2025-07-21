<?php
require_once 'includes/db.php';

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Function to get all users
function getAllUsers($conn) {
    $sql = "SELECT 
                pb.Profile_ID,
                pb.Given_Name,
                pb.Last_Name,
                pb.Gender,
                r.Email,
                r.Permissions,
                l.Address,
                l.Nationality,
                c.Contact_Number,
                c.Emergency_Contact,
                i.Specialization
            FROM Profile_Bio pb
            LEFT JOIN Profile p ON p.Profile_ID = pb.Profile_ID
            LEFT JOIN Account a ON a.Profile_ID = p.Profile_ID
            LEFT JOIN Role r ON r.Role_ID = a.Role_ID
            LEFT JOIN Location l ON l.Location_ID = p.Location_ID
            LEFT JOIN Contacts c ON c.Contacts_ID = p.Contacts_ID
            LEFT JOIN Instructor i ON i.Profile_ID = p.Profile_ID
            ORDER BY pb.Given_Name, pb.Last_Name";
    return $conn->query($sql);
}

// Function to add new user
function addUser($conn, $userData) {
    try {
        $conn->begin_transaction();
        
        // Insert into Location
        $location_sql = "INSERT INTO Location (Address, Nationality) VALUES (?, ?)";
        $location_stmt = $conn->prepare($location_sql);
        $location_stmt->bind_param("ss", $userData['address'], $userData['nationality']);
        $location_stmt->execute();
        $location_id = $conn->insert_id;
        
        // Insert into Contacts
        $contacts_sql = "INSERT INTO Contacts (Contact_Number, Emergency_Contact) VALUES (?, ?)";
        $contacts_stmt = $conn->prepare($contacts_sql);
        $contacts_stmt->bind_param("ss", $userData['contact_number'], $userData['emergency_contact']);
        $contacts_stmt->execute();
        $contacts_id = $conn->insert_id;
        
        // Insert into Profile
        $profile_sql = "INSERT INTO Profile (Location_ID, Contacts_ID) VALUES (?, ?)";
        $profile_stmt = $conn->prepare($profile_sql);
        $profile_stmt->bind_param("ii", $location_id, $contacts_id);
        $profile_stmt->execute();
        $profile_id = $conn->insert_id;
        
        // Insert into Profile_Bio
        $bio_sql = "INSERT INTO Profile_Bio (Profile_ID, Given_Name, Last_Name, Gender) VALUES (?, ?, ?, ?)";
        $bio_stmt = $conn->prepare($bio_sql);
        $bio_stmt->bind_param("isss", $profile_id, $userData['first_name'], $userData['last_name'], $userData['gender']);
        $bio_stmt->execute();
        
        // Insert into Role
        $role_sql = "INSERT INTO Role (Email, Password_HASH, Permissions) VALUES (?, ?, ?)";
        $role_stmt = $conn->prepare($role_sql);
        $hashed_password = password_hash($userData['password'], PASSWORD_DEFAULT);
        $role_stmt->bind_param("sss", $userData['email'], $hashed_password, $userData['permissions']);
        $role_stmt->execute();
        $role_id = $conn->insert_id;
        
        // Insert into Account
        $account_sql = "INSERT INTO Account (Profile_ID, Role_ID) VALUES (?, ?)";
        $account_stmt = $conn->prepare($account_sql);
        $account_stmt->bind_param("ii", $profile_id, $role_id);
        $account_stmt->execute();
        
        // If user is an instructor, add to Instructor table
        if ($userData['permissions'] == 'Instructor') {
            $instructor_sql = "INSERT INTO Instructor (Profile_ID, Specialization) VALUES (?, ?)";
            $instructor_stmt = $conn->prepare($instructor_sql);
            $instructor_stmt->bind_param("is", $profile_id, $userData['specialization']);
            $instructor_stmt->execute();
        }
        
        // If user is a student, add to Student table
        if ($userData['permissions'] == 'Student') {
            $student_sql = "INSERT INTO Student (Profile_ID) VALUES (?)";
            $student_stmt = $conn->prepare($student_sql);
            $student_stmt->bind_param("i", $profile_id);
            $student_stmt->execute();
        }
        $conn->commit();
        return "User added successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        if (strpos($e->getMessage(), 'a foreign key constraint fails') !== false) {
            return "Error: Cannot delete user because this user is still enrolled in or associated with a school record. Please remove or update related school records first.";
        }
        return "Error: " . $e->getMessage();
    }
}

// Function to update user
function updateUser($conn, $user_id, $userData) {
    try {
        $conn->begin_transaction();
        
        // Update Profile_Bio
        $bio_sql = "UPDATE Profile_Bio SET Given_Name = ?, Last_Name = ?, Gender = ? WHERE Profile_ID = ?";
        $bio_stmt = $conn->prepare($bio_sql);
        $bio_stmt->bind_param("sssi", $userData['first_name'], $userData['last_name'], $userData['gender'], $user_id);
        $bio_stmt->execute();
        
        // Update Location
        $location_sql = "UPDATE Location l 
                        JOIN Profile p ON p.Location_ID = l.Location_ID 
                        SET l.Address = ?, l.Nationality = ? 
                        WHERE p.Profile_ID = ?";
        $location_stmt = $conn->prepare($location_sql);
        $location_stmt->bind_param("ssi", $userData['address'], $userData['nationality'], $user_id);
        $location_stmt->execute();
        
        // Update Contacts
        $contacts_sql = "UPDATE Contacts c 
                        JOIN Profile p ON p.Contacts_ID = c.Contacts_ID 
                        SET c.Contact_Number = ?, c.Emergency_Contact = ? 
                        WHERE p.Profile_ID = ?";
        $contacts_stmt = $conn->prepare($contacts_sql);
        $contacts_stmt->bind_param("ssi", $userData['contact_number'], $userData['emergency_contact'], $user_id);
        $contacts_stmt->execute();
        
        // Update Role
        $role_sql = "UPDATE Role r 
                    JOIN Account a ON a.Role_ID = r.Role_ID 
                    SET r.Email = ?, r.Permissions = ? 
                    WHERE a.Profile_ID = ?";
        $role_stmt = $conn->prepare($role_sql);
        $role_stmt->bind_param("ssi", $userData['email'], $userData['permissions'], $user_id);
        $role_stmt->execute();
        
        // Update password if provided
        if (!empty($userData['password'])) {
            $pass_sql = "UPDATE Role r 
                        JOIN Account a ON a.Role_ID = r.Role_ID 
                        SET r.Password_Hash = ? 
                        WHERE a.Profile_ID = ?";
            $pass_stmt = $conn->prepare($pass_sql);
            $hashed_password = password_hash($userData['password'], PASSWORD_DEFAULT);
            $pass_stmt->bind_param("si", $hashed_password, $user_id);
            $pass_stmt->execute();
        }
        
        // Handle Instructor specialization
        if ($userData['permissions'] == 'Student') {
            // Check if student record exists
            $check_sql = "SELECT * FROM Student WHERE Profile_ID = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            if ($result->num_rows == 0) {
                // Create new student
                $student_sql = "INSERT INTO Student (Profile_ID) VALUES (?)";
                $student_stmt = $conn->prepare($student_sql);
                $student_stmt->bind_param("i", $user_id);
                $student_stmt->execute();
            }
        } else {
            // Remove from student table if role changed
            $remove_sql = "DELETE FROM Student WHERE Profile_ID = ?";
            $remove_stmt = $conn->prepare($remove_sql);
            $remove_stmt->bind_param("i", $user_id);
            $remove_stmt->execute();
        }
        if ($userData['permissions'] == 'Instructor') {
            // Check if instructor record exists
            $check_sql = "SELECT * FROM Instructor WHERE Profile_ID = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing instructor
                $instructor_sql = "UPDATE Instructor SET Specialization = ? WHERE Profile_ID = ?";
                $instructor_stmt = $conn->prepare($instructor_sql);
                $instructor_stmt->bind_param("si", $userData['specialization'], $user_id);
                $instructor_stmt->execute();
            } else {
                // Create new instructor
                $instructor_sql = "INSERT INTO Instructor (Profile_ID, Specialization) VALUES (?, ?)";
                $instructor_stmt = $conn->prepare($instructor_sql);
                $instructor_stmt->bind_param("is", $user_id, $userData['specialization']);
                $instructor_stmt->execute();
            }
        } else {
            // Remove from instructor table if role changed
            $remove_sql = "DELETE FROM Instructor WHERE Profile_ID = ?";
            $remove_stmt = $conn->prepare($remove_sql);
            $remove_stmt->bind_param("i", $user_id);
            $remove_stmt->execute();
        }
        
        $conn->commit();
        return "User updated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        return "Error: " . $e->getMessage();
    }
}

// Function to delete user
function deleteUser($conn, $user_id) {
    try {
        $conn->begin_transaction();
        
        // Delete instructor record if exists
        $instructor_sql = "DELETE FROM Instructor WHERE Profile_ID = ?";
        $instructor_stmt = $conn->prepare($instructor_sql);
        $instructor_stmt->bind_param("i", $user_id);
        $instructor_stmt->execute();
        // Delete student record if exists
        $student_sql = "DELETE FROM Student WHERE Profile_ID = ?";
        $student_stmt = $conn->prepare($student_sql);
        $student_stmt->bind_param("i", $user_id);
        $student_stmt->execute();
        
        // Delete account and related records
        $account_sql = "DELETE FROM Account WHERE Profile_ID = ?";
        $account_stmt = $conn->prepare($account_sql);
        $account_stmt->bind_param("i", $user_id);
        $account_stmt->execute();
        
        // Delete profile bio
        $bio_sql = "DELETE FROM Profile_Bio WHERE Profile_ID = ?";
        $bio_stmt = $conn->prepare($bio_sql);
        $bio_stmt->bind_param("i", $user_id);
        $bio_stmt->execute();
        
        // Get Location_ID and Contacts_ID before deleting profile
        $ids_sql = "SELECT Location_ID, Contacts_ID FROM Profile WHERE Profile_ID = ?";
        $ids_stmt = $conn->prepare($ids_sql);
        $ids_stmt->bind_param("i", $user_id);
        $ids_stmt->execute();
        $ids_result = $ids_stmt->get_result();
        $ids = $ids_result->fetch_assoc();
        
        // Delete profile
        $profile_sql = "DELETE FROM Profile WHERE Profile_ID = ?";
        $profile_stmt = $conn->prepare($profile_sql);
        $profile_stmt->bind_param("i", $user_id);
        $profile_stmt->execute();
        
        // Delete location and contacts
        if ($ids) {
            $location_sql = "DELETE FROM Location WHERE Location_ID = ?";
            $location_stmt = $conn->prepare($location_sql);
            $location_stmt->bind_param("i", $ids['Location_ID']);
            $location_stmt->execute();
            
            $contacts_sql = "DELETE FROM Contacts WHERE Contacts_ID = ?";
            $contacts_stmt = $conn->prepare($contacts_sql);
            $contacts_stmt->bind_param("i", $ids['Contacts_ID']);
            $contacts_stmt->execute();
        }
        
        $conn->commit();
        return "User deleted successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        return "Error: " . $e->getMessage();
    }
}

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
            <h2 class="form-title" id="form-title"><i class="fas fa-user-plus"></i> Add New User</h2>
            <form method="POST" action="adminUserManagement.php" id="user-form">
                <input type="hidden" id="operation" name="operation" value="add">
                <input type="hidden" id="user_id" name="user_id" value="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="first_name"><i class="fas fa-user"></i> First Name *</label>
                        <input class="form-input" name="first_name" id="first_name" placeholder="Enter first name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="last_name"><i class="fas fa-user"></i> Last Name *</label>
                        <input class="form-input" name="last_name" id="last_name" placeholder="Enter last name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email"><i class="fas fa-envelope"></i> Email *</label>
                        <div class="autocomplete-container">
                            <input class="form-input" name="email" id="email" type="email" placeholder="Enter email address" required autocomplete="off">
                            <div class="autocomplete-suggestions" id="user-suggestions"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="contact_number"><i class="fas fa-phone"></i> Contact Number *</label>
                        <input class="form-input" name="contact_number" id="contact_number" placeholder="Enter contact number" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="address"><i class="fas fa-home"></i> Address *</label>
                        <input class="form-input" name="address" id="address" placeholder="Enter address" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="nationality"><i class="fas fa-globe"></i> Nationality *</label>
                        <input class="form-input" name="nationality" id="nationality" placeholder="Enter nationality" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="gender"><i class="fas fa-venus-mars"></i> Gender *</label>
                        <select class="form-input" name="gender" id="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="emergency_contact"><i class="fas fa-phone-alt"></i> Emergency Contact *</label>
                        <input class="form-input" name="emergency_contact" id="emergency_contact" placeholder="Enter emergency contact" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="permissions"><i class="fas fa-user-tag"></i> Role *</label>
                        <select class="form-input" name="permissions" id="permissions" required>
                            <option value="">Select Role</option>
                            <option value="Admin">Admin</option>
                            <option value="Instructor">Instructor</option>
                            <option value="Student">Student</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password" id="password-label"><i class="fas fa-lock"></i> Password *</label>
                        <input class="form-input" name="password" id="password" type="password" placeholder="Enter password" required>
                    </div>
                </div>
                
                <div id="instructorFields" class="form-group hidden">
                    <label class="form-label" for="specialization"><i class="fas fa-chalkboard-teacher"></i> Specialization</label>
                    <input class="form-input" name="specialization" id="specialization" placeholder="Enter specialization (optional)">
                </div>

                <div class="button-group">
                    <button type="submit" class="submit-btn" id="submit-btn">
                        <i class="fas fa-plus"></i> Add User
                    </button>
                    <button type="button" class="cancel-btn" id="cancel-btn" style="display: none;" onclick="resetForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="delete-btn" id="delete-btn" style="display: none;" onclick="deleteCurrentItem()">
                        <i class="fas fa-trash-alt"></i> Delete User
                    </button>
                </div>
            </form>
        </section>
        
        <section class="table-section">
            <div class="section-header">
                <div class="header-icon-title">
                    <i class="fas fa-users"></i>
                    <h2>Existing Users</h2>
                </div>
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchBar" class="search-input" placeholder="Search users...">
                </div>
            </div>
            <div class="table-container">
                <table class="data-table" id="users-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Name</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-user-tag"></i> Role</th>
                            <th><i class="fas fa-phone"></i> Contact</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = getAllUsers($conn);
                        
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr class='clickable-row' onclick='editUser(" . 
                                     $row['Profile_ID'] . ", {" .
                                     "name: \"" . htmlspecialchars($row['Given_Name'] . ' ' . $row['Last_Name']) . "\", " .
                                     "email: \"" . htmlspecialchars($row['Email']) . "\", " .
                                     "first_name: \"" . htmlspecialchars($row['Given_Name']) . "\", " .
                                     "last_name: \"" . htmlspecialchars($row['Last_Name']) . "\", " .
                                     "permissions: \"" . htmlspecialchars($row['Permissions']) . "\", " .
                                     "contact_number: \"" . htmlspecialchars($row['Contact_Number']) . "\", " .
                                     "gender: \"" . htmlspecialchars($row['Gender']) . "\", " .
                                     "nationality: \"" . htmlspecialchars($row['Nationality']) . "\", " .
                                     "address: \"" . htmlspecialchars($row['Address']) . "\", " .
                                     "emergency_contact: \"" . htmlspecialchars($row['Emergency_Contact']) . "\", " .
                                     "specialization: \"" . htmlspecialchars($row['Specialization'] ?: '') . "\"" .
                                     "})'>";
                                
                                // Name column
                                echo "<td>" . htmlspecialchars($row['Given_Name'] . ' ' . $row['Last_Name']) . "</td>";
                                
                                // Email column
                                echo "<td>" . htmlspecialchars($row['Email']) . "</td>";
                                
                                // Role column with badge
                                echo "<td>";
                                echo "<div class='role-badge " . strtolower($row['Permissions']) . "'>" . 
                                     "<i class='fas " . ($row['Permissions'] == 'Admin' ? 'fa-shield-alt' : 
                                                     ($row['Permissions'] == 'Instructor' ? 'fa-chalkboard-teacher' : 'fa-user-graduate')) . "'></i> " .
                                     "<span>" . htmlspecialchars($row['Permissions']) . "</span></div>";
                                echo "</td>";
                                
                                // Contact column
                                echo "<td>" . htmlspecialchars($row['Contact_Number']) . "</td>";
                                
                                // Actions column
                                echo "<td>";
                                echo "<div class='action-buttons'>";
                                echo "<button class='edit-btn' onclick='event.stopPropagation(); editUser(" . 
                                     $row['Profile_ID'] . ", {" .
                                     "name: \"" . htmlspecialchars($row['Given_Name'] . ' ' . $row['Last_Name']) . "\", " .
                                     "email: \"" . htmlspecialchars($row['Email']) . "\", " .
                                     "first_name: \"" . htmlspecialchars($row['Given_Name']) . "\", " .
                                     "last_name: \"" . htmlspecialchars($row['Last_Name']) . "\", " .
                                     "permissions: \"" . htmlspecialchars($row['Permissions']) . "\", " .
                                     "contact_number: \"" . htmlspecialchars($row['Contact_Number']) . "\", " .
                                     "gender: \"" . htmlspecialchars($row['Gender']) . "\", " .
                                     "nationality: \"" . htmlspecialchars($row['Nationality']) . "\", " .
                                     "address: \"" . htmlspecialchars($row['Address']) . "\", " .
                                     "emergency_contact: \"" . htmlspecialchars($row['Emergency_Contact']) . "\", " .
                                     "specialization: \"" . htmlspecialchars($row['Specialization'] ?: '') . "\"" .
                                     "})'><i class='fas fa-edit'></i> Edit</button>";
                                echo "<button class='delete-btn' onclick='event.stopPropagation(); deleteUser(" . 
                                     $row['Profile_ID'] . ", \"" . htmlspecialchars($row['Given_Name'] . ' ' . $row['Last_Name']) . "\")'><i class='fas fa-trash-alt'></i> Delete</button>";
                                echo "</div>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='no-data'>No users found. Add your first user above!</td></tr>";
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