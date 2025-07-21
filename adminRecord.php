<?php 
require_once 'includes/db.php';

// Ensure user is logged in and has admin permissions
if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: quickAccess.php");
    exit();
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] == 'get_students' && isset($_GET['class_id'])) {
        try {
            $class_id = (int)$_GET['class_id'];
            $sql = "SELECT DISTINCT s.Student_ID, pb.Given_Name, pb.Last_Name 
                    FROM Student s
                    JOIN Profile p ON s.Profile_ID = p.Profile_ID
                    JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                    JOIN Enrollment e ON s.Student_ID = e.Student_ID
                    WHERE e.Class_ID = ?
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
        try {
            $class_id = (int)$_GET['class_id'];
            $sql = "SELECT DISTINCT s.Subject_ID, s.Subject_Name 
                    FROM Subject s
                    JOIN Class c ON c.Class_ID = ?
                    JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                    WHERE s.Clearance_ID = cl.Clearance_ID
                    ORDER BY s.Subject_Name";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $class_id);
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
        $class_id = (int)$_GET['class_id'];
        $sql = "SELECT g.Grade_ID, g.Grade_Value, g.Grade_Letter, g.Quarter, g.Date_Recorded,
                       pb.Given_Name, pb.Last_Name, s.Subject_Name
                FROM Grades g
                JOIN Student st ON g.Student_ID = st.Student_ID
                JOIN Profile p ON st.Profile_ID = p.Profile_ID
                JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                JOIN Subject s ON g.Subject_ID = s.Subject_ID
                WHERE g.Class_ID = ?
                ORDER BY pb.Last_Name, pb.Given_Name, s.Subject_Name, g.Quarter";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $grades = [];
        while($row = $result->fetch_assoc()) {
            $grades[] = $row;
        }
        echo json_encode($grades);
        exit;
    }
    
    if ($_GET['action'] == 'search_student' && isset($_GET['query'])) {
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
        $student_id = (int)$_GET['student_id'];
        $sql = "SELECT g.Grade_ID, g.Grade_Value, g.Grade_Letter, g.Quarter, g.Date_Recorded,
                       s.Subject_Name, pb.Given_Name, pb.Last_Name
                FROM Grades g
                JOIN Subject s ON g.Subject_ID = s.Subject_ID
                JOIN Student st ON g.Student_ID = st.Student_ID
                JOIN Profile p ON st.Profile_ID = p.Profile_ID
                JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                WHERE g.Student_ID = ?
                ORDER BY s.Subject_Name, g.Quarter";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $grades = [];
        while($row = $result->fetch_assoc()) {
            $grades[] = $row;
        }
        echo json_encode($grades);
        exit;
    }
}

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
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
    
    if ($operation == 'add_grade') {
        $student_id = (int)$_POST['student_id'];
        $subject_id = (int)$_POST['subject_id'];
        $class_id = (int)$_POST['class_id'];
        $quarter = cleanInput($_POST['quarter']);
        $grade_value = (float)$_POST['grade_value'];
        $grade_letter = calculateLetterGrade($grade_value);
        
        // Check if grade already exists
        $check_sql = "SELECT Grade_ID FROM Grades WHERE Student_ID = ? AND Subject_ID = ? AND Quarter = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("iis", $student_id, $subject_id, $quarter);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $error_message = "Grade already exists for this student, subject, and quarter.";
        } else {
            // Insert new grade
            $sql = "INSERT INTO Grades (Student_ID, Subject_ID, Class_ID, Quarter, Grade_Value, Grade_Letter, Date_Recorded, Recorded_By) 
                    VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 1)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiisds", $student_id, $subject_id, $class_id, $quarter, $grade_value, $grade_letter);
            
            if ($stmt->execute()) {
                $success_message = "Grade recorded successfully!";
            } else {
                $error_message = "Error recording grade: " . $stmt->error;
            }
        }
    } elseif ($operation == 'update_grade') {
        $grade_id = (int)$_POST['grade_id'];
        $grade_value = (float)$_POST['grade_value'];
        $grade_letter = calculateLetterGrade($grade_value);
        
        $sql = "UPDATE Grades SET Grade_Value = ?, Grade_Letter = ?, Date_Recorded = CURDATE() WHERE Grade_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dsi", $grade_value, $grade_letter, $grade_id);
        
        if ($stmt->execute()) {
            $success_message = "Grade updated successfully!";
        } else {
            $error_message = "Error updating grade: " . $stmt->error;
        }
    } elseif ($operation == 'delete_grade') {
        $grade_id = (int)$_POST['grade_id'];
        
        $sql = "DELETE FROM Grades WHERE Grade_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $grade_id);
        
        if ($stmt->execute()) {
            $success_message = "Grade deleted successfully!";
        } else {
            $error_message = "Error deleting grade: " . $stmt->error;
        }
    }
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
        
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Academic Year Filter -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-filter"></i> Filter by Academic Year</h2>
            <form method="GET" style="padding: 20px;">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="year"><i class="fas fa-calendar-alt"></i> Academic Year</label>
                        <select class="form-select" name="year" id="year" onchange="this.form.submit()">
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
            <div style="padding: 20px;">
                <div class="form-grid">
                    <?php while($class = $classes_result->fetch_assoc()): ?>
                        <div class="form-group">
                            <button type="button" class="submit-btn" onclick="loadClassStudents(<?php echo $class['Class_ID']; ?>)">
                                <i class="fas fa-graduation-cap"></i> Grade <?php echo $class['Grade_Level']; ?> - <?php echo htmlspecialchars($class['Section']); ?>
                                <br><small>Room <?php echo htmlspecialchars($class['Room']); ?></small>
                            </button>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>
        
        <!-- Grade Entry Form -->
        <section class="form-section" id="grade-form-section" style="display: none;">
            <h2 class="form-title" id="grade-form-title"><i class="fas fa-edit"></i> Record Grade</h2>
            <form method="POST" action="adminRecord.php" id="grade-form">
                <input type="hidden" id="operation" name="operation" value="add_grade">
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
                        <label class="form-label" for="quarter"><i class="fas fa-calendar"></i> Quarter *</label>
                        <select class="form-select" name="quarter" id="quarter" required>
                            <option value="">Select Quarter</option>
                            <option value="1st Quarter">1st Quarter</option>
                            <option value="2nd Quarter">2nd Quarter</option>
                            <option value="3rd Quarter">3rd Quarter</option>
                            <option value="4th Quarter">4th Quarter</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="grade_value"><i class="fas fa-star"></i> Grade (0-100) *</label>
                        <input type="number" class="form-select" name="grade_value" id="grade_value" min="0" max="100" step="0.01" required>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="submit-btn" id="submit-btn"><i class="fas fa-save"></i> Record Grade</button>
                    <button type="button" class="cancel-btn" onclick="resetGradeForm()"><i class="fas fa-refresh"></i> Reset</button>
                    <button type="button" class="cancel-btn" onclick="hideGradeForm()"><i class="fas fa-times"></i> Hide Form</button>
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
        function loadClassStudents(classId) {
            // Reset form first
            resetGradeForm();
            
            document.getElementById('class_id').value = classId;
            
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
                })
                .catch(error => {
                    console.error('Error loading students:', error);
                    alert('Error loading students. Please try again.');
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
                })
                .catch(error => {
                    console.error('Error loading subjects:', error);
                    alert('Error loading subjects. Please try again.');
                });
            
            // Load existing grades for this class
            loadClassGrades(classId);
            
            // Show forms
            document.getElementById('grade-form-section').style.display = 'block';
            document.getElementById('grades-table-section').style.display = 'block';
        }
        
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
                            <tr>
                                <td><i class="fas fa-user"></i> ${grade.Given_Name} ${grade.Last_Name}</td>
                                <td>${grade.Subject_Name}</td>
                                <td>${grade.Quarter}</td>
                                <td>${grade.Grade_Value}</td>
                                <td><span class="grade-badge">${grade.Grade_Letter}</span></td>
                                <td>${grade.Date_Recorded}</td>
                                <td class="action-buttons">
                                    <button class="edit-btn" onclick="editGrade(${grade.Grade_ID}, ${grade.Grade_Value})"><i class="fas fa-edit"></i> Edit</button>
                                    <button class="delete-btn" onclick="deleteGrade(${grade.Grade_ID})"><i class="fas fa-trash"></i> Delete</button>
                                </td>
                            </tr>
                        `;
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
            
            // Show the form if it's hidden
            document.getElementById('grade-form-section').style.display = 'block';
            
            // Scroll to form
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
            document.getElementById('quarter').value = '';
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Record Grade';
            document.getElementById('grade-form-title').innerHTML = '<i class="fas fa-edit"></i> Record Grade';
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
                                    <td class="action-buttons">
                                        <button class="edit-btn" onclick="editGrade(${grade.Grade_ID}, ${grade.Grade_Value})"><i class="fas fa-edit"></i> Edit</button>
                                        <button class="delete-btn" onclick="deleteGrade(${grade.Grade_ID})"><i class="fas fa-trash"></i> Delete</button>
                                    </td>
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
            const quarter = document.getElementById('quarter').value;
            const gradeValue = document.getElementById('grade_value').value;
            
            if (!studentId || !subjectId || !quarter || !gradeValue) {
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
    </script>
</body>
</html>