<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is instructor or admin
if(!isset($_SESSION['email']) || !in_array($_SESSION['permissions'], ['Instructor', 'Admin'])) {
    header("Location: quickAccess.php");
    exit();
}

$is_admin = $_SESSION['permissions'] === 'Admin';
$instructor_id = $_SESSION['user_id'];
$current_year = (int)date('Y');
$success_message = $error_message = '';

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_grades'])) {
        $student_id = $_POST['student_id'];
        $subject_id = $_POST['subject_id'];
        $grade = $_POST['grade'];
        $term = $_POST['term'];
        
        // Verify instructor has access to this subject/student
        if (!$is_admin) {
            $verify_sql = "SELECT 1 FROM schedule s 
                          WHERE s.instructor_id = ? 
                          AND s.subject_id = ? 
                          AND s.class_id IN (
                              SELECT e.class_id 
                              FROM enrollments e 
                              WHERE e.student_id = ?
                          )";
            $stmt = $conn->prepare($verify_sql);
            $stmt->bind_param('iii', $instructor_id, $subject_id, $student_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                $error_message = "You don't have permission to grade this student for this subject.";
                goto skip_grade_update;
            }
        }
        
        // Check if grade already exists
        $check_sql = "SELECT record_id FROM record 
                     WHERE student_id = ? AND subject_id = ? 
                     AND school_year = ? AND term = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param('iiis', $student_id, $subject_id, $current_year, $term);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing grade
            $record_id = $result->fetch_assoc()['record_id'];
            $update_sql = "UPDATE record SET grade = ?, instructor_id = ?, record_date = NOW() 
                          WHERE record_id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param('dii', $grade, $instructor_id, $record_id);
        } else {
            // Insert new grade
            $insert_sql = "INSERT INTO record (student_id, instructor_id, subject_id, school_year, 
                                             term, grade, record_date) 
                          VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param('iiissd', $student_id, $instructor_id, $subject_id, $current_year, 
                                      $term, $grade);
        }
        
        if ($stmt->execute()) {
            $success_message = "Grade successfully recorded!";
        } else {
            $error_message = "Error recording grade: " . $conn->error;
        }
    }
}
skip_grade_update:

// Get classes and subjects for instructor
if ($is_admin) {
    $classes_sql = "SELECT DISTINCT c.*, 
                    (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.class_id) as student_count,
                    (SELECT COUNT(*) FROM schedule s WHERE s.class_id = c.class_id) as subject_count
                    FROM classes c 
                    WHERE c.school_year = ?
                    ORDER BY c.grade_level, c.section";
    $stmt = $conn->prepare($classes_sql);
    $stmt->bind_param('i', $current_year);
} else {
    $classes_sql = "SELECT DISTINCT c.*, 
                    (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.class_id) as student_count,
                    (SELECT COUNT(*) FROM schedule s 
                     WHERE s.class_id = c.class_id AND s.instructor_id = ?) as subject_count
                    FROM classes c 
                    JOIN schedule s ON c.class_id = s.class_id 
                    WHERE s.instructor_id = ? AND c.school_year = ?
                    ORDER BY c.grade_level, c.section";
    $stmt = $conn->prepare($classes_sql);
    $stmt->bind_param('iii', $instructor_id, $instructor_id, $current_year);
}
$stmt->execute();
$classes_result = $stmt->get_result();

// Get selected class details if any
$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$students = [];
$subjects = [];

