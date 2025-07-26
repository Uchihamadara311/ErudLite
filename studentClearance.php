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

// Get selected filters from URL parameters
$selected_subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : '';
$selected_term = isset($_GET['term']) ? cleanInput($_GET['term']) : '';

// Get student's subjects
$subjects_sql = "SELECT DISTINCT sub.Subject_ID, sub.Subject_Name,
                        CONCAT(pb.Given_Name, ' ', pb.Last_Name) as Instructor_Name
                 FROM Subject sub
                 JOIN Record r ON sub.Subject_ID = r.Subject_ID
                 JOIN Instructor i ON r.Instructor_ID = i.Instructor_ID
                 JOIN Profile p ON i.Profile_ID = p.Profile_ID
                 JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                 WHERE r.Student_ID = ?
                 ORDER BY sub.Subject_Name";
$stmt = $conn->prepare($subjects_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$subjects_result = $stmt->get_result();

$subjects_data = [];
while ($subject = $subjects_result->fetch_assoc()) {
    $subjects_data[] = $subject;
}

// Get available terms for the student
$terms_sql = "SELECT DISTINCT cl.Term
              FROM Record r
              JOIN Record_Details rd ON r.Record_ID = rd.Record_ID
              JOIN Clearance cl ON rd.Clearance_ID = cl.Clearance_ID
              WHERE r.Student_ID = ?
              ORDER BY cl.Term";
$stmt = $conn->prepare($terms_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$terms_result = $stmt->get_result();

$terms_data = [];
while ($term = $terms_result->fetch_assoc()) {
    $terms_data[] = $term;
}

// Get clearance requirements and status
$clearance_data = [];
$subject_name = '';
$instructor_name = '';

if ($selected_subject_id && $selected_term) {
    // Get subject and instructor information
    $subject_info_sql = "SELECT sub.Subject_Name,
                               CONCAT(pb.Given_Name, ' ', pb.Last_Name) as Instructor_Name
                        FROM Subject sub
                        JOIN Record r ON sub.Subject_ID = r.Subject_ID
                        JOIN Instructor i ON r.Instructor_ID = i.Instructor_ID
                        JOIN Profile p ON i.Profile_ID = p.Profile_ID
                        JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                        WHERE sub.Subject_ID = ? AND r.Student_ID = ?
                        LIMIT 1";
    $stmt = $conn->prepare($subject_info_sql);
    $stmt->bind_param("ii", $selected_subject_id, $student_id);
    $stmt->execute();
    $subject_info_result = $stmt->get_result();
    $subject_info = $subject_info_result->fetch_assoc();
    
    if ($subject_info) {
        $subject_name = $subject_info['Subject_Name'];
        $instructor_name = $subject_info['Instructor_Name'];
    }
    
    // Get clearance requirements and grades
    $clearance_sql = "SELECT cl.Requirements, rd.Grade, cl.Term
                      FROM Record r
                      JOIN Record_Details rd ON r.Record_ID = rd.Record_ID
                      JOIN Clearance cl ON rd.Clearance_ID = cl.Clearance_ID
                      WHERE r.Student_ID = ? AND r.Subject_ID = ? AND cl.Term = ?";
    $stmt = $conn->prepare($clearance_sql);
    $stmt->bind_param("iis", $student_id, $selected_subject_id, $selected_term);
    $stmt->execute();
    $clearance_result = $stmt->get_result();
    
    while ($clearance = $clearance_result->fetch_assoc()) {
        $clearance_data[] = $clearance;
    }
}

// Function to determine clearance status
function getClearanceStatus($grade, $requirements) {
    if ($grade === null || $grade === '') return ['status' => 'No Grade', 'class' => 'no-grade'];
    if ($grade >= 75) return ['status' => 'CLEARED', 'class' => 'cleared'];
    if ($grade >= 60) return ['status' => 'CONDITIONAL', 'class' => 'conditional'];
    return ['status' => 'NOT CLEARED', 'class' => 'not-cleared'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Clearance - ERUDLITE</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/reportCard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .backButton {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(180deg, #475cb6ff 0%, #3a4c9dff 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            margin: 20px;
        }
        
        .backButton:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .cleared { color: #28a745; font-weight: bold; }
        .conditional { color: #ffc107; font-weight: bold; }
        .not-cleared { color: #dc3545; font-weight: bold; }
        .no-grade { color: #6c757d; font-style: italic; }
        
        .student-info-header {
            background: linear-gradient(180deg, #475cb6ff 0%, #3a4c9dff 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover {
            background: #5a6fd8;
        }
        
        .requirements-list {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        
        .requirement-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .requirement-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <header id="header-placeholder"></header>
    <a href="studentDashboard.php" class="backButton">
        <i class="fas fa-arrow-left"></i>Back to Dashboard
    </a>
    <main class="report-container">
        <div class="student-info-header">
            <h1><i class="fas fa-clipboard-check"></i> Student Clearance Status</h1>
            <div style="margin-top: 10px;">
                <strong>Student:</strong> <?php echo htmlspecialchars($student_name); ?> | 
                <strong>Grade:</strong> <?php echo $grade_level; ?> | 
                <strong>School Year:</strong> <?php echo htmlspecialchars($school_year); ?>
            </div>
        </div>

        <div class="filter-section">
            <h3><i class="fas fa-filter"></i> Select Subject and Term</h3>
            <form method="GET" action="studentClearance.php">
                <div class="filter-grid">
                    <div class="info-section">
                        <label for="subject_id"><strong>Subject:</strong></label>
                        <select id="subject_id" name="subject_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Select Subject</option>
                            <?php foreach($subjects_data as $subject): ?>
                                <option value="<?php echo $subject['Subject_ID']; ?>" 
                                        <?php echo ($subject['Subject_ID'] == $selected_subject_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['Subject_Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="info-section">
                        <label for="term"><strong>Term:</strong></label>
                        <select id="term" name="term" class="form-select" onchange="this.form.submit()">
                            <option value="">Select Term</option>
                            <?php foreach($terms_data as $term): ?>
                                <option value="<?php echo htmlspecialchars($term['Term']); ?>" 
                                        <?php echo ($term['Term'] == $selected_term) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($term['Term']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php if ($selected_subject_id && $selected_term): ?>
                <div style="margin-top: 15px; padding: 10px; background: #e3f2fd; border-radius: 5px;">
                    <strong>Selected:</strong> <?php echo htmlspecialchars($subject_name); ?> - <?php echo htmlspecialchars($selected_term); ?>
                    <?php if ($instructor_name): ?>
                    | <strong>Instructor:</strong> <?php echo htmlspecialchars($instructor_name); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($selected_subject_id && $selected_term): ?>
        <div class="grades-card">
            <h3><i class="fas fa-tasks"></i> Clearance Requirements - <?php echo htmlspecialchars($subject_name); ?></h3>
            
            <?php if (!empty($clearance_data)): ?>
                <?php foreach($clearance_data as $clearance): ?>
                    <div class="requirements-list">
                        <h4>Term: <?php echo htmlspecialchars($clearance['Term']); ?></h4>
                        
                        <?php if ($clearance['Requirements']): ?>
                            <?php 
                            $requirements = explode("\n", $clearance['Requirements']);
                            foreach($requirements as $requirement): 
                                if (trim($requirement)):
                                    $status_info = getClearanceStatus($clearance['Grade'], $clearance['Requirements']);
                            ?>
                                <div class="requirement-item">
                                    <span><?php echo htmlspecialchars(trim($requirement)); ?></span>
                                    <div>
                                        <span class="<?php echo $status_info['class']; ?>">
                                            <?php echo $status_info['status']; ?>
                                        </span>
                                        <?php if ($clearance['Grade']): ?>
                                            <span style="margin-left: 10px; font-weight: bold;">
                                                Grade: <?php echo number_format($clearance['Grade'], 1); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        <?php else: ?>
                            <div class="requirement-item">
                                <span>No specific requirements listed</span>
                                <div>
                                    <?php 
                                    $status_info = getClearanceStatus($clearance['Grade'], '');
                                    ?>
                                    <span class="<?php echo $status_info['class']; ?>">
                                        <?php echo $status_info['status']; ?>
                                    </span>
                                    <?php if ($clearance['Grade']): ?>
                                        <span style="margin-left: 10px; font-weight: bold;">
                                            Grade: <?php echo number_format($clearance['Grade'], 1); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="report-actions">
                    <button class="btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Clearance
                    </button>
                    <button class="btn-secondary" onclick="downloadClearance()">
                        <i class="fas fa-download"></i> Download PDF
                    </button>
                    <a href="studentReport.php" class="btn-success">
                        <i class="fas fa-chart-line"></i> View Full Report
                    </a>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-info-circle" style="font-size: 3em; margin-bottom: 20px;"></i>
                    <h3>No clearance data found</h3>
                    <p>No clearance requirements found for the selected subject and term.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="grades-card">
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-search" style="font-size: 3em; margin-bottom: 20px;"></i>
                <h3>Select Subject and Term</h3>
                <p>Please select a subject and term from the filters above to view your clearance requirements.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
            <h4><i class="fas fa-info-circle"></i> Clearance Status Guide</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                <div class="legend-item">
                    <span class="cleared">● CLEARED</span> - Grade ≥ 75 (Requirements met)
                </div>
                <div class="legend-item">
                    <span class="conditional">● CONDITIONAL</span> - Grade 60-74 (Additional requirements may apply)
                </div>
                <div class="legend-item">
                    <span class="not-cleared">● NOT CLEARED</span> - Grade < 60 (Requirements not met)
                </div>
                <div class="legend-item">
                    <span class="no-grade">● NO GRADE</span> - No grade recorded yet
                </div>
            </div>
        </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script>
        function downloadClearance() {
            // Create a temporary form to submit for PDF generation
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'generateClearancePDF.php';
            form.style.display = 'none';
            
            const subjectInput = document.createElement('input');
            subjectInput.name = 'subject_id';
            subjectInput.value = '<?php echo $selected_subject_id; ?>';
            form.appendChild(subjectInput);
            
            const termInput = document.createElement('input');
            termInput.name = 'term';
            termInput.value = '<?php echo htmlspecialchars($selected_term); ?>';
            form.appendChild(termInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        // Add print-specific styling
        const printStyles = `
            @media print {
                .backButton, .filter-section, .report-actions { display: none !important; }
                .report-container { padding: 0 !important; }
                .student-info-header { background: #f8f9fa !important; color: #333 !important; }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = printStyles;
        document.head.appendChild(styleSheet);
        
        // Add interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to requirement items
            const requirementItems = document.querySelectorAll('.requirement-item');
            requirementItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                    this.style.transform = 'translateX(5px)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = 'transparent';
                    this.style.transform = 'translateX(0)';
                });
            });
        });
    </script>
</body>
</html>