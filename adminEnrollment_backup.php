<?php 
require_once 'includes/db.php';

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

// Handle form submission using Post-Redirect-Get pattern
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = isset($_POST['operation']) ? $_POST['operation'] : 'enroll';
    $redirect_year = isset($_POST['year']) ? $_POST['year'] : date('Y') . '-' . (date('Y') + 1);

    if ($operation == 'enroll') {
        $class_id = (int)$_POST['class_id'];
        $student_id = (int)$_POST['student_id'];

        if (empty($class_id) || empty($student_id)) {
            $_SESSION['error_message'] = "Please select both a class and a student.";
        } else {
            // Check if student is already enrolled in any class for this academic year
            $check_sql = "SELECT e.Class_ID
                          FROM Enrollment e
                          JOIN Class c ON e.Class_ID = c.Class_ID
                          JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                          WHERE e.Student_ID = ? AND cl.School_Year = ? AND e.Status = 'Active'";
            $stmt = $conn->prepare($check_sql);
            if ($stmt) {
                $stmt->bind_param("is", $student_id, $redirect_year);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $_SESSION['error_message'] = "Student is already enrolled in a class for this academic year.";
                } else {
                    // Enroll student
                    $sql = "INSERT INTO Enrollment (Class_ID, Student_ID, Enrollment_Date, Status) VALUES (?, ?, CURDATE(), 'Active')";
                    $insert_stmt = $conn->prepare($sql);
                    if ($insert_stmt) {
                        $insert_stmt->bind_param("ii", $class_id, $student_id);
                        if ($insert_stmt->execute()) {
                            $_SESSION['success_message'] = "Student enrolled successfully!";
                        } else {
                            $_SESSION['error_message'] = "Error enrolling student: " . $insert_stmt->error;
                        }
                    } else {
                        $_SESSION['error_message'] = "Error preparing enrollment statement: " . $conn->error;
                    }
                }
            } else {
                $_SESSION['error_message'] = "Error preparing check statement: " . $conn->error;
            }
        }
    } elseif ($operation == 'unenroll') {
        $enrollment_id = (int)$_POST['enrollment_id'];
        $sql = "UPDATE Enrollment SET Status = 'Inactive' WHERE Class_ID = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $enrollment_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Student unenrolled successfully!";
            } else {
                $_SESSION['error_message'] = "Error unenrolling student: " . $stmt->error;
            }
        } else {
            $_SESSION['error_message'] = "Error preparing unenrollment statement: " . $conn->error;
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: adminEnrollment.php?year=" . urlencode($redirect_year));
    exit();
}

// Retrieve messages from session and then unset them
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get academic year filter. Default to empty if not set.
$selected_year = isset($_GET['year']) ? $_GET['year'] : '';

// Get all available classes with grade levels for the selected year
$classes_sql = "SELECT c.Class_ID, cl.Grade_Level, cl.School_Year, cl.Term, cr.Room, cr.Section,
                       COUNT(e.Student_ID) as enrolled_count
                FROM Class c
                JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                LEFT JOIN Enrollment e ON c.Class_ID = e.Class_ID AND e.Status = 'Active'
                WHERE cl.School_Year = ?
                GROUP BY c.Class_ID, cl.Grade_Level, cl.School_Year, cl.Term, cr.Room, cr.Section
                ORDER BY cl.Grade_Level, cr.Room";
$stmt_classes = $conn->prepare($classes_sql);
if(!$stmt_classes) { die("Prepare failed for classes: " . $conn->error); }
$stmt_classes->bind_param("s", $selected_year);
$stmt_classes->execute();
$classes_result = $stmt_classes->get_result();
if(!$classes_result) { die("Get result failed for classes: " . $conn->error); }

// Get all students not enrolled in any active class for the selected academic year
$available_students_sql = "SELECT s.Student_ID, pb.Given_Name, pb.Last_Name, pb.Date_of_Birth
                           FROM Student s
                           JOIN Profile_Bio pb ON s.Profile_ID = pb.Profile_ID
                           WHERE s.Student_ID NOT IN (
                               SELECT e.Student_ID 
                               FROM Enrollment e
                               JOIN Class c ON e.Class_ID = c.Class_ID
                               JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                               WHERE e.Status = 'Active' AND cl.School_Year = ?
                           )
                           ORDER BY pb.Last_Name, pb.Given_Name";
