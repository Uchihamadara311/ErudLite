<?php
require_once 'db.php';

// Function to populate subjects for each grade level
function populateSubjects($conn) {
    $subjects_by_grade = [
        '1' => ['Mathematics', 'English', 'Filipino', 'Science', 'Araling Panlipunan', 'Music', 'Arts', 'Physical Education', 'Health'],
        '2' => ['Mathematics', 'English', 'Filipino', 'Science', 'Araling Panlipunan', 'Music', 'Arts', 'Physical Education', 'Health'],
        '3' => ['Mathematics', 'English', 'Filipino', 'Science', 'Araling Panlipunan', 'Music', 'Arts', 'Physical Education', 'Health'],
        '4' => ['Mathematics', 'English', 'Filipino', 'Science', 'Araling Panlipunan', 'Music', 'Arts', 'Physical Education', 'Health', 'Technology and Livelihood Education'],
        '5' => ['Mathematics', 'English', 'Filipino', 'Science', 'Araling Panlipunan', 'Music', 'Arts', 'Physical Education', 'Health', 'Technology and Livelihood Education'],
        '6' => ['Mathematics', 'English', 'Filipino', 'Science', 'Araling Panlipunan', 'Music', 'Arts', 'Physical Education', 'Health', 'Technology and Livelihood Education']
    ];

    // Create clearance records for each grade level and school year
    $current_year = date('Y');
    $school_year = $current_year . '-' . ($current_year + 1);
    
    foreach($subjects_by_grade as $grade => $subjects) {
        // Create clearance for this grade level
        $clearance_sql = "INSERT INTO Clearance (School_Year, Term, Grade_Level) VALUES (?, 'First Semester', ?) ON DUPLICATE KEY UPDATE Clearance_ID = LAST_INSERT_ID(Clearance_ID)";
        $stmt = $conn->prepare($clearance_sql);
        $stmt->bind_param("ss", $school_year, $grade);
        $stmt->execute();
        $clearance_id = $conn->insert_id;
        
        foreach($subjects as $subject_name) {
            $subject_sql = "INSERT INTO Subject (Subject_Name, Description, Clearance_ID) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE Subject_ID = Subject_ID";
            $description = "Grade $grade $subject_name curriculum";
            $stmt = $conn->prepare($subject_sql);
            $stmt->bind_param("ssi", $subject_name, $description, $clearance_id);
            $stmt->execute();
        }
    }
    
    echo "Subjects populated successfully!\n";
}

// Function to create sample classrooms
function populateClassrooms($conn) {
    $classrooms = [
        ['101', 'Section A', 1],
        ['102', 'Section B', 1],
        ['103', 'Section C', 1],
        ['201', 'Section A', 2],
        ['202', 'Section B', 2],
        ['203', 'Section C', 2],
        ['301', 'Section A', 3],
        ['302', 'Section B', 3],
        ['303', 'Section C', 3]
    ];
    
    foreach($classrooms as $classroom) {
        $sql = "INSERT INTO Classroom (Room, Section, Floor_No) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE Room_ID = Room_ID";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $classroom[0], $classroom[1], $classroom[2]);
        $stmt->execute();
    }
    
    echo "Classrooms populated successfully!\n";
}

