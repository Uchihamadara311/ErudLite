<?php
require_once 'includes/db.php';
session_start();

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Ensure user is logged in and has admin permissions
if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: quickAccess.php");
    exit();
}

// Initialize messages
$success_message = '';
$error_message = '';

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = isset($_POST['operation']) ? $_POST['operation'] : 'add';
    $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;

    if ($operation == 'delete' && $class_id > 0) {
        // Check if class has enrolled students
        $check_sql = "SELECT COUNT(*) as student_count FROM Enrollment WHERE Class_ID = ? AND Status = 'Active'";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['student_count'];

        if ($count > 0) {
            $error_message = "Cannot delete class with enrolled students. Please transfer students first.";
        } else {
            // It's crucial to delete related records first if they have foreign key constraints
            // For example, from 'Schedule' if a schedule depends on a class
            $delete_schedule_sql = "DELETE FROM Schedule WHERE Class_ID = ?";
            $stmt_schedule = $conn->prepare($delete_schedule_sql);
            $stmt_schedule->bind_param("i", $class_id);
            $stmt_schedule->execute(); // Execute even if no schedules exist

            $sql = "DELETE FROM Class WHERE Class_ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $class_id);
            if ($stmt->execute()) {
                $success_message = "Class deleted successfully!";
            } else {
                $error_message = "Error deleting class: " . $stmt->error;
            }
        }
    } else { // Add or Edit operation
        $clearance_id = (int)$_POST['clearance_id'];
        $room_id = (int)$_POST['room_id'];

        if ($operation == 'edit' && $class_id > 0) {
            // Check for existing class (prevent duplicate room assignment for the same clearance)
            $check_sql = "SELECT Class_ID FROM Class WHERE Clearance_ID = ? AND Room_ID = ? AND Class_ID != ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("iii", $clearance_id, $room_id, $class_id);
            $stmt->execute();

            if ($stmt->get_result()->num_rows > 0) {
                $error_message = "A class already exists for this grade level and room.";
            } else {
                // Update existing class
                $sql = "UPDATE Class SET Clearance_ID = ?, Room_ID = ? WHERE Class_ID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $clearance_id, $room_id, $class_id);
                if ($stmt->execute()) {
                    $success_message = "Class updated successfully!";
                } else {
                    $error_message = "Error updating class: " . $stmt->error;
                }
            }
        } else { // Add new class
            // Check if class already exists for this clearance and room
            $check_sql = "SELECT Class_ID FROM Class WHERE Clearance_ID = ? AND Room_ID = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ii", $clearance_id, $room_id);
            $stmt->execute();

            if ($stmt->get_result()->num_rows > 0) {
                $error_message = "A class already exists for this grade level and room.";
            } else {
                // Insert new class
                $sql = "INSERT INTO Class (Clearance_ID, Room_ID) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $clearance_id, $room_id);
                if ($stmt->execute()) {
                    $success_message = "Class created successfully!";
                } else {
                    $error_message = "Error creating class: " . $stmt->error;
                }
            }
        }
    }
}

// Get clearances (grade levels and school years)
$clearances_sql = "SELECT Clearance_ID, School_Year, Term, Grade_Level FROM Clearance ORDER BY School_Year DESC, Grade_Level";
$clearances_result = $conn->query($clearances_sql);

// Get classrooms
$rooms_sql = "SELECT Room_ID, Room, Section, Floor_No FROM Classroom ORDER BY Floor_No, Room";
$rooms_result = $conn->query($rooms_sql);

// Query for existing classes to display in the table
$classes_sql = "SELECT c.Class_ID, cl.Clearance_ID, cl.Grade_Level, cl.School_Year, cl.Term,
                       cr.Room_ID, cr.Room, cr.Section, cr.Floor_No,
                       COUNT(e.Student_ID) as student_count
                FROM Class c
                JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                LEFT JOIN Enrollment e ON c.Class_ID = e.Class_ID AND e.Status = 'Active'
                GROUP BY c.Class_ID, cl.Clearance_ID, cl.Grade_Level, cl.School_Year, cl.Term,
                         cr.Room_ID, cr.Room, cr.Section, cr.Floor_No
                ORDER BY cl.School_Year DESC, cl.Grade_Level, cr.Room";
$classes_data_result = $conn->query($classes_sql);

