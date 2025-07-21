<?php 
require_once 'includes/db.php';
session_start();

// Function to get all instructors for subject assignments
function getAllInstructorsForSubject($conn) {
    $sql = "SELECT 
                i.Instructor_ID,
                pb.Given_Name,
                pb.Last_Name,
                i.Specialization
            FROM Instructor i
            JOIN Profile p ON p.Profile_ID = i.Profile_ID
            JOIN Profile_Bio pb ON pb.Profile_ID = p.Profile_ID
            ORDER BY pb.Given_Name, pb.Last_Name";
    return $conn->query($sql);
}

// Function to get all subjects with grade levels
function getAllSubjectsWithGrade($conn) {
    $sql = "SELECT s.Subject_ID, s.Subject_Name, COALESCE(c.Grade_Level, 'N/A') as Grade_Level
            FROM Subject s
            JOIN Clearance c ON s.Clearance_ID = c.Clearance_ID
            ORDER BY c.Grade_Level, s.Subject_Name";
    return $conn->query($sql);
}

// Ensure user is logged in and has admin permissions
if (!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: quickAccess.php");
    exit();
}

// Function to assign subject to instructor
function assignSubject($conn, $instructor_id, $subject_id) {
    try {
        // Check if assignment already exists
        $check_sql = "SELECT * FROM Assigned_Subject WHERE Instructor_ID = ? AND Subject_ID = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $instructor_id, $subject_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return "This subject is already assigned to this instructor.";
        }
        
        // Insert new assignment
        $sql = "INSERT INTO Assigned_Subject (Instructor_ID, Subject_ID) VALUES (?, ?)";
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
        $sql = "DELETE FROM Assigned_Subject WHERE Instructor_ID = ? AND Subject_ID = ?";
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
    
    if ($instructor_id > 0 && $subject_id > 0) {
        if ($operation == 'assign') {
            $result = assignSubject($conn, $instructor_id, $subject_id);
            if (strpos($result, 'successfully') !== false) {
                $success_message = $result;
            } else {
                $error_message = $result;
            }
        } elseif ($operation == 'unassign') {
            $result = unassignSubject($conn, $instructor_id, $subject_id);
            if (strpos($result, 'successfully') !== false) {
                $success_message = $result;
            } else {
                $error_message = $result;
            }
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
                            $instructor_result = getAllInstructorsForSubject($conn);
                            if ($instructor_result && $instructor_result->num_rows > 0) {
                                while($instructor = $instructor_result->fetch_assoc()) {
                                    echo "<option value='" . $instructor['Instructor_ID'] . "'>" . 
                                         htmlspecialchars($instructor['Given_Name'] . ' ' . $instructor['Last_Name']) . 
                                         " (" . htmlspecialchars($instructor['Specialization']) . ")</option>";
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
                            $subject_result = getAllSubjectsWithGrade($conn);
                            if ($subject_result && $subject_result->num_rows > 0) {
                                while($subject = $subject_result->fetch_assoc()) {
                                    echo "<option value='" . $subject['Subject_ID'] . "'>" . 
                                         htmlspecialchars($subject['Subject_Name']) . " (Grade " . $subject['Grade_Level'] . ")</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="submit-btn" id="submit-btn"><i class="fas fa-plus-circle"></i> Assign Subject</button>
                    <button type="button" class="cancel-btn" id="cancel-btn" style="display: none;" onclick="resetForm()"><i class="fas fa-times-circle"></i> Cancel</button>
                    <button type="button" class="delete-btn" id="delete-btn" style="display: none;" onclick="unassignSubject()"><i class="fas fa-minus-circle"></i> Unassign Subject</button>
                </div>
            </form>
        </section>
        
        <section class="table-section">
            <div class="section-header">
                <div class="header-icon-title">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h2>Current Subject Assignments</h2>
                </div>
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchBar" class="search-input" placeholder="Search assignments...">
                </div>
            </div>
            <div class="table-container">
                <table class="data-table" id="assignments-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Instructor Name</th>
                            <th><i class="fas fa-book"></i> Subject Name</th>
                            <th><i class="fas fa-graduation-cap"></i> Grade Level</th>
                            <th><i class="fas fa-briefcase"></i> Specialization</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT 
                                       a.Instructor_ID, 
                                       a.Subject_ID,
                                       pb.Given_Name,
                                       pb.Last_Name,
                                       s.Subject_Name,
                                       COALESCE(c.Grade_Level, 'N/A') as Grade_Level,
                                       i.Specialization
                                FROM Assigned_Subject a 
                                JOIN Instructor i ON a.Instructor_ID = i.Instructor_ID
                                JOIN Profile p ON i.Profile_ID = p.Profile_ID
                                JOIN Profile_Bio pb ON pb.Profile_ID = p.Profile_ID
                                JOIN Subject s ON a.Subject_ID = s.Subject_ID
                                LEFT JOIN Clearance c ON s.Clearance_ID = c.Clearance_ID
                                ORDER BY s.Subject_Name, pb.Given_Name, pb.Last_Name, c.Grade_Level";
                        $result = $conn->query($sql);
                        
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                $gradeLevel = htmlspecialchars($row['Grade_Level']);
                                $instructorName = htmlspecialchars($row['Given_Name'] . ' ' . $row['Last_Name']);
                                $subjectName = htmlspecialchars($row['Subject_Name']);
                                $specialization = htmlspecialchars($row['Specialization']);

                                echo "<tr class='clickable-row' onclick='editAssignment(" . 
                                     $row['Instructor_ID'] . ", " . $row['Subject_ID'] . ", {" .
                                     "instructor_name: \"" . $instructorName . "\", " .
                                     "subject_name: \"" . $subjectName . "\", " .
                                     "grade_level: " . ($gradeLevel ?: 'null') . ", " .
                                     "specialization: \"" . $specialization . "\"" .
                                     "})'>";
                                echo "<td>" . $instructorName . "</td>";
                                echo "<td>" . $subjectName . "</td>";
                                if ($gradeLevel && $gradeLevel !== 'N/A') {
                                    echo "<td><span class='grade-badge grade-level-{$gradeLevel}'>Grade {$gradeLevel}</span></td>";
                                } else {
                                    echo "<td><span class='grade-badge grade-level-none'>N/A</span></td>";
                                }
                                echo "<td>" . $specialization . "</td>";
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