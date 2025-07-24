<?php 
require_once 'includes/db.php';

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Ensure user is logged in with appropriate permissions
if(!isset($_SESSION['email']) || !in_array($_SESSION['permissions'], ['Admin', 'Instructor'])) {
    header("Location: index.php");
    exit();
}

// Initialize messages
$success_message = '';
$error_message = '';

// Retrieve messages from session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get current academic year and grade levels
$current_year = date('Y');
$academic_year = ($current_year - 1) . '-' . $current_year;

// Get all grade levels
$grade_levels = [];
$grade_sql = "SELECT DISTINCT Grade_Level FROM Clearance ORDER BY Grade_Level";
$grade_result = $conn->query($grade_sql);
while ($grade = $grade_result->fetch_assoc()) {
    $grade_levels[] = $grade['Grade_Level'];
}

// Get all school years
$school_years = [];
$year_sql = "SELECT DISTINCT School_Year FROM Clearance ORDER BY School_Year DESC";
$year_result = $conn->query($year_sql);
while ($year = $year_result->fetch_assoc()) {
    $school_years[] = $year['School_Year'];
}

// Set default selections
$selected_grade = isset($_GET['grade_level']) ? cleanInput($_GET['grade_level']) : '';
$selected_year = isset($_GET['school_year']) ? cleanInput($_GET['school_year']) : $academic_year;
$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// Get students for selected grade and year
$students = [];
if (!empty($selected_grade) && !empty($selected_year)) {
    $students_sql = "SELECT DISTINCT s.Student_ID, pb.Given_Name, pb.Last_Name
                     FROM Student s
                     JOIN Profile p ON s.Profile_ID = p.Profile_ID
                     JOIN Profile_Bio pb ON p.Profile_ID = pb.Profile_ID
                     JOIN Enrollment e ON s.Student_ID = e.Student_ID
                     JOIN Class c ON e.Class_ID = c.Class_ID
                     JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                     WHERE cl.Grade_Level = ? AND cl.School_Year = ? AND e.Status = 'Active'
                     ORDER BY pb.Last_Name, pb.Given_Name";
    $stmt = $conn->prepare($students_sql);
    $stmt->bind_param("ss", $selected_grade, $selected_year);
    $stmt->execute();
    $students_result = $stmt->get_result();
    
    while ($student = $students_result->fetch_assoc()) {
        $students[] = $student;
    }
}

// Get clearance details and student progress
$clearance_data = [];
$subject_progress = [];
$overall_status = 'Not Evaluated';

