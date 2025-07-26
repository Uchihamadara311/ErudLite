<?php 
require_once 'includes/db.php';

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Ensure user is logged in and has instructor permissions
if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Instructor') {
    header("Location: index.php");
    exit();
}

// Get instructor ID from session
$instructor_email = $_SESSION['email'];
$instructor_sql = "SELECT i.Instructor_ID
                    FROM Instructor i 
                    JOIN Profile p ON i.Profile_ID = p.Profile_ID 
                    JOIN Account a ON p.Profile_ID = a.Profile_ID
                    JOIN Role r ON a.Role_ID = r.Role_ID
                    WHERE r.Email = ?;";
$stmt = $conn->prepare($instructor_sql);
$stmt->bind_param("s", $instructor_email);
$stmt->execute();
$instructor_result = $stmt->get_result();
$instructor_data = $instructor_result->fetch_assoc();

if (!$instructor_data) {
    $_SESSION['error_message'] = "Instructor profile not found.";
    header("Location: index.php");
    exit();
}

$instructor_id = $instructor_data['Instructor_ID'];

// Initialize messages
$success_message = '';
$error_message = '';

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] == 'get_students' && isset($_GET['class_id'])) {
        // Get students for instructor's classes only
        try {
            $class_id = (int)$_GET['class_id'];
            
            // Verify this class belongs to the instructor
            $verify_sql = "SELECT c.Class_ID FROM Class c 
                          JOIN Schedule s ON c.Class_ID = s.Class_ID 
                          WHERE s.Instructor_ID = ? AND c.Class_ID = ?";
            $stmt = $conn->prepare($verify_sql);
            $stmt->bind_param("ii", $instructor_id, $class_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                echo json_encode(['success' => false, 'error' => 'Unauthorized access to this class']);
                exit;
            }
            
            $sql = "SELECT DISTINCT s.Student_ID, pb.Given_Name, pb.Last_Name, cr.Room
                    FROM Student s
                    JOIN Profile p ON s.Profile_ID = p.Profile_ID
                    JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                    JOIN Enrollment e ON s.Student_ID = e.Student_ID
                    JOIN Class c ON e.Class_ID = c.Class_ID
                    JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                    WHERE c.Class_ID = ? AND e.Status = 'Active'
                    ORDER BY pb.Last_Name, pb.Given_Name";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $students = [];
            while($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
            echo json_encode(['success' => true, 'students' => $students]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_GET['action'] == 'get_subjects' && isset($_GET['class_id'])) {
        // Get subjects assigned to this instructor for this class
        try {
            $class_id = (int)$_GET['class_id'];
            
            $sql = "SELECT DISTINCT s.Subject_ID, s.Subject_Name
                    FROM Subject s
                    JOIN Schedule sch ON s.Subject_ID = sch.Subject_ID
                    WHERE sch.Instructor_ID = ? AND sch.Class_ID = ?
                    ORDER BY s.Subject_Name";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $instructor_id, $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $subjects = [];
            while($row = $result->fetch_assoc()) {
                $subjects[] = $row;
            }
            echo json_encode(['success' => true, 'subjects' => $subjects]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_GET['action'] == 'get_grades' && isset($_GET['class_id'])) {
        // Get grades for students in instructor's classes only
        $class_id = (int)$_GET['class_id'];
        
        // Verify this class belongs to the instructor
        $verify_sql = "SELECT c.Class_ID FROM Class c 
                      JOIN Schedule s ON c.Class_ID = s.Class_ID 
                      WHERE s.Instructor_ID = ? AND c.Class_ID = ?";
        $stmt = $conn->prepare($verify_sql);
        $stmt->bind_param("ii", $instructor_id, $class_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode([]);
            exit;
        }
        
        $sql = "SELECT rd.Record_ID as Grade_ID, rd.Grade as Grade_Value, 
                       r.Student_ID, pb.Given_Name, pb.Last_Name, s.Subject_Name, cl.Term as Quarter, rd.Record_Date as Date_Recorded
                FROM Record r
                JOIN Record_Details rd ON r.Record_ID = rd.Record_ID
                JOIN Student st ON r.Student_ID = st.Student_ID
                JOIN Profile p ON st.Profile_ID = p.Profile_ID
                JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                JOIN Subject s ON r.Subject_ID = s.Subject_ID
                JOIN Clearance cl ON rd.Clearance_ID = cl.Clearance_ID
                WHERE r.Instructor_ID = ? AND st.Student_ID IN (
                    SELECT DISTINCT e.Student_ID FROM Enrollment e WHERE e.Class_ID = ?
                )
                ORDER BY pb.Last_Name, pb.Given_Name, s.Subject_Name, cl.Term";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $instructor_id, $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $grades = [];
        while($row = $result->fetch_assoc()) {
            $row['Grade_Letter'] = calculateLetterGrade($row['Grade_Value']);
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

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = isset($_POST['operation']) ? $_POST['operation'] : 'add_grade';
    $redirect_year = isset($_POST['year']) ? $_POST['year'] : date('Y') . '-' . (date('Y') + 1);

    if ($operation == 'add_grade') {
        $student_id = (int)$_POST['student_id'];
        $subject_id = (int)$_POST['subject_id'];
        $class_id = (int)$_POST['class_id'];
        $semester = cleanInput($_POST['semester']);
        $grade_value = (float)$_POST['grade_value'];

        // Verify instructor has access to this subject and class
        $verify_sql = "SELECT s.Schedule_ID FROM Schedule s WHERE s.Instructor_ID = ? AND s.Subject_ID = ? AND s.Class_ID = ?";
        $stmt = $conn->prepare($verify_sql);
        $stmt->bind_param("iii", $instructor_id, $subject_id, $class_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $_SESSION['error_message'] = "You don't have permission to grade this subject for this class.";
        } else {
            // Get clearance ID for the semester
            $clearance_sql = "SELECT cl.Clearance_ID FROM Clearance cl 
                             JOIN Class c ON cl.Clearance_ID = c.Clearance_ID 
                             WHERE c.Class_ID = ? AND cl.Term = ? LIMIT 1";
            $stmt = $conn->prepare($clearance_sql);
            $stmt->bind_param("is", $class_id, $semester);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $clearance_id = $row['Clearance_ID'];
                
                // Check if record already exists
                $check_sql = "SELECT rd.Record_ID FROM Record r JOIN Record_Details rd ON r.Record_ID = rd.Record_ID 
                             WHERE r.Student_ID = ? AND r.Subject_ID = ? AND r.Instructor_ID = ? AND rd.Clearance_ID = ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("iiii", $student_id, $subject_id, $instructor_id, $clearance_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $_SESSION['error_message'] = "Grade already exists for this student, subject, and quarter.";
                } else {
                    // Insert new record
                    $sql = "INSERT INTO Record (Student_ID, Instructor_ID, Subject_ID) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iii", $student_id, $instructor_id, $subject_id);
                    if ($stmt->execute()) {
                        $record_id = $stmt->insert_id;
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
            } else {
                $_SESSION['error_message'] = "No clearance found for this class and term.";
            }
        }
    } elseif ($operation == 'update_grade') {
        $record_id = (int)$_POST['grade_id'];
        $grade_value = (float)$_POST['grade_value'];
        
        // Verify this record belongs to the instructor
        $verify_sql = "SELECT r.Record_ID FROM Record r WHERE r.Record_ID = ? AND r.Instructor_ID = ?";
        $stmt = $conn->prepare($verify_sql);
        $stmt->bind_param("ii", $record_id, $instructor_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $_SESSION['error_message'] = "You don't have permission to update this grade.";
        } else {
            $sql = "UPDATE Record_Details SET Grade = ?, Record_Date = CURDATE() WHERE Record_ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $grade_value, $record_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Grade updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating grade: " . $stmt->error;
            }
        }
    } elseif ($operation == 'delete_grade') {
        $record_id = (int)$_POST['grade_id'];
        
        // Verify this record belongs to the instructor
        $verify_sql = "SELECT r.Record_ID FROM Record r WHERE r.Record_ID = ? AND r.Instructor_ID = ?";
        $stmt = $conn->prepare($verify_sql);
        $stmt->bind_param("ii", $record_id, $instructor_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $_SESSION['error_message'] = "You don't have permission to delete this grade.";
        } else {
            $sql = "DELETE FROM Record_Details WHERE Record_ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $record_id);
            if ($stmt->execute()) {
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
    }
    
    header("Location: instructorRecord.php?year=" . urlencode($redirect_year));
    exit();
}

// Retrieve messages from session
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

// Check if a specific class is pre-selected from the schedule
$preselected_class_id = null;
if (isset($_GET['class_id'])) {
    $preselected_class_id = (int)$_GET['class_id'];
    
    // Verify this class belongs to the instructor
    $verify_sql = "SELECT c.Class_ID FROM Class c 
                  JOIN Schedule s ON c.Class_ID = s.Class_ID 
                  WHERE s.Instructor_ID = ? AND c.Class_ID = ?";
    $stmt = $conn->prepare($verify_sql);
    $stmt->bind_param("ii", $instructor_id, $preselected_class_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $preselected_class_id = null; // Invalid class for this instructor
    }
}

// Get instructor's classes
$classes_sql = "SELECT DISTINCT c.Class_ID, cl.Grade_Level, cl.School_Year, cr.Room, cr.Section
                FROM Class c
                JOIN Schedule s ON c.Class_ID = s.Class_ID
                JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                WHERE s.Instructor_ID = ? AND cl.School_Year = ?
                ORDER BY cl.Grade_Level, cr.Room";
$stmt = $conn->prepare($classes_sql);
$stmt->bind_param("is", $instructor_id, $selected_year);
$stmt->execute();
$classes_result = $stmt->get_result();
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Management - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/adminLinks.css">
    <link rel="stylesheet" href="css/adminManagement.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
        <div class="admin-back-btn-wrap admin-back-btn-upperleft">
            <a href="index.php" class="admin-back-btn"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        <h1 class="page-title">Grade Management System</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Academic Year Filter -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-filter"></i> Filter by Academic Year</h2>
            <form method="GET" action="instructorRecord.php" style="padding: 20px;">
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
            <h2 class="form-title"><i class="fas fa-school"></i> Select Your Class</h2>
            <form id="class-filter-form" method="GET" style="padding: 20px;">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label" for="class_select"><i class="fas fa-graduation-cap"></i> Class</label>
                        <select class="form-select" name="class_select" id="class_select" onchange="if(this.value) loadClassStudents(this.value)">
                            <option value="">Select a Class</option>
                            <?php while($class = $classes_result->fetch_assoc()): ?>
                                <option value="<?php echo $class['Class_ID']; ?>" <?php echo ($preselected_class_id == $class['Class_ID']) ? 'selected' : ''; ?>>
                                    Grade <?php echo $class['Grade_Level']; ?> - <?php echo htmlspecialchars($class['Section']); ?> (Room <?php echo htmlspecialchars($class['Room']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </form>
        </section>
        
        <!-- Grade Entry Form -->
        <section class="form-section" id="grade-form-section" style="display: none;">
            <h2 class="form-title" id="grade-form-title"><i class="fas fa-edit"></i> Record Grade</h2>
            <form method="POST" action="instructorRecord.php?year=<?php echo urlencode($selected_year); ?>" id="grade-form">
                <input type="hidden" id="operation" name="operation" value="add_grade">
                <input type="hidden" name="year" value="<?php echo htmlspecialchars($selected_year); ?>">
                <input type="hidden" id="grade_id" name="grade_id" value="">
                <input type="hidden" id="class_id" name="class_id" value="">
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
        function loadClassStudents(classId) {
            resetGradeForm();
            document.getElementById('class_id').value = classId;
            
            // Load students for this class
            fetch('instructorRecord.php?action=get_students&class_id=' + classId)
                .then(response => response.json())
                .then(data => {
                    const studentSelect = document.getElementById('student_id');
                    studentSelect.innerHTML = '<option value="">Select a Student</option>';
                    if (data.success) {
                        data.students.forEach(student => {
                            studentSelect.innerHTML += `<option value="${student.Student_ID}">${student.Given_Name} ${student.Last_Name}</option>`;
                        });
                    } else {
                        alert('Error loading students: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error loading students:', error);
                    alert('Error loading students. Please try again.');
                });
            
            // Load subjects for this class
            fetch('instructorRecord.php?action=get_subjects&class_id=' + classId)
                .then(response => response.json())
                .then(data => {
                    const subjectSelect = document.getElementById('subject_id');
                    subjectSelect.innerHTML = '<option value="">Select a Subject</option>';
                    if (data.success) {
                        data.subjects.forEach(subject => {
                            subjectSelect.innerHTML += `<option value="${subject.Subject_ID}">${subject.Subject_Name}</option>`;
                        });
                    } else {
                        alert('Error loading subjects: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error loading subjects:', error);
                    alert('Error loading subjects. Please try again.');
                });
            
            // Load existing grades
            loadClassGrades(classId);
            
            // Show forms
            document.getElementById('grade-form-section').style.display = 'block';
            document.getElementById('grades-table-section').style.display = 'block';
        }
        
        function loadClassGrades(classId) {
            fetch('instructorRecord.php?action=get_grades&class_id=' + classId)
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
                            <tr class="grade-row" data-grade-id="${grade.Grade_ID}" data-student-id="${grade.Student_ID}" data-subject-name="${grade.Subject_Name}" data-semester="${grade.Quarter}" data-grade-value="${grade.Grade_Value}">
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
        
        function deleteGradeFromForm() {
            const gradeId = document.getElementById('grade_id').value;
            if (!gradeId) return;
            if(confirm('Are you sure you want to delete this grade?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'instructorRecord.php';
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
        
        // Search functionality
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
        });
        
        // Auto-load data if a class is pre-selected from schedule
        document.addEventListener('DOMContentLoaded', function() {
            const classSelect = document.getElementById('class_select');
            if (classSelect.value) {
                // Automatically load students and subjects for the pre-selected class
                loadClassStudents(classSelect.value);
            }
        });
    </script>
</body>
</html>