// Function to create sample instructors
function populateInstructors($conn) {
    $instructors = [
        ['Maria', 'Santos', 'Female', '1985-03-15', 'maria.santos@erudlite.edu', 'Mathematics, Science'],
        ['Juan', 'Cruz', 'Male', '1982-07-22', 'juan.cruz@erudlite.edu', 'English, Filipino'],
        ['Ana', 'Reyes', 'Female', '1988-11-08', 'ana.reyes@erudlite.edu', 'Arts, Music'],
        ['Carlos', 'Garcia', 'Male', '1980-04-12', 'carlos.garcia@erudlite.edu', 'Physical Education, Health'],
        ['Sofia', 'Lopez', 'Female', '1987-09-30', 'sofia.lopez@erudlite.edu', 'Araling Panlipunan, TLE']
    ];
    
    foreach($instructors as $instructor) {
        // Create profile first
        $profile_sql = "INSERT INTO Profile (Location_ID, Contacts_ID) VALUES (NULL, NULL)";
        $conn->query($profile_sql);
        $profile_id = $conn->insert_id;
        
        // Create profile bio
        $bio_sql = "INSERT INTO Profile_Bio (Profile_ID, Given_Name, Last_Name, Gender, Date_of_Birth) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($bio_sql);
        $stmt->bind_param("issss", $profile_id, $instructor[0], $instructor[1], $instructor[2], $instructor[3]);
        $stmt->execute();
        
        // Create role/login
        $role_sql = "INSERT INTO Role (Role_Name, Email, Password_Hash, Permissions) VALUES (?, ?, ?, ?)";
        $password_hash = password_hash('password123', PASSWORD_DEFAULT);
        $role_name = 'Instructor';
        $permissions = 'Instructor';
        $stmt = $conn->prepare($role_sql);
        $stmt->bind_param("ssss", $role_name, $instructor[4], $password_hash, $permissions);
        $stmt->execute();
        $role_id = $conn->insert_id;
        
        // Create login info
        $login_sql = "INSERT INTO Login_Info (Status, Last_Login, Updated_At) VALUES ('Active', NOW(), NOW())";
        $conn->query($login_sql);
        $login_id = $conn->insert_id;
        
        // Create account
        $account_sql = "INSERT INTO Account (Profile_ID, Role_ID, Login_ID) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($account_sql);
        $stmt->bind_param("iii", $profile_id, $role_id, $login_id);
        $stmt->execute();
        
        // Create instructor record
        $instructor_sql = "INSERT INTO Instructor (Profile_ID, Hire_Date, Employ_Status, Specialization) VALUES (?, CURDATE(), 'Active', ?)";
        $stmt = $conn->prepare($instructor_sql);
        $stmt->bind_param("is", $profile_id, $instructor[5]);
        $stmt->execute();
        $instructor_id = $conn->insert_id;
        
        // Assign subjects to instructor based on specialization
        $specializations = explode(', ', $instructor[5]);
        foreach($specializations as $subject_name) {
            $subject_sql = "SELECT Subject_ID FROM Subject WHERE Subject_Name LIKE ?";
            $stmt = $conn->prepare($subject_sql);
            $search_term = "%$subject_name%";
            $stmt->bind_param("s", $search_term);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while($row = $result->fetch_assoc()) {
                $assign_sql = "INSERT INTO Assigned_Subject (Instructor_ID, Subject_ID) VALUES (?, ?) ON DUPLICATE KEY UPDATE Instructor_ID = Instructor_ID";
                $stmt2 = $conn->prepare($assign_sql);
                $stmt2->bind_param("ii", $instructor_id, $row['Subject_ID']);
                $stmt2->execute();
            }
        }
    }
    
    echo "Instructors populated successfully!\n";
}

