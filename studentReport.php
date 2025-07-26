<?php 
require_once 'includes/db.php';

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Ensure user is logged in and has student permissions
if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Student') {
    header("Location: index.php");
    exit();
}

// Get student ID from session
$student_email = $_SESSION['email'];
$student_sql = "SELECT s.Student_ID, pb.Given_Name, pb.Last_Name, cl.Grade_Level, cl.School_Year
                FROM Student s 
                JOIN Profile p ON s.Profile_ID = p.Profile_ID 
                JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                JOIN Account a ON p.Profile_ID = a.Profile_ID
                JOIN Role r ON a.Role_ID = r.Role_ID
                JOIN Enrollment e ON s.Student_ID = e.Student_ID
                JOIN Class c ON e.Class_ID = c.Class_ID
                JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                WHERE r.Email = ? AND e.Status = 'Active'
                ORDER BY cl.School_Year DESC, cl.Grade_Level DESC
                LIMIT 1";
$stmt = $conn->prepare($student_sql);
$stmt->bind_param("s", $student_email);
$stmt->execute();
$student_result = $stmt->get_result();
$student_data = $student_result->fetch_assoc();

if (!$student_data) {
    $_SESSION['error_message'] = "Student profile not found.";
    header("Location: index.php");
    exit();
}

$student_id = $student_data['Student_ID'];
$student_name = trim($student_data['Given_Name'] . ' ' . $student_data['Last_Name']);
$grade_level = $student_data['Grade_Level'];
$school_year = $student_data['School_Year'];

// Get selected school year from URL parameter or use current
$selected_year = isset($_GET['year']) ? cleanInput($_GET['year']) : $school_year;

// Get student's grades for the selected school year
$grades_sql = "SELECT DISTINCT sub.Subject_Name, sub.Subject_ID,
                      AVG(CASE WHEN cl.Term = '1st Semester' THEN rd.Grade END) as Q1_Grade,
                      AVG(CASE WHEN cl.Term = '2nd Semester' THEN rd.Grade END) as Q2_Grade,
                      AVG(CASE WHEN cl.Term = '3rd Semester' THEN rd.Grade END) as Q3_Grade,
                      AVG(CASE WHEN cl.Term = '4th Semester' THEN rd.Grade END) as Q4_Grade,
                      AVG(rd.Grade) as Final_Grade
               FROM Record r
               JOIN Record_Details rd ON r.Record_ID = rd.Record_ID
               JOIN Clearance cl ON rd.Clearance_ID = cl.Clearance_ID
               JOIN Subject sub ON r.Subject_ID = sub.Subject_ID
               WHERE r.Student_ID = ? AND cl.School_Year = ?
               GROUP BY sub.Subject_ID, sub.Subject_Name
               ORDER BY sub.Subject_Name";
$stmt = $conn->prepare($grades_sql);
$stmt->bind_param("is", $student_id, $selected_year);
$stmt->execute();
$grades_result = $stmt->get_result();

// Get available school years for the student
$years_sql = "SELECT DISTINCT cl.School_Year
              FROM Record r
              JOIN Record_Details rd ON r.Record_ID = rd.Record_ID
              JOIN Clearance cl ON rd.Clearance_ID = cl.Clearance_ID
              WHERE r.Student_ID = ?
              ORDER BY cl.School_Year DESC";
