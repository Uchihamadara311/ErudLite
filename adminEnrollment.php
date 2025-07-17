<?php 
require_once 'includes/db.php';

// Check if user is logged in and is admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: quickAccess.php");
    exit();
}

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Function to enroll student in class
function enrollStudent($conn, $class_id, $student_id) {
    try {
        // Check if enrollment already exists
        $check_sql = "SELECT * FROM enrollments WHERE class_id = ? AND student_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $class_id, $student_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return "Student is already enrolled in this class.";
        }
        
        // Check class capacity
        $capacity_sql = "SELECT max_students, (SELECT COUNT(*) FROM enrollments WHERE class_id = ?) as current_students FROM classes WHERE class_id = ?";
        $capacity_stmt = $conn->prepare($capacity_sql);
        $capacity_stmt->bind_param("ii", $class_id, $class_id);
        $capacity_stmt->execute();
        $capacity_result = $capacity_stmt->get_result()->fetch_assoc();
        
        if ($capacity_result['current_students'] >= $capacity_result['max_students']) {
            return "Class is at full capacity.";
        }
        
        // Insert new enrollment
        $sql = "INSERT INTO enrollments (class_id, student_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $class_id, $student_id);
        
        if ($stmt->execute()) {
            return "Student enrolled successfully!";
        } else {
            return "Error enrolling student: " . $stmt->error;
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Function to unenroll student from class
function unenrollStudent($conn, $class_id, $student_id) {
    try {
        $sql = "DELETE FROM enrollments WHERE class_id = ? AND student_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $class_id, $student_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return "Student unenrolled successfully!";
            } else {
                return "Enrollment not found.";
            }
        } else {
            return "Error unenrolling student: " . $stmt->error;
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = $_POST['operation'] ?? 'enroll';
    
    // Get class_id and student_id from appropriate source
    if ($operation == 'unenroll') {
        $class_id = (int)($_POST['hidden_class_id'] ?? 0);
        $student_id = (int)($_POST['hidden_student_id'] ?? 0);
    } else {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $student_id = (int)($_POST['student_id'] ?? 0);
    }
    
    if ($operation == 'enroll' && $class_id > 0 && $student_id > 0) {
        $result = enrollStudent($conn, $class_id, $student_id);
        if (strpos($result, 'successfully') !== false) {
            $success_message = $result;
        } else {
            $error_message = $result;
        }
    } elseif ($operation == 'unenroll' && $class_id > 0 && $student_id > 0) {
        $result = unenrollStudent($conn, $class_id, $student_id);
        if (strpos($result, 'successfully') !== false) {
            $success_message = $result;
        } else {
            $error_message = $result;
        }
    } else {
        $error_message = "Please select both class and student.";
    }
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Enrollment - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/adminLinks.css">
    <link rel="stylesheet" href="css/adminManagement.css">
    <style>.hidden { display:none; }</style>
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
        <div class="admin-back-btn-wrap admin-back-btn-upperleft">
            <a href="adminLinks.php" class="admin-back-btn"><i class="fa fa-arrow-left"></i> Back to Admin Dashboard</a>
        </div>
        <h1 class="page-title">Student Enrollment Management</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <section class="form-section">
            <h2 class="form-title" id="form-title">Enroll Student in Class</h2>
            <form method="POST" action="adminEnrollment.php" id="enrollment-form">
                <input type="hidden" id="operation" name="operation" value="enroll">
                <input type="hidden" id="hidden_class_id" name="hidden_class_id" value="">
                <input type="hidden" id="hidden_student_id" name="hidden_student_id" value="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="class_id">Select Class *</label>
                        <select class="form-select" name="class_id" id="class_id" required>
                            <option value="">Select a Class</option>
                            <?php
                            $class_sql = "SELECT c.class_id, c.grade_level, c.section, c.room, c.school_year, c.max_students,
                                         (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.class_id) as current_students
                                         FROM classes c 
                                         ORDER BY c.grade_level, c.section";
                            $class_result = $conn->query($class_sql);
                            
                            if ($class_result->num_rows > 0) {
                                while($class = $class_result->fetch_assoc()) {
                                    echo "<option value='" . $class['class_id'] . "'>" . 
                                         "Grade " . $class['grade_level'] . " - " . htmlspecialchars($class['section']) . 
                                         " (Room: " . htmlspecialchars($class['room']) . ") - " .
                                         $class['current_students'] . "/" . $class['max_students'] . " students</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="student_id">Select Student *</label>
                        <select class="form-select" name="student_id" id="student_id" required>
                            <option value="">Select a Student</option>
                            <?php
                            $student_sql = "SELECT u.user_id, u.first_name, u.last_name 
                                           FROM users u 
                                           JOIN students s ON u.user_id = s.student_id 
                                           WHERE u.permissions = 'Student' 
                                           ORDER BY u.first_name, u.last_name";
                            $student_result = $conn->query($student_sql);
                            
                            if ($student_result->num_rows > 0) {
                                while($student = $student_result->fetch_assoc()) {
                                    echo "<option value='" . $student['user_id'] . "'>" . 
                                         htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn" id="submit-btn">Enroll Student</button>
                <button type="button" class="cancel-btn" id="cancel-btn" style="display: none; margin-left: 10px;" onclick="resetForm()">Cancel</button>
                <button type="button" class="delete-btn" id="delete-btn" style="display: none; margin-left: 10px;" onclick="unenrollStudent()">Unenroll Student</button>
            </form>
        </section>
        
        <section class="table-section">
            <div class="table-header">
                <span>Current Enrollments</span>
                <div class="search-container" style="width: 70%">
                    <input type="text" id="searchBar" class="form-input" placeholder="Search enrollments..." style="width: 50%; margin-bottom: 10px;">
                    <i class="fas fa-search search-icon"></i>
                </div>
            </div>
            <div class="table-container">
                <table class="subjects-table" id="enrollments-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Grade Level</th>
                            <th>Section</th>
                            <th>Room</th>
                            <th>School Year</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT e.class_id, e.student_id, 
                                       u.first_name, u.last_name, 
                                       c.grade_level, c.section, c.room, c.school_year
                                FROM enrollments e 
                                JOIN users u ON e.student_id = u.user_id 
                                JOIN classes c ON e.class_id = c.class_id
                                ORDER BY c.grade_level, c.section, u.first_name, u.last_name";
                        $result = $conn->query($sql);
                        
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr class='clickable-row' onclick='editEnrollment(" . 
                                     $row['class_id'] . ", " . $row['student_id'] . ", {" .
                                     "student_name: \"" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "\", " .
                                     "class_info: \"Grade " . $row['grade_level'] . " - " . htmlspecialchars($row['section']) . "\", " .
                                     "grade_level: " . $row['grade_level'] . ", " .
                                     "section: \"" . htmlspecialchars($row['section']) . "\", " .
                                     "room: \"" . htmlspecialchars($row['room']) . "\", " .
                                     "school_year: \"" . htmlspecialchars($row['school_year']) . "\"" .
                                     "})'>";
                                echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                                echo "<td>Grade " . $row['grade_level'] . " - " . htmlspecialchars($row['section']) . "</td>";
                                echo "<td><span class='grade-badge'>Grade " . htmlspecialchars($row['grade_level']) . "</span></td>";
                                echo "<td>" . htmlspecialchars($row['section']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['room']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['school_year']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='no-data'>No enrollments found. Enroll your first student above!</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script>
        // Function to edit enrollment (for unenrolling)
        function editEnrollment(classId, studentId, enrollmentData) {
            // Update form title
            document.getElementById('form-title').textContent = 'Unenroll Student: ' + enrollmentData.student_name + ' from ' + enrollmentData.class_info;
            
            // Update submit button
            document.getElementById('submit-btn').textContent = 'Enroll Student';
            document.getElementById('submit-btn').style.display = 'none';
            
            // Show cancel and delete buttons
            document.getElementById('cancel-btn').style.display = 'inline-block';
            document.getElementById('delete-btn').style.display = 'inline-block';
            
            // Set form values
            document.getElementById('class_id').value = classId;
            document.getElementById('student_id').value = studentId;
            document.getElementById('operation').value = 'unenroll';
            
            // Set hidden fields for unenroll operation
            document.getElementById('hidden_class_id').value = classId;
            document.getElementById('hidden_student_id').value = studentId;
            
            // Disable selects (visually only)
            document.getElementById('class_id').disabled = true;
            document.getElementById('student_id').disabled = true;
            
            // Scroll to form
            document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Function to reset form
        function resetForm() {
            document.getElementById('form-title').textContent = 'Enroll Student in Class';
            document.getElementById('submit-btn').textContent = 'Enroll Student';
            document.getElementById('submit-btn').style.display = 'inline-block';
            document.getElementById('cancel-btn').style.display = 'none';
            document.getElementById('delete-btn').style.display = 'none';
            document.getElementById('operation').value = 'enroll';
            document.getElementById('class_id').disabled = false;
            document.getElementById('student_id').disabled = false;
            document.getElementById('enrollment-form').reset();
            document.getElementById('operation').value = 'enroll';
            document.getElementById('hidden_class_id').value = '';
            document.getElementById('hidden_student_id').value = '';
        }
        
        // Function to unenroll student
        function unenrollStudent() {
            const classSelect = document.getElementById('class_id');
            const studentSelect = document.getElementById('student_id');
            const className = classSelect.options[classSelect.selectedIndex].text;
            const studentName = studentSelect.options[studentSelect.selectedIndex].text;
            
            if (confirm('Are you sure you want to unenroll "' + studentName + '" from "' + className + '"?')) {
                document.getElementById('operation').value = 'unenroll';
                document.getElementById('enrollment-form').submit();
            }
        }
        
        // Search functionality
        document.getElementById('searchBar').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll("#enrollments-table tbody tr");
            rows.forEach(function(row) {
                let text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(filter) > -1 ? "" : "none";
            });
        });
        
        // Row hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.clickable-row');
            rows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                    this.style.cursor = 'pointer';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });
    </script>
</body>
</html>
