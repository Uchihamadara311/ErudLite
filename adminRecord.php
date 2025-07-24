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

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] == 'get_students' && isset($_GET['class_id'])) {
        // AJAX handler: Get students enrolled in classes of the same room/grade combination
        // Uses Enrollment and Class tables to find students for the given room/grade
        // SQL: Selects students who are enrolled in classes with the same room/grade, joining with Profile, Profile_Bio, and Classroom for names and room info
        try {
            $class_id = (int)$_GET['class_id'];
            
            // First get the room_id and grade_level for this class
            $room_sql = "SELECT c.Room_ID, cl.Grade_Level, cl.School_Year FROM Class c JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID WHERE c.Class_ID = ?";
            $stmt = $conn->prepare($room_sql);
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $room_result = $stmt->get_result();
            $room_info = $room_result->fetch_assoc();
            
            if ($room_info) {
                // Get all students enrolled in classes with the same room/grade/year
                $sql = "SELECT DISTINCT s.Student_ID, pb.Given_Name, pb.Last_Name, cr.Room
                        FROM Student s
                        JOIN Profile p ON s.Profile_ID = p.Profile_ID
                        JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                        JOIN Enrollment e ON s.Student_ID = e.Student_ID
                        JOIN Class c ON e.Class_ID = c.Class_ID
                        JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                        JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                        WHERE c.Room_ID = ? AND cl.Grade_Level = ? AND cl.School_Year = ? AND e.Status = 'Active'
                        ORDER BY pb.Last_Name, pb.Given_Name";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iis", $room_info['Room_ID'], $room_info['Grade_Level'], $room_info['School_Year']);
                $stmt->execute();
                $result = $stmt->get_result();
                $students = [];
                while($row = $result->fetch_assoc()) {
                    $students[] = $row;
                }
                echo json_encode(['success' => true, 'students' => $students]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Class not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_GET['action'] == 'get_subjects' && isset($_GET['class_id'])) {
        // AJAX handler: Get subjects assigned to classes in the same room/grade combination
        // Uses Schedule table to find subjects for all classes in the same room/grade
        // SQL: Selects subjects assigned to classes via Schedule, joining with Subject for names
        try {
            $class_id = (int)$_GET['class_id'];
            
            // First get the room_id and grade_level for this class
            $room_sql = "SELECT c.Room_ID, cl.Grade_Level, cl.School_Year FROM Class c JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID WHERE c.Class_ID = ?";
            $stmt = $conn->prepare($room_sql);
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $room_result = $stmt->get_result();
            $room_info = $room_result->fetch_assoc();
            
            if ($room_info) {
                // Get all subjects for classes in the same room/grade/year
                $sql = "SELECT DISTINCT s.Subject_ID, s.Subject_Name
                        FROM Class c
                        JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                        JOIN Schedule sch ON c.Class_ID = sch.Class_ID
                        JOIN Subject s ON sch.Subject_ID = s.Subject_ID
                        WHERE c.Room_ID = ? AND cl.Grade_Level = ? AND cl.School_Year = ?
                        ORDER BY s.Subject_Name";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iis", $room_info['Room_ID'], $room_info['Grade_Level'], $room_info['School_Year']);
                $stmt->execute();
                $result = $stmt->get_result();
                $subjects = [];
                while($row = $result->fetch_assoc()) {
                    $subjects[] = $row;
                }
                echo json_encode(['success' => true, 'subjects' => $subjects]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Class not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_GET['action'] == 'get_grades' && isset($_GET['class_id'])) {
        // AJAX handler: Get grades for all students in classes of the same room/grade combination
        // SQL: Selects grade records for students in the class room/grade, joining with student, subject, clearance, and profile tables for details
        $class_id = (int)$_GET['class_id'];
        
        // First get the room_id and grade_level for this class
        $room_sql = "SELECT c.Room_ID, cl.Grade_Level, cl.School_Year FROM Class c JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID WHERE c.Class_ID = ?";
        $stmt = $conn->prepare($room_sql);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $room_result = $stmt->get_result();
        $room_info = $room_result->fetch_assoc();
        
        if ($room_info) {
            // Get grades for all students in classes with the same room/grade/year
            $sql = "SELECT rd.Record_ID as Grade_ID, rd.Grade as Grade_Value, 
                           r.Student_ID, pb.Given_Name, pb.Last_Name, s.Subject_Name, cl.Term as Quarter, rd.Record_Date as Date_Recorded,
                           rd.Grade as Grade_Value
                    FROM Record r
                    JOIN Record_Details rd ON r.Record_ID = rd.Record_ID
                    JOIN Student st ON r.Student_ID = st.Student_ID
                    JOIN Profile p ON st.Profile_ID = p.Profile_ID
                    JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                    JOIN Subject s ON r.Subject_ID = s.Subject_ID
                    JOIN Clearance cl ON rd.Clearance_ID = cl.Clearance_ID
                    WHERE r.Subject_ID = s.Subject_ID AND cl.Clearance_ID = rd.Clearance_ID AND r.Student_ID = st.Student_ID
                    AND cl.Term IS NOT NULL 
                    AND cl.Clearance_ID IN (
                        SELECT c2.Clearance_ID FROM Class c2 
                        JOIN Clearance cl2 ON c2.Clearance_ID = cl2.Clearance_ID 
                        WHERE c2.Room_ID = ? AND cl2.Grade_Level = ? AND cl2.School_Year = ?
                    )
                    AND st.Student_ID IN (
                        SELECT DISTINCT e.Student_ID FROM Enrollment e 
                        JOIN Class c3 ON e.Class_ID = c3.Class_ID 
                        JOIN Clearance cl3 ON c3.Clearance_ID = cl3.Clearance_ID
                        WHERE c3.Room_ID = ? AND cl3.Grade_Level = ? AND cl3.School_Year = ?
                    )
                    ORDER BY pb.Last_Name, pb.Given_Name, s.Subject_Name, cl.Term";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisiis", $room_info['Room_ID'], $room_info['Grade_Level'], $room_info['School_Year'], 
                            $room_info['Room_ID'], $room_info['Grade_Level'], $room_info['School_Year']);
            $stmt->execute();
            $result = $stmt->get_result();
            $grades = [];
            while($row = $result->fetch_assoc()) {
                $row['Grade_Letter'] = calculateLetterGrade($row['Grade_Value']); // Add letter grade for display
                $grades[] = $row;
            }
            echo json_encode($grades);
        } else {
            echo json_encode([]);
        }
        exit;
    }
    
    if ($_GET['action'] == 'search_student' && isset($_GET['query'])) {
        // AJAX handler: Search for students by name in the selected academic year
        // SQL: Selects students whose first or last name matches the query, and who are enrolled in the selected year
        $query = '%' . $_GET['query'] . '%';
        $selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y') . '-' . (date('Y') + 1);
        $sql = "SELECT DISTINCT s.Student_ID, pb.Given_Name, pb.Last_Name,
                       cl.Grade_Level, cr.Section, cr.Room
                FROM Student s
                JOIN Profile p ON s.Profile_ID = p.Profile_ID
                JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                JOIN Enrollment e ON s.Student_ID = e.Student_ID
                JOIN Class c ON e.Class_ID = c.Class_ID
                JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                WHERE (pb.Given_Name LIKE ? OR pb.Last_Name LIKE ?)
                AND cl.School_Year = ?
                ORDER BY pb.Last_Name, pb.Given_Name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $query, $query, $selected_year);
        $stmt->execute();
        $result = $stmt->get_result();
        $html = '<div class="search-results">';
        if ($result->num_rows > 0) {
            while($student = $result->fetch_assoc()) {
                $html .= '<div class="student-result" onclick="loadStudentGrades(' . $student['Student_ID'] . ')">';
                $html .= '<h4><i class="fas fa-user"></i> ' . htmlspecialchars($student['Given_Name'] . ' ' . $student['Last_Name']) . '</h4>';
                $html .= '<p><i class="fas fa-graduation-cap"></i> Grade ' . $student['Grade_Level'] . ' - ' . htmlspecialchars($student['Section']) . ' (Room ' . htmlspecialchars($student['Room']) . ')</p>';
                $html .= '</div>';
            }
        } else {
            $html .= '<p>No students found matching your search.</p>';
        }
        $html .= '</div>';
        echo json_encode(['html' => $html]);
        exit;
    }
    
    if ($_GET['action'] == 'get_student_grades' && isset($_GET['student_id'])) {
        // AJAX handler: Get all grades for a specific student
        // SQL: Selects all grade records for the student, joining with subject, clearance, and profile tables for details
        $student_id = (int)$_GET['student_id'];
        $sql = "SELECT rd.Record_ID as Grade_ID, rd.Grade as Grade_Value, 
                       r.Student_ID, pb.Given_Name, pb.Last_Name, s.Subject_Name, cl.Term as Quarter, rd.Record_Date as Date_Recorded,
                       rd.Grade as Grade_Value
                FROM Record r
                JOIN Record_Details rd ON r.Record_ID = rd.Record_ID
                JOIN Student st ON r.Student_ID = st.Student_ID
                JOIN Profile p ON st.Profile_ID = p.Profile_ID
                JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                JOIN Subject s ON r.Subject_ID = s.Subject_ID
                JOIN Clearance cl ON rd.Clearance_ID = cl.Clearance_ID
                WHERE r.Student_ID = ?
                ORDER BY s.Subject_Name, cl.Term";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $grades = [];
        while($row = $result->fetch_assoc()) {
            $row['Grade_Letter'] = calculateLetterGrade($row['Grade_Value']); // Add letter grade for display
            $grades[] = $row;
        }
        echo json_encode($grades);
        exit;
    }
}

// Function to calculate letter grade
function calculateLetterGrade($grade_value) {
    if ($grade_value >= 97) return 'A+';
    elseif ($grade_value >= 93) return 'A';
    elseif ($grade_value >= 90) return 'A-';
    elseif ($grade_value >= 87) return 'B+';
    elseif ($grade_value >= 83) return 'B';
    elseif ($grade_value >= 80) return 'B-';
    elseif ($grade_value >= 77) return 'C+';
    elseif ($grade_value >= 73) return 'C';
    elseif ($grade_value >= 70) return 'C-';
    elseif ($grade_value >= 67) return 'D+';
    elseif ($grade_value >= 65) return 'D';
    else return 'F';
}

// Handle form submission using Post-Redirect-Get pattern
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = isset($_POST['operation']) ? $_POST['operation'] : 'add_grade';
    $redirect_year = isset($_POST['year']) ? $_POST['year'] : date('Y') . '-' . (date('Y') + 1);

    if ($operation == 'add_grade') {
        // Add a new grade record for a student
        $student_id = (int)$_POST['student_id'];
        $subject_id = (int)$_POST['subject_id'];
        $class_id = (int)$_POST['class_id'];
        $semester = cleanInput($_POST['semester']); // Renamed for consistency
        $grade_value = (float)$_POST['grade_value'];
        $grade_letter = calculateLetterGrade($grade_value);

        // Get Clearance_ID for the selected semester/term
        // Since we're using distinct classes, we need to find the clearance based on room/grade/year and term
        // First get the room_id, grade_level, and school_year for the selected class
        $room_info_sql = "SELECT c.Room_ID, cl.Grade_Level, cl.School_Year FROM Class c JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID WHERE c.Class_ID = ?";
        $stmt = $conn->prepare($room_info_sql);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $room_result = $stmt->get_result();
        $room_info = $room_result->fetch_assoc();
        
        $clearance_id = null;
        if ($room_info) {
            // Now find the clearance for the same room/grade/year but with the selected term
            $clearance_sql = "SELECT cl.Clearance_ID FROM Clearance cl 
                             JOIN Class c ON cl.Clearance_ID = c.Clearance_ID 
                             WHERE c.Room_ID = ? AND cl.Grade_Level = ? AND cl.School_Year = ? AND cl.Term = ? 
                             LIMIT 1";
            $stmt = $conn->prepare($clearance_sql);
            $stmt->bind_param("iiss", $room_info['Room_ID'], $room_info['Grade_Level'], $room_info['School_Year'], $semester);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $clearance_id = $row['Clearance_ID'];
            } else {
                $_SESSION['error_message'] = "No clearance found for this class and term: " . $semester . " (Room: " . $room_info['Room_ID'] . ", Grade: " . $room_info['Grade_Level'] . ", Year: " . $room_info['School_Year'] . ")";
            }
        } else {
            $_SESSION['error_message'] = "Class information not found.";
        }

        // Get instructor ID for the selected subject in classes of the same room/grade combination
        $instructor_id = 0;
        if ($room_info && $clearance_id) {
            $instructor_sql = "SELECT DISTINCT sch.Instructor_ID FROM Schedule sch 
                              JOIN Class c ON sch.Class_ID = c.Class_ID 
                              JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID 
                              WHERE c.Room_ID = ? AND cl.Grade_Level = ? AND cl.School_Year = ? AND sch.Subject_ID = ? 
                              LIMIT 1";
            $stmt = $conn->prepare($instructor_sql);
            $stmt->bind_param("iisi", $room_info['Room_ID'], $room_info['Grade_Level'], $room_info['School_Year'], $subject_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $instructor_id = (int)$row['Instructor_ID'];
            }
        }
        if ($instructor_id === 0) {
            $_SESSION['error_message'] = "Instructor not found for this class and subject.";
        } elseif ($clearance_id) {
            // Check if record already exists for this student, subject, instructor, and clearance
            // SQL: Prevents duplicate grade entries for the same student, subject, instructor, and quarter
            $check_sql = "SELECT rd.Record_ID FROM Record r JOIN Record_Details rd ON r.Record_ID = rd.Record_ID WHERE r.Student_ID = ? AND r.Subject_ID = ? AND r.Instructor_ID = ? AND rd.Clearance_ID = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("iiii", $student_id, $subject_id, $instructor_id, $clearance_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $_SESSION['error_message'] = "Grade already exists for this student, subject, and quarter.";
            } else {
            // Insert into Record
            // SQL: Create a new record for the grade
            $sql = "INSERT INTO Record (Student_ID, Instructor_ID, Subject_ID) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $student_id, $instructor_id, $subject_id);
            if ($stmt->execute()) {
                $record_id = $stmt->insert_id;
                // Insert into Record_Details
                // SQL: Add the grade details for the record
                $sql = "INSERT INTO Record_Details (Record_ID, Clearance_ID, Grade, Record_Date) VALUES (?, ?, ?, CURDATE())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $record_id, $clearance_id, $grade_value);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Grade recorded successfully!";
                } else {
                    $_SESSION['error_message'] = "Error recording grade details: " . $stmt->error;
                }
            } else {
                $_SESSION['error_message'] = "Error recording grade: " . $stmt->error;
            }
            }
        }
    } elseif ($operation == 'update_grade') {
        // Update an existing grade record
        $record_id = (int)$_POST['grade_id'];
        $grade_value = (float)$_POST['grade_value'];
        // SQL: Update the grade value and date for the record
        $sql = "UPDATE Record_Details SET Grade = ?, Record_Date = CURDATE() WHERE Record_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $grade_value, $record_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Grade updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating grade: " . $stmt->error;
        }
    } elseif ($operation == 'delete_grade') {
        // Delete a grade record
        $record_id = (int)$_POST['grade_id'];
        // SQL: Delete the grade details first, then the main record
        $sql = "DELETE FROM Record_Details WHERE Record_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $record_id);
        if ($stmt->execute()) {
            // Then delete from Record
            $sql = "DELETE FROM Record WHERE Record_ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $record_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Grade deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Error deleting grade record: " . $stmt->error;
            }
        } else {
            $_SESSION['error_message'] = "Error deleting grade details: " . $stmt->error;
        }
    }
    
    // Redirect to prevent form resubmission, preserving form state
    $redirect_params = [
        'year' => $redirect_year
    ];
    
    // Preserve the selected class if it was submitted
    if (isset($_POST['class_id']) && !empty($_POST['class_id'])) {
        $redirect_params['class_id'] = (int)$_POST['class_id'];
    }
    
    // Preserve the student selection (use preserve value if available, otherwise current selection)
    if (isset($_POST['preserve_student_id']) && !empty($_POST['preserve_student_id'])) {
        $redirect_params['student_id'] = (int)$_POST['preserve_student_id'];
    } elseif (isset($_POST['student_id']) && !empty($_POST['student_id'])) {
        $redirect_params['student_id'] = (int)$_POST['student_id'];
    }
    
    // Preserve the subject selection (use preserve value if available, otherwise current selection)
    if (isset($_POST['preserve_subject_id']) && !empty($_POST['preserve_subject_id'])) {
        $redirect_params['subject_id'] = (int)$_POST['preserve_subject_id'];
    } elseif (isset($_POST['subject_id']) && !empty($_POST['subject_id'])) {
        $redirect_params['subject_id'] = (int)$_POST['subject_id'];
    }
    
    // Preserve the semester selection (use preserve value if available, otherwise current selection)
    if (isset($_POST['preserve_semester']) && !empty($_POST['preserve_semester'])) {
        $redirect_params['semester'] = $_POST['preserve_semester'];
    } elseif (isset($_POST['semester']) && !empty($_POST['semester'])) {
        $redirect_params['semester'] = $_POST['semester'];
    }
    
    $redirect_url = "adminRecord.php?" . http_build_query($redirect_params);
    header("Location: " . $redirect_url);
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