$stmt = $conn->prepare($years_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$years_result = $stmt->get_result();

// Calculate overall GPA
$total_grades = 0;
$subject_count = 0;
$subjects_data = [];

while ($grade = $grades_result->fetch_assoc()) {
    $subjects_data[] = $grade;
    if ($grade['Final_Grade']) {
        $total_grades += $grade['Final_Grade'];
        $subject_count++;
    }
}

$overall_gpa = $subject_count > 0 ? round($total_grades / $subject_count, 2) : 0;

// Function to determine grade status
function getGradeStatus($grade) {
    if ($grade === null || $grade === '') return 'N/A';
    if ($grade >= 75) return 'PASSED';
    if ($grade >= 60) return 'CONDITIONAL';
    return 'FAILED';
}

// Function to get grade color class
function getGradeColorClass($grade) {
    if ($grade === null || $grade === '') return 'grade-na';
    if ($grade >= 90) return 'grade-excellent';
    if ($grade >= 85) return 'grade-very-good';
    if ($grade >= 80) return 'grade-good';
    if ($grade >= 75) return 'grade-satisfactory';
    if ($grade >= 60) return 'grade-conditional';
    return 'grade-failed';
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Report - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/studentReport.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .student-info {
            background: linear-gradient(180deg, #667eeaff 0%, #3a4c9dff 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .info-item {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .info-label {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 18px;
            font-weight: 600;
        }
        
        .year-selector {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .grades-table th {
            background: linear-gradient(180deg, #475cb6ff 0%, #3a4c9dff 100%);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 600;
            border: none;
        }
        
        .grades-table td {
            padding: 12px 15px;
            text-align: center;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        
        .grades-table td:first-child {
            text-align: left;
            font-weight: 500;
            color: #333;
        }
        
        .grades-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .grade-excellent { color: #28a745; font-weight: bold; }
        .grade-very-good { color: #20c997; font-weight: bold; }
        .grade-good { color: #ffc107; font-weight: bold; }
        .grade-satisfactory { color: #fd7e14; font-weight: bold; }
        .grade-conditional { color: #dc3545; font-weight: bold; }
        .grade-failed { color: #6c757d; font-weight: bold; }
        .grade-na { color: #6c757d; font-style: italic; }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
        }
        
        .summary-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eeaff;
            margin-bottom: 10px;
        }
        
        .summary-label {
            color: #666;
            font-weight: 500;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eeaff;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            transition: color 0.3s ease;
        }
        
        .back-button:hover {
            color: #764ba2;
        }
        
        @media print {
            .action-buttons, .back-button, .year-selector { display: none; }
            .report-container { padding: 0; }
            .student-info { background: #f8f9fa !important; color: #333 !important; }
        }
    </style>
</head>
<body>
    <header id="header-placeholder"></header>
    <main class="report-container">
        <a href="studentDashboard.php" class="back-button">
            <i class="fas fa-arrow-left"></i>Back to Dashboard
        </a>
        
        <div class="student-info">
            <h1><i class="fas fa-graduation-cap"></i> Academic Report</h1>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Student Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($student_name); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Grade Level</div>
                    <div class="info-value">Grade <?php echo $grade_level; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">School Year</div>
                    <div class="info-value"><?php echo htmlspecialchars($selected_year); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Overall GPA</div>
                    <div class="info-value <?php echo getGradeColorClass($overall_gpa); ?>">
                        <?php echo $overall_gpa; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="year-selector">
            <h4 style="color: #212f6eff"><i class="fas fa-calendar-alt"></i> Select School Year</h4>
            <form method="GET" action="studentReport.php" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                <select name="year" class="form-select" onchange="this.form.submit()" style="min-width: 200px;">
                    <?php while($year = $years_result->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($year['School_Year']); ?>" 
                                <?php echo ($year['School_Year'] == $selected_year) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($year['School_Year']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <span style="color: #666;">View grades for different academic years</span>
            </form>
        </div>
        
        <?php if (!empty($subjects_data)): ?>
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-number"><?php echo count($subjects_data); ?></div>
                <div class="summary-label">Total Subjects</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?php echo $overall_gpa; ?></div>
                <div class="summary-label">Overall GPA</div>
            </div>
            <div class="summary-card">
                <div class="summary-number">
                    <?php 
                    $passed_count = 0;
                    foreach($subjects_data as $subject) {
                        if ($subject['Final_Grade'] && $subject['Final_Grade'] >= 75) $passed_count++;
                    }
                    echo $passed_count;
                    ?>
                </div>
                <div class="summary-label">Subjects Passed</div>
            </div>
            <div class="summary-card">
                <div class="summary-number">
                    <?php echo $overall_gpa >= 75 ? 'PASSED' : 'AT RISK'; ?>
                </div>
                <div class="summary-label">Status</div>
            </div>
        </div>
        
        <div class="action-buttons">
            <button onclick="window.print()" class="action-btn btn-primary">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button onclick="downloadPDF()" class="action-btn btn-secondary">
                <i class="fas fa-download"></i> Download PDF
            </button>
            <a href="studentAcademicStats.php" class="action-btn btn-success">
                <i class="fas fa-chart-line"></i> View Statistics
            </a>
        </div>
        
        <table class="grades-table">
            <thead>
                <tr>
                    <th rowspan="2"><i class="fas fa-book"></i> Learning Area</th>
                    <th colspan="4"><i class="fas fa-calendar"></i> Grading Period</th>
                    <th rowspan="2"><i class="fas fa-trophy"></i> Final Rating</th>
                    <th rowspan="2"><i class="fas fa-flag"></i> Remarks</th>
                </tr>
                <tr>
                    <th>1st Semester</th>
                    <th>2nd Semester</th>
                    <th>3rd Semester</th>
                    <th>4th Semester</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($subjects_data as $subject): ?>
                    <tr>
                        <td><i class="fas fa-book-open"></i> <?php echo htmlspecialchars($subject['Subject_Name']); ?></td>
                        <td class="<?php echo getGradeColorClass($subject['Q1_Grade']); ?>">
                            <?php echo $subject['Q1_Grade'] ? number_format($subject['Q1_Grade'], 1) : 'N/A'; ?>
                        </td>
                        <td class="<?php echo getGradeColorClass($subject['Q2_Grade']); ?>">
                            <?php echo $subject['Q2_Grade'] ? number_format($subject['Q2_Grade'], 1) : 'N/A'; ?>
                        </td>
                        <td class="<?php echo getGradeColorClass($subject['Q3_Grade']); ?>">
                            <?php echo $subject['Q3_Grade'] ? number_format($subject['Q3_Grade'], 1) : 'N/A'; ?>
                        </td>
                        <td class="<?php echo getGradeColorClass($subject['Q4_Grade']); ?>">
                            <?php echo $subject['Q4_Grade'] ? number_format($subject['Q4_Grade'], 1) : 'N/A'; ?>
                        </td>
                        <td class="<?php echo getGradeColorClass($subject['Final_Grade']); ?>">
                            <strong><?php echo $subject['Final_Grade'] ? number_format($subject['Final_Grade'], 1) : 'N/A'; ?></strong>
                        </td>
                        <td>
                            <span class="<?php echo getGradeColorClass($subject['Final_Grade']); ?>">
                                <?php echo getGradeStatus($subject['Final_Grade']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">
            <i class="fas fa-exclamation-triangle" style="font-size: 3em; color: #ffc107; margin-bottom: 20px;"></i>
            <h3>No grades available for the selected school year</h3>
            <p>Please check with your instructor or select a different school year.</p>
        </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
            <p style="margin: 0; color: #666; font-size: 14px;">
                <i class="fas fa-info-circle"></i> 
                This report was generated on <?php echo date('F j, Y \a\t g:i A'); ?> | 
                <strong>Grading Scale:</strong> 90-100 (Excellent), 85-89 (Very Good), 80-84 (Good), 75-79 (Satisfactory), 60-74 (Conditional), Below 60 (Failed)
            </p>
        </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script>
        function downloadPDF() {
            // Create a temporary form to submit for PDF generation
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'generateReportPDF.php';
            form.style.display = 'none';
            
            const yearInput = document.createElement('input');
            yearInput.name = 'year';
            yearInput.value = '<?php echo htmlspecialchars($selected_year); ?>';
            form.appendChild(yearInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight rows on hover
            const rows = document.querySelectorAll('.grades-table tbody tr');
            rows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                    this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.boxShadow = 'none';
                });
            });
        });
    </script>
</body>
</html>