<?php
require_once 'includes/db.php';

// Ensure user is logged in and has student permissions
if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Student') {
    header("Location: index.php");
    exit();
}

// Get student data (same as in studentReport.php)
$student_email = $_SESSION['email'];
$student_sql = "SELECT s.Student_ID, pb.Given_Name, pb.Last_Name, pb.Middle_Name, cl.Grade_Level, cl.School_Year
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
    die("Student profile not found.");
}

$student_id = $student_data['Student_ID'];
$student_name = trim($student_data['Given_Name'] . ' ' . $student_data['Middle_Name'] . ' ' . $student_data['Last_Name']);
$selected_year = isset($_POST['year']) ? $_POST['year'] : $student_data['School_Year'];

// Get student's grades
$grades_sql = "SELECT DISTINCT sub.Subject_Name, sub.Subject_ID,
                      AVG(CASE WHEN rd.Quarter = 1 THEN rd.Grade END) as Q1_Grade,
                      AVG(CASE WHEN rd.Quarter = 2 THEN rd.Grade END) as Q2_Grade,
                      AVG(CASE WHEN rd.Quarter = 3 THEN rd.Grade END) as Q3_Grade,
                      AVG(CASE WHEN rd.Quarter = 4 THEN rd.Grade END) as Q4_Grade,
                      AVG(rd.Grade) as Final_Grade
               FROM Record r
               JOIN Record_Details rd ON r.Record_ID = rd.Record_ID
               JOIN Subject sub ON rd.Subject_ID = sub.Subject_ID
               JOIN Clearance cl ON r.Clearance_ID = cl.Clearance_ID
               WHERE r.Student_ID = ? AND cl.School_Year = ?
               GROUP BY sub.Subject_ID, sub.Subject_Name
               ORDER BY sub.Subject_Name";
$stmt = $conn->prepare($grades_sql);
$stmt->bind_param("is", $student_id, $selected_year);
$stmt->execute();
$grades_result = $stmt->get_result();

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Academic_Report_' . str_replace(' ', '_', $student_name) . '_' . $selected_year . '.pdf"');

// Simple PDF generation using HTML to PDF conversion
// For a more robust solution, consider using libraries like TCPDF or FPDF
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Academic Report - <?php echo htmlspecialchars($student_name); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .student-info { margin-bottom: 30px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: center; }
        th { background-color: #f0f0f0; font-weight: bold; }
        td:first-child { text-align: left; }
        .grade-excellent { color: #28a745; font-weight: bold; }
        .grade-very-good { color: #20c997; font-weight: bold; }
        .grade-good { color: #ffc107; font-weight: bold; }
        .grade-satisfactory { color: #fd7e14; font-weight: bold; }
        .grade-conditional { color: #dc3545; font-weight: bold; }
        .grade-failed { color: #6c757d; font-weight: bold; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>ACADEMIC REPORT</h1>
        <h2>ErudLite School Management System</h2>
    </div>
    
    <div class="student-info">
        <div class="info-grid">
            <div><strong>Student Name:</strong> <?php echo htmlspecialchars($student_name); ?></div>
            <div><strong>Grade Level:</strong> Grade <?php echo $student_data['Grade_Level']; ?></div>
            <div><strong>School Year:</strong> <?php echo htmlspecialchars($selected_year); ?></div>
            <div><strong>Generated:</strong> <?php echo date('F j, Y'); ?></div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th rowspan="2">Learning Area</th>
                <th colspan="4">Grading Period</th>
                <th rowspan="2">Final Rating</th>
                <th rowspan="2">Remarks</th>
            </tr>
            <tr>
                <th>1st Quarter</th>
                <th>2nd Quarter</th>
                <th>3rd Quarter</th>
                <th>4th Quarter</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_grades = 0;
            $subject_count = 0;
            while($subject = $grades_result->fetch_assoc()): 
                if ($subject['Final_Grade']) {
                    $total_grades += $subject['Final_Grade'];
                    $subject_count++;
                }
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($subject['Subject_Name']); ?></td>
                    <td><?php echo $subject['Q1_Grade'] ? number_format($subject['Q1_Grade'], 1) : 'N/A'; ?></td>
                    <td><?php echo $subject['Q2_Grade'] ? number_format($subject['Q2_Grade'], 1) : 'N/A'; ?></td>
                    <td><?php echo $subject['Q3_Grade'] ? number_format($subject['Q3_Grade'], 1) : 'N/A'; ?></td>
                    <td><?php echo $subject['Q4_Grade'] ? number_format($subject['Q4_Grade'], 1) : 'N/A'; ?></td>
                    <td><strong><?php echo $subject['Final_Grade'] ? number_format($subject['Final_Grade'], 1) : 'N/A'; ?></strong></td>
                    <td><?php echo $subject['Final_Grade'] && $subject['Final_Grade'] >= 75 ? 'PASSED' : ($subject['Final_Grade'] >= 60 ? 'CONDITIONAL' : 'FAILED'); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 30px;">
        <p><strong>Overall GPA:</strong> <?php echo $subject_count > 0 ? number_format($total_grades / $subject_count, 2) : 'N/A'; ?></p>
        <p><strong>Academic Status:</strong> <?php echo ($subject_count > 0 && ($total_grades / $subject_count) >= 75) ? 'PROMOTED' : 'AT RISK'; ?></p>
    </div>
    
    <div class="footer">
        <p>This is an official academic report generated by ErudLite School Management System</p>
        <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
    </div>
</body>
</html>

<script>
    // Auto-trigger print dialog for PDF generation
    window.onload = function() {
        window.print();
    };
</script>
