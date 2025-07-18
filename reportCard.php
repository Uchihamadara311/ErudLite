<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is a student
if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Student') {
    header("Location: quickAccess.php");
    exit();
}

// Get student information from session
$student_id = $_SESSION['user_id'];
$current_year = (int)date('Y');

// Get student's details
$stmt = $conn->prepare('SELECT u.first_name, u.last_name FROM users u WHERE u.user_id = ?');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Get student ID
$student_id = $_SESSION['user_id'];
$current_year = (int)date('Y');

// Get student's current class info
$class_sql = "SELECT c.class_id, c.grade_level, c.section, c.room 
              FROM enrollments e 
              JOIN classes c ON e.class_id = c.class_id 
              WHERE e.student_id = ? AND c.school_year = ?
              ORDER BY c.grade_level DESC LIMIT 1";
$stmt = $conn->prepare($class_sql);
$stmt->bind_param('ii', $student_id, $current_year);
$stmt->execute();
$class_result = $stmt->get_result();
$class_info = $class_result->fetch_assoc();

// Get student's subjects and grades
$subjects_sql = "SELECT 
                    s.subject_id,
                    s.subject_name,
                    s.description,
                    CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
                    r.grade,
                    r.term
                FROM subjects s
                JOIN schedule sch ON s.subject_id = sch.subject_id
                JOIN instructors i ON sch.instructor_id = i.instructor_id
                JOIN users u ON i.instructor_id = u.user_id
                LEFT JOIN record r ON s.subject_id = r.subject_id 
                    AND r.student_id = ? 
                    AND r.school_year = ?
                WHERE sch.class_id = ?
                ORDER BY s.subject_name";
$stmt = $conn->prepare($subjects_sql);
$stmt->bind_param('iii', $student_id, $current_year, $class_info['class_id']);
$stmt->execute();
$subjects_result = $stmt->get_result();

