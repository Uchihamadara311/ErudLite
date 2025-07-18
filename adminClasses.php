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

// Handle AJAX request for getting class subjects
if (isset($_GET['action']) && $_GET['action'] == 'get_subjects' && isset($_GET['class_id'])) {
    $class_id = (int)$_GET['class_id'];
    
    $sql = "SELECT subject_id FROM class_subjects WHERE class_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $subjects = [];
    while($row = $result->fetch_assoc()) {
        $subjects[] = $row['subject_id'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($subjects);
    exit();
}

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Function to add a new class
function addClass($conn, $classData) {
    try {
        $conn->begin_transaction();

        // Insert into classes table
        $sql = "INSERT INTO classes (max_students, school_year, grade_level, room, section) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiss", $classData['max_students'], $classData['school_year'], $classData['grade_level'], $classData['room'], $classData['section']);
        
        if (!$stmt->execute()) {
            throw new Exception("Error adding class: " . $stmt->error);
        }
        
        $class_id = $conn->insert_id;
        
        // Insert subject associations
        if (!empty($classData['subjects'])) {
            $sql = "INSERT INTO class_subjects (class_id, subject_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            foreach ($classData['subjects'] as $subject_id) {
                $stmt->bind_param("ii", $class_id, $subject_id);
                if (!$stmt->execute()) {
                    throw new Exception("Error associating subject: " . $stmt->error);
                }
            }
        }
        
        $conn->commit();
        return "Class added successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        return "Error: " . $e->getMessage();
    }
}

// Function to update a class
function updateClass($conn, $class_id, $classData) {
    try {
        $conn->begin_transaction();

        // Update classes table
        $sql = "UPDATE classes SET max_students = ?, school_year = ?, grade_level = ?, room = ?, section = ? WHERE class_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiissi", $classData['max_students'], $classData['school_year'], $classData['grade_level'], $classData['room'], $classData['section'], $class_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating class: " . $stmt->error);
        }
        
        // Update subject associations
        $sql = "DELETE FROM class_subjects WHERE class_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $class_id);
        if (!$stmt->execute()) {
            throw new Exception("Error removing subject associations: " . $stmt->error);
        }
        
        if (!empty($classData['subjects'])) {
            $sql = "INSERT INTO class_subjects (class_id, subject_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            foreach ($classData['subjects'] as $subject_id) {
                $stmt->bind_param("ii", $class_id, $subject_id);
                if (!$stmt->execute()) {
                    throw new Exception("Error associating subject: " . $stmt->error);
                }
            }
        }
        
        $conn->commit();
        return "Class updated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
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
        
        // Delete subject associations
        $delete_subjects = $conn->prepare("DELETE FROM class_subjects WHERE class_id = ?");
        $delete_subjects->bind_param("i", $class_id);
        $delete_subjects->execute();
        
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
            'section' => cleanInput($_POST['section']),
            'subjects' => isset($_POST['subjects']) ? array_map('intval', $_POST['subjects']) : []
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
                'section' => cleanInput($_POST['section']),
                'subjects' => isset($_POST['subjects']) ? array_map('intval', $_POST['subjects']) : []
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
    <link rel="stylesheet" href="css/adminLinks.css">
    <link rel="stylesheet" href="css/adminManagement.css">
    <style>
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px;
        }
        .checkbox-item input[type="checkbox"] {
            margin: 0;
        }
        .checkbox-item label {
            margin: 0;
            font-size: 14px;
            color: #333;
        }
        .full-width {
            grid-column: 1 / -1;
        }
    </style>
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
        <div class="admin-back-btn-wrap admin-back-btn-upperleft">
            <a href="adminLinks.php" class="admin-back-btn"><i class="fa fa-arrow-left"></i> Back to Admin Dashboard</a>
        </div>
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

                    <div class="form-group full-width">
                        <label class="form-label">Subjects for this Class *</label>
                        <div class="checkbox-group">
                            <?php
                            $sql = "SELECT subject_id, subject_name, grade_level FROM subjects ORDER BY grade_level, subject_name";
                            $subjects = $conn->query($sql);
                            
                            if ($subjects->num_rows > 0) {
                                while($subject = $subjects->fetch_assoc()) {
                                    echo "<div class='checkbox-item'>";
                                    echo "<input type='checkbox' name='subjects[]' id='subject" . $subject['subject_id'] . "' value='" . $subject['subject_id'] . "' data-grade='" . $subject['grade_level'] . "'>";
                                    echo "<label for='subject" . $subject['subject_id'] . "'>" . htmlspecialchars($subject['subject_name']) . " (Grade " . $subject['grade_level'] . ")</label>";
                                    echo "</div>";
                                }
                            }
                            ?>
                        </div>
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
                            <th>Subjects</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT c.class_id, c.grade_level, c.section, c.room, c.max_students, c.school_year,
                                       (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.class_id) as current_students,
                                       GROUP_CONCAT(s.subject_name ORDER BY s.subject_name SEPARATOR ', ') as subjects
                                FROM classes c 
                                LEFT JOIN class_subjects cs ON c.class_id = cs.class_id
                                LEFT JOIN subjects s ON cs.subject_id = s.subject_id
                                GROUP BY c.class_id
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
                                echo "<td>" . htmlspecialchars($row['subjects'] ?: 'No subjects assigned') . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='no-data'>No classes found. Add your first class above!</td></tr>";
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
            
            // Load subjects for this class
            fetch('adminClasses.php?action=get_subjects&class_id=' + classId)
                .then(response => response.json())
                .then(subjects => {
                    // Clear all checkboxes first
                    document.querySelectorAll('input[name="subjects[]"]').forEach(checkbox => checkbox.checked = false);
                    
                    // Check the ones assigned to this class
                    subjects.forEach(subjectId => {
                        const checkbox = document.getElementById('subject' + subjectId);
                        if (checkbox) checkbox.checked = true;
                    });
                })
                .catch(error => console.error('Error loading subjects:', error));
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
        
        // Function to filter subjects based on selected grade level
        function filterSubjectsByGrade(gradeLevel) {
            document.querySelectorAll('.checkbox-item').forEach(item => {
                const checkbox = item.querySelector('input[type="checkbox"]');
                const subjectGrade = checkbox.getAttribute('data-grade');
                if (subjectGrade == gradeLevel) {
                    item.style.display = '';
                    checkbox.disabled = false;
                } else {
                    item.style.display = 'none';
                    checkbox.disabled = true;
                    checkbox.checked = false;
                }
            });
        }
        
        // Handle grade level change
        document.getElementById('grade_level').addEventListener('change', function() {
            filterSubjectsByGrade(this.value);
        });

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
