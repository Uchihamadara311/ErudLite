<?php 
require_once 'includes/db.php';

if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: quickAccess.php");
    exit();
}


if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if this is actually user data and not other data
    if (!isset($_POST['first_name']) || !isset($_POST['last_name']) || !isset($_POST['email'])) {
        $error_message = "Invalid form data for user management.";
    } else {
        // Instructor fields (Essential)
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $address = $_POST['address'];
        $nationality = $_POST['nationality'];
        $gender = $_POST['gender'];
        $contact_number = $_POST['contact_number'];
        $emergency_contact = $_POST['emergency_contact'];
        $permissions = $_POST['permissions'];
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Instructor fields (Optional)
        $specialization = !empty($_POST['specialization']) ? $_POST['specialization'] : '';

        // Instructor Info (Essential)
        $sql = "INSERT INTO users (first_name, last_name, email, address, nationality, gender, contact_number, emergency_contact, permissions, password_hash)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssss", $first_name, $last_name, $email, $address, $nationality, $gender, $contact_number, $emergency_contact, $permissions, $password_hash);
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            
            // Instructor Info (Optional)
            if ($permissions === 'Instructor') {
                $sql2 = "INSERT INTO instructors (instructor_id, hire_date, employ_status, specialization)
                         VALUES (?, CURRENT_DATE, 'Active', ?)";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->bind_param("is", $user_id, $specialization);
                $stmt2->execute();
            }
            
            $success_message = "User registered successfully!";
        } else {
            $error_message = "Error registering user: " . $stmt->error;
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap">
    <style>.hidden { display:none; }</style>
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
            <h2 class="form-title">Add New User</h2>
            <form method="POST" action="adminUserManagement.php">
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
                        <input class="form-input" name="email" id="email" type="email" placeholder="Enter email address" required>
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
                        <label class="form-label" for="password">Password *</label>
                        <input class="form-input" name="password" id="password" type="password" placeholder="Enter password" required>
                    </div>
                </div>
                
                <div id="instructorFields" class="form-group hidden">
                    <label class="form-label" for="specialization">Specialization</label>
                    <input class="form-input" name="specialization" id="specialization" placeholder="Enter specialization (optional)">
                </div>

                <button type="submit" class="submit-btn">Register User</button>
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
                <table class="subjects-table" id="subjects-table">
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
                        $sql = "SELECT * FROM users ORDER BY permissions, first_name ASC";
                        $result = $conn->query($sql);
                        
                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr>";
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
    <script src="js/searchBar.js"></script>
    <script>
        document.getElementById('permissions').addEventListener('change', function() {
            var instructorFields = document.getElementById('instructorFields');
            instructorFields.classList.add('hidden');
            if (this.value === 'Instructor') instructorFields.classList.remove('hidden');
        });
        document.addEventListener('DOMContentLoaded', function() {
            var role = document.getElementById('permissions').value;
            var instructorFields = document.getElementById('instructorFields');
            if(role === 'Instructor') instructorFields.classList.remove('hidden');
        });
    </script>
</body>
</html>