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

// Function to add a new class
function addClass($conn, $classData) {
    try {
        // Insert into classes table
        $sql = "INSERT INTO classes (max_students, school_year, grade_level, room, section) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiss", $classData['max_students'], $classData['school_year'], $classData['grade_level'], $classData['room'], $classData['section']);
        
        if ($stmt->execute()) {
            return "Class added successfully!";
        } else {
            return "Error adding class: " . $stmt->error;
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Function to update a class
function updateClass($conn, $class_id, $classData) {
    try {
        $sql = "UPDATE classes SET max_students = ?, school_year = ?, grade_level = ?, room = ?, section = ? WHERE class_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiissi", $classData['max_students'], $classData['school_year'], $classData['grade_level'], $classData['room'], $classData['section'], $class_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return "Class updated successfully!";
            } else {
                return "No changes were made.";
            }
        } else {
            return "Error updating class: " . $stmt->error;
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Function to delete a class
function deleteClass($conn, $class_id) {
    try {
        $conn->begin_transaction();
        
        // Delete enrollments first
        $delete_enrollments = $conn->prepare("DELETE FROM enrollments WHERE class_id = ?");
        $delete_enrollments->bind_param("i", $class_id);
        $delete_enrollments->execute();
        
        // Delete schedules
        $delete_schedules = $conn->prepare("DELETE FROM schedule WHERE class_id = ?");
        $delete_schedules->bind_param("i", $class_id);
        $delete_schedules->execute();
        
        // Delete class
        $delete_class = $conn->prepare("DELETE FROM classes WHERE class_id = ?");
        $delete_class->bind_param("i", $class_id);
        
        if ($delete_class->execute()) {
            if ($delete_class->affected_rows > 0) {
                $conn->commit();
                return "Class deleted successfully!";
            } else {
                $conn->rollback();
                return "Class not found.";
            }
        } else {
            $conn->rollback();
            return "Error deleting class: " . $delete_class->error;
        }
    } catch (Exception $e) {
        $conn->rollback();
        return "Error: " . $e->getMessage();
    }
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = $_POST['operation'] ?? 'add';
    $class_id = (int)($_POST['class_id'] ?? 0);
    
    if ($operation == 'delete' && $class_id > 0) {
        $result = deleteClass($conn, $class_id);
        if (strpos($result, 'successfully') !== false) {
            $success_message = $result;
        } else {
            $error_message = $result;
        }
    } elseif ($operation == 'edit' && $class_id > 0) {
        $classData = [
            'max_students' => (int)cleanInput($_POST['max_students']),
            'school_year' => (int)cleanInput($_POST['school_year']),
            'grade_level' => (int)cleanInput($_POST['grade_level']),
            'room' => cleanInput($_POST['room']),
            'section' => cleanInput($_POST['section'])
        ];
        
        $result = updateClass($conn, $class_id, $classData);
        if (strpos($result, 'successfully') !== false) {
            $success_message = $result;
        } else {
            $error_message = $result;
        }
    } else {
        // Add new class
        $required = ['max_students', 'school_year', 'grade_level', 'room', 'section'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($_POST[$field])) $missing[] = $field;
        }
        
        if (!empty($missing)) {
            $error_message = "Missing required fields: " . implode(', ', $missing);
        } else {
            $classData = [
                'max_students' => (int)cleanInput($_POST['max_students']),
                'school_year' => (int)cleanInput($_POST['school_year']),
                'grade_level' => (int)cleanInput($_POST['grade_level']),
                'room' => cleanInput($_POST['room']),
                'section' => cleanInput($_POST['section'])
            ];
            
            $result = addClass($conn, $classData);
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
    <title>Class Management - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/adminManagement.css">
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
        <h1 class="page-title">Class Management</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <section class="form-section">
            <h2 class="form-title" id="form-title">Add New Class</h2>
            <form method="POST" action="adminClasses.php" id="class-form">
                <input type="hidden" id="operation" name="operation" value="add">
                <input type="hidden" id="class_id" name="class_id" value="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="grade_level">Grade Level *</label>
                        <select class="form-select" name="grade_level" id="grade_level" required>
                            <option value="">Select Grade Level</option>
                            <option value="1">Grade 1</option>
                            <option value="2">Grade 2</option>
                            <option value="3">Grade 3</option>
                            <option value="4">Grade 4</option>
                            <option value="5">Grade 5</option>
                            <option value="6">Grade 6</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="section">Section *</label>
                        <input class="form-input" name="section" id="section" placeholder="Enter section name" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="room">Room *</label>
                        <input class="form-input" name="room" id="room" placeholder="Enter room number/name" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="max_students">Maximum Students *</label>
                        <input class="form-input" name="max_students" id="max_students" type="number" min="1" max="50" placeholder="Enter maximum students" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="school_year">School Year *</label>
                        <input class="form-input" name="school_year" id="school_year" type="number" min="2020" max="2030" placeholder="Enter school year (e.g., 2024)" required>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn" id="submit-btn">Add Class</button>
                <button type="button" class="cancel-btn" id="cancel-btn" style="display: none; margin-left: 10px;" onclick="resetForm()">Cancel</button>
                <button type="button" class="delete-btn" id="delete-btn" style="display: none; margin-left: 10px;" onclick="deleteCurrentClass()">Delete Class</button>
            </form>
        </section>
        
        <section class="table-section">
            <div class="table-header">
                <span>Existing Classes</span>
                <div class="search-container" style="width: 70%">
                    <input type="text" id="searchBar" class="form-input" placeholder="Search classes..." style="width: 50%; margin-bottom: 10px;">
                    <i class="fas fa-search search-icon"></i>
                </div>
            </div>
            <div class="table-container">
                <table class="subjects-table" id="classes-table">
                    <thead>
                        <tr>
                            <th>Grade Level</th>
                            <th>Section</th>
                            <th>Room</th>
                            <th>Students</th>
                            <th>School Year</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT c.class_id, c.grade_level, c.section, c.room, c.max_students, c.school_year,
                                       (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.class_id) as current_students
                                FROM classes c 
                                ORDER BY c.grade_level, c.section";
                        $result = $conn->query($sql);
                        
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr class='clickable-row' onclick='editClass(" . 
                                     $row['class_id'] . ", {" .
                                     "grade_level: " . $row['grade_level'] . ", " .
                                     "section: \"" . htmlspecialchars($row['section']) . "\", " .
                                     "room: \"" . htmlspecialchars($row['room']) . "\", " .
                                     "max_students: " . $row['max_students'] . ", " .
                                     "school_year: " . $row['school_year'] . 
                                     "})'>";
                                echo "<td><span class='grade-badge'>Grade " . htmlspecialchars($row['grade_level']) . "</span></td>";
                                echo "<td>" . htmlspecialchars($row['section']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['room']) . "</td>";
                                echo "<td>" . $row['current_students'] . "/" . $row['max_students'] . "</td>";
                                echo "<td>" . htmlspecialchars($row['school_year']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='no-data'>No classes found. Add your first class above!</td></tr>";
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
        // Override editClass function for classes
        function editClass(classId, classData) {
            // Update form title
            document.getElementById('form-title').textContent = 'Edit Class: Grade ' + classData.grade_level + ' - ' + classData.section;
            
            // Update submit button
            document.getElementById('submit-btn').textContent = 'Update Class';
            
            // Show cancel and delete buttons
            document.getElementById('cancel-btn').style.display = 'inline-block';
            document.getElementById('delete-btn').style.display = 'inline-block';
            
            // Set operation mode
            document.getElementById('operation').value = 'edit';
            document.getElementById('class_id').value = classId;
            
            // Populate form fields
            document.getElementById('grade_level').value = classData.grade_level;
            document.getElementById('section').value = classData.section;
            document.getElementById('room').value = classData.room;
            document.getElementById('max_students').value = classData.max_students;
            document.getElementById('school_year').value = classData.school_year;
            
            // Scroll to form
            document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Reset form function
        function resetForm() {
            document.getElementById('form-title').textContent = 'Add New Class';
            document.getElementById('submit-btn').textContent = 'Add Class';
            document.getElementById('cancel-btn').style.display = 'none';
            document.getElementById('delete-btn').style.display = 'none';
            document.getElementById('operation').value = 'add';
            document.getElementById('class_id').value = '';
            document.getElementById('class-form').reset();
            document.getElementById('operation').value = 'add';
        }
        
        // Delete class function
        function deleteCurrentClass() {
            const classId = document.getElementById('class_id').value;
            const gradeLevel = document.getElementById('grade_level').value;
            const section = document.getElementById('section').value;
            
            if (confirm('Are you sure you want to delete Grade ' + gradeLevel + ' - ' + section + '? This will also delete all enrollments and schedules for this class.')) {
                document.getElementById('operation').value = 'delete';
                document.getElementById('class-form').submit();
            }
        }
        
        // Search functionality
        document.getElementById('searchBar').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll("#classes-table tbody tr");
            rows.forEach(function(row) {
                let text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(filter) > -1 ? "" : "none";
            });
        });
    </script>
</body>
</html>