// Group subjects by term
$terms = ['First', 'Second', 'Third', 'Fourth'];
$subjects_by_term = [];
while ($row = $subjects_result->fetch_assoc()) {
    $term = $row['term'] ?? 'Current';
    $subjects_by_term[$term][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/reportCard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .report-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 10px;
        }
        
        .student-info {
            margin: 20px 0;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .grades-table {
            margin-top: 20px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: white;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .grade {
            font-weight: bold;
            text-align: center;
        }
        
        .passing {
            color: #28a745;
        }
        
        .failing {
            color: #dc3545;
        }
        
        .remarks {
            font-style: italic;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        @media print {
            .action-buttons {
                display: none;
            }
        }
    </style>
    <style>
        .classroom-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .class-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 10px;
        }
        
        .terms-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .term-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .term-header {
            background-color: #007bff;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .subject-card {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            transition: transform 0.2s;
        }
        
        .subject-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .subject-name {
            font-size: 1.1em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .subject-details {
            font-size: 0.9em;
            color: #666;
        }
        
        .subject-grade {
            font-size: 1.2em;
            font-weight: bold;
            color: #28a745;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .subject-grade.failing {
            color: #dc3545;
        }
        
        .no-grade {
            color: #6c757d;
            font-style: italic;
        }
        
        .term-average {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
            text-align: right;
            font-size: 1.1em;
        }
        
        .current-term {
            border: 2px solid #007bff;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }
        
        .btn-primary, .btn-secondary {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        @media (max-width: 768px) {
            .terms-container {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        @media print {
            .action-buttons {
                display: none;
            }
            
            .term-card {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div id="header-placeholder"></div>
    <main>
        <div class="classroom-container">
            <div class="class-header">
                <h1>My Classroom</h1>
                <?php if ($class_info): ?>
                    <h2>Grade <?php echo htmlspecialchars($class_info['grade_level']); ?> - 
                        Section <?php echo htmlspecialchars($class_info['section']); ?></h2>
                    <p>School Year: <?php echo $current_year; ?></p>
                    <p>Room: <?php echo htmlspecialchars($class_info['room']); ?></p>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <button class="btn-primary" onclick="downloadPDF()">
                        <i class="fas fa-download"></i> Download Report
                    </button>
                    <button class="btn-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>

            <div class="terms-container">
                <?php foreach ($terms as $term): ?>
                    <div class="term-card <?php echo ($term == 'Current' ? 'current-term' : ''); ?>">
                        <div class="term-header">
                            <h3><?php echo $term; ?> Term</h3>
                        </div>
                        
                        <?php if (isset($subjects_by_term[$term]) && !empty($subjects_by_term[$term])): ?>
                            <?php 
                            $term_total = 0;
                            $term_count = 0;
                            ?>
                            <?php foreach ($subjects_by_term[$term] as $subject): ?>
                                <div class="subject-card">
                                    <div class="subject-name">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </div>
                                    <div class="subject-details">
                                        <p>Instructor: <?php echo htmlspecialchars($subject['instructor_name']); ?></p>
                                        <?php if ($subject['description']): ?>
                                            <p><?php echo htmlspecialchars($subject['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (isset($subject['grade'])): ?>
                                        <div class="subject-grade <?php echo ($subject['grade'] < 75 ? 'failing' : ''); ?>">
                                            <span>Grade: <?php echo $subject['grade']; ?></span>
                                            <span><?php echo ($subject['grade'] >= 75 ? 
                                                '<i class="fas fa-check-circle"></i>' : 
                                                '<i class="fas fa-exclamation-circle"></i>'); ?></span>
                                        </div>
                                        <?php 
                                        $term_total += $subject['grade'];
                                        $term_count++;
                                        ?>
                                    <?php else: ?>
                                        <div class="no-grade">
                                            <i class="fas fa-clock"></i> Grade not yet available
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if ($term_count > 0): ?>
                                <div class="term-average">
                                    <strong>Term Average: 
                                        <?php 
                                        $average = $term_total / $term_count;
                                        echo number_format($average, 2); 
                                        echo $average >= 75 ? 
                                            ' <i class="fas fa-check-circle" style="color: #28a745;"></i>' : 
                                            ' <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i>';
                                        ?>
                                    </strong>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="no-grade">No subjects recorded for this term</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            const element = document.querySelector('.classroom-container');
            const opt = {
                margin: 1,
                filename: 'classroom-report.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            
            html2pdf().from(element).set(opt).save();
        }
    </script>
</body>
</html>
                </button>
            </div>
        </div>

        <div class="student-info">
                <h2>Student Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Student Name:</label>
                        <span><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>School Year:</label>
                        <span><?php echo $current_year; ?></span>
                    </div>
                    <?php if ($class_info): ?>
                    <div class="info-item">
                        <label>Grade & Section:</label>
                        <span>Grade <?php echo htmlspecialchars($class_info['grade_level']); ?> - 
                              <?php echo htmlspecialchars($class_info['section']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        <div class="grades-section">
                <h2>Academic Performance</h2>
                <div class="grades-table">
                    <table>
                        <thead>
                            <tr>
                                <th rowspan="2">Subject</th>
                                <th colspan="4">Grading Period</th>
                                <th rowspan="2">Final Grade</th>
                                <th rowspan="2">Remarks</th>
                            </tr>
                            <tr>
                                <th>1st</th>
                                <th>2nd</th>
                                <th>3rd</th>
                                <th>4th</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Get all subjects and grades for this student
                            $grades_sql = "SELECT 
                                s.subject_name,
                                GROUP_CONCAT(IF(r.term = 'First', r.grade, NULL)) as first_term,
                                GROUP_CONCAT(IF(r.term = 'Second', r.grade, NULL)) as second_term,
                                GROUP_CONCAT(IF(r.term = 'Third', r.grade, NULL)) as third_term,
                                GROUP_CONCAT(IF(r.term = 'Fourth', r.grade, NULL)) as fourth_term
                            FROM subjects s
                            JOIN schedule sch ON s.subject_id = sch.subject_id
                            LEFT JOIN record r ON s.subject_id = r.subject_id 
                                AND r.student_id = ? 
                                AND r.school_year = ?
                            WHERE sch.class_id = ?
                            GROUP BY s.subject_id
                            ORDER BY s.subject_name";
                            
                            $stmt = $conn->prepare($grades_sql);
                            $stmt->bind_param('iii', $student_id, $current_year, $class_info['class_id']);
                            $stmt->execute();
                            $grades_result = $stmt->get_result();
                            
                            $overall_total = 0;
                            $overall_count = 0;
                            
                            while ($subject = $grades_result->fetch_assoc()):
                                $terms = [
                                    $subject['first_term'],
                                    $subject['second_term'],
                                    $subject['third_term'],
                                    $subject['fourth_term']
                                ];
                                
                                $term_total = 0;
                                $term_count = 0;
                                foreach ($terms as $grade) {
                                    if ($grade !== null) {
                                        $term_total += $grade;
                                        $term_count++;
                                    }
                                }
                                
                                $final_grade = $term_count > 0 ? ($term_total / $term_count) : null;
                                if ($final_grade !== null) {
                                    $overall_total += $final_grade;
                                    $overall_count++;
                                }
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                    <?php foreach ($terms as $grade): ?>
                                        <td class="grade <?php echo $grade >= 75 ? 'passing' : 
                                                             ($grade !== null ? 'failing' : ''); ?>">
                                            <?php echo $grade !== null ? number_format($grade, 2) : '-'; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="grade <?php echo $final_grade >= 75 ? 'passing' : 
                                                         ($final_grade !== null ? 'failing' : ''); ?>">
                                        <?php echo $final_grade !== null ? 
                                                   number_format($final_grade, 2) : '-'; ?>
                                    </td>
                                    <td class="remarks">
                                        <?php 
                                        if ($final_grade !== null) {
                                            echo $final_grade >= 75 ? 'Passed' : 'Failed';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            
                            <?php if ($overall_count > 0): ?>
                                <tr>
                                    <td colspan="5"><strong>General Average</strong></td>
                                    <td class="grade <?php echo ($overall_total / $overall_count) >= 75 ? 
                                                         'passing' : 'failing'; ?>">
                                        <?php echo number_format($overall_total / $overall_count, 2); ?>
                                    </td>
                                    <td class="remarks">
                                        <?php echo ($overall_total / $overall_count) >= 75 ? 
                                                   'Passed' : 'Failed'; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="downloadPDF()">
                    <i class="fas fa-download"></i> Download Report Card
                </button>
                <button class="btn btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report Card
                </button>
            </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script src="js/reportCard.js"></script>
</body>
</html>