if ($selected_student > 0 && !empty($selected_grade) && !empty($selected_year)) {
    // Get clearance requirements
    $clearance_sql = "SELECT Clearance_ID, Requirements FROM Clearance 
                      WHERE Grade_Level = ? AND School_Year = ?";
    $stmt = $conn->prepare($clearance_sql);
    $stmt->bind_param("ss", $selected_grade, $selected_year);
    $stmt->execute();
    $clearance_result = $stmt->get_result();
    $clearance_data = $clearance_result->fetch_assoc();
    
    if ($clearance_data) {
        // Get student's subject records and grades
        $progress_sql = "SELECT sub.Subject_ID, sub.Subject_Name, sub.Description,
                               rd.Grade, rd.Record_Date, r.Record_ID,
                               CASE 
                                   WHEN rd.Grade >= 75 THEN 'Passed'
                                   WHEN rd.Grade >= 60 THEN 'Conditional'
                                   WHEN rd.Grade < 60 AND rd.Grade IS NOT NULL THEN 'Failed'
                                   ELSE 'Not Graded'
                               END as Status,
                               CASE 
                                   WHEN rd.Grade >= 75 THEN '#4CAF50'
                                   WHEN rd.Grade >= 60 THEN '#FF9800'
                                   WHEN rd.Grade < 60 AND rd.Grade IS NOT NULL THEN '#F44336'
                                   ELSE '#9E9E9E'
                               END as Status_Color
                        FROM Subject sub
                        LEFT JOIN Record r ON sub.Subject_ID = r.Subject_ID AND r.Student_ID = ?
                        LEFT JOIN Record_Details rd ON r.Record_ID = rd.Record_ID
                        LEFT JOIN Clearance cl ON rd.Clearance_ID = cl.Clearance_ID
                        WHERE cl.Grade_Level = ? AND cl.School_Year = ?
                        ORDER BY sub.Subject_Name";
        $stmt = $conn->prepare($progress_sql);
        $stmt->bind_param("iss", $selected_student, $selected_grade, $selected_year);
        $stmt->execute();
        $progress_result = $stmt->get_result();
        
        $total_subjects = 0;
        $passed_subjects = 0;
        $failed_subjects = 0;
        $conditional_subjects = 0;
        $not_graded = 0;
        
        while ($subject = $progress_result->fetch_assoc()) {
            $subject_progress[] = $subject;
            $total_subjects++;
            
            switch ($subject['Status']) {
                case 'Passed':
                    $passed_subjects++;
                    break;
                case 'Failed':
                    $failed_subjects++;
                    break;
                case 'Conditional':
                    $conditional_subjects++;
                    break;
                case 'Not Graded':
                    $not_graded++;
                    break;
            }
        }
        
        // Determine overall clearance status
        if ($total_subjects > 0) {
            if ($failed_subjects == 0 && $not_graded == 0) {
                $overall_status = 'Cleared';
            } elseif ($failed_subjects > 0) {
                $overall_status = 'Not Cleared - Failed Subjects';
            } elseif ($conditional_subjects > 0 && $failed_subjects == 0) {
                $overall_status = 'Conditional Clearance';
            } else {
                $overall_status = 'Pending - Incomplete Grades';
            }
        }
    }
}

