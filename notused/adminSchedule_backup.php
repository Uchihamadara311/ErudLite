<?php 
require_once 'includes/db.php';

if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: index.php");
    exit();
}

// Check if user is logged in and is admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Handle AJAX request for getting subjects by instructor
if (isset($_GET['action']) && $_GET['action'] == 'get_subjects' && isset($_GET['instructor_id'])) {
    $instructor_id = (int)$_GET['instructor_id'];
    
    $subject_sql = "SELECT s.subject_id, s.subject_name, s.grade_level 
                    FROM subjects s 
                    JOIN assigned_subject a ON s.subject_id = a.subject_id 
                    WHERE a.instructor_id = ? 
                    ORDER BY s.grade_level, s.subject_name";
    $subject_stmt = $conn->prepare($subject_sql);
    $subject_stmt->bind_param("i", $instructor_id);
    $subject_stmt->execute();
    $subject_result = $subject_stmt->get_result();
    
    $subjects = [];
    while($subject = $subject_result->fetch_assoc()) {
        $subjects[] = $subject;
    }
    
    header('Content-Type: application/json');
    echo json_encode($subjects);
    exit();
}

// Handle AJAX request for getting classes by subject grade level
if (isset($_GET['action']) && $_GET['action'] == 'get_classes' && isset($_GET['subject_id'])) {
    $subject_id = (int)$_GET['subject_id'];
    
    $class_sql = "SELECT c.class_id, c.grade_level, c.section, c.room 
                  FROM classes c 
                  JOIN subjects s ON c.grade_level = s.grade_level 
                  WHERE s.subject_id = ? 
                  ORDER BY c.grade_level, c.section";
    $class_stmt = $conn->prepare($class_sql);
    $class_stmt->bind_param("i", $subject_id);
    $class_stmt->execute();
    $class_result = $class_stmt->get_result();
    
    $classes = [];
    while($class = $class_result->fetch_assoc()) {
        $classes[] = $class;
    }
    
    header('Content-Type: application/json');
    echo json_encode($classes);
    exit();
}

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Function to add a new schedule
function addSchedule($conn, $scheduleData) {
    try {
        $conn->begin_transaction();
        $days = is_array($scheduleData['days']) ? $scheduleData['days'] : [$scheduleData['days']];
        $conflicts = [];
        
        // Check for conflicts on all selected days (instructor cannot have overlapping times across all classes/grade levels)
        foreach ($days as $day) {
            $conflict_sql = "SELECT s.schedule_id, s.time, s.day, c.room, u.first_name, u.last_name 
                            FROM schedule s 
                            JOIN classes c ON s.class_id = c.class_id 
                            JOIN users u ON s.instructor_id = u.user_id 
                            WHERE s.time = ? AND s.day = ? AND (s.instructor_id = ? OR c.room = (SELECT room FROM classes WHERE class_id = ?))";
            $conflict_stmt = $conn->prepare($conflict_sql);
            $conflict_stmt->bind_param("ssii", $scheduleData['time'], $day, $scheduleData['instructor_id'], $scheduleData['class_id']);
            $conflict_stmt->execute();
            $result = $conflict_stmt->get_result();
            if ($result->num_rows > 0) {
                // Check if any conflict is for the same instructor (regardless of class/grade)
                while ($row = $result->fetch_assoc()) {
                    if ($row['instructor_id'] == $scheduleData['instructor_id']) {
                        $conflicts[] = ucfirst(strtolower($day));
                        break;
                    }
                }
                // If not instructor, check for room conflict (already handled by original query)
                if (!in_array(ucfirst(strtolower($day)), $conflicts)) {
                    $conflicts[] = ucfirst(strtolower($day));
                }
            }
        }
        
        if (!empty($conflicts)) {
            $conn->rollback();
            return "Schedule conflict detected on: " . implode(', ', $conflicts) . ". Either the instructor or room is already booked at this time.";
        }
        
        // Insert schedule for each day
        $sql = "INSERT INTO schedule (instructor_id, class_id, subject_id, time, day, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        foreach ($days as $day) {
            $stmt->bind_param("iiiisss", $scheduleData['instructor_id'], $scheduleData['class_id'], $scheduleData['subject_id'], $scheduleData['time'], $day, $scheduleData['status'], $scheduleData['notes']);
            
            if (!$stmt->execute()) {
                $conn->rollback();
                return "Error adding schedule: " . $stmt->error;
            }
        }
        
        $conn->commit();
        return "Schedule added successfully for " . count($days) . " day(s)!";
    } catch (Exception $e) {
        $conn->rollback();
        return "Error: " . $e->getMessage();
    }
}