?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Management - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/adminLinks.css">
    <link rel="stylesheet" href="css/adminManagement.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
        <div class="admin-back-btn-wrap admin-back-btn-upperleft">
            <a href="adminLinks.php" class="admin-back-btn"><i class="fa fa-arrow-left"></i> Back to Admin Dashboard</a>
        </div>
        <h1 class="page-title">Class Management</h1>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <section class="form-section">
            <h2 class="form-title" id="form-title"><i class="fas fa-school"></i> Create New Class</h2>
            <form method="POST" action="adminClasses.php" id="class-form">
                <input type="hidden" id="operation" name="operation" value="add">
                <input type="hidden" id="class_id" name="class_id" value="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="clearance_id"><i class="fas fa-graduation-cap"></i> Grade Level & School Year *</label>
                        <select class="form-select" name="clearance_id" id="clearance_id" required>
                            <option value="">Select Grade Level</option>
                            <?php
                            // Reset clearance result pointer if it's already been fetched once
                            if ($clearances_result) {
                                $clearances_result->data_seek(0);
                            }
                            if ($clearances_result && $clearances_result->num_rows > 0) {
                                while($clearance = $clearances_result->fetch_assoc()) {
                                    echo "<option value='" . $clearance['Clearance_ID'] . "'>" .
                                         "Grade " . htmlspecialchars($clearance['Grade_Level']) . " - " .
                                         htmlspecialchars($clearance['School_Year']) . " (" .
                                         htmlspecialchars($clearance['Term']) . ")</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="room_id"><i class="fas fa-door-open"></i> Classroom *</label>
                        <select class="form-select" name="room_id" id="room_id" required>
                            <option value="">Select Classroom</option>
                            <?php
                            // Reset rooms result pointer
                            if ($rooms_result) {
                                $rooms_result->data_seek(0);
                            }
                            if ($rooms_result && $rooms_result->num_rows > 0) {
                                while($room = $rooms_result->fetch_assoc()) {
                                    echo "<option value='" . $room['Room_ID'] . "'>" .
                                         "Room " . htmlspecialchars($room['Room']) . " - " .
                                         htmlspecialchars($room['Section']) . " (Floor " .
                                         $room['Floor_No'] . ")</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="submit-btn" id="submit-btn"><i class="fas fa-plus"></i> Create Class</button>
                    <button type="button" class="cancel-btn" id="cancel-btn" style="display: none;" onclick="resetForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="delete-btn" id="delete-btn" style="display: none;" onclick="deleteCurrentClass()">
                        <i class="fas fa-trash-alt"></i> Delete Class
                    </button>
                </div>
            </form>
        </section>

        <section class="table-section">
            <div class="section-header">
                <div class="header-icon-title">
                    <i class="fas fa-school"></i>
                    <h2>Existing Classes</h2>
                </div>
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchBar" class="search-input" placeholder="Search classes...">
                </div>
            </div>
            <div class="table-container">
                <table class="data-table" id="classes-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-graduation-cap"></i> Grade Level</th>
                            <th><i class="fas fa-calendar-alt"></i> School Year</th>
                            <th><i class="fas fa-door-open"></i> Classroom</th>
                            <th><i class="fas fa-users"></i> Enrolled Students</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($classes_data_result && $classes_data_result->num_rows > 0) {
                            while($row = $classes_data_result->fetch_assoc()) {
                                $gradeLevel = htmlspecialchars($row['Grade_Level']);
                                echo "<tr class='clickable-row'>"; // Removed onclick here as it's better handled by separate buttons
                                echo "<td><span class='grade-badge grade-level-{$gradeLevel}'><i class='fas fa-graduation-cap'></i> Grade {$gradeLevel}</span></td>";
                                echo "<td>" . htmlspecialchars($row['School_Year']) . " (" . htmlspecialchars($row['Term']) . ")</td>";
                                echo "<td><i class='fas fa-door-open'></i> Room " . htmlspecialchars($row['Room']) . " - " . htmlspecialchars($row['Section']) . "</td>";
                                echo "<td><span class='role-badge student'><i class='fas fa-users'></i> " . $row['student_count'] . " students</span></td>";
                                echo "<td class='action-buttons'>";
                                echo "<button class='edit-btn' onclick='editClass(" .
                                     $row['Class_ID'] . ", " .
                                     $row['Clearance_ID'] . ", " .
                                     $row['Room_ID'] . ")'><i class='fas fa-edit'></i> Edit</button>";
                                echo "<button class='delete-btn' onclick='deleteClass(" .
                                     $row['Class_ID'] . ")'><i class='fas fa-trash-alt'></i> Delete</button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='no-data'><i class='fas fa-info-circle'></i> No classes found. Create your first class above!</td></tr>";
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
        function editClass(classId, clearanceId, roomId) {
            document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> Edit Class';
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Class';
            document.getElementById('cancel-btn').style.display = 'inline-block';
            document.getElementById('delete-btn').style.display = 'inline-block';
            document.getElementById('operation').value = 'edit';
            document.getElementById('class_id').value = classId;

            // Set the selected values for clearance and room
            document.getElementById('clearance_id').value = clearanceId;
            document.getElementById('room_id').value = roomId;

            document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('form-title').innerHTML = '<i class="fas fa-school"></i> Create New Class';
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-plus"></i> Create Class';
            document.getElementById('cancel-btn').style.display = 'none';
            document.getElementById('delete-btn').style.display = 'none';
            document.getElementById('operation').value = 'add';
            document.getElementById('class_id').value = '';
            document.getElementById('class-form').reset();
        }

        function deleteCurrentClass() {
            const classId = document.getElementById('class_id').value;
            if(classId && confirm('Are you sure you want to delete this class? This action cannot be undone.')) {
                document.getElementById('operation').value = 'delete';
                // The hidden input 'class_id' is already set by editClass, so just submit
                document.getElementById('class-form').submit();
            }
        }

        function deleteClass(classId) {
            if(confirm('Are you sure you want to delete this class? This action cannot be undone.')) {
                const form = document.getElementById('class-form');
                document.getElementById('operation').value = 'delete';
                document.getElementById('class_id').value = classId;
                form.submit();
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
```