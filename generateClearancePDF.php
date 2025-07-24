<?php
require_once 'includes/db.php';

// Ensure user is logged in and has student permissions
if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Student') {
    header("Location: index.php");
    exit();
}

// Get student data
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
    die("Student profile not found.");
}

$student_id = $student_data['Student_ID'];
$student_name = trim($student_data['Given_Name'] . ' ' . $student_data['Last_Name']);
$selected_subject_id = $_POST['subject_id'] ?? '';
$selected_term = $_POST['term'] ?? '';

// Get clearance data
$clearance_data = [];
$subject_name = '';

if ($selected_subject_id && $selected_term) {
    // Get subject information
    $subject_info_sql = "SELECT sub.Subject_Name FROM Subject sub WHERE sub.Subject_ID = ?";
    $stmt = $conn->prepare($subject_info_sql);
    $stmt->bind_param("i", $selected_subject_id);
    $stmt->execute();
    $subject_info_result = $stmt->get_result();
    $subject_info = $subject_info_result->fetch_assoc();
    $subject_name = $subject_info['Subject_Name'] ?? 'Unknown Subject';
    
    // Get clearance requirements
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

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Clearance_' . str_replace(' ', '_', $student_name) . '_' . str_replace(' ', '_', $subject_name) . '.pdf"');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Student Clearance - <?php echo htmlspecialchars($student_name); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .student-info { margin-bottom: 30px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .requirements { margin-bottom: 20px; }
        .requirement-item { padding: 10px; border: 1px solid #ddd; margin: 5px 0; display: flex; justify-content: space-between; }
        .cleared { color: #28a745; font-weight: bold; }
        .conditional { color: #ffc107; font-weight: bold; }
        .not-cleared { color: #dc3545; font-weight: bold; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>STUDENT CLEARANCE REPORT</h1>
        <h2>ErudLite School Management System</h2>
    </div>
    
    <div class="student-info">
        <div class="info-grid">
            <div><strong>Student Name:</strong> <?php echo htmlspecialchars($student_name); ?></div>
            <div><strong>Grade Level:</strong> Grade <?php echo $student_data['Grade_Level']; ?></div>
            <div><strong>Subject:</strong> <?php echo htmlspecialchars($subject_name); ?></div>
            <div><strong>Term:</strong> <?php echo htmlspecialchars($selected_term); ?></div>
        </div>
    </div>
    
    <div class="requirements">
        <h3>Clearance Requirements and Status</h3>
        <?php if (!empty($clearance_data)): ?>
            <?php foreach($clearance_data as $clearance): ?>
                <?php if ($clearance['Requirements']): ?>
                    <?php 
                    $requirements = explode("\n", $clearance['Requirements']);
                    foreach($requirements as $requirement): 
                        if (trim($requirement)):
                            $status = '';
                            $class = '';
                            if ($clearance['Grade'] >= 75) {
                                $status = 'CLEARED';
                                $class = 'cleared';
                            } elseif ($clearance['Grade'] >= 60) {
                                $status = 'CONDITIONAL';
                                $class = 'conditional';
                            } elseif ($clearance['Grade']) {
                                $status = 'NOT CLEARED';
                                $class = 'not-cleared';
                            } else {
                                $status = 'NO GRADE';
                                $class = 'no-grade';
                            }
                    ?>
                        <div class="requirement-item">
                            <span><?php echo htmlspecialchars(trim($requirement)); ?></span>
                            <span class="<?php echo $class; ?>">
                                <?php echo $status; ?>
                                <?php if ($clearance['Grade']): ?>
                                    (Grade: <?php echo number_format($clearance['Grade'], 1); ?>)
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                <?php else: ?>
                    <div class="requirement-item">
                        <span>General clearance requirement</span>
                        <span class="<?php echo $class; ?>">
                            <?php echo $status; ?>
                            <?php if ($clearance['Grade']): ?>
                                (Grade: <?php echo number_format($clearance['Grade'], 1); ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No clearance requirements found for the selected subject and term.</p>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <p>This is an official clearance report generated by ErudLite School Management System</p>
        <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
    </div>
</body>
</html>

<script>
    window.onload = function() {
        window.print();
    };
</script>