// Get classes for filtering
$classes_sql = "SELECT c.Class_ID, cl.Grade_Level, cl.School_Year, cr.Room, cr.Section
                FROM Class c
                JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                WHERE cl.School_Year = ?
                ORDER BY cl.Grade_Level, cr.Room";
$stmt = $conn->prepare($classes_sql);
$stmt->bind_param("s", $selected_year);
$stmt->execute();
$classes_result = $stmt->get_result();
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records & Grading - ErudLite</title>
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
        <h1 class="page-title">Student Records & Grading System</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Academic Year Filter -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-filter"></i> Filter by Academic Year</h2>
            <form method="GET" action="adminRecord.php" style="padding: 20px;">
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
        
        <!-- Class Selection -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-school"></i> Select Class to Manage Grades</h2>
            <form id="class-filter-form" method="GET" style="padding: 20px;">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="filter_grade"><i class="fas fa-layer-group"></i> Grade Level</label>
                        <select class="form-select" name="filter_grade" id="filter_grade" onchange="updateClassList()">
                            <option value="">All Grades</option>
                            <?php for($g=1;$g<=6;$g++): ?>
                                <option value="<?php echo $g; ?>" <?php if(isset($_GET['filter_grade']) && $_GET['filter_grade']==$g) echo 'selected'; ?>>Grade <?php echo $g; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label" for="class_select"><i class="fas fa-graduation-cap"></i> Class</label>
                        <select class="form-select" name="class_select" id="class_select" onchange="if(this.value) loadClassStudents(this.value, null)">
                            <option value="">Select a Class</option>
                            <?php 
                            // Get distinct classes for the selected academic year
                            $filter_grade = isset($_GET['filter_grade']) ? $_GET['filter_grade'] : '';
                            $class_query = "SELECT DISTINCT cr.Room_ID, cl.Grade_Level, cr.Room, cr.Section FROM Class c JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID JOIN Classroom cr ON c.Room_ID = cr.Room_ID WHERE cl.School_Year = ? AND c.Class_ID IN (SELECT DISTINCT Class_ID FROM Enrollment)";
                            $params = [$selected_year];
                            $types = "s";
                            if($filter_grade) { $class_query .= " AND cl.Grade_Level = ?"; $params[] = $filter_grade; $types .= "i"; }
                            $class_query .= " ORDER BY cl.Grade_Level, cr.Room";
                            $stmt = $conn->prepare($class_query);
                            $stmt->bind_param($types, ...$params);
                            $stmt->execute();
                            $classes_result = $stmt->get_result();
                            
                            // Group classes by room to avoid duplicates
                            $unique_classes = [];
                            while($class = $classes_result->fetch_assoc()) {
                                $key = $class['Grade_Level'] . '_' . $class['Room_ID'];
                                if (!isset($unique_classes[$key])) {
                                    $unique_classes[$key] = $class;
                                }
                            }
                            
                            foreach($unique_classes as $class): 
                                $gradeClass = 'grade-level-' . (int)$class['Grade_Level'];
                                // Get the first Class_ID for this room and grade
                                $classid_query = "SELECT c.Class_ID FROM Class c JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID WHERE cl.School_Year = ? AND cl.Grade_Level = ? AND c.Room_ID = ? LIMIT 1";
                                $stmt2 = $conn->prepare($classid_query);
                                $stmt2->bind_param("sii", $selected_year, $class['Grade_Level'], $class['Room_ID']);
                                $stmt2->execute();
                                $classid_result = $stmt2->get_result();
                                $classid_row = $classid_result->fetch_assoc();
                                $class_id = $classid_row ? $classid_row['Class_ID'] : '';
                            ?>
                                <option value="<?php echo $class_id; ?>" class="<?php echo $gradeClass; ?>">
                                    Grade <?php echo $class['Grade_Level']; ?> - <?php echo htmlspecialchars($class['Section']); ?> (Room <?php echo htmlspecialchars($class['Room']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
            <script>
            function updateClassList() {
                const grade = document.getElementById('filter_grade').value;
                const params = new URLSearchParams(window.location.search);
                params.set('filter_grade', grade);
                window.location.search = params.toString();
            }
            </script>
        </section>
        
        <!-- Grade Entry Form -->
        <section class="form-section" id="grade-form-section" style="display: none;">
            <h2 class="form-title" id="grade-form-title"><i class="fas fa-edit"></i> Record Grade</h2>
            <form method="POST" action="adminRecord.php?year=<?php echo urlencode($selected_year); ?>" id="grade-form">
                <input type="hidden" id="operation" name="operation" value="add_grade">
                <input type="hidden" name="year" value="<?php echo htmlspecialchars($selected_year); ?>">
                <input type="hidden" id="grade_id" name="grade_id" value="">
                <input type="hidden" id="class_id" name="class_id" value="">
                <!-- Hidden inputs to preserve form state after submission -->
                <input type="hidden" id="preserve_student_id" name="preserve_student_id" value="">
                <input type="hidden" id="preserve_subject_id" name="preserve_subject_id" value="">
                <input type="hidden" id="preserve_semester" name="preserve_semester" value="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="student_id"><i class="fas fa-user"></i> Student *</label>
                        <select class="form-select" name="student_id" id="student_id" required>
                            <option value="">Select a Student</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="subject_id"><i class="fas fa-book"></i> Subject *</label>
                        <select class="form-select" name="subject_id" id="subject_id" required>
                            <option value="">Select a Subject</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="semester"><i class="fas fa-calendar"></i> Semester *</label>
                        <select class="form-select" name="semester" id="semester" required>
                            <option value="">Select Semester</option>
                            <option value="1st Semester">1st Semester</option>
                            <option value="2nd Semester">2nd Semester</option>
                            <option value="3rd Semester">3rd Semester</option>
                            <option value="4th Semester">4th Semester</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="grade_value"><i class="fas fa-star"></i> Grade (0-100) *</label>
                        <input type="number" class="form-select" name="grade_value" id="grade_value" min="0" max="100" step="0.01" required>
                    </div>
                </div>
                
                <div class="button-group" id="grade-form-buttons">
                    <button type="submit" class="submit-btn" id="submit-btn"><i class="fas fa-save"></i> Record Grade</button>
                    <button type="button" class="cancel-btn" id="cancel-btn" onclick="resetGradeForm()"><i class="fas fa-refresh"></i> Cancel</button>
                    <button type="button" class="delete-btn" id="delete-btn" style="display:none;" onclick="deleteGradeFromForm()"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </form>
        </section>
        
        <!-- Student Search -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-search"></i> Search Student Records</h2>
            <div style="padding: 20px;">
                <div class="form-group">
                    <label class="form-label" for="student_search"><i class="fas fa-user"></i> Student Name</label>
                    <input type="text" class="form-select" id="student_search" placeholder="Type student name to search..." onkeyup="searchStudentRecords()">
                </div>
                <div id="search_results"></div>
            </div>
        </section>
        
        <!-- Current Grades Table -->
        <section class="table-section" id="grades-table-section" style="display: none;">
            <div class="section-header">
                <div class="header-icon-title">
                    <i class="fas fa-chart-line"></i>
                    <h2 id="grades-table-title">Student Grades</h2>
                </div>
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchBar" class="search-input" placeholder="Search grades...">
                </div>
            </div>
            <div class="table-container">
                <table class="data-table" id="grades-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Student</th>
                            <th><i class="fas fa-book"></i> Subject</th>
                            <th><i class="fas fa-calendar"></i> Quarter</th>
                            <th><i class="fas fa-star"></i> Grade</th>
                            <th><i class="fas fa-award"></i> Letter</th>
                            <th><i class="fas fa-calendar-alt"></i> Date</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grades-tbody">
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script>
        function loadClassStudents(classId, callback) {
            // Reset form first
            resetGradeForm();
            document.getElementById('class_id').value = classId;
            
            let loadedCount = 0;
            const totalLoads = 3; // students, subjects, grades
            
            function checkComplete() {
                loadedCount++;
                if (loadedCount >= totalLoads && callback) {
                    callback();
                }
            }
            
            // Load students for this class
            fetch('adminRecord.php?action=get_students&class_id=' + classId)
                .then(response => response.json())
                .then(data => {
                    const studentSelect = document.getElementById('student_id');
                    studentSelect.innerHTML = '<option value="">Select a Student</option>';
                    if (data.success) {
                        data.students.forEach(student => {
                            studentSelect.innerHTML += `<option value="${student.Student_ID}">${student.Given_Name} ${student.Last_Name}</option>`;
                        });
                    } else {
                        console.error('Error from server:', data.error);
                        alert('Error loading students: ' + (data.error || 'Unknown error'));
                    }
                    checkComplete();
                })
                .catch(error => {
                    console.error('Error loading students:', error);
                    alert('Error loading students. Please try again.');
                    checkComplete();
                });
                
            // Load subjects for this class
            fetch('adminRecord.php?action=get_subjects&class_id=' + classId)
                .then(response => response.json())
                .then(data => {
                    const subjectSelect = document.getElementById('subject_id');
                    subjectSelect.innerHTML = '<option value="">Select a Subject</option>';
                    if (data.success) {
                        data.subjects.forEach(subject => {
                            subjectSelect.innerHTML += `<option value="${subject.Subject_ID}">${subject.Subject_Name}</option>`;
                        });
                    } else {
                        console.error('Error from server:', data.error);
                        alert('Error loading subjects: ' + (data.error || 'Unknown error'));
                    }
                    checkComplete();
                })
                .catch(error => {
                    console.error('Error loading subjects:', error);
                    alert('Error loading subjects. Please try again.');
                    checkComplete();
                });
                
            // Load existing grades for this class
            loadClassGrades(classId);
            checkComplete();
            
            // Show forms
            document.getElementById('grade-form-section').style.display = 'block';
            document.getElementById('grades-table-section').style.display = 'block';
        }

        // Automatically load students/subjects/grades if a class is pre-selected
        document.addEventListener('DOMContentLoaded', function() {
            var classSelect = document.getElementById('class_select');
            
            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const preselectedClassId = urlParams.get('class_id');
            const preselectedStudentId = urlParams.get('student_id');
            const preselectedSubjectId = urlParams.get('subject_id');
            const preselectedSemester = urlParams.get('semester');
            
            // If there's a preselected class, set it and load its data
            if (preselectedClassId && classSelect) {
                // Find and select the class option
                for (let i = 0; i < classSelect.options.length; i++) {
                    if (classSelect.options[i].value == preselectedClassId) {
                        classSelect.selectedIndex = i;
                        break;
                    }
                }
                
                // Load the class data and then restore other selections
                loadClassStudents(preselectedClassId, function() {
                    // After students and subjects are loaded, restore selections
                    if (preselectedStudentId) {
                        const studentSelect = document.getElementById('student_id');
                        studentSelect.value = preselectedStudentId;
                    }
                    
                    if (preselectedSubjectId) {
                        const subjectSelect = document.getElementById('subject_id');
                        subjectSelect.value = preselectedSubjectId;
                    }
                    
                    if (preselectedSemester) {
                        const semesterSelect = document.getElementById('semester');
                        semesterSelect.value = preselectedSemester;
                    }
                });
            } else if (classSelect && classSelect.value) {
                // Fallback to existing behavior
                loadClassStudents(classSelect.value);
            }
        });
        
        function loadClassGrades(classId) {
            fetch('adminRecord.php?action=get_grades&class_id=' + classId)
                .then(response => response.json())
                .then(grades => {
                    const tbody = document.getElementById('grades-tbody');
                    tbody.innerHTML = '';
                    if (grades.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="7" class="no-data"><i class="fas fa-info-circle"></i> No grades found for this class.</td></tr>';
                        return;
                    }
                    grades.forEach(grade => {
                        tbody.innerHTML += `
                            <tr class="grade-row" data-grade-id="${grade.Grade_ID}" data-student-id="${grade.Student_ID}" data-subject-name="${grade.Subject_Name}" data-subject-id="${grade.Subject_ID || ''}" data-semester="${grade.Quarter}" data-grade-value="${grade.Grade_Value}">
                                <td><i class="fas fa-user"></i> ${grade.Given_Name} ${grade.Last_Name}</td>
                                <td>${grade.Subject_Name}</td>
                                <td>${grade.Quarter}</td>
                                <td>${grade.Grade_Value}</td>
                                <td><span class="grade-badge">${grade.Grade_Letter}</span></td>
                                <td>${grade.Date_Recorded}</td>
                                <td class="action-buttons"></td>
                            </tr>
                        `;
                    });
                    document.querySelectorAll('.grade-row').forEach(row => {
                        row.addEventListener('click', function(e) {
                            if (e.target.closest('button')) return;
                            document.getElementById('operation').value = 'update_grade';
                            document.getElementById('grade_id').value = this.dataset.gradeId;
                            document.getElementById('student_id').value = this.dataset.studentId;
                            const subjectSelect = document.getElementById('subject_id');
                            for (let i = 0; i < subjectSelect.options.length; i++) {
                                if (subjectSelect.options[i].text === this.dataset.subjectName) {
                                    subjectSelect.selectedIndex = i;
                                    break;
                                }
                            }
                            document.getElementById('semester').value = this.dataset.semester;
                            document.getElementById('grade_value').value = this.dataset.gradeValue;
                            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Grade';
                            document.getElementById('grade-form-title').innerHTML = '<i class="fas fa-edit"></i> Update Grade';
                            document.getElementById('grade-form-section').style.display = 'block';
                            document.getElementById('delete-btn').style.display = 'inline-block';
                            document.getElementById('grade-form-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    });
                })
                .catch(error => {
                    console.error('Error loading grades:', error);
                    const tbody = document.getElementById('grades-tbody');
                    tbody.innerHTML = '<tr><td colspan="7" class="error-message"><i class="fas fa-exclamation-circle"></i> Error loading grades. Please try again.</td></tr>';
                });
        }
        
        function editGrade(gradeId, currentValue) {
            document.getElementById('operation').value = 'update_grade';
            document.getElementById('grade_id').value = gradeId;
            document.getElementById('grade_value').value = currentValue;
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Grade';
            document.getElementById('grade-form-title').innerHTML = '<i class="fas fa-edit"></i> Update Grade';
            document.getElementById('grade-form-section').style.display = 'block';
            document.getElementById('delete-btn').style.display = 'inline-block';
            document.getElementById('grade-form-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        function deleteGrade(gradeId) {
            if(confirm('Are you sure you want to delete this grade?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'adminRecord.php';
                
                const operationInput = document.createElement('input');
                operationInput.type = 'hidden';
                operationInput.name = 'operation';
                operationInput.value = 'delete_grade';
                form.appendChild(operationInput);
                
                const gradeInput = document.createElement('input');
                gradeInput.type = 'hidden';
                gradeInput.name = 'grade_id';
                gradeInput.value = gradeId;
                form.appendChild(gradeInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function hideGradeForm() {
            document.getElementById('grade-form-section').style.display = 'none';
            document.getElementById('grades-table-section').style.display = 'none';
            resetGradeForm();
        }
        
        function resetGradeForm() {
            document.getElementById('operation').value = 'add_grade';
            document.getElementById('grade_id').value = '';
            document.getElementById('grade_value').value = '';
            document.getElementById('student_id').value = '';
            document.getElementById('subject_id').value = '';
            document.getElementById('semester').value = '';
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Record Grade';
            document.getElementById('grade-form-title').innerHTML = '<i class="fas fa-edit"></i> Record Grade';
            document.getElementById('delete-btn').style.display = 'none';
        }
        
        function searchStudentRecords() {
            const query = document.getElementById('student_search').value;
            if(query.length > 2) {
                const year = document.getElementById('year').value;
                fetch('adminRecord.php?action=search_student&query=' + encodeURIComponent(query) + '&year=' + encodeURIComponent(year))
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('search_results').innerHTML = data.html;
                    });
            } else {
                document.getElementById('search_results').innerHTML = '';
            }
        }
        
        function loadStudentGrades(studentId) {
            fetch('adminRecord.php?action=get_student_grades&student_id=' + studentId)
                .then(response => response.json())
                .then(grades => {
                    const tbody = document.getElementById('grades-tbody');
                    tbody.innerHTML = '';
                    if(grades.length > 0) {
                        document.getElementById('grades-table-title').innerHTML = `Grades for ${grades[0].Given_Name} ${grades[0].Last_Name}`;
                        grades.forEach(grade => {
                            tbody.innerHTML += `
                                <tr>
                                    <td><i class="fas fa-user"></i> ${grade.Given_Name} ${grade.Last_Name}</td>
                                    <td>${grade.Subject_Name}</td>
                                    <td>${grade.Quarter}</td>
                                    <td>${grade.Grade_Value}</td>
                                    <td><span class="grade-badge">${grade.Grade_Letter}</span></td>
                                    <td>${grade.Date_Recorded}</td>
                                    <td class="action-buttons"></td>
                                </tr>
                            `;
                        });
                        document.getElementById('grades-table-section').style.display = 'block';
                        document.getElementById('grade-form-section').style.display = 'none';
                    } else {
                        tbody.innerHTML = '<tr><td colspan="7" class="no-data"><i class="fas fa-info-circle"></i> No grades found for this student.</td></tr>';
                        document.getElementById('grades-table-section').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error loading student grades:', error);
                    const tbody = document.getElementById('grades-tbody');
                    tbody.innerHTML = '<tr><td colspan="7" class="error-message"><i class="fas fa-exclamation-circle"></i> Error loading student grades. Please try again.</td></tr>';
                    document.getElementById('grades-table-section').style.display = 'block';
                });
        }
        
        // Search functionality for grades table
        document.getElementById('searchBar').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll("#grades-table tbody tr");
            rows.forEach(function(row) {
                let text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(filter) > -1 ? "" : "none";
            });
        });
        
        // Form validation
        document.getElementById('grade-form').addEventListener('submit', function(e) {
            const studentId = document.getElementById('student_id').value;
            const subjectId = document.getElementById('subject_id').value;
            const semester = document.getElementById('semester').value;
            const gradeValue = document.getElementById('grade_value').value;
            
            if (!studentId || !subjectId || !semester || !gradeValue) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            const grade = parseFloat(gradeValue);
            if (isNaN(grade) || grade < 0 || grade > 100) {
                e.preventDefault();
                alert('Please enter a valid grade between 0 and 100.');
                return false;
            }
            
            // Update preserve fields with current selections before submission
            document.getElementById('preserve_student_id').value = studentId;
            document.getElementById('preserve_subject_id').value = subjectId;
            document.getElementById('preserve_semester').value = semester;
        });
        
        // Update preserve fields when selections change
        document.getElementById('student_id').addEventListener('change', function() {
            document.getElementById('preserve_student_id').value = this.value;
        });
        
        document.getElementById('subject_id').addEventListener('change', function() {
            document.getElementById('preserve_subject_id').value = this.value;
        });
        
        document.getElementById('semester').addEventListener('change', function() {
            document.getElementById('preserve_semester').value = this.value;
        });
        
        function deleteGradeFromForm() {
            const gradeId = document.getElementById('grade_id').value;
            if (!gradeId) return;
            if(confirm('Are you sure you want to delete this grade?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'adminRecord.php';
                const operationInput = document.createElement('input');
                operationInput.type = 'hidden';
                operationInput.name = 'operation';
                operationInput.value = 'delete_grade';
                form.appendChild(operationInput);
                const gradeInput = document.createElement('input');
                gradeInput.type = 'hidden';
                gradeInput.name = 'grade_id';
                gradeInput.value = gradeId;
                form.appendChild(gradeInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>