// Function to get status color
function getOverallStatusColor($status) {
    switch ($status) {
        case 'Cleared':
            return '#4CAF50';
        case 'Conditional Clearance':
            return '#FF9800';
        case 'Not Cleared - Failed Subjects':
            return '#F44336';
        case 'Pending - Incomplete Grades':
            return '#9E9E9E';
        default:
            return '#9E9E9E';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Clearance - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/adminLinks.css">
    <link rel="stylesheet" href="css/adminManagement.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .clearance-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .clearance-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #ddd;
        }
        
        .clearance-card.cleared {
            border-left-color: #4CAF50;
        }
        
        .clearance-card.conditional {
            border-left-color: #FF9800;
        }
        
        .clearance-card.failed {
            border-left-color: #F44336;
        }
        
        .clearance-card.pending {
            border-left-color: #9E9E9E;
        }
        
        .clearance-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .clearance-label {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .status-indicator {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            color: white;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .subject-progress-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .subject-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #ddd;
            transition: transform 0.2s ease;
        }
        
        .subject-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .subject-card.passed {
            border-left-color: #4CAF50;
        }
        
        .subject-card.failed {
            border-left-color: #F44336;
        }
        
        .subject-card.conditional {
            border-left-color: #FF9800;
        }
        
        .subject-card.not-graded {
            border-left-color: #9E9E9E;
        }
        
        .subject-name {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .subject-grade {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .subject-status {
            padding: 4px 12px;
            border-radius: 12px;
            color: white;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .requirements-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .requirements-title {
            font-size: 18px;
            font-weight: bold;
            color: #495057;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .legend {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
        
        .print-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            margin: 10px 0;
            text-decoration: none;
            display: inline-block;
        }
        
        .print-btn:hover {
            background: #5a6268;
        }
        
        @media print {
            .admin-back-btn-wrap, .form-section:first-of-type, .print-btn, footer, header {
                display: none !important;
            }
            .admin-container {
                margin: 0 !important;
                padding: 20px !important;
            }
        }
    </style>
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
        <div class="admin-back-btn-wrap admin-back-btn-upperleft">
            <a href="index.php" class="admin-back-btn"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        <h1 class="page-title">Subject Clearance System</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Selection Form -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-filter"></i> Student Selection</h2>
            <form method="GET" action="adminSubjectClearance.php" style="padding: 20px;">
                <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div class="form-group">
                        <label class="form-label" for="school_year"><i class="fas fa-calendar"></i> School Year</label>
                        <select class="form-select" name="school_year" id="school_year" onchange="this.form.submit()">
                            <option value="">Select School Year</option>
                            <?php foreach ($school_years as $year): ?>
                                <option value="<?php echo htmlspecialchars($year); ?>" <?php echo ($selected_year == $year) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="grade_level"><i class="fas fa-layer-group"></i> Grade Level</label>
                        <select class="form-select" name="grade_level" id="grade_level" onchange="this.form.submit()">
                            <option value="">Select Grade Level</option>
                            <?php foreach ($grade_levels as $grade): ?>
                                <option value="<?php echo htmlspecialchars($grade); ?>" <?php echo ($selected_grade == $grade) ? 'selected' : ''; ?>>
                                    Grade <?php echo htmlspecialchars($grade); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (!empty($students)): ?>
                    <div class="form-group">
                        <label class="form-label" for="student_id"><i class="fas fa-user-graduate"></i> Student</label>
                        <select class="form-select" name="student_id" id="student_id" onchange="this.form.submit()">
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['Student_ID']; ?>" <?php echo ($selected_student == $student['Student_ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['Last_Name'] . ', ' . $student['Given_Name']); ?>
                                    <?php if (!empty($student['Student_Number'])): ?>
                                        (<?php echo htmlspecialchars($student['Student_Number']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </section>
        
        <?php if (!empty($subject_progress)): ?>
        <!-- Student Clearance Overview -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-chart-pie"></i> Clearance Overview</h2>
            <div style="padding: 20px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div class="status-indicator" style="background-color: <?php echo getOverallStatusColor($overall_status); ?>;">
                        <?php echo $overall_status; ?>
                    </div>
                </div>
                
                <div class="clearance-overview">
                    <div class="clearance-card cleared">
                        <div class="clearance-number" style="color: #4CAF50;"><?php echo $passed_subjects; ?></div>
                        <div class="clearance-label">Passed Subjects</div>
                    </div>
                    <div class="clearance-card conditional">
                        <div class="clearance-number" style="color: #FF9800;"><?php echo $conditional_subjects; ?></div>
                        <div class="clearance-label">Conditional</div>
                    </div>
                    <div class="clearance-card failed">
                        <div class="clearance-number" style="color: #F44336;"><?php echo $failed_subjects; ?></div>
                        <div class="clearance-label">Failed Subjects</div>
                    </div>
                    <div class="clearance-card pending">
                        <div class="clearance-number" style="color: #9E9E9E;"><?php echo $not_graded; ?></div>
                        <div class="clearance-label">Not Graded</div>
                    </div>
                </div>
                
                <!-- Legend -->
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #4CAF50;"></div>
                        <span>Passed (>75)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #FF9800;"></div>
                        <span>Conditional (60-74)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #F44336;"></div>
                        <span>Failed (60 below)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #9E9E9E;"></div>
                        <span>Not Graded</span>
                    </div>
                </div>
                
                <button class="print-btn" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Clearance Report
                </button>
            </div>
        </section>
        
        <!-- Requirements -->
        <?php if (!empty($clearance_data['Requirements'])): ?>
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-list-check"></i> Clearance Requirements</h2>
            <div style="padding: 20px;">
                <div class="requirements-box">
                    <div class="requirements-title">
                        <i class="fas fa-clipboard-list"></i>
                        Grade <?php echo htmlspecialchars($selected_grade); ?> Requirements (<?php echo htmlspecialchars($selected_year); ?>)
                    </div>
                    <div><?php echo nl2br(htmlspecialchars($clearance_data['Requirements'])); ?></div>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Subject Progress Details -->
        <section class="form-section">
            <h2 class="form-title"><i class="fas fa-book"></i> Subject Progress Details</h2>
            <div style="padding: 20px;">
                <div class="subject-progress-grid">
                    <?php foreach ($subject_progress as $subject): ?>
                        <div class="subject-card <?php echo strtolower(str_replace(' ', '-', $subject['Status'])); ?>">
                            <div class="subject-name"><?php echo htmlspecialchars($subject['Subject_Name']); ?></div>
                            
                            <?php if (!empty($subject['Description'])): ?>
                                <div style="color: #7f8c8d; font-size: 14px; margin-bottom: 10px;">
                                    <?php echo htmlspecialchars($subject['Description']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="subject-grade" style="color: <?php echo $subject['Status_Color']; ?>;">
                                <?php echo $subject['Grade'] !== null ? $subject['Grade'] . '%' : 'No Grade'; ?>
                            </div>
                            
                            <div class="subject-status" style="background-color: <?php echo $subject['Status_Color']; ?>;">
                                <?php echo $subject['Status']; ?>
                            </div>
                            
                            <?php if (!empty($subject['Record_Date'])): ?>
                                <div style="color: #6c757d; font-size: 12px; margin-top: 10px;">
                                    <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($subject['Record_Date'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <?php if (empty($subject_progress) && $selected_student > 0): ?>
        <section class="form-section">
            <div style="padding: 40px; text-align: center; color: #6c757d;">
                <i class="fas fa-info-circle" style="font-size: 48px; margin-bottom: 20px;"></i>
                <h3>No Subject Records Found</h3>
                <p>No subject records or grades found for the selected student in this academic year.</p>
                <p>Please ensure the student is enrolled and has been assigned grades.</p>
            </div>
        </section>
        <?php elseif (empty($students) && !empty($selected_grade) && !empty($selected_year)): ?>
        <section class="form-section">
            <div style="padding: 40px; text-align: center; color: #6c757d;">
                <i class="fas fa-user-slash" style="font-size: 48px; margin-bottom: 20px;"></i>
                <h3>No Students Found</h3>
                <p>No students found for Grade <?php echo htmlspecialchars($selected_grade); ?> in <?php echo htmlspecialchars($selected_year); ?>.</p>
                <p>Please check the enrollment records or select a different grade level.</p>
            </div>
        </section>
        <?php elseif (empty($selected_grade) || empty($selected_year)): ?>
        <section class="form-section">
            <div style="padding: 40px; text-align: center; color: #6c757d;">
                <i class="fas fa-search" style="font-size: 48px; margin-bottom: 20px;"></i>
                <h3>Select Criteria</h3>
                <p>Please select a school year and grade level to view student clearance information.</p>
            </div>
        </section>
        <?php endif; ?>
    </div>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script>
        // Auto-submit form when selections change
        document.addEventListener('DOMContentLoaded', function() {
            const selects = document.querySelectorAll('select');
            selects.forEach(select => {
                select.addEventListener('change', function() {
                    // Add a small delay to prevent rapid submissions
                    setTimeout(() => {
                        this.form.submit();
                    }, 100);
                });
            });
        });
        
        // Print functionality
        function printClearance() {
            const printWindow = window.open('', '_blank');
            const content = document.querySelector('.admin-container').innerHTML;
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Subject Clearance Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .page-title { text-align: center; color: #2c3e50; }
                        .clearance-overview { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
                        .clearance-card { border: 1px solid #ddd; padding: 15px; text-align: center; }
                        .subject-progress-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px; }
                        .subject-card { border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; }
                        .status-indicator { padding: 5px 10px; border-radius: 15px; color: white; font-weight: bold; }
                        .print-btn, .admin-back-btn-wrap, .form-section:first-of-type { display: none !important; }
                    </style>
                </head>
                <body>
                    ${content}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html>
