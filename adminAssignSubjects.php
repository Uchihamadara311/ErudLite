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

// Function to assign subject to instructor
function assignSubject($conn, $instructor_id, $subject_id) {
    try {
        // Check if assignment already exists
        $check_sql = "SELECT * FROM assigned_subject WHERE instructor_id = ? AND subject_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $instructor_id, $subject_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return "This subject is already assigned to this instructor.";
        }
        
        // Insert new assignment
        $sql = "INSERT INTO assigned_subject (instructor_id, subject_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $instructor_id, $subject_id);
        
        if ($stmt->execute()) {
            return "Subject assigned successfully!";
        } else {
            return "Error assigning subject: " . $stmt->error;
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Function to unassign subject from instructor
function unassignSubject($conn, $instructor_id, $subject_id) {
    try {
        $sql = "DELETE FROM assigned_subject WHERE instructor_id = ? AND subject_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $instructor_id, $subject_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return "Subject unassigned successfully!";
            } else {
                return "Assignment not found.";
            }
        } else {
            return "Error unassigning subject: " . $stmt->error;
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = $_POST['operation'] ?? 'assign';
    $instructor_id = (int)($_POST['instructor_id'] ?? 0);
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    
    if ($operation == 'assign' && $instructor_id > 0 && $subject_id > 0) {
        $result = assignSubject($conn, $instructor_id, $subject_id);
        if (strpos($result, 'successfully') !== false) {
            $success_message = $result;
        } else {
            $error_message = $result;
        }
    } elseif ($operation == 'unassign' && $instructor_id > 0 && $subject_id > 0) {
        $result = unassignSubject($conn, $instructor_id, $subject_id);
        if (strpos($result, 'successfully') !== false) {
            $success_message = $result;
        } else {
            $error_message = $result;
        }
    } else {
        $error_message = "Please select both instructor and subject.";
    }
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Subjects - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/adminManagement.css">
    <style>.hidden { display:none; }</style>
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
        <h1 class="page-title">Assign Subjects to Instructors</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <section class="form-section">
            <h2 class="form-title" id="form-title">Assign Subject to Instructor</h2>
            <form method="POST" action="adminAssignSubjects.php" id="assignment-form">
                <input type="hidden" id="operation" name="operation" value="assign">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="instructor_id">Select Instructor *</label>
                        <select class="form-select" name="instructor_id" id="instructor_id" required>
                            <option value="">Select an Instructor</option>
                            <?php
                            $instructor_sql = "SELECT u.user_id, u.first_name, u.last_name, i.specialization 
                                             FROM users u 
                                             JOIN instructors i ON u.user_id = i.instructor_id 
                                             WHERE u.permissions = 'Instructor' 
                                             ORDER BY u.first_name, u.last_name";
                            $instructor_result = $conn->query($instructor_sql);
                            
                            if ($instructor_result->num_rows > 0) {
                                while($instructor = $instructor_result->fetch_assoc()) {
                                    echo "<option value='" . $instructor['user_id'] . "'>" . 
                                         htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name']) . 
                                         " (" . htmlspecialchars($instructor['specialization']) . ")</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="subject_id">Select Subject *</label>
                        <select class="form-select" name="subject_id" id="subject_id" required>
                            <option value="">Select a Subject</option>
                            <?php
                            $subject_sql = "SELECT subject_id, subject_name, grade_level FROM subjects ORDER BY grade_level, subject_name";
                            $subject_result = $conn->query($subject_sql);
                            
                            if ($subject_result->num_rows > 0) {
                                while($subject = $subject_result->fetch_assoc()) {
                                    echo "<option value='" . $subject['subject_id'] . "'>" . 
                                         htmlspecialchars($subject['subject_name']) . " (Grade " . $subject['grade_level'] . ")</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn" id="submit-btn">Assign Subject</button>
                <button type="button" class="cancel-btn" id="cancel-btn" style="display: none; margin-left: 10px;" onclick="resetForm()">Cancel</button>
                <button type="button" class="delete-btn" id="delete-btn" style="display: none; margin-left: 10px;" onclick="unassignSubject()">Unassign Subject</button>
            </form>
        </section>
        
        <section class="table-section">
            <div class="table-header">
                <span>Current Subject Assignments</span>
                <div class="search-container" style="width: 70%">
                    <input type="text" id="searchBar" class="form-input" placeholder="Search assignments..." style="width: 50%; margin-bottom: 10px;">
                    <i class="fas fa-search search-icon"></i>
                </div>
            </div>
            <div class="table-container">
                <table class="subjects-table" id="assignments-table">
                    <thead>
                        <tr>
                            <th>Instructor Name</th>
                            <th>Subject Name</th>
                            <th>Grade Level</th>
                            <th>Specialization</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT a.instructor_id, a.subject_id, 
                                       u.first_name, u.last_name, 
                                       s.subject_name, s.grade_level,
                                       i.specialization
                                FROM assigned_subject a 
                                JOIN users u ON a.instructor_id = u.user_id 
                                JOIN subjects s ON a.subject_id = s.subject_id
                                JOIN instructors i ON a.instructor_id = i.instructor_id
                                ORDER BY u.first_name, u.last_name, s.grade_level, s.subject_name";
                        $result = $conn->query($sql);
                        
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr class='clickable-row' onclick='editAssignment(" . 
                                     $row['instructor_id'] . ", " . $row['subject_id'] . ", {" .
                                     "instructor_name: \"" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "\", " .
                                     "subject_name: \"" . htmlspecialchars($row['subject_name']) . "\", " .
                                     "grade_level: " . $row['grade_level'] . ", " .
                                     "specialization: \"" . htmlspecialchars($row['specialization']) . "\"" .
                                     "})'>";
                                echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['subject_name']) . "</td>";
                                echo "<td><span class='grade-badge'>Grade " . htmlspecialchars($row['grade_level']) . "</span></td>";
                                echo "<td>" . htmlspecialchars($row['specialization']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='no-data'>No subject assignments found. Make your first assignment above!</td></tr>";
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
        // Function to edit assignment (for unassigning)
        function editAssignment(instructorId, subjectId, assignmentData) {
            // Update form title
            document.getElementById('form-title').textContent = 'Unassign Subject: ' + assignmentData.subject_name + ' from ' + assignmentData.instructor_name;
            
            // Update submit button
            document.getElementById('submit-btn').textContent = 'Assign Subject';
            document.getElementById('submit-btn').style.display = 'none';
            
            // Show cancel and delete buttons
            document.getElementById('cancel-btn').style.display = 'inline-block';
            document.getElementById('delete-btn').style.display = 'inline-block';
            
            // Set form values
            document.getElementById('instructor_id').value = instructorId;
            document.getElementById('subject_id').value = subjectId;
            document.getElementById('operation').value = 'unassign';
            
            // Disable selects
            document.getElementById('instructor_id').disabled = true;
            document.getElementById('subject_id').disabled = true;
            
            // Scroll to form
            document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Function to reset form
        function resetForm() {
            document.getElementById('form-title').textContent = 'Assign Subject to Instructor';
            document.getElementById('submit-btn').textContent = 'Assign Subject';
            document.getElementById('submit-btn').style.display = 'inline-block';
            document.getElementById('cancel-btn').style.display = 'none';
            document.getElementById('delete-btn').style.display = 'none';
            document.getElementById('operation').value = 'assign';
            document.getElementById('instructor_id').disabled = false;
            document.getElementById('subject_id').disabled = false;
            document.getElementById('assignment-form').reset();
            document.getElementById('operation').value = 'assign';
        }
        
        // Function to unassign subject
        function unassignSubject() {
            const instructorSelect = document.getElementById('instructor_id');
            const subjectSelect = document.getElementById('subject_id');
            const instructorName = instructorSelect.options[instructorSelect.selectedIndex].text;
            const subjectName = subjectSelect.options[subjectSelect.selectedIndex].text;
            
            if (confirm('Are you sure you want to unassign "' + subjectName + '" from "' + instructorName + '"?')) {
                document.getElementById('operation').value = 'unassign';
                document.getElementById('assignment-form').submit();
            }
        }
        
        // Search functionality
        document.getElementById('searchBar').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll("#assignments-table tbody tr");
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