$stmt_students = $conn->prepare($available_students_sql);
if(!$stmt_students) { die("Prepare failed for students: " . $conn->error); }
$stmt_students->bind_param("s", $selected_year);
$stmt_students->execute();
$available_students_result = $stmt_students->get_result();
if(!$available_students_result) { die("Get result failed for students: " . $conn->error); }
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Enrollment - ErudLite</title>
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
        <h1 class="page-title">Student Enrollment Management</h1>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Academic Year Filter -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-filter"></i> Filter by Academic Year</h2>
            <form method="GET" action="adminEnrollment.php" style="padding: 20px;">
                <div class="form-grid" style="grid-template-columns: 1fr;">
                    <div class="form-group">
                        <label class="form-label" for="year"><i class="fas fa-calendar-alt"></i> Academic Year</label>
                        <select class="form-select" name="year" id="year" onchange="this.form.submit()">
                            <!-- <option value="">Select Academic Year</option> -->
                            <option value="" <?php echo empty($selected_year) ? 'selected' : ''; ?>>Select Academic Year</option>
                            <?php
                            $years_sql = "SELECT DISTINCT School_Year FROM Clearance ORDER BY School_Year DESC";
                            $years_result = $conn->query($years_sql);
                            while($year = $years_result->fetch_assoc()) {
                                $selected = ($year['School_Year'] == $selected_year) ? 'selected' : '';
                                echo "<option value='" . $year['School_Year'] . "' $selected>" . $year['School_Year'] . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </form>
        </section>

        <section class="form-section">
            <h2 class="form-title" id="form-title"><i class="fas fa-user-plus"></i> Enroll Student in Class</h2>
            <form method="POST" action="adminEnrollment.php?year=<?php echo urlencode($selected_year); ?>" id="enrollment-form">
                <input type="hidden" id="operation" name="operation" value="enroll">
                <input type="hidden" name="year" value="<?php echo htmlspecialchars($selected_year); ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="class_id"><i class="fas fa-school"></i> Select Class *</label>
                        <select class="form-select" name="class_id" id="class_id" required>
                            <option value="">Select a Class (<?php echo $selected_year; ?>)</option>
                            <?php
                            // Reset pointer to beginning of results
                            $classes_result->data_seek(0);
                            if ($classes_result && $classes_result->num_rows > 0) {
                                while($class = $classes_result->fetch_assoc()) {
                                    echo "<option value='" . $class['Class_ID'] . "'>" .
                                          "Grade " . $class['Grade_Level'] . " - " .
                                          htmlspecialchars($class['Section']) . " (Room " .
                                          htmlspecialchars($class['Room']) . ") - " .
                                         $class['enrolled_count'] . " students enrolled</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                                        <div class="form-group">
                        <label class="form-label" for="student_id"><i class="fas fa-user"></i> Select Student *</label>
                        <select class="form-select" name="student_id" id="student_id" required>
                            <option value="">Select a Student</option>
                            <?php
                            if ($available_students_result && $available_students_result->num_rows > 0) {
                                while($student = $available_students_result->fetch_assoc()) {
                                    echo "<option value='" . $student['Student_ID'] . "'>" .
                                          htmlspecialchars($student['Given_Name'] . ' ' . $student['Last_Name']) .
                                          " (Born: " . $student['Date_of_Birth'] . ")</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                                <div class="button-group">
                    <button type="submit" class="submit-btn" id="submit-btn"><i class="fas fa-user-plus"></i> Enroll Student</button>
                    <button type="button" class="cancel-btn" id="cancel-btn" style="display: none;" onclick="resetForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </section>                <section class="table-section">
            <div class="section-header">
                <div class="header-icon-title">
                    <i class="fas fa-users"></i>
                    <h2>Current Enrollments (<?php echo $selected_year; ?>)</h2>
                </div>
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchBar" class="search-input" placeholder="Search enrollments...">
                </div>
            </div>
            <div class="table-container">
                <table class="data-table" id="enrollments-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Student Name</th>
                            <th><i class="fas fa-graduation-cap"></i> Grade Level</th>
                            <th><i class="fas fa-door-open"></i> Room</th>
                            <th><i class="fas fa-calendar-alt"></i> Enrollment Date</th>
                            <th><i class="fas fa-info-circle"></i> Status</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($enrolled_result && $enrolled_result->num_rows > 0) {
                            while($row = $enrolled_result->fetch_assoc()) {
                                $gradeLevel = htmlspecialchars($row['Grade_Level']);
                                echo "<tr class='clickable-row'>";
                                echo "<td><i class='fas fa-user'></i> " . htmlspecialchars($row['Given_Name'] . ' ' . $row['Last_Name']) . "</td>";
                                echo "<td><span class='grade-badge grade-level-{$gradeLevel}'><i class='fas fa-graduation-cap'></i> Grade {$gradeLevel}</span></td>";
                                echo "<td><i class='fas fa-door-open'></i> Room " . htmlspecialchars($row['Room']) . " - " . htmlspecialchars($row['Section']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['Enrollment_Date']) . "</td>";
                                echo "<td><span class='role-badge admin'><i class='fas fa-check-circle'></i> " . htmlspecialchars($row['Status']) . "</span></td>";
                                echo "<td class='action-buttons'>";
                                echo "<button class='delete-btn' onclick='unenrollStudent(" .
                                      $row['Class_ID'] . ", \"" .
                                      htmlspecialchars($row['Given_Name'] . ' ' . $row['Last_Name']) . "\")'><i class='fas fa-user-minus'></i> Unenroll</button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='no-data'><i class='fas fa-info-circle'></i> No active enrollments found for the " . htmlspecialchars($selected_year) . " school year.</td></tr>";
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
        function resetForm() {
            document.getElementById('form-title').innerHTML = '<i class="fas fa-user-plus"></i> Enroll Student in Class';
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-user-plus"></i> Enroll Student';
            document.getElementById('cancel-btn').style.display = 'none';
            document.getElementById('operation').value = 'enroll';
            document.getElementById('enrollment-form').reset();
        }
        
        function unenrollStudent(enrollmentId, studentName) {
            if(confirm('Are you sure you want to unenroll ' + studentName + ' from this class?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'adminEnrollment.php?year=<?php echo urlencode($selected_year); ?>';
                
                const operationInput = document.createElement('input');
                operationInput.type = 'hidden';
                operationInput.name = 'operation';
                operationInput.value = 'unenroll';
                form.appendChild(operationInput);
                
                const enrollmentInput = document.createElement('input');
                enrollmentInput.type = 'hidden';
                enrollmentInput.name = 'enrollment_id';
                enrollmentInput.value = enrollmentId;
                form.appendChild(enrollmentInput);
                
                document.body.appendChild(form);
                form.submit();
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
    </script>
</body>
</html>