// Function to create sample students
function populateStudents($conn) {
    $students = [
        ['John', 'Doe', 'Male', '2015-05-15', '1'],
        ['Jane', 'Smith', 'Female', '2014-08-22', '2'],
        ['Mark', 'Johnson', 'Male', '2013-12-03', '3'],
        ['Emily', 'Brown', 'Female', '2012-01-18', '4'],
        ['David', 'Wilson', 'Male', '2011-06-25', '5'],
        ['Sarah', 'Taylor', 'Female', '2010-09-14', '6'],
        ['Michael', 'Anderson', 'Male', '2015-02-28', '1'],
        ['Lisa', 'Davis', 'Female', '2014-11-11', '2'],
        ['Robert', 'Miller', 'Male', '2013-07-07', '3'],
        ['Amanda', 'Garcia', 'Female', '2012-04-30', '4']
    ];
    
    foreach($students as $student) {
        // Create profile first
        $profile_sql = "INSERT INTO Profile (Location_ID, Contacts_ID) VALUES (NULL, NULL)";
        $conn->query($profile_sql);
        $profile_id = $conn->insert_id;
        
        // Create profile bio
        $bio_sql = "INSERT INTO Profile_Bio (Profile_ID, Given_Name, Last_Name, Gender, Date_of_Birth) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($bio_sql);
        $stmt->bind_param("issss", $profile_id, $student[0], $student[1], $student[2], $student[3]);
        $stmt->execute();
        
        // Create student record
        $student_sql = "INSERT INTO Student (Profile_ID, Health_Info, Behavior) VALUES (?, 'Healthy', 'Good')";
        $stmt = $conn->prepare($student_sql);
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        $student_id = $conn->insert_id;
        
        // Create a class for this grade level if it doesn't exist
        $grade_level = $student[4];
        $clearance_sql = "SELECT Clearance_ID FROM Clearance WHERE Grade_Level = ? LIMIT 1";
        $stmt = $conn->prepare($clearance_sql);
        $stmt->bind_param("s", $grade_level);
        $stmt->execute();
        $clearance_result = $stmt->get_result();
        
        if($clearance_result->num_rows > 0) {
            $clearance_row = $clearance_result->fetch_assoc();
            $clearance_id = $clearance_row['Clearance_ID'];
            
            // Find or create a classroom for this grade
            $room_sql = "SELECT Room_ID FROM Classroom WHERE Floor_No = ? LIMIT 1";
            $stmt = $conn->prepare($room_sql);
            $floor = (int)$grade_level;
            $stmt->bind_param("i", $floor);
            $stmt->execute();
            $room_result = $stmt->get_result();
            
            if($room_result->num_rows > 0) {
                $room_row = $room_result->fetch_assoc();
                $room_id = $room_row['Room_ID'];
                
                // Create or find class
                $class_sql = "SELECT Class_ID FROM Class WHERE Clearance_ID = ? AND Room_ID = ? LIMIT 1";
                $stmt = $conn->prepare($class_sql);
                $stmt->bind_param("ii", $clearance_id, $room_id);
                $stmt->execute();
                $class_result = $stmt->get_result();
                
                if($class_result->num_rows == 0) {
                    // Create new class
                    $create_class_sql = "INSERT INTO Class (Clearance_ID, Room_ID) VALUES (?, ?)";
                    $stmt = $conn->prepare($create_class_sql);
                    $stmt->bind_param("ii", $clearance_id, $room_id);
                    $stmt->execute();
                    $class_id = $conn->insert_id;
                } else {
                    $class_row = $class_result->fetch_assoc();
                    $class_id = $class_row['Class_ID'];
                }
                
                // Enroll student in class
                $enroll_sql = "INSERT INTO Enrollment (Class_ID, Student_ID, Enrollment_Date, Status) VALUES (?, ?, CURDATE(), 'Active') ON DUPLICATE KEY UPDATE Status = 'Active'";
                $stmt = $conn->prepare($enroll_sql);
                $stmt->bind_param("ii", $class_id, $student_id);
                $stmt->execute();
            }
        }
    }
    
    echo "Students populated successfully!\n";
}

// Main execution
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['populate'])) {
    try {
        populateSubjects($conn);
        populateClassrooms($conn);
        populateInstructors($conn);
        populateStudents($conn);
        echo "<div class='message success'>Database populated successfully!</div>";
    } catch (Exception $e) {
        echo "<div class='message error'>Error populating database: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Populate Database - ErudLite</title>
    <link rel="stylesheet" href="../css/essential.css">
    <link rel="stylesheet" href="../css/adminManagement.css">
</head>
<body>
    <div class="admin-container">
        <h1 class="page-title">Database Population</h1>
        
        <div class="form-section">
            <h2 class="form-title">Populate Database with Sample Data</h2>
            <form method="POST">
                <div style="padding: 20px;">
                    <p>This will populate the database with:</p>
                    <ul>
                        <li>Subjects for Grades 1-6</li>
                        <li>Sample Classrooms</li>
                        <li>Sample Instructors</li>
                        <li>Sample Students</li>
                        <li>Class assignments and enrollments</li>
                    </ul>
                    <button type="submit" name="populate" class="submit-btn">Populate Database</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
