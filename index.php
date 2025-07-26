<?php require 'includes/db.php';

if(!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// For students, check enrollment status
$is_enrolled = true; // Default for non-students
if(isset($_SESSION['permissions']) && $_SESSION['permissions'] === 'Student') {
    $student_email = $_SESSION['email'];
    
    // Check if student has active enrollment
    $enrollment_check_sql = "SELECT s.Student_ID
                            FROM Student s 
                            JOIN Profile p ON s.Profile_ID = p.Profile_ID 
                            JOIN Account a ON p.Profile_ID = a.Profile_ID
                            JOIN Role r ON a.Role_ID = r.Role_ID
                            JOIN Enrollment e ON s.Student_ID = e.Student_ID
                            WHERE r.Email = ? AND e.Status = 'Active'
                            LIMIT 1";
    $stmt = $conn->prepare($enrollment_check_sql);
    $stmt->bind_param("s", $student_email);
    $stmt->execute();
    $enrollment_result = $stmt->get_result();
    $is_enrolled = ($enrollment_result->fetch_assoc() !== null);
}

?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Access - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/quickAccess.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .disabled-link {
            opacity: 0.5;
            pointer-events: none;
            cursor: not-allowed;
        }
        
        .disabled-link:hover {
            transform: none !important;
            box-shadow: none !important;
        }
    </style>
   
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="background-change"></div>
    <main>
        <div class="choice-section">
            <?php if(isset($_SESSION['permissions']) && $_SESSION['permissions'] === 'Admin'): ?>
                <!-- Admin Options -->
                <a href="adminSubjectManagement.php">
                    <span>
                        <i class="fa-solid fa-book" style="font-size: 2em; margin-bottom: 5px;"></i>
                        <br>
                        Subject<br>Management
                    </span>
                </a>
                <a href="adminLinks.php">
                    <span>
                        <i class="icon fa-solid fa-users"></i>
                        <br>Admin<br>Dashboard
                    </span>
                </a>
                <a href="adminEnrollment.php">
                    <span>
                        <i class="icon fa-solid fa-user-graduate"></i>
                        <br>Enrollment<br>Management
                    </span>
                </a>
                <a href="manageAccount.php">
                    <span>
                        <i class="icon fa-solid fa-cog"></i>
                        <br>Manage<br>Account
                    </span>
                </a>
            <?php elseif(isset($_SESSION['permissions']) && $_SESSION['permissions'] === 'Instructor'): ?>
                <!-- Instructor Options -->
                <a href="instructorAttendanceManagement.php">
                    <span>
                        <i class="fa-solid fa-award" style="font-size: 2em; margin-bottom: 5px;"></i>
                        <br>
                        Attendance<br>Management
                    </span>
                </a>
                <a href="instructorRecord.php">
                    <span>
                        <i class="icon fa-solid fa-chart-simple"></i>
                        <br>Grade<br>Management
                    </span>
                </a>
                <a href="instructorSchedule.php">
                    <span>
                        <i class="icon fa-solid fa-calendar-days"></i>
                        <br>Schedule<br>and Calendar
                    </span>
                </a>
                <a href="instructorSubjectClearance.php">
                    <span>
                        <i class="icon fa-regular fa-square-check"></i>
                        <br>Subject<br>Clearance
                    </span>
                </a>
            <?php else: ?>
                <!-- Student Options -->
                <a href="studentDashboard.php">
                    <span>
                        <i class="fa-solid fa-tachometer-alt" style="font-size: 2em; margin-bottom: 5px;"></i>
                        <br>
                        Student<br>Dashboard
                    </span>
                </a>
                <a href="<?php echo $is_enrolled ? 'studentReport.php' : '#'; ?>" class="<?php echo !$is_enrolled ? 'disabled-link' : ''; ?>">
                    <span>
                        <i class="icon fa-solid fa-chart-simple"></i>
                        <br>Student Report<br><?php echo $is_enrolled ? 'Card' : '(Enrollment Required)'; ?>
                    </span>
                </a>
                <a href="<?php echo $is_enrolled ? 'studentSchedule.php' : '#'; ?>" class="<?php echo !$is_enrolled ? 'disabled-link' : ''; ?>">
                    <span>
                        <i class="icon fa-solid fa-calendar-days"></i>
                        <br>Schedule<br><?php echo $is_enrolled ? 'and Calendar' : '(Enrollment Required)'; ?>
                    </span>
                </a>
                <a href="<?php echo $is_enrolled ? 'studentClearance.php' : '#'; ?>" class="<?php echo !$is_enrolled ? 'disabled-link' : ''; ?>">
                    <span>
                        <i class="icon fa-regular fa-square-check"></i>
                        <br>Subject<br><?php echo $is_enrolled ? 'Clearance' : '(Enrollment Required)'; ?>
                    </span>
                </a>
            <?php endif; ?>
        </div>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script src="js/hover-background.js"></script>
    
    <script>
        // Handle disabled links for non-enrolled students
        document.addEventListener('DOMContentLoaded', function() {
            const disabledLinks = document.querySelectorAll('.disabled-link');
            disabledLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    alert('This feature requires active enrollment. Please contact the registrar\'s office at registrar@erudlite.edu or call (555) 123-4567 to complete your enrollment process.');
                });
            });
        });
    </script>
</body>
</html>