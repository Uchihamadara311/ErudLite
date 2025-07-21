<?php
require_once 'db.php';

echo "Starting database population...\n";

try {
    // Clear existing data to start fresh
    $conn->query("DELETE FROM Assigned_Subject");
    $conn->query("DELETE FROM Enrollment"); 
    $conn->query("DELETE FROM Schedule");
    $conn->query("DELETE FROM Instructor");
    $conn->query("DELETE FROM Student");
    $conn->query("DELETE FROM Class");
    $conn->query("DELETE FROM Account");
    $conn->query("DELETE FROM Role WHERE Role_Name != 'Admin'");
    $conn->query("DELETE FROM Login_Info WHERE Login_ID > 1");
    $conn->query("DELETE FROM Profile_Bio WHERE Profile_ID > 1");
    $conn->query("DELETE FROM Profile WHERE Profile_ID > 1");
    $conn->query("DELETE FROM Subject WHERE Subject_ID > 3");
    $conn->query("DELETE FROM Clearance");
    $conn->query("DELETE FROM Classroom");
    
    // Reset auto increment
    $conn->query("ALTER TABLE Instructor AUTO_INCREMENT = 1");
    $conn->query("ALTER TABLE Student AUTO_INCREMENT = 1");
    $conn->query("ALTER TABLE Class AUTO_INCREMENT = 1");
    
    echo "Cleared existing data...\n";
    
    // Create current school year clearances
    $current_year = date('Y');
    $school_year = $current_year . '-' . ($current_year + 1);
    
    $clearance_ids = [];
    for($grade = 1; $grade <= 6; $grade++) {
        $sql = "INSERT INTO Clearance (School_Year, Term, Grade_Level) VALUES (?, 'First Semester', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $school_year, $grade);
        $stmt->execute();
        $clearance_ids[$grade] = $conn->insert_id;
        echo "Created clearance for Grade $grade\n";
    }
    
    // Populate subjects for each grade
    $subjects_by_grade = [
        1 => ['Mathematics', 'English', 'Filipino', 'Science', 'Araling Panlipunan', 'Music', 'Arts', 'Physical Education', 'Health'],
        2 => ['Mathematics', 'English', 'Filipino', 'Science', 'Araling Panlipunan', 'Music', 'Arts', 'Physical Education', 'Health'],
        3 => ['Mathematics', 'English', 'Filipino', 'Science', 'Araling Panlipunan', 'Music', 'Arts', 'Physical Education', 'Health'],
        4 => ['Mathematics', 'English', 'Filipino', 'Science', 'Araling Panlipunan', 'Music', 'Arts', 'Physical Education', 'Health', 'Technology and Livelihood Education'],
        5 => ['Mathematics', 'English', 'Filipino', 'Science', 'Araling Panlipunan', 'Music', 'Arts', 'Physical Education', 'Health', 'Technology and Livelihood Education'],
        6 => ['Mathematics', 'English', 'Filipino', 'Science', 'Araling Panlipunan', 'Music', 'Arts', 'Physical Education', 'Health', 'Technology and Livelihood Education']
    ];
    
    // Clear existing subjects except the first 3
    $conn->query("UPDATE Subject SET Subject_Name = 'Mathematics', Description = 'Grade 1 Mathematics curriculum', Clearance_ID = {$clearance_ids[1]} WHERE Subject_ID = 3");
    $conn->query("UPDATE Subject SET Subject_Name = 'Filipino', Description = 'Grade 1 Filipino curriculum', Clearance_ID = {$clearance_ids[1]} WHERE Subject_ID = 2");
    $conn->query("UPDATE Subject SET Subject_Name = 'Music', Description = 'Grade 1 Music curriculum', Clearance_ID = {$clearance_ids[1]} WHERE Subject_ID = 1");
    
    foreach($subjects_by_grade as $grade => $subjects) {
        foreach($subjects as $subject_name) {
            // Skip if already exists
            $check_sql = "SELECT Subject_ID FROM Subject WHERE Subject_Name = ? AND Clearance_ID = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("si", $subject_name, $clearance_ids[$grade]);
            $stmt->execute();
            if($stmt->get_result()->num_rows == 0) {
                $sql = "INSERT INTO Subject (Subject_Name, Description, Clearance_ID) VALUES (?, ?, ?)";
                $description = "Grade $grade $subject_name curriculum";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $subject_name, $description, $clearance_ids[$grade]);
                $stmt->execute();
            }
        }
        echo "Added subjects for Grade $grade\n";
    }
    
    // Create classrooms
    $classrooms = [
        ['101', 'Section A', 1],
        ['102', 'Section B', 1], 
        ['201', 'Section A', 2],
        ['202', 'Section B', 2],
        ['301', 'Section A', 3],
        ['302', 'Section B', 3],
        ['401', 'Section A', 4],
        ['402', 'Section B', 4],
        ['501', 'Section A', 5],
        ['502', 'Section B', 5],
        ['601', 'Section A', 6],
        ['602', 'Section B', 6]
    ];
    
    foreach($classrooms as $classroom) {
        $sql = "INSERT INTO Classroom (Room, Section, Floor_No) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $classroom[0], $classroom[1], $classroom[2]);
        $stmt->execute();
    }
    echo "Created classrooms\n";
    
    // Create sample instructors
    $instructors = [
        ['Maria', 'Santos', 'Female', '1985-03-15', 'maria.santos@erudlite.edu', 'Mathematics, Science'],
        ['Juan', 'Cruz', 'Male', '1982-07-22', 'juan.cruz@erudlite.edu', 'English, Filipino'], 
        ['Ana', 'Reyes', 'Female', '1988-11-08', 'ana.reyes@erudlite.edu', 'Arts, Music'],
        ['Carlos', 'Garcia', 'Male', '1980-04-12', 'carlos.garcia@erudlite.edu', 'Physical Education, Health'],
        ['Sofia', 'Lopez', 'Female', '1987-09-30', 'sofia.lopez@erudlite.edu', 'Araling Panlipunan, Technology and Livelihood Education']
    ];
    
    foreach($instructors as $instructor) {
        // Create profile
        $conn->query("INSERT INTO Profile (Location_ID, Contacts_ID) VALUES (NULL, NULL)");
        $profile_id = $conn->insert_id;
        
        // Create profile bio
        $sql = "INSERT INTO Profile_Bio (Profile_ID, Given_Name, Last_Name, Gender, Date_of_Birth) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $profile_id, $instructor[0], $instructor[1], $instructor[2], $instructor[3]);
        $stmt->execute();
        
        // Create role
        $sql = "INSERT INTO Role (Role_Name, Email, Password_Hash, Permissions) VALUES (?, ?, ?, ?)";
        $password_hash = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare($sql);
        $role_name = 'Instructor';
        $permissions = 'Instructor';
        $stmt->bind_param("ssss", $role_name, $instructor[4], $password_hash, $permissions);
        $stmt->execute();
        $role_id = $conn->insert_id;
        
        // Create login info
        $conn->query("INSERT INTO Login_Info (Status, Last_Login, Updated_At) VALUES ('Active', NOW(), NOW())");
        $login_id = $conn->insert_id;
        
        // Create account
        $sql = "INSERT INTO Account (Profile_ID, Role_ID, Login_ID) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $profile_id, $role_id, $login_id);
        $stmt->execute();
        
        // Create instructor
        $sql = "INSERT INTO Instructor (Profile_ID, Hire_Date, Employ_Status, Specialization) VALUES (?, CURDATE(), 'Active', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $profile_id, $instructor[5]);
        $stmt->execute();
        $instructor_id = $conn->insert_id;
        
        echo "Created instructor: {$instructor[0]} {$instructor[1]}\n";
        
        // Assign subjects to instructor
        $specializations = explode(', ', $instructor[5]);
        foreach($specializations as $subject_name) {
            $sql = "SELECT Subject_ID FROM Subject WHERE Subject_Name = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $subject_name);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while($row = $result->fetch_assoc()) {
                $sql2 = "INSERT INTO Assigned_Subject (Instructor_ID, Subject_ID) VALUES (?, ?)";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->bind_param("ii", $instructor_id, $row['Subject_ID']);
                $stmt2->execute();
            }
        }
    }
    
    // Create sample students and enroll them
    $students = [
        ['John', 'Doe', 'Male', '2015-05-15', 1],
        ['Jane', 'Smith', 'Female', '2014-08-22', 2], 
        ['Mark', 'Johnson', 'Male', '2013-12-03', 3],
        ['Emily', 'Brown', 'Female', '2012-01-18', 4],
        ['David', 'Wilson', 'Male', '2011-06-25', 5],
        ['Sarah', 'Taylor', 'Female', '2010-09-14', 6],
        ['Michael', 'Anderson', 'Male', '2015-02-28', 1],
        ['Lisa', 'Davis', 'Female', '2014-11-11', 2],
        ['Robert', 'Miller', 'Male', '2013-07-07', 3],
        ['Amanda', 'Garcia', 'Female', '2012-04-30', 4]
    ];
    
    foreach($students as $student) {
        // Create profile
        $conn->query("INSERT INTO Profile (Location_ID, Contacts_ID) VALUES (NULL, NULL)");
        $profile_id = $conn->insert_id;
        
        // Create profile bio
        $sql = "INSERT INTO Profile_Bio (Profile_ID, Given_Name, Last_Name, Gender, Date_of_Birth) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $profile_id, $student[0], $student[1], $student[2], $student[3]);
        $stmt->execute();
        
        // Create student
        $sql = "INSERT INTO Student (Profile_ID, Health_Info, Behavior) VALUES (?, 'Healthy', 'Good')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        $student_id = $conn->insert_id;
        
        echo "Created student: {$student[0]} {$student[1]} (Grade {$student[4]})\n";
        
        // Create class if doesn't exist and enroll student
        $grade = $student[4];
        $clearance_id = $clearance_ids[$grade];
        
        // Find classroom for this grade
        $sql = "SELECT Room_ID FROM Classroom WHERE Floor_No = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $grade);
        $stmt->execute();
        $room_result = $stmt->get_result();
        
        if($room_result->num_rows > 0) {
            $room_id = $room_result->fetch_assoc()['Room_ID'];
            
            // Find or create class
            $sql = "SELECT Class_ID FROM Class WHERE Clearance_ID = ? AND Room_ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $clearance_id, $room_id);
            $stmt->execute();
            $class_result = $stmt->get_result();
            
            if($class_result->num_rows == 0) {
                $sql = "INSERT INTO Class (Clearance_ID, Room_ID) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $clearance_id, $room_id);
                $stmt->execute();
                $class_id = $conn->insert_id;
                echo "Created class for Grade $grade\n";
            } else {
                $class_id = $class_result->fetch_assoc()['Class_ID'];
            }
            
            // Enroll student
            $sql = "INSERT INTO Enrollment (Class_ID, Student_ID, Enrollment_Date, Status) VALUES (?, ?, CURDATE(), 'Active')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $class_id, $student_id);
            $stmt->execute();
        }
    }
    
    echo "\nDatabase populated successfully!\n";
    echo "Created:\n";
    echo "- Subjects for all grade levels\n";
    echo "- 12 Classrooms (2 per grade level)\n"; 
    echo "- 5 Instructors with subject assignments\n";
    echo "- 10 Students enrolled in classes\n";
    echo "- Classes for each grade level\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
