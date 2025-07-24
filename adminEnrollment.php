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
            // Get the section, room, grade, and year for the selected class
            $class_info_sql = "SELECT cl.Grade_Level, cl.School_Year, cr.Section, cr.Room
                               FROM Class c
                               JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                               JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                               WHERE c.Class_ID = ?";
            $stmt_info = $conn->prepare($class_info_sql);
            if ($stmt_info) {
                $stmt_info->bind_param("i", $class_id);
                $stmt_info->execute();
                $info_result = $stmt_info->get_result();
                if ($info_row = $info_result->fetch_assoc()) {
                    $grade_level = $info_row['Grade_Level'];
                    $school_year = $info_row['School_Year'];
                    $section = $info_row['Section'];
                    $room = $info_row['Room'];

                    // Check if student is already enrolled in any class for this section/room/grade/year
                    $check_sql = "SELECT e.Class_ID 
                                  FROM Enrollment e
                                  JOIN Class c ON e.Class_ID = c.Class_ID
                                  JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                                  JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                                  WHERE e.Student_ID = ? AND cl.School_Year = ? AND cl.Grade_Level = ? AND cr.Section = ? AND cr.Room = ? AND e.Status = 'Active'";
                    $stmt = $conn->prepare($check_sql);
                    if ($stmt) {
                        $stmt->bind_param("isiss", $student_id, $school_year, $grade_level, $section, $room);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->num_rows > 0) {
                            $_SESSION['error_message'] = "Student is already enrolled in this section for this academic year.";
                        } else {
                            // Get all classes for this section and check which ones student is NOT enrolled in
                            $get_classes_sql = "SELECT c.Class_ID FROM Class c
                                                JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                                                JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                                                WHERE cl.School_Year = ? AND cl.Grade_Level = ? AND cr.Section = ? AND cr.Room = ?
                                                AND c.Class_ID NOT IN (
                                                    SELECT e.Class_ID FROM Enrollment e 
                                                    WHERE e.Student_ID = ? AND e.Status = 'Active'
                                                )";
                            $stmt_classes = $conn->prepare($get_classes_sql);
                            if ($stmt_classes) {
                                $stmt_classes->bind_param("sissi", $school_year, $grade_level, $section, $room, $student_id);
                                $stmt_classes->execute();
                                $classes_result = $stmt_classes->get_result();
                                $success = true;
                                $enrolled_count = 0;
                                while ($class_row = $classes_result->fetch_assoc()) {
                                    $cid = $class_row['Class_ID'];
                                    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle existing records
                                    $sql = "INSERT INTO Enrollment (Class_ID, Student_ID, Enrollment_Date, Status) 
                                            VALUES (?, ?, CURDATE(), 'Active')
                                            ON DUPLICATE KEY UPDATE 
                                            Enrollment_Date = CURDATE(), Status = 'Active'";
                                    $insert_stmt = $conn->prepare($sql);
                                    if ($insert_stmt) {
                                        $insert_stmt->bind_param("ii", $cid, $student_id);
                                        if ($insert_stmt->execute()) {
                                            $enrolled_count++;
                                        } else {
                                            $success = false;
                                            $_SESSION['error_message'] = "Error enrolling student: " . $insert_stmt->error;
                                            break;
                                        }
                                    } else {
                                        $success = false;
                                        $_SESSION['error_message'] = "Error preparing enrollment statement: " . $conn->error;
                                        break;
                                    }
                                }
                                if ($success && $enrolled_count > 0) {
                                    $_SESSION['success_message'] = "Student enrolled successfully in $enrolled_count semester(s)!";
                                } elseif ($enrolled_count == 0) {
                                    $_SESSION['error_message'] = "Student is already enrolled in all semesters for this section.";
                                }
                            } else {
                                $_SESSION['error_message'] = "Error preparing class fetch statement: " . $conn->error;
                            }
                        }
                    } else {
                        $_SESSION['error_message'] = "Error preparing check statement: " . $conn->error;
                    }
                } else {
                    $_SESSION['error_message'] = "Class information not found.";
                }
            } else {
                $_SESSION['error_message'] = "Error preparing class info statement: " . $conn->error;
            }
        }
    } elseif ($operation == 'unenroll') {
        $class_id = (int)$_POST['class_id'];
        $student_id = (int)$_POST['student_id'];
        
        // Get the section, room, grade, and year for the selected class to unenroll from all semesters
        $class_info_sql = "SELECT cl.Grade_Level, cl.School_Year, cr.Section, cr.Room
                           FROM Class c
                           JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                           JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                           WHERE c.Class_ID = ?";
        $stmt_info = $conn->prepare($class_info_sql);
        if ($stmt_info) {
            $stmt_info->bind_param("i", $class_id);
            $stmt_info->execute();
            $info_result = $stmt_info->get_result();
            if ($info_row = $info_result->fetch_assoc()) {
                $grade_level = $info_row['Grade_Level'];
                $school_year = $info_row['School_Year'];
                $section = $info_row['Section'];
                $room = $info_row['Room'];

                // Unenroll from all classes for this section/room/grade/year (all semesters)
                $sql = "UPDATE Enrollment e 
                        JOIN Class c ON e.Class_ID = c.Class_ID
                        JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                        JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                        SET e.Status = 'Inactive' 
                        WHERE e.Student_ID = ? AND cl.School_Year = ? AND cl.Grade_Level = ? AND cr.Section = ? AND cr.Room = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("isiss", $student_id, $school_year, $grade_level, $section, $room);
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = "Student unenrolled successfully from all semesters!";
                    } else {
                        $_SESSION['error_message'] = "Error unenrolling student: " . $stmt->error;
                    }
                } else {
                    $_SESSION['error_message'] = "Error preparing unenrollment statement: " . $conn->error;
                }
            } else {
                $_SESSION['error_message'] = "Class information not found.";
            }
        } else {
            $_SESSION['error_message'] = "Error preparing class info statement: " . $conn->error;
        }
    } elseif ($operation == 'update') {
        $new_class_id = (int)$_POST['class_id'];
        $student_id = (int)$_POST['student_id'];
        $original_class_id = (int)$_POST['original_class_id'];

        if (empty($new_class_id) || empty($student_id) || empty($original_class_id)) {
            $_SESSION['error_message'] = "Missing data for update.";
        } else {
            // Get info for both old and new classes
            $old_class_info_sql = "SELECT cl.Grade_Level, cl.School_Year, cr.Section, cr.Room
                                   FROM Class c
                                   JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                                   JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                                   WHERE c.Class_ID = ?";
            $new_class_info_sql = "SELECT cl.Grade_Level, cl.School_Year, cr.Section, cr.Room
                                   FROM Class c
                                   JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                                   JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                                   WHERE c.Class_ID = ?";
            
            $conn->begin_transaction();
            try {
                // Get old class info
                $stmt_old = $conn->prepare($old_class_info_sql);
                if (!$stmt_old) throw new Exception("Error preparing old class info statement: " . $conn->error);
                $stmt_old->bind_param("i", $original_class_id);
                $stmt_old->execute();
                $old_info = $stmt_old->get_result()->fetch_assoc();
                if (!$old_info) throw new Exception("Old class information not found");

                // Get new class info
                $stmt_new = $conn->prepare($new_class_info_sql);
                if (!$stmt_new) throw new Exception("Error preparing new class info statement: " . $conn->error);
                $stmt_new->bind_param("i", $new_class_id);
                $stmt_new->execute();
                $new_info = $stmt_new->get_result()->fetch_assoc();
                if (!$new_info) throw new Exception("New class information not found");

                // Set all old enrollments to Inactive (all semesters of old section)
                $sql_inactive = "UPDATE Enrollment e 
                                JOIN Class c ON e.Class_ID = c.Class_ID
                                JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                                JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                                SET e.Status = 'Inactive' 
                                WHERE e.Student_ID = ? AND cl.School_Year = ? AND cl.Grade_Level = ? AND cr.Section = ? AND cr.Room = ?";
                $stmt_inactive = $conn->prepare($sql_inactive);
                if (!$stmt_inactive) throw new Exception("Error preparing statement to set old enrollment inactive: " . $conn->error);
                $stmt_inactive->bind_param("isiss", $student_id, $old_info['School_Year'], $old_info['Grade_Level'], $old_info['Section'], $old_info['Room']);
                if (!$stmt_inactive->execute()) throw new Exception("Error setting old enrollment to inactive: " . $stmt_inactive->error);

                // Insert new enrollment records for all semesters of new section
                $get_new_classes_sql = "SELECT c.Class_ID FROM Class c
                                        JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                                        JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                                        WHERE cl.School_Year = ? AND cl.Grade_Level = ? AND cr.Section = ? AND cr.Room = ?";
                $stmt_new_classes = $conn->prepare($get_new_classes_sql);
                if (!$stmt_new_classes) throw new Exception("Error preparing new classes statement: " . $conn->error);
                $stmt_new_classes->bind_param("siss", $new_info['School_Year'], $new_info['Grade_Level'], $new_info['Section'], $new_info['Room']);
                $stmt_new_classes->execute();
                $new_classes_result = $stmt_new_classes->get_result();
                
                while ($new_class_row = $new_classes_result->fetch_assoc()) {
                    $new_cid = $new_class_row['Class_ID'];
                    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle existing records
                    $sql_active = "INSERT INTO Enrollment (Class_ID, Student_ID, Enrollment_Date, Status) 
                                   VALUES (?, ?, CURDATE(), 'Active')
                                   ON DUPLICATE KEY UPDATE 
                                   Enrollment_Date = CURDATE(), Status = 'Active'";
                    $stmt_active = $conn->prepare($sql_active);
                    if (!$stmt_active) throw new Exception("Error preparing statement to insert new enrollment: " . $conn->error);
                    $stmt_active->bind_param("ii", $new_cid, $student_id);
                    if (!$stmt_active->execute()) throw new Exception("Error inserting new enrollment: " . $stmt_active->error);
                }

                $conn->commit();
                $_SESSION['success_message'] = "Student enrollment updated successfully for all semesters!";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error_message'] = "Failed to update enrollment: " . $e->getMessage();
            }
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

// Get academic year filter
$selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y') . '-' . (date('Y') + 1);

// Get all available classes with grade levels for the selected year (grouped by section)
$classes_sql = "SELECT cl.Grade_Level, cl.School_Year, cr.Room, cr.Section,
                       COUNT(DISTINCT e.Student_ID) as enrolled_count,
                       MIN(c.Class_ID) as Class_ID
                FROM Class c
                JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                LEFT JOIN Enrollment e ON c.Class_ID = e.Class_ID AND e.Status = 'Active'
                WHERE cl.School_Year = ?
                GROUP BY cl.Grade_Level, cl.School_Year, cr.Room, cr.Section
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

// Show only one row per student per section/room/grade/year (distinct enrollment group)
$enrolled_sql = "SELECT s.Student_ID, pb.Given_Name, pb.Last_Name, MIN(e.Enrollment_Date) AS Enrollment_Date, e.Status, clr.Grade_Level, cr.Room, cr.Section
                FROM Student s
                JOIN Profile p ON s.Profile_ID = p.Profile_ID
                JOIN Profile_Bio pb ON s.Profile_ID = pb.Profile_ID
                JOIN Enrollment e ON s.Student_ID = e.Student_ID
                JOIN Class c ON e.Class_ID = c.Class_ID
                JOIN Clearance clr ON c.Clearance_ID = clr.Clearance_ID
                JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                WHERE e.Status = 'Active' AND clr.School_Year = ?
                GROUP BY s.Student_ID, pb.Given_Name, pb.Last_Name, clr.Grade_Level, cr.Room, cr.Section, e.Status
                ORDER BY clr.Grade_Level, cr.Room, cr.Section, pb.Last_Name, pb.Given_Name";
$stmt_enrollments = $conn->prepare($enrolled_sql);
if(!$stmt_enrollments) { die("Prepare failed for enrollments: " . $conn->error); }
$stmt_enrollments->bind_param("s", $selected_year);
$stmt_enrollments->execute();
$enrolled_result = $stmt_enrollments->get_result();
if(!$enrolled_result) { die("Get result failed for enrollments: " . $conn->error); }
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
                <input type="hidden" id="original_class_id" name="original_class_id" value="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="grade_level"><i class="fas fa-graduation-cap"></i> Grade Level</label>
                        <select class="form-select" name="grade_level" id="grade_level">
                            <option value="">All Grades</option>
                            <option value="1">Grade 1</option>
                            <option value="2">Grade 2</option>
                            <option value="3">Grade 3</option>
                            <option value="4">Grade 4</option>
                            <option value="5">Grade 5</option>
                            <option value="6">Grade 6</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="class_id"><i class="fas fa-school"></i> Select Class *</label>
                        <select class="form-select" name="class_id" id="class_id" required>
                            <option value="">Select a Class (<?php echo $selected_year; ?>)</option>
                            <?php
                            // Separate classes by grade level
                            $classes_result->data_seek(0);
                            $grade_classes = [];
                            if ($classes_result && $classes_result->num_rows > 0) {
                                while($class = $classes_result->fetch_assoc()) {
                                    $grade = $class['Grade_Level'];
                                    if (!isset($grade_classes[$grade])) {
                                        $grade_classes[$grade] = [];
                                    }
                                    $grade_classes[$grade][] = [
                                        'id' => $class['Class_ID'],
                                        'section' => htmlspecialchars($class['Section']),
                                        'room' => htmlspecialchars($class['Room']),
                                        'enrolled' => $class['enrolled_count']
                                    ];
                                }
                            }
                            foreach ($grade_classes as $grade => $classes) {
                                echo '<optgroup label="Grade ' . $grade . '" class="grade-group grade-' . $grade . '">';
                                foreach ($classes as $class) {
                                    echo "<option value='" . $class['id'] . "' data-grade='" . $grade . "'>" .
                                        $class['section'] . " (Room " . $class['room'] . ") - " .
                                        $class['enrolled'] . " students enrolled</option>";
                                }
                                echo '</optgroup>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="student_id"><i class="fas fa-user"></i> Select Student *</label>
                        <input type="text" class="form-control" id="studentSearch" placeholder="Search by ID or Name..." style="margin-bottom:8px;" autocomplete="off">
                        <div id="studentSuggestions" class="suggestion-list" style="position:relative;z-index:10;"></div>
                        <select class="form-select" name="student_id" id="student_id" required>
                            <option value="">Select a Student</option>
                            <?php
                            if ($available_students_result && $available_students_result->num_rows > 0) {
                                while($student = $available_students_result->fetch_assoc()) {
                                    echo "<option value='" . $student['Student_ID'] . "'>" .
                                    htmlspecialchars($student['Given_Name'] . ' ' . $student['Last_Name']) .
                                    " (Student_ID: " . $student['Student_ID'] . ")</option>";
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
                    <button type="button" class="delete-btn" id="unenroll-btn" style="display: none;"><i class="fas fa-user-minus"></i> Unenroll</button>
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
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($enrolled_result && $enrolled_result->num_rows > 0) {
                            while($row = $enrolled_result->fetch_assoc()) {
                                $gradeLevel = htmlspecialchars($row['Grade_Level']);
                                $studentName = htmlspecialchars($row['Given_Name'] . ' ' . $row['Last_Name']);
                                // Find a class_id for this group (for edit/unenroll actions, pick any class in the group)
                                $find_class_sql = "SELECT c.Class_ID FROM Class c
                                                    JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                                                    JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                                                    WHERE cl.School_Year = ? AND cl.Grade_Level = ? AND cr.Section = ? AND cr.Room = ?
                                                    LIMIT 1";
                                $find_class_stmt = $conn->prepare($find_class_sql);
                                $find_class_stmt->bind_param("siss", $selected_year, $row['Grade_Level'], $row['Section'], $row['Room']);
                                $find_class_stmt->execute();
                                $find_class_result = $find_class_stmt->get_result();
                                $class_id_for_action = ($find_class_row = $find_class_result->fetch_assoc()) ? $find_class_row['Class_ID'] : 0;

                                echo "<tr class='clickable-row' style='cursor: pointer;' onclick='editEnrollment(" .
                                      $class_id_for_action . ", " . $row['Student_ID'] . ", \"" . $studentName . "\")'>";
                                echo "<td><i class='fas fa-user'></i> " . $studentName . "</td>";
                                echo "<td><span class='grade-badge grade-level-{$gradeLevel}'><i class='fas fa-graduation-cap'></i> Grade {$gradeLevel}</span></td>";
                                echo "<td><i class='fas fa-door-open'></i> Room " . htmlspecialchars($row['Room']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['Enrollment_Date']) . "</td>";
                                echo "<td class='action-buttons'>";
                                echo "<button class='edit-btn' onclick='event.stopPropagation(); editEnrollment(" .
                                      $class_id_for_action . ", " . $row['Student_ID'] . ", \"" . $studentName . "\")'><i class='fas fa-edit'></i> Edit</button>";
                                echo "<button class='delete-btn' onclick='event.stopPropagation(); unenrollStudent(" .
                                      $class_id_for_action . ", " . $row['Student_ID'] . ", \"" . $studentName . "\")'><i class='fas fa-user-minus'></i> Unenroll</button>";
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
        // Removed unused filterClassList and term event listeners. Grade level filtering is handled below.

        // Student search autosuggest
        const studentSearch = document.getElementById('studentSearch');
        const studentSelect = document.getElementById('student_id');
        const suggestionBox = document.getElementById('studentSuggestions');

        studentSearch.addEventListener('input', function() {
            var filter = this.value.toLowerCase();
            suggestionBox.innerHTML = '';
            if (!filter) {
                Array.from(studentSelect.options).forEach(function(option) {
                    option.hidden = false;
                });
                return;
            }
            var matches = [];
            Array.from(studentSelect.options).forEach(function(option) {
                if (!option.value) return;
                var text = option.textContent.toLowerCase();
                option.hidden = (text.indexOf(filter) === -1);
                if (text.indexOf(filter) !== -1) {
                    matches.push({value: option.value, label: option.textContent});
                }
            });
            if (matches.length > 0) {
                var list = document.createElement('ul');
                list.style.position = 'absolute';
                list.style.background = '#fff';
                list.style.border = '1px solid #ccc';
                list.style.width = studentSearch.offsetWidth + 'px';
                list.style.listStyle = 'none';
                list.style.margin = 0;
                list.style.padding = '2px 0';
                list.style.maxHeight = '180px';
                list.style.overflowY = 'auto';
                matches.forEach(function(match) {
                    var item = document.createElement('li');
                    item.textContent = match.label;
                    item.style.padding = '4px 8px';
                    item.style.cursor = 'pointer';
                    item.addEventListener('mousedown', function(e) {
                        studentSelect.value = match.value;
                        studentSearch.value = match.label;
                        suggestionBox.innerHTML = '';
                        // Optionally trigger change event
                        studentSelect.dispatchEvent(new Event('change'));
                    });
                    list.appendChild(item);
                });
                suggestionBox.appendChild(list);
            }
        });
        // Hide suggestions on blur
        studentSearch.addEventListener('blur', function() {
            setTimeout(function() { suggestionBox.innerHTML = ''; }, 150);
        });
        function resetForm() {
            document.getElementById('form-title').innerHTML = '<i class="fas fa-user-plus"></i> Enroll Student in Class';
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-user-plus"></i> Enroll Student';
            document.getElementById('cancel-btn').style.display = 'none';
            document.getElementById('operation').value = 'enroll';
            document.getElementById('enrollment-form').reset();
            document.getElementById('student_id').disabled = false;
            document.getElementById('original_class_id').value = '';
            document.getElementById('unenroll-btn').style.display = 'none';
            document.getElementById('unenroll-btn').onclick = null;

            // Remove the hidden student input if it exists
            const hiddenStudentInput = document.getElementById('hidden_student_id');
            if (hiddenStudentInput) {
                hiddenStudentInput.remove();
            }
            // Reset class filter
            // The grade level filter is handled by the IIFE below.
        }

        function editEnrollment(classId, studentId, studentName) {
            document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> Update Enrollment for ' + studentName;
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Enrollment';
            document.getElementById('cancel-btn').style.display = 'inline-block';
            document.getElementById('unenroll-btn').style.display = 'inline-block';
            document.getElementById('unenroll-btn').onclick = function() { unenrollStudent(classId, studentId, studentName); };
            document.getElementById('operation').value = 'update';
            
            // Set form values
            document.getElementById('class_id').value = classId;
            document.getElementById('original_class_id').value = classId;
            
            // Handle student dropdown
            const studentSelect = document.getElementById('student_id');
            let studentOption = studentSelect.querySelector("option[value='" + studentId + "']");
            
            if (!studentOption) {
                studentOption = document.createElement('option');
                studentOption.value = studentId;
                studentOption.textContent = studentName + " (Currently Enrolled)";
                studentSelect.appendChild(studentOption);
            }
            
            studentSelect.value = studentId;
            studentSelect.disabled = true; // Prevent changing the student during an update

            // Add a hidden input to carry the student_id value since disabled inputs are not submitted
            let hiddenStudentInput = document.getElementById('hidden_student_id');
            if (!hiddenStudentInput) {
                hiddenStudentInput = document.createElement('input');
                hiddenStudentInput.type = 'hidden';
                hiddenStudentInput.id = 'hidden_student_id';
                hiddenStudentInput.name = 'student_id';
                document.getElementById('enrollment-form').appendChild(hiddenStudentInput);
            }
            hiddenStudentInput.value = studentId;

            window.scrollTo({ top: document.getElementById('enrollment-form').offsetTop, behavior: 'smooth' });
        }
        
        function unenrollStudent(classId, studentId, studentName) {
            if(confirm('Are you sure you want to unenroll ' + studentName + ' from this class?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'adminEnrollment.php?year=<?php echo urlencode($selected_year); ?>';
                
                const operationInput = document.createElement('input');
                operationInput.type = 'hidden';
                operationInput.name = 'operation';
                operationInput.value = 'unenroll';
                form.appendChild(operationInput);
                
                const classInput = document.createElement('input');
                classInput.type = 'hidden';
                classInput.name = 'class_id';
                classInput.value = classId;
                form.appendChild(classInput);

                const studentInput = document.createElement('input');
                studentInput.type = 'hidden';
                studentInput.name = 'student_id';
                studentInput.value = studentId;
                form.appendChild(studentInput);
                
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

        // Dynamically filter class dropdown based on selected grade level
(function() {
    var gradeLevelSelect = document.getElementById('grade_level');
    var classSelect = document.getElementById('class_id');
    function updateClassDropdown() {
        var selectedGrade = gradeLevelSelect.value;
        // Loop through optgroups and options
        Array.from(classSelect.getElementsByTagName('optgroup')).forEach(function(group) {
            var gradeMatch = group.label.match(/Grade (\d+)/);
            var groupGrade = gradeMatch ? gradeMatch[1] : '';
            if (!selectedGrade || groupGrade === selectedGrade) {
                group.style.display = '';
                Array.from(group.children).forEach(function(option) {
                    option.style.display = '';
                });
            } else {
                group.style.display = 'none';
                Array.from(group.children).forEach(function(option) {
                    option.style.display = 'none';
                });
            }
        });
        // Reset selection if current selected class is hidden
        if (classSelect.selectedIndex > 0 && classSelect.options[classSelect.selectedIndex].style.display === 'none') {
            classSelect.selectedIndex = 0;
        }
    }
    gradeLevelSelect.addEventListener('change', updateClassDropdown);
    // Initial call to set correct state on page load
    updateClassDropdown();
})();
    </script>
</body>
</html>