// Function to update a schedule
function updateSchedule($conn, $schedule_id, $scheduleData) {
    try {
        $days = is_array($scheduleData['days']) ? $scheduleData['days'] : [$scheduleData['days']];
        $conflicts = [];
        
        // Check for conflicts on all selected days (excluding current schedule, instructor cannot have overlapping times across all classes/grade levels)
        foreach ($days as $day) {
            $conflict_sql = "SELECT s.schedule_id, s.time, s.day, s.instructor_id, c.room, u.first_name, u.last_name 
                            FROM schedule s 
                            JOIN classes c ON s.class_id = c.class_id 
                            JOIN users u ON s.instructor_id = u.user_id 
                            WHERE s.schedule_id != ? AND s.time = ? AND s.day = ? AND (s.instructor_id = ? OR c.room = (SELECT room FROM classes WHERE class_id = ?))";
            $conflict_stmt = $conn->prepare($conflict_sql);
            $conflict_stmt->bind_param("issii", $schedule_id, $scheduleData['time'], $day, $scheduleData['instructor_id'], $scheduleData['class_id']);
            $conflict_stmt->execute();
            $result = $conflict_stmt->get_result();
            if ($result->num_rows > 0) {
                // Check if any conflict is for the same instructor (regardless of class/grade)
                while ($row = $result->fetch_assoc()) {
                    if ($row['instructor_id'] == $scheduleData['instructor_id']) {
                        $conflicts[] = ucfirst(strtolower($day));
                        break;
                    }
                }
                // If not instructor, check for room conflict (already handled by original query)
                if (!in_array(ucfirst(strtolower($day)), $conflicts)) {
                    $conflicts[] = ucfirst(strtolower($day));
                }
            }
        }
        
        if (!empty($conflicts)) {
            return "Schedule conflict detected on: " . implode(', ', $conflicts) . ". Either the instructor or room is already booked at this time.";
        }
        
        // For simplicity, we'll update the single schedule entry
        // In a more complex system, you might want to handle multiple day updates differently
        $day = $days[0]; // Use first day for single schedule update
        $sql = "UPDATE schedule SET instructor_id = ?, class_id = ?, subject_id = ?, time = ?, day = ?, status = ?, notes = ? WHERE schedule_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiisssi", $scheduleData['instructor_id'], $scheduleData['class_id'], $scheduleData['subject_id'], $scheduleData['time'], $day, $scheduleData['status'], $scheduleData['notes'], $schedule_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return "Schedule updated successfully!";
            } else {
                return "No changes were made.";
            }
        } else {
            return "Error updating schedule: " . $stmt->error;
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Function to delete a schedule
function deleteSchedule($conn, $schedule_id) {
    try {
        $sql = "DELETE FROM schedule WHERE schedule_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $schedule_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return "Schedule deleted successfully!";
            } else {
                return "Schedule not found.";
            }
        } else {
            return "Error deleting schedule: " . $stmt->error;
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = $_POST['operation'] ?? 'add';
    $schedule_id = (int)($_POST['schedule_id'] ?? 0);
    
    if ($operation == 'delete' && $schedule_id > 0) {
        $result = deleteSchedule($conn, $schedule_id);
        if (strpos($result, 'successfully') !== false) {
            $success_message = $result;
        } else {
            $error_message = $result;
        }
    } elseif ($operation == 'edit' && $schedule_id > 0) {
        $scheduleData = [
            'instructor_id' => (int)cleanInput($_POST['instructor_id']),
            'class_id' => (int)cleanInput($_POST['class_id']),
            'subject_id' => (int)cleanInput($_POST['subject_id']),
            'time' => cleanInput($_POST['time']),
            'days' => isset($_POST['days']) ? $_POST['days'] : [cleanInput($_POST['day'])],
            'status' => cleanInput($_POST['status']),
            'notes' => cleanInput($_POST['notes'])
        ];
        
        $result = updateSchedule($conn, $schedule_id, $scheduleData);
        if (strpos($result, 'successfully') !== false) {
            $success_message = $result;
        } else {
            $error_message = $result;
        }
    } else {
        // Add new schedule
        $required = ['instructor_id', 'class_id', 'subject_id', 'time', 'status'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($_POST[$field])) $missing[] = $field;
        }
        
        // Check if days are selected
        if (empty($_POST['days']) && empty($_POST['day'])) {
            $missing[] = 'days';
        }
        
        if (!empty($missing)) {
            $error_message = "Missing required fields: " . implode(', ', $missing);
        } else {
            $scheduleData = [
                'instructor_id' => (int)cleanInput($_POST['instructor_id']),
                'class_id' => (int)cleanInput($_POST['class_id']),
                'subject_id' => (int)cleanInput($_POST['subject_id']),
                'time' => cleanInput($_POST['time']),
                'days' => isset($_POST['days']) ? $_POST['days'] : [cleanInput($_POST['day'])],
                'status' => cleanInput($_POST['status']),
                'notes' => cleanInput($_POST['notes'])
            ];
            
            $result = addSchedule($conn, $scheduleData);
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
    <title>Schedule Management - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/adminLinks.css">
    <link rel="stylesheet" href="css/adminManagement.css">
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
        
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 5px;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 14px;
            color: #495057;
            position: relative;
        }
        
        .checkbox-label input[type="checkbox"] {
            margin-right: 8px;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .checkbox-label:hover {
            color: #007bff;
        }
        
        .checkbox-label input[type="checkbox"]:checked + .checkmark {
            background-color: #007bff;
        }
        
        @media (max-width: 768px) {
            .checkbox-group {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
        <div class="admin-back-btn-wrap admin-back-btn-upperleft">
            <a href="adminLinks.php" class="admin-back-btn"><i class="fa fa-arrow-left"></i> Back to Admin Dashboard</a>
        </div>
        <h1 class="page-title">Schedule Management</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <section class="form-section">
            <h2 class="form-title" id="form-title">Add New Schedule</h2>
            <form method="POST" action="adminSchedule.php" id="schedule-form">
                <input type="hidden" id="operation" name="operation" value="add">
                <input type="hidden" id="schedule_id" name="schedule_id" value="">
                <div class="form-grid">
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
                        <label class="form-label" for="class_id">Class *</label>
                        <select class="form-select" name="class_id" id="class_id" required>
                            <option value="">Select a Subject first</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="subject_id">Subject *</label>
                        <select class="form-select" name="subject_id" id="subject_id" required>
                            <option value="">Select an Instructor first</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="days">Days *</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="days[]" value="MONDAY" id="monday">
                                <span class="checkmark"></span>
                                Monday
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="days[]" value="TUESDAY" id="tuesday">
                                <span class="checkmark"></span>
                                Tuesday
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="days[]" value="WEDNESDAY" id="wednesday">
                                <span class="checkmark"></span>
                                Wednesday
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="days[]" value="THURSDAY" id="thursday">
                                <span class="checkmark"></span>
                                Thursday
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="days[]" value="FRIDAY" id="friday">
                                <span class="checkmark"></span>
                                Friday
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="days[]" value="SATURDAY" id="saturday">
                                <span class="checkmark"></span>
                                Saturday
                            </label>
                        </div>
                        <input type="hidden" name="day" id="day" value="">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="time">Time *</label>
                        <input class="form-input" name="time" id="time" type="time" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="status">Status *</label>
                        <select class="form-select" name="status" id="status" required>
                            <option value="">Select Status</option>
                            <option value="Active">Active</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea class="form-textarea" name="notes" id="notes" placeholder="Enter any additional notes (optional)"></textarea>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn" id="submit-btn">Add Schedule</button>
                <button type="button" class="cancel-btn" id="cancel-btn" style="display: none; margin-left: 10px;" onclick="resetForm()">Cancel</button>
                <button type="button" class="delete-btn" id="delete-btn" style="display: none; margin-left: 10px;" onclick="deleteCurrentSchedule()">Delete Schedule</button>
            </form>
        </section>
        
        <section class="table-section">
            <div class="table-header">
                <span>Current Schedules</span>
                <div class="search-container" style="width: 70%">
                    <input type="text" id="searchBar" class="form-input" placeholder="Search schedules..." style="width: 50%; margin-bottom: 10px;">
                    <i class="fas fa-search search-icon"></i>
                </div>
            </div>
            <div class="table-container">
                <table class="subjects-table" id="schedules-table">
                    <thead>
                        <tr>
                            <th>Instructor</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Day</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT s.schedule_id, s.instructor_id, s.class_id, s.subject_id, s.time, s.day, s.status, s.notes,
                                       u.first_name, u.last_name, 
                                       c.grade_level, c.section, c.room,
                                       sub.subject_name
                                FROM schedule s 
                                JOIN users u ON s.instructor_id = u.user_id 
                                JOIN classes c ON s.class_id = c.class_id
                                JOIN subjects sub ON s.subject_id = sub.subject_id
                                ORDER BY s.day, s.time, c.grade_level, c.section";
                        $result = $conn->query($sql);
                        
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr class='clickable-row' onclick='editSchedule(" . 
                                     $row['schedule_id'] . ", {" .
                                     "instructor_id: " . $row['instructor_id'] . ", " .
                                     "class_id: " . $row['class_id'] . ", " .
                                     "subject_id: " . $row['subject_id'] . ", " .
                                     "time: \"" . htmlspecialchars($row['time']) . "\", " .
                                     "day: \"" . htmlspecialchars($row['day']) . "\", " .
                                     "status: \"" . htmlspecialchars($row['status']) . "\", " .
                                     "notes: \"" . htmlspecialchars($row['notes']) . "\"" .
                                     "})'>";
                                echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                                echo "<td>Grade " . $row['grade_level'] . " - " . htmlspecialchars($row['section']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['subject_name']) . "</td>";
                                echo "<td>" . ucfirst(strtolower($row['day'])) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' class='no-data'>No schedules found. Add your first schedule above!</td></tr>";
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
        // Override editSchedule function for schedules
        function editSchedule(scheduleId, scheduleData) {
            // Update form title
            document.getElementById('form-title').textContent = 'Edit Schedule';
            
            // Update submit button
            document.getElementById('submit-btn').textContent = 'Update Schedule';
            
            // Show cancel and delete buttons
            document.getElementById('cancel-btn').style.display = 'inline-block';
            document.getElementById('delete-btn').style.display = 'inline-block';
            
            // Set operation mode
            document.getElementById('operation').value = 'edit';
            document.getElementById('schedule_id').value = scheduleId;
            
            // Populate form fields
            document.getElementById('instructor_id').value = scheduleData.instructor_id;
            document.getElementById('time').value = scheduleData.time;
            document.getElementById('status').value = scheduleData.status;
            document.getElementById('notes').value = scheduleData.notes;
            
            // Load subjects for the selected instructor and then set the subject
            const subjectSelect = document.getElementById('subject_id');
            const classSelect = document.getElementById('class_id');
            
            subjectSelect.innerHTML = '<option value="">Loading subjects...</option>';
            classSelect.innerHTML = '<option value="">Loading classes...</option>';
            
            fetch(`adminSchedule.php?action=get_subjects&instructor_id=${scheduleData.instructor_id}`)
                .then(response => response.json())
                .then(subjects => {
                    subjectSelect.innerHTML = '<option value="">Select a Subject</option>';
                    
                    if (subjects.length > 0) {
                        subjects.forEach(subject => {
                            const option = document.createElement('option');
                            option.value = subject.subject_id;
                            option.textContent = `${subject.subject_name} (Grade ${subject.grade_level})`;
                            if (subject.subject_id == scheduleData.subject_id) {
                                option.selected = true;
                            }
                            subjectSelect.appendChild(option);
                        });
                        
                        // After subjects are loaded, load classes for the selected subject
                        if (scheduleData.subject_id) {
                            return fetch(`adminSchedule.php?action=get_classes&subject_id=${scheduleData.subject_id}`);
                        }
                    } else {
                        subjectSelect.innerHTML = '<option value="">No subjects assigned to this instructor</option>';
                    }
                })
                .then(response => response ? response.json() : null)
                .then(classes => {
                    if (classes) {
                        classSelect.innerHTML = '<option value="">Select a Class</option>';
                        
                        if (classes.length > 0) {
                            classes.forEach(classItem => {
                                const option = document.createElement('option');
                                option.value = classItem.class_id;
                                option.textContent = `Grade ${classItem.grade_level} - ${classItem.section} (Room: ${classItem.room})`;
                                if (classItem.class_id == scheduleData.class_id) {
                                    option.selected = true;
                                }
                                classSelect.appendChild(option);
                            });
                        } else {
                            classSelect.innerHTML = '<option value="">No classes found for this subject grade level</option>';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading data:', error);
                    subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
                    classSelect.innerHTML = '<option value="">Error loading classes</option>';
                });
            
            // Clear all checkboxes first
            document.querySelectorAll('input[name="days[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Set the day checkbox for editing
            const dayCheckbox = document.getElementById(scheduleData.day.toLowerCase());
            if (dayCheckbox) {
                dayCheckbox.checked = true;
            }
            
            // Set hidden day field for backward compatibility
            document.getElementById('day').value = scheduleData.day;
            
            // Scroll to form
            document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Reset form function
        function resetForm() {
            document.getElementById('form-title').textContent = 'Add New Schedule';
            document.getElementById('submit-btn').textContent = 'Add Schedule';
            document.getElementById('cancel-btn').style.display = 'none';
            document.getElementById('delete-btn').style.display = 'none';
            document.getElementById('operation').value = 'add';
            document.getElementById('schedule_id').value = '';
            document.getElementById('schedule-form').reset();
            document.getElementById('operation').value = 'add';
            
            // Clear all checkboxes
            document.querySelectorAll('input[name="days[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Reset subjects and classes dropdowns
            document.getElementById('subject_id').innerHTML = '<option value="">Select an Instructor first</option>';
            document.getElementById('class_id').innerHTML = '<option value="">Select a Subject first</option>';
        }
        
        // Delete schedule function
        function deleteCurrentSchedule() {
            if (confirm('Are you sure you want to delete this schedule?')) {
                document.getElementById('operation').value = 'delete';
                document.getElementById('schedule-form').submit();
            }
        }
        
        // Search functionality
        document.getElementById('searchBar').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll("#schedules-table tbody tr");
            rows.forEach(function(row) {
                let text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(filter) > -1 ? "" : "none";
            });
        });
        
        // Form validation
        document.getElementById('schedule-form').addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('input[name="days[]"]');
            const isChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
            
            if (!isChecked) {
                e.preventDefault();
                alert('Please select at least one day for the schedule.');
                return false;
            }
        });
        
        // Handle instructor selection to load subjects
        document.getElementById('instructor_id').addEventListener('change', function() {
            const instructorId = this.value;
            const subjectSelect = document.getElementById('subject_id');
            const classSelect = document.getElementById('class_id');
            
            // Clear current subjects and classes
            subjectSelect.innerHTML = '<option value="">Loading subjects...</option>';
            classSelect.innerHTML = '<option value="">Select a Subject first</option>';
            
            if (instructorId) {
                // Fetch subjects for this instructor
                fetch(`adminSchedule.php?action=get_subjects&instructor_id=${instructorId}`)
                    .then(response => response.json())
                    .then(subjects => {
                        subjectSelect.innerHTML = '<option value="">Select a Subject</option>';
                        
                        if (subjects.length > 0) {
                            subjects.forEach(subject => {
                                const option = document.createElement('option');
                                option.value = subject.subject_id;
                                option.textContent = `${subject.subject_name} (Grade ${subject.grade_level})`;
                                subjectSelect.appendChild(option);
                            });
                        } else {
                            subjectSelect.innerHTML = '<option value="">No subjects assigned to this instructor</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading subjects:', error);
                        subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
                    });
            } else {
                subjectSelect.innerHTML = '<option value="">Select an Instructor first</option>';
            }
        });
        
        // Handle subject selection to load classes
        document.getElementById('subject_id').addEventListener('change', function() {
            const subjectId = this.value;
            const classSelect = document.getElementById('class_id');
            
            // Clear current classes
            classSelect.innerHTML = '<option value="">Loading classes...</option>';
            
            if (subjectId) {
                // Fetch classes for this subject's grade level
                fetch(`adminSchedule.php?action=get_classes&subject_id=${subjectId}`)
                    .then(response => response.json())
                    .then(classes => {
                        classSelect.innerHTML = '<option value="">Select a Class</option>';
                        
                        if (classes.length > 0) {
                            classes.forEach(classItem => {
                                const option = document.createElement('option');
                                option.value = classItem.class_id;
                                option.textContent = `Grade ${classItem.grade_level} - ${classItem.section} (Room: ${classItem.room})`;
                                classSelect.appendChild(option);
                            });
                        } else {
                            classSelect.innerHTML = '<option value="">No classes found for this subject grade level</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading classes:', error);
                        classSelect.innerHTML = '<option value="">Error loading classes</option>';
                    });
            } else {
                classSelect.innerHTML = '<option value="">Select a Subject first</option>';
            }
        });
    </script>
</body>
</html>