if ($selected_class) {
    // Get students in selected class
    $students_sql = "SELECT u.user_id, u.first_name, u.last_name 
                    FROM users u 
                    JOIN students s ON u.user_id = s.student_id 
                    JOIN enrollments e ON s.student_id = e.student_id 
                    WHERE e.class_id = ?
                    ORDER BY u.last_name, u.first_name";
    $stmt = $conn->prepare($students_sql);
    $stmt->bind_param('i', $selected_class);
    $stmt->execute();
    $students_result = $stmt->get_result();
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
    
    // Get subjects for selected class
    if ($is_admin) {
        $subjects_sql = "SELECT DISTINCT 
                            s.*,
                            CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
                            sch.schedule_id,
                            sch.instructor_id
                        FROM subjects s 
                        JOIN schedule sch ON s.subject_id = sch.subject_id 
                        JOIN instructors i ON sch.instructor_id = i.instructor_id
                        JOIN users u ON i.instructor_id = u.user_id 
                        WHERE sch.class_id = ? AND sch.status = 'Active'
                        ORDER BY s.subject_name";
        $stmt = $conn->prepare($subjects_sql);
        $stmt->bind_param('i', $selected_class);
    } else {
        $subjects_sql = "SELECT DISTINCT 
                            s.*,
                            sch.schedule_id
                        FROM subjects s 
                        JOIN schedule sch ON s.subject_id = sch.subject_id 
                        WHERE sch.class_id = ? 
                        AND sch.instructor_id = ? 
                        AND sch.status = 'Active'
                        ORDER BY s.subject_name";
        $stmt = $conn->prepare($subjects_sql);
        $stmt->bind_param('ii', $selected_class, $instructor_id);
    }
    $stmt->execute();
    $subjects_result = $stmt->get_result();
    while ($row = $subjects_result->fetch_assoc()) {
        $subjects[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records Management - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/adminManagement.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .grade-input {
            width: 80px;
            padding: 5px;
            text-align: center;
        }
        
        .grade-cell {
            text-align: center;
        }
        
        .grade-form {
            margin-bottom: 20px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 10px;
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .selection-box {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            height: 300px;
            overflow-y: auto;
        }

        .selection-box h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            color: #495057;
        }

        .selection-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #e9ecef;
        }

        .selection-item:hover {
            background-color: #e9ecef;
        }

        .selection-item.selected {
            background-color: #007bff;
            color: white;
        }

        .term-item {
            display: inline-block;
            margin: 5px;
            padding: 10px 15px;
            border-radius: 20px;
            background-color: #e9ecef;
            cursor: pointer;
            transition: all 0.2s;
        }

        .term-item:hover {
            background-color: #dee2e6;
        }

        .term-item.selected {
            background-color: #007bff;
            color: white;
        }

        .grade-input-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
            padding: 15px;
            background-color: white;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #495057;
        }
        
        .grade-status {
            font-weight: bold;
        }
        
        .grade-status.passing {
            color: #28a745;
        }
        
        .grade-status.failing {
            color: #dc3545;
        }
        
        .filter-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .class-stats {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #666;
        }
        
        .grade-cell {
            position: relative;
        }
        
        .grade-status {
            padding: 3px 6px;
            margin: 2px 0;
            border-radius: 3px;
            cursor: help;
        }
        
        .grade-status:hover {
            opacity: 0.9;
        }
        
        .no-grade {
            color: #6c757d;
            font-style: italic;
            font-size: 0.9em;
        }
        
        .data-table th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 1;
        }
        
        .table-container {
            max-height: 600px;
            overflow-y: auto;
        }
        
        @media print {
            .filter-section, .grade-form {
                display: none;
            }
            
            .table-container {
                max-height: none;
                overflow: visible;
            }
        }
    </style>
