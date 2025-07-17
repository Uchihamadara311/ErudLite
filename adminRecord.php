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

// Function to add a new record
function addRecord($conn, $recordData) {
    try {
        // Check if record already exists
        $check_sql = "SELECT record_id FROM record WHERE student_id = ? AND instructor_id = ? AND subject_id = ? AND school_year = ? AND term = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iiiis", $recordData['student_id'], $recordData['instructor_id'], $recordData['subject_id'], $recordData['school_year'], $recordData['term']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return "Record already exists for this student, instructor, subject, and term.";
        }
        
        // Insert record
        $sql = "INSERT INTO record (student_id, instructor_id, subject_id, school_year, term, grade, record_date) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiis", $recordData['student_id'], $recordData['instructor_id'], $recordData['subject_id'], $recordData['school_year'], $recordData['term'], $recordData['grade']);
        
        if ($stmt->execute()) {
            return "Record added successfully!";
        } else {
            return "Error adding record: " . $stmt->error;
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Function to update a record
function updateRecord($conn, $record_id, $recordData) {
    try {
        // Check if another record exists with the same criteria (excluding current record)
        $check_sql = "SELECT record_id FROM record WHERE record_id != ? AND student_id = ? AND instructor_id = ? AND subject_id = ? AND school_year = ? AND term = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iiiiss", $record_id, $recordData['student_id'], $recordData['instructor_id'], $recordData['subject_id'], $recordData['school_year'], $recordData['term']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return "Another record already exists for this student, instructor, subject, and term.";
        }
        
        $sql = "UPDATE record SET student_id = ?, instructor_id = ?, subject_id = ?, school_year = ?, term = ?, grade = ? WHERE record_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiissi", $recordData['student_id'], $recordData['instructor_id'], $recordData['subject_id'], $recordData['school_year'], $recordData['term'], $recordData['grade'], $record_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return "Record updated successfully!";
            } else {
                return "No changes were made.";
            }
        } else {
            return "Error updating record: " . $stmt->error;
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Function to delete a record
function deleteRecord($conn, $record_id) {
    try {
        $sql = "DELETE FROM record WHERE record_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $record_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return "Record deleted successfully!";
            } else {
                return "Record not found.";
            }
        } else {
            return "Error deleting record: " . $stmt->error;
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = $_POST['operation'] ?? 'add';
    $record_id = (int)($_POST['record_id'] ?? 0);
    
    if ($operation == 'delete' && $record_id > 0) {
        $result = deleteRecord($conn, $record_id);
        if (strpos($result, 'successfully') !== false) {
            $success_message = $result;
        } else {
            $error_message = $result;
        }
    } elseif ($operation == 'edit' && $record_id > 0) {
        $recordData = [
            'student_id' => (int)cleanInput($_POST['student_id']),
            'instructor_id' => (int)cleanInput($_POST['instructor_id']),
            'subject_id' => (int)cleanInput($_POST['subject_id']),
            'school_year' => (int)cleanInput($_POST['school_year']),
            'term' => cleanInput($_POST['term']),
            'grade' => cleanInput($_POST['grade'])
        ];
        
        $result = updateRecord($conn, $record_id, $recordData);
        if (strpos($result, 'successfully') !== false) {
            $success_message = $result;
        } else {
            $error_message = $result;
        }
    } else {
        // Add new record
        $required = ['student_id', 'instructor_id', 'subject_id', 'school_year', 'term', 'grade'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($_POST[$field])) $missing[] = $field;
        }
        
        if (!empty($missing)) {
            $error_message = "Missing required fields: " . implode(', ', $missing);
        } else {
            $recordData = [
                'student_id' => (int)cleanInput($_POST['student_id']),
                'instructor_id' => (int)cleanInput($_POST['instructor_id']),
                'subject_id' => (int)cleanInput($_POST['subject_id']),
                'school_year' => (int)cleanInput($_POST['school_year']),
                'term' => cleanInput($_POST['term']),
                'grade' => cleanInput($_POST['grade'])
            ];
            
            $result = addRecord($conn, $recordData);
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
    <title>Record Management - ErudLite</title>
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
        <h1 class="page-title">Record Management</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <section class="form-section">
            <h2 class="form-title" id="form-title">Add New Record</h2>
            <form method="POST" action="adminRecord.php" id="record-form">
                <input type="hidden" id="operation" name="operation" value="add">
                <input type="hidden" id="record_id" name="record_id" value="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="student_id">Student *</label>
                        <select class="form-select" name="student_id" id="student_id" required>
                            <option value="">Select a Student</option>
                            <?php
                            $student_sql = "SELECT u.user_id, u.first_name, u.last_name, u.email 
                                           FROM users u 
                                           WHERE u.permissions = 'Student' 
                                           ORDER BY u.first_name, u.last_name";
                            $student_result = $conn->query($student_sql);
                            
                            if ($student_result->num_rows > 0) {
                                while($student = $student_result->fetch_assoc()) {
                                    echo "<option value='" . $student['user_id'] . "'>" . 
                                         htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . 
                                         " (" . htmlspecialchars($student['email']) . ")</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="instructor_id">Instructor *</label>
                        <select class="form-select" name="instructor_id" id="instructor_id" required>
                            <option value="">Select an Instructor</option>
                            <?php
                            $instructor_sql = "SELECT u.user_id, u.first_name, u.last_name 
                                             FROM users u 
                                             JOIN instructors i ON u.user_id = i.instructor_id 
                                             WHERE u.permissions = 'Instructor' 
                                             ORDER BY u.first_name, u.last_name";
                            $instructor_result = $conn->query($instructor_sql);
                            
                            if ($instructor_result->num_rows > 0) {
                                while($instructor = $instructor_result->fetch_assoc()) {
                                    echo "<option value='" . $instructor['user_id'] . "'>" . 
                                         htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="subject_id">Subject *</label>
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
                    
                    <div class="form-group">
                        <label class="form-label" for="school_year">School Year *</label>
                        <select class="form-select" name="school_year" id="school_year" required>
                            <option value="">Select School Year</option>
                            <?php
                            $current_year = date('Y');
                            for ($year = $current_year - 5; $year <= $current_year + 5; $year++) {
                                $selected = ($year == $current_year) ? 'selected' : '';
                                echo "<option value='$year' $selected>$year</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="term">Term *</label>
                        <select class="form-select" name="term" id="term" required>
                            <option value="">Select Term</option>
                            <option value="First Quarter">First Quarter</option>
                            <option value="Second Quarter">Second Quarter</option>
                            <option value="Third Quarter">Third Quarter</option>
                            <option value="Fourth Quarter">Fourth Quarter</option>
                            <option value="Midterm">Midterm</option>
                            <option value="Final">Final</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="grade">Grade *</label>
                        <input class="form-input" name="grade" id="grade" type="text" placeholder="Enter grade" required>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn" id="submit-btn">Add Record</button>
                <button type="button" class="cancel-btn" id="cancel-btn" style="display: none; margin-left: 10px;" onclick="resetForm()">Cancel</button>
                <button type="button" class="delete-btn" id="delete-btn" style="display: none; margin-left: 10px;" onclick="deleteCurrentRecord()">Delete Record</button>
            </form>
        </section>
        
        <section class="table-section">
            <div class="table-header">
                <span>Student Records</span>
                <div class="search-container" style="width: 70%">
                    <input type="text" id="searchBar" class="form-input" placeholder="Search records..." style="width: 50%; margin-bottom: 10px;">
                    <i class="fas fa-search search-icon"></i>
                </div>
            </div>
            <div class="table-container">
                <table class="subjects-table" id="records-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Instructor</th>
                            <th>Subject</th>
                            <th>School Year</th>
                            <th>Term</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT r.record_id, r.student_id, r.instructor_id, r.subject_id, r.school_year, r.term, r.grade, r.record_date,
                                       s.first_name AS student_first, s.last_name AS student_last, s.email AS student_email,
                                       i.first_name AS instructor_first, i.last_name AS instructor_last,
                                       sub.subject_name
                                FROM record r 
                                JOIN users s ON r.student_id = s.user_id 
                                JOIN users i ON r.instructor_id = i.user_id
                                JOIN subjects sub ON r.subject_id = sub.subject_id
                                ORDER BY r.record_date DESC, s.first_name, s.last_name";
                        $result = $conn->query($sql);
                        
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr class='clickable-row' onclick='editRecord(" . 
                                     $row['record_id'] . ", {" .
                                     "student_id: " . $row['student_id'] . ", " .
                                     "instructor_id: " . $row['instructor_id'] . ", " .
                                     "subject_id: " . $row['subject_id'] . ", " .
                                     "school_year: " . $row['school_year'] . ", " .
                                     "term: \"" . htmlspecialchars($row['term']) . "\", " .
                                     "grade: \"" . htmlspecialchars($row['grade']) . "\"" .
                                     "})'>";
                                echo "<td>" . htmlspecialchars($row['student_first'] . ' ' . $row['student_last']) . 
                                     "<br><small>" . htmlspecialchars($row['student_email']) . "</small></td>";
                                echo "<td>" . htmlspecialchars($row['instructor_first'] . ' ' . $row['instructor_last']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['subject_name']) . "</td>";
                                echo "<td>" . $row['school_year'] . "</td>";
                                echo "<td>" . htmlspecialchars($row['term']) . "</td>";
                                echo "<td><span class='grade-badge'>" . htmlspecialchars($row['grade']) . "</span></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' class='no-data'>No records found. Add your first record above!</td></tr>";
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
    <script>
        // Override editRecord function for records
        function editRecord(recordId, recordData) {
            // Update form title
            document.getElementById('form-title').textContent = 'Edit Record';
            
            // Update submit button
            document.getElementById('submit-btn').textContent = 'Update Record';
            
            // Show cancel and delete buttons
            document.getElementById('cancel-btn').style.display = 'inline-block';
            document.getElementById('delete-btn').style.display = 'inline-block';
            
            // Set operation mode
            document.getElementById('operation').value = 'edit';
            document.getElementById('record_id').value = recordId;
            
            // Populate form fields
            document.getElementById('student_id').value = recordData.student_id;
            document.getElementById('instructor_id').value = recordData.instructor_id;
            document.getElementById('subject_id').value = recordData.subject_id;
            document.getElementById('school_year').value = recordData.school_year;
            document.getElementById('term').value = recordData.term;
            document.getElementById('grade').value = recordData.grade;
            
            // Scroll to form
            document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Reset form function
        function resetForm() {
            document.getElementById('form-title').textContent = 'Add New Record';
            document.getElementById('submit-btn').textContent = 'Add Record';
            document.getElementById('cancel-btn').style.display = 'none';
            document.getElementById('delete-btn').style.display = 'none';
            document.getElementById('operation').value = 'add';
            document.getElementById('record_id').value = '';
            document.getElementById('record-form').reset();
            document.getElementById('operation').value = 'add';
        }
        
        // Delete record function
        function deleteCurrentRecord() {
            if (confirm('Are you sure you want to delete this record?')) {
                document.getElementById('operation').value = 'delete';
                document.getElementById('record-form').submit();
            }
        }
        
        // Search functionality
        document.getElementById('searchBar').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll("#records-table tbody tr");
            rows.forEach(function(row) {
                let text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(filter) > -1 ? "" : "none";
            });
        });
    </script>
    <style>
        .grade-badge {
            display: inline-block;
            padding: 4px 8px;
            background-color: #007bff;
            color: white;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-badge.status-active {
            background-color: #28a745;
        }
        
        .status-badge.status-cancelled {
            background-color: #dc3545;
        }
        
        .status-badge.status-suspended {
            background-color: #ffc107;
            color: #212529;
        }
        
        .clickable-row:hover {
            background-color: #f8f9fa;
            cursor: pointer;
        }
        
        .no-data {
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }
        
        small {
            color: #6c757d;
        }
    </style>
</body>
</html>
