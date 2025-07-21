<?php
require_once 'includes/db.php';


// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Set JSON response header
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? cleanInput($_POST['action']) : '';
    
    if ($action == 'get_subjects') {
        $instructor_id = isset($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : 0;
        
        if ($instructor_id > 0) {
            $subjects_sql = "SELECT DISTINCT s.Subject_ID, s.Subject_Name, COALESCE(clr.Grade_Level, 'N/A') as Grade_Level
                             FROM Subject s
                             LEFT JOIN Clearance clr ON s.Clearance_ID = clr.Clearance_ID
                             JOIN Assigned_Subject asub ON s.Subject_ID = asub.Subject_ID
                             WHERE asub.Instructor_ID = ?
                             ORDER BY clr.Grade_Level, s.Subject_Name";
            $stmt = $conn->prepare($subjects_sql);
            $stmt->bind_param("i", $instructor_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $subjects = [];
            while($row = $result->fetch_assoc()) {
                $subjects[] = $row;
            }
            
            echo json_encode(['success' => true, 'subjects' => $subjects]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid instructor ID']);
        }
    }
    
    elseif ($action == 'get_classes') {
        $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
        $year = isset($_POST['year']) ? cleanInput($_POST['year']) : '';
        
        if ($subject_id > 0 && !empty($year)) {
            // Get the grade level for the selected subject first
            $grade_level_sql = "SELECT clr.Grade_Level 
                                FROM Subject s
                                JOIN Clearance clr ON s.Clearance_ID = clr.Clearance_ID
                                WHERE s.Subject_ID = ? LIMIT 1";
            $stmt = $conn->prepare($grade_level_sql);
            $stmt->bind_param("i", $subject_id);
            $stmt->execute();
            $grade_result = $stmt->get_result();
            
            $classes = [];
            
            if ($grade_result->num_rows > 0) {
                $grade_level = $grade_result->fetch_assoc()['Grade_Level'];
                
                // Get classes for the subject's grade level
                $classes_sql = "SELECT c.Class_ID, cl.Grade_Level, cr.Section, cr.Room
                                FROM Class c
                                JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                                JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                                WHERE cl.Grade_Level = ? AND cl.School_Year = ?
                                ORDER BY cr.Section";
                $stmt_classes = $conn->prepare($classes_sql);
                $stmt_classes->bind_param("ss", $grade_level, $year);
                $stmt_classes->execute();
                $result = $stmt_classes->get_result();
                while($row = $result->fetch_assoc()) {
                    $classes[] = $row;
                }
            } else {
                // Fallback: if no grade level found, show all classes for the academic year
                $classes_sql = "SELECT c.Class_ID, cl.Grade_Level, cr.Section, cr.Room
                                FROM Class c
                                JOIN Clearance cl ON c.Clearance_ID = cl.Clearance_ID
                                JOIN Classroom cr ON c.Room_ID = cr.Room_ID
                                WHERE cl.School_Year = ?
                                ORDER BY cl.Grade_Level, cr.Section";
                $stmt_classes = $conn->prepare($classes_sql);
                $stmt_classes->bind_param("s", $year);
                $stmt_classes->execute();
                $result = $stmt_classes->get_result();
                while($row = $result->fetch_assoc()) {
                    $classes[] = $row;
                }
            }
            
            echo json_encode(['success' => true, 'classes' => $classes]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid subject ID or year']);
        }
    }
    
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