</head>
<body>
    <div id="header-placeholder"></div>
    <main>
        <div class="admin-container">
            <div class="admin-back-btn-wrap">
                <a href="adminLinks.php" class="admin-back-btn">
                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            
            <h1 class="page-title">
                <?php echo $is_admin ? 'Student Records Management' : 'My Students\' Records'; ?>
            </h1>

            <?php if ($success_message): ?>
                <div class="message success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="filter-section">
                <form method="GET" class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="class_id">Select Class:</label>
                        <select name="class_id" id="class_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Choose a class...</option>
                            <?php while ($class = $classes_result->fetch_assoc()): ?>
                                <option value="<?php echo $class['class_id']; ?>"
                                        <?php echo $selected_class == $class['class_id'] ? 'selected' : ''; ?>>
                                    Grade <?php echo $class['grade_level']; ?> - 
                                    Section <?php echo $class['section']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>

            <?php if ($selected_class && !empty($students) && !empty($subjects)): ?>
                <div class="grade-form">
                    <h2>Record Grades</h2>
                    <form method="POST" id="gradeForm">
                        <div class="grid-container">
                            <div class="selection-box">
                                <h3>Students</h3>
                                <?php foreach ($students as $student): ?>
                                    <div class="selection-item student-item" data-id="<?php echo $student['user_id']; ?>">
                                        <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?>
                                    </div>
                                <?php endforeach; ?>
                                <input type="hidden" name="student_id" id="student_id" required>
                            </div>

                            <div class="selection-box">
                                <h3>Subjects</h3>
                                <?php foreach ($subjects as $subject): ?>
                                    <div class="selection-item subject-item" data-id="<?php echo $subject['subject_id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        <?php if ($is_admin && isset($subject['instructor_name'])): ?>
                                            <br><small>(<?php echo htmlspecialchars($subject['instructor_name']); ?>)</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <input type="hidden" name="subject_id" id="subject_id" required>
                            </div>

                            <div class="selection-box">
                                <h3>Term</h3>
                                <div style="text-align: center">
                                    <?php foreach (['First', 'Second', 'Third', 'Fourth'] as $termOption): ?>
                                        <div class="term-item" data-term="<?php echo $termOption; ?>">
                                            <?php echo $termOption; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="term" id="term" required>
                            </div>
                        </div>

                        <div class="grade-input-container">
                            <div style="flex: 1">
                                <label class="form-label" for="grade">Enter Grade:</label>
                                <input type="number" name="grade" id="grade" class="form-input grade-input" 
                                       min="0" max="100" step="0.01" required>
                            </div>
                            <button type="submit" name="submit_grades" class="submit-btn">Record Grade</button>
                        </div>
                    </form>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Handle student selection
                    document.querySelectorAll('.student-item').forEach(item => {
                        item.addEventListener('click', function() {
                            document.querySelectorAll('.student-item').forEach(i => i.classList.remove('selected'));
                            this.classList.add('selected');
                            document.getElementById('student_id').value = this.dataset.id;
                        });
                    });

                    // Handle subject selection
                    document.querySelectorAll('.subject-item').forEach(item => {
                        item.addEventListener('click', function() {
                            document.querySelectorAll('.subject-item').forEach(i => i.classList.remove('selected'));
                            this.classList.add('selected');
                            document.getElementById('subject_id').value = this.dataset.id;
                        });
                    });

                    // Handle term selection
                    document.querySelectorAll('.term-item').forEach(item => {
                        item.addEventListener('click', function() {
                            document.querySelectorAll('.term-item').forEach(i => i.classList.remove('selected'));
                            this.classList.add('selected');
                            document.getElementById('term').value = this.dataset.term;
                        });
                    });

                    // Form validation
                    document.getElementById('gradeForm').addEventListener('submit', function(e) {
                        if (!document.getElementById('student_id').value ||
                            !document.getElementById('subject_id').value ||
                            !document.getElementById('term').value) {
                            e.preventDefault();
                            alert('Please select a student, subject, and term before submitting.');
                        }
                    });
                });
                </script>

                <div class="table-section">
                    <h2>Student Records</h2>
                    <div id="studentRecordsTable" style="display: none;" class="table-container">
                        <div class="selected-student-info" style="margin-bottom: 20px; padding: 15px; background: #e9ecef; border-radius: 8px;">
                            <h3 style="margin: 0;">Selected Student: <span id="selectedStudentName">None</span></h3>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>First</th>
                                    <th>Second</th>
                                    <th>Third</th>
                                    <th>Fourth</th>
                                    <th>Average</th>
                                </tr>
                            </thead>
                            <tbody id="gradeTableBody">
                            </tbody>
                        </table>
                    </div>
                    <div id="noStudentSelected" class="message info" style="display: block;">
                        Please select a student to view their records.
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const subjects = <?php echo json_encode($subjects); ?>;
                    const students = <?php echo json_encode($students); ?>;
                    const currentYear = <?php echo $current_year; ?>;
                    
                    function updateGradeTable(studentId) {
                        const tableDiv = document.getElementById('studentRecordsTable');
                        const noSelectionDiv = document.getElementById('noStudentSelected');
                        const tbody = document.getElementById('gradeTableBody');
                        const nameSpan = document.getElementById('selectedStudentName');
                        
                        if (!studentId) {
                            tableDiv.style.display = 'none';
                            noSelectionDiv.style.display = 'block';
                            return;
                        }
                        
                        // Show the table and hide the message
                        tableDiv.style.display = 'block';
                        noSelectionDiv.style.display = 'none';
                        
                        // Update selected student name
                        const student = students.find(s => s.user_id == studentId);
                        nameSpan.textContent = `${student.last_name}, ${student.first_name}`;
                        
                        // Clear existing rows
                        tbody.innerHTML = '';
                        
                        // Add a row for each subject
                        subjects.forEach(subject => {
                            const row = document.createElement('tr');
                            
                            // Subject name cell
                            const subjectCell = document.createElement('td');
                            subjectCell.textContent = subject.subject_name;
                            row.appendChild(subjectCell);
                            
                            // Term cells (First to Fourth)
                            ['First', 'Second', 'Third', 'Fourth'].forEach(term => {
                                const termCell = document.createElement('td');
                                termCell.className = 'grade-cell';
                                termCell.innerHTML = '<div class="no-grade">Not yet graded</div>';
                                row.appendChild(termCell);
                            });
                            
                            // Average cell
                            const avgCell = document.createElement('td');
                            avgCell.className = 'grade-cell';
                            avgCell.textContent = '-';
                            row.appendChild(avgCell);
                            
                            tbody.appendChild(row);
                            
                            // Fetch grades for this subject
                            console.log(`Fetching grades for student ${studentId}, subject ${subject.subject_id}, year ${currentYear}`);
                            fetch(`get_student_grades.php?student_id=${studentId}&subject_id=${subject.subject_id}&year=${currentYear}`)
                            .then(response => {
                                if (!response.ok) {
                                    return response.text().then(text => {
                                        console.error('Server response:', text);
                                        throw new Error('Network response was not ok');
                                    });
                                }
                                return response.json();
                            })
                            .then(data => {
                                if (data.error) {
                                    throw new Error(data.error);
                                }
                                
                                let total = 0;
                                let count = 0;
                                
                                data.forEach(grade => {
                                    if (grade && grade.term && typeof grade.grade === 'number') {
                                        const termIndex = ['First', 'Second', 'Third', 'Fourth'].indexOf(grade.term);
                                        if (termIndex !== -1) {
                                            const cell = row.children[termIndex + 1];
                                            cell.innerHTML = `<div class="grade-status ${grade.grade >= 75 ? 'passing' : 'failing'}"
                                                                 title="Graded by: ${grade.graded_by || 'Unknown'}\nDate: ${grade.record_date || 'No date'}">
                                                                 ${grade.grade.toFixed(2)}
                                                            </div>`;
                                            total += grade.grade;
                                            count++;
                                        }
                                    }
                                });
                                
                                // Update average
                                if (count > 0) {
                                    const average = total / count;
                                    avgCell.innerHTML = `<div class="grade-status ${average >= 75 ? 'passing' : 'failing'}">
                                                           ${average.toFixed(2)}
                                                       </div>`;
                                }
                            })
                            .catch(error => console.error('Error fetching grades:', error));
                        });
                    }
                    
                    // Update table when student is selected
                    document.querySelectorAll('.student-item').forEach(item => {
                        const existingClick = item.onclick;
                        item.onclick = function(e) {
                            if (existingClick) existingClick.call(this, e);
                            updateGradeTable(this.dataset.id);
                        };
                    });
                });
                </script>
            <?php endif; ?>
            <?php elseif ($selected_class): ?>
                <div class="message info">
                    <?php
                    // Debug information
                    echo "No students or subjects found for class ID: " . $selected_class . "<br>";
                    
                    // Check enrollments
                    $debug_sql = "SELECT COUNT(*) as count FROM enrollments WHERE class_id = ?";
                    $stmt = $conn->prepare($debug_sql);
                    $stmt->bind_param('i', $selected_class);
                    $stmt->execute();
                    $enrollment_count = $stmt->get_result()->fetch_assoc()['count'];
                    echo "Number of enrolled students: " . $enrollment_count . "<br>";
                    
                    // Check schedules
                    $debug_sql = "SELECT COUNT(*) as count FROM schedule WHERE class_id = ? AND status = 'Active'";
                    $stmt = $conn->prepare($debug_sql);
                    $stmt->bind_param('i', $selected_class);
                    $stmt->execute();
                    $schedule_count = $stmt->get_result()->fetch_assoc()['count'];
                    echo "Number of active subjects scheduled: " . $schedule_count . "<br>";
                    
                    // Show class details
                    $debug_sql = "SELECT * FROM classes WHERE class_id = ?";
                    $stmt = $conn->prepare($debug_sql);
                    $stmt->bind_param('i', $selected_class);
                    $stmt->execute();
                    $class_details = $stmt->get_result()->fetch_assoc();
                    if ($class_details) {
                        echo "Class details: Grade " . $class_details['grade_level'] . 
                             " Section " . $class_details['section'] . 
                             " (School Year: " . $class_details['school_year'] . ")<br>";
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
</body>
</html>
