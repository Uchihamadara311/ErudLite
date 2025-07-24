<?php
require_once 'db.php';

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Function to get all users
function getAllUsers($conn) {
    $sql = "SELECT 
                pb.Profile_ID,
                pb.Given_Name,
                pb.Last_Name,
                pb.Gender,
                r.Email,
                r.Permissions,
                l.Address,
                l.Nationality,
                c.Contact_Number,
                c.Emergency_Contact,
                i.Specialization
            FROM Profile_Bio pb
            JOIN Profile p ON p.Profile_ID = pb.Profile_ID
            JOIN Account a ON a.Profile_ID = p.Profile_ID
            JOIN Role r ON r.Role_ID = a.Role_ID
            JOIN Location l ON l.Location_ID = p.Location_ID
            JOIN Contacts c ON c.Contacts_ID = p.Contacts_ID
            LEFT JOIN Instructor i ON i.Profile_ID = p.Profile_ID
            ORDER BY pb.Given_Name, pb.Last_Name";
    return $conn->query($sql);
}

function getAllSubjects($conn) {
    $sql = "SELECT Subject_ID, Subject_Name FROM Subject ORDER BY Subject_Name";
    return $conn->query($sql);
}

// Function to get subject count
function getSubjectCount($conn) {
    $sql = "SELECT COUNT(*) as count FROM Subject";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Function to get classes by subject
function getClassesBySubject($conn) {
    $sql = "SELECT s.subject_id, s.subject_name, c.class_id, c.grade_level, c.section, c.room, c.max_students, c.school_year
            FROM subjects s
            JOIN class_subjects cs ON s.subject_id = cs.subject_id
            JOIN classes c ON cs.class_id = c.class_id
            ORDER BY s.subject_name, c.grade_level, c.section";
    return $conn->query($sql);
}

// Function to get all classes
function getAllClasses($conn) {
    $sql = "SELECT class_id, grade_level, section, room, max_students, school_year FROM classes ORDER BY grade_level, section";
    return $conn->query($sql);
}

// Function to add class
function addClass($conn, $classData) {
    $conn->begin_transaction();
    try {
        $sql = "INSERT INTO classes (grade_level, section, room, max_students, school_year) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issii", 
            $classData['grade_level'], 
            $classData['section'], 
            $classData['room'],
            $classData['max_students'],
            $classData['school_year']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error adding class");
        }
        
        $class_id = $conn->insert_id;
        
        // Link class with subjects
        if (isset($classData['subject_ids'])) {
            $sql = "INSERT INTO class_subjects (class_id, subject_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            foreach ($classData['subject_ids'] as $subject_id) {
                $stmt->bind_param("ii", $class_id, $subject_id);
                $stmt->execute();
            }
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// Function to update class
function updateClass($conn, $class_id, $classData) {
    $conn->begin_transaction();
    try {
        $sql = "UPDATE classes SET grade_level=?, section=?, room=?, max_students=?, school_year=? WHERE class_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issiii", 
            $classData['grade_level'], 
            $classData['section'], 
            $classData['room'],
            $classData['max_students'],
            $classData['school_year'],
            $class_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating class");
        }
        
        // Update subject associations if provided
        if (isset($classData['subject_ids'])) {
            // Remove old associations
            $sql = "DELETE FROM class_subjects WHERE class_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            
            // Add new associations
            $sql = "INSERT INTO class_subjects (class_id, subject_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            foreach ($classData['subject_ids'] as $subject_id) {
                $stmt->bind_param("ii", $class_id, $subject_id);
                $stmt->execute();
            }
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// Function to delete class
function deleteClass($conn, $class_id) {
    $conn->begin_transaction();
    try {
        // Delete subject associations first
        $sql = "DELETE FROM class_subjects WHERE class_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        
        // Delete class
        $sql = "DELETE FROM classes WHERE class_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $class_id);
        if (!$stmt->execute()) {
            throw new Exception("Error deleting class");
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// Function to delete a user
function deleteUser($conn, $profile_id) {
    $conn->begin_transaction();
    try {
        // Get IDs needed for deletion
        $stmt = $conn->prepare("SELECT Location_ID, Contacts_ID FROM Profile WHERE Profile_ID = ?");
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("User not found");
        }
        $ids = $result->fetch_assoc();

        // Get Account details for deletion
        $stmt = $conn->prepare("SELECT Role_ID, Login_ID FROM Account WHERE Profile_ID = ?");
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $account_ids = $result->fetch_assoc();

        // Delete in correct order to maintain referential integrity
        
        // 1. Delete from role-specific tables
        $conn->prepare("DELETE FROM Instructor WHERE Profile_ID = ?")->execute([$profile_id]);
        $conn->prepare("DELETE FROM Student WHERE Profile_ID = ?")->execute([$profile_id]);
        
        // 2. Delete from Account and related tables
        $conn->prepare("DELETE FROM Account_Details WHERE Account_ID IN (SELECT Account_ID FROM Account WHERE Profile_ID = ?)")->execute([$profile_id]);
        $conn->prepare("DELETE FROM Account WHERE Profile_ID = ?")->execute([$profile_id]);
        $conn->prepare("DELETE FROM Role WHERE Role_ID = ?")->execute([$account_ids['Role_ID']]);
        $conn->prepare("DELETE FROM Login_Info WHERE Login_ID = ?")->execute([$account_ids['Login_ID']]);
        
        // 3. Delete from Profile and related tables
        $conn->prepare("DELETE FROM Profile_Bio WHERE Profile_ID = ?")->execute([$profile_id]);
        $conn->prepare("DELETE FROM Profile WHERE Profile_ID = ?")->execute([$profile_id]);
        $conn->prepare("DELETE FROM Location WHERE Location_ID = ?")->execute([$ids['Location_ID']]);
        $conn->prepare("DELETE FROM Contacts WHERE Contacts_ID = ?")->execute([$ids['Contacts_ID']]);
        
        $conn->commit();
        return "User deleted successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        return "Error deleting user: " . $e->getMessage();
    }
}

// Function to update a user
function updateUser($conn, $profile_id, $userData) {
    $conn->begin_transaction();
    try {
        // Fetch current user data from multiple tables
        $sql = "SELECT 
                pb.Given_Name, pb.Last_Name, pb.Gender,
                r.Email, r.Password_Hash, r.Permissions,
                l.Nationality, l.Address,
                c.Contact_Number, c.Emergency_Contact,
                i.Specialization
            FROM Profile_Bio pb
            JOIN Profile p ON p.Profile_ID = pb.Profile_ID
            JOIN Account a ON a.Profile_ID = p.Profile_ID
            JOIN Role r ON r.Role_ID = a.Role_ID
            JOIN Location l ON l.Location_ID = p.Location_ID
            JOIN Contacts c ON c.Contacts_ID = p.Contacts_ID
            LEFT JOIN Instructor i ON i.Profile_ID = p.Profile_ID
            WHERE p.Profile_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $conn->rollback();
            return "User not found.";
        }
        $current = $result->fetch_assoc();

        // For each field, if blank, keep old value
        $fields = ['first_name', 'last_name', 'email', 'address', 'nationality', 'gender', 'contact_number', 'emergency_contact', 'permissions'];
        foreach ($fields as $field) {
            if (!isset($userData[$field]) || $userData[$field] === "") {
                $userData[$field] = $current[$field];
            }
        }

        // Password: if blank, keep old hash
        if (!empty($userData['password'])) {
            $password_hash = password_hash($userData['password'], PASSWORD_DEFAULT);
        } else {
            $password_hash = $current['password_hash'];
        }

        // Handle role changes
        $role_updated = false;

        // Handle instructor role
        if ($userData['permissions'] === 'Instructor') {
            $check = $conn->prepare("SELECT instructor_id FROM instructors WHERE instructor_id = ?");
            $check->bind_param("i", $user_id);
            $check->execute();

            // Specialization: if blank, keep old value
            $specialization = $userData['specialization'];
            if ($specialization === "") {
                $spec_stmt = $conn->prepare("SELECT specialization FROM instructors WHERE instructor_id = ?");
                $spec_stmt->bind_param("i", $user_id);
                $spec_stmt->execute();
                $spec_result = $spec_stmt->get_result();
                $specialization = ($spec_result->num_rows > 0) ? $spec_result->fetch_assoc()['specialization'] : "";
            }

            if ($check->get_result()->num_rows > 0) {
                // Update existing instructor
                $update_inst = $conn->prepare("UPDATE instructors SET specialization = ? WHERE instructor_id = ?");
                $update_inst->bind_param("si", $specialization, $user_id);
                $role_updated = $update_inst->execute();
            } else {
                // Create new instructor
                $insert_inst = $conn->prepare("INSERT INTO instructors (instructor_id, hire_date, employ_status, specialization) VALUES (?, CURRENT_DATE, 'Active', ?)");
                $insert_inst->bind_param("is", $user_id, $specialization);
                $role_updated = $insert_inst->execute();
            }
        } else {
            // Delete instructor record if changing from instructor
            $delete_inst = $conn->prepare("DELETE FROM instructors WHERE instructor_id = ?");
            $delete_inst->bind_param("i", $user_id);
            $delete_inst->execute();
        }

        // Handle student role
        if ($userData['permissions'] === 'Student') {
            $check = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
            $check->bind_param("i", $user_id);
            $check->execute();

            if ($check->get_result()->num_rows == 0) {
                // Create new student
                $insert_std = $conn->prepare("INSERT INTO students (student_id) VALUES (?)");
                $insert_std->bind_param("i", $user_id);
                $role_updated = $insert_std->execute();
            }
        } else {
            // Delete student record if changing from student
            $delete_std = $conn->prepare("DELETE FROM students WHERE student_id = ?");
            $delete_std->bind_param("i", $user_id);
            $delete_std->execute();
        }

        // Update Profile_Bio
        $stmt = $conn->prepare("UPDATE Profile_Bio SET Given_Name=?, Last_Name=?, Gender=? WHERE Profile_ID=?");
        $stmt->bind_param("sssi", $userData['first_name'], $userData['last_name'], $userData['gender'], $profile_id);
        $stmt->execute();
        
        // Get Location_ID and Contacts_ID for the profile
        $stmt = $conn->prepare("SELECT Location_ID, Contacts_ID FROM Profile WHERE Profile_ID=?");
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = $result->fetch_assoc();
        
        // Update Location
        $stmt = $conn->prepare("UPDATE Location SET Nationality=?, Address=? WHERE Location_ID=?");
        $stmt->bind_param("ssi", $userData['nationality'], $userData['address'], $ids['Location_ID']);
        $stmt->execute();
        
        // Update Contacts
        $stmt = $conn->prepare("UPDATE Contacts SET Contact_Number=?, Emergency_Contact=? WHERE Contacts_ID=?");
        $stmt->bind_param("ssi", $userData['contact_number'], $userData['emergency_contact'], $ids['Contacts_ID']);
        $stmt->execute();
        
        // Get Role_ID for the account
        $stmt = $conn->prepare("SELECT Role_ID FROM Account WHERE Profile_ID=?");
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $role = $result->fetch_assoc();
        
        // Update Role
        $stmt = $conn->prepare("UPDATE Role SET Email=?, Password_Hash=?, Permissions=? WHERE Role_ID=?");
        $stmt->bind_param("sssi", $userData['email'], $password_hash, $userData['permissions'], $role['Role_ID']);
        $stmt->execute();
        $role_updated = $stmt->affected_rows > 0;

        // Handle role-specific updates
        if ($userData['permissions'] === 'Instructor') {
            $stmt = $conn->prepare("SELECT Profile_ID FROM Instructor WHERE Profile_ID=?");
            $stmt->bind_param("i", $profile_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                // Update existing instructor
                $stmt = $conn->prepare("UPDATE Instructor SET Specialization=? WHERE Profile_ID=?");
                $stmt->bind_param("si", $userData['specialization'], $profile_id);
                $stmt->execute();
            } else {
                // Create new instructor
                $stmt = $conn->prepare("INSERT INTO Instructor (Profile_ID, Hire_Date, Employ_Status, Specialization) VALUES (?, CURRENT_DATE, 'Active', ?)");
                $stmt->bind_param("is", $profile_id, $userData['specialization']);
                $stmt->execute();
            }
            // Remove from Student if exists
            $stmt = $conn->prepare("DELETE FROM Student WHERE Profile_ID=?");
            $stmt->bind_param("i", $profile_id);
            $stmt->execute();
        } elseif ($userData['permissions'] === 'Student') {
            $stmt = $conn->prepare("SELECT Profile_ID FROM Student WHERE Profile_ID=?");
            $stmt->bind_param("i", $profile_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows == 0) {
                // Create new student
                $stmt = $conn->prepare("INSERT INTO Student (Profile_ID) VALUES (?)");
                $stmt->bind_param("i", $profile_id);
                $stmt->execute();
            }
            // Remove from Instructor if exists
            $stmt = $conn->prepare("DELETE FROM Instructor WHERE Profile_ID=?");
            $stmt->bind_param("i", $profile_id);
            $stmt->execute();
        }
        
        $conn->commit();
        return "User updated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        return "Error updating user: " . $e->getMessage();
    }
}

// Function to add a new user
function addUser($conn, $userData) {
    $conn->begin_transaction();
    try {
        // 1. Insert Location
        $stmt = $conn->prepare("INSERT INTO Location (Nationality, Address) VALUES (?, ?)");
        $stmt->bind_param("ss", $userData['nationality'], $userData['address']);
        if (!$stmt->execute()) {
            throw new Exception("Error adding location");
        }
        $location_id = $conn->insert_id;

        // 2. Insert Contacts
        $stmt = $conn->prepare("INSERT INTO Contacts (Contact_Number, Emergency_Contact) VALUES (?, ?)");
        $stmt->bind_param("ss", $userData['contact_number'], $userData['emergency_contact']);
        if (!$stmt->execute()) {
            throw new Exception("Error adding contacts");
        }
        $contacts_id = $conn->insert_id;

        // 3. Insert Profile
        $stmt = $conn->prepare("INSERT INTO Profile (Location_ID, Contacts_ID) VALUES (?, ?)");
        $stmt->bind_param("ii", $location_id, $contacts_id);
        if (!$stmt->execute()) {
            throw new Exception("Error adding profile");
        }
        $profile_id = $conn->insert_id;

        // 4. Insert Profile_Bio
        $stmt = $conn->prepare("INSERT INTO Profile_Bio (Profile_ID, Given_Name, Last_Name, Gender) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $profile_id, $userData['first_name'], $userData['last_name'], $userData['gender']);
        if (!$stmt->execute()) {
            throw new Exception("Error adding profile bio");
        }

        // 5. Insert Role
        $password_hash = password_hash($userData['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO Role (Role_Name, Email, Password_Hash, Permissions) VALUES (?, ?, ?, ?)");
        $role_name = $userData['first_name'] . ' ' . $userData['last_name'];
        $stmt->bind_param("ssss", $role_name, $userData['email'], $password_hash, $userData['permissions']);
        if (!$stmt->execute()) {
            throw new Exception("Error adding role");
        }
        $role_id = $conn->insert_id;

        // 6. Insert Login_Info
        $stmt = $conn->prepare("INSERT INTO Login_Info (Status, Updated_At) VALUES ('Active', NOW())");
        if (!$stmt->execute()) {
            throw new Exception("Error adding login info");
        }
        $login_id = $conn->insert_id;

        // 7. Insert Account
        $stmt = $conn->prepare("INSERT INTO Account (Profile_ID, Role_ID, Login_ID) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $profile_id, $role_id, $login_id);
        if (!$stmt->execute()) {
            throw new Exception("Error adding account");
        }
        $account_id = $conn->insert_id;

        // 8. Add role-specific information
        if ($userData['permissions'] === 'Instructor') {
            $stmt = $conn->prepare("INSERT INTO Instructor (Profile_ID, Hire_Date, Employ_Status, Specialization) VALUES (?, CURRENT_DATE, 'Active', ?)");
            $stmt->bind_param("is", $profile_id, $userData['specialization']);
            if (!$stmt->execute()) {
                throw new Exception("Error adding instructor");
            }
        } elseif ($userData['permissions'] === 'Student') {
            $stmt = $conn->prepare("INSERT INTO Student (Profile_ID) VALUES (?)");
            $stmt->bind_param("i", $profile_id);
            if (!$stmt->execute()) {
                throw new Exception("Error adding student");
            }
        }

        $conn->commit();
        return "User registered successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        return "Error: " . $e->getMessage();
    }
}

// Function to get all instructors for subject assignment
function getAllInstructorsForSubject($conn) {
    $sql = "SELECT 
                i.Instructor_ID,
                pb.Given_Name,
                pb.Last_Name,
                i.Specialization,
                i.Employ_Status
            FROM Instructor i
            JOIN Profile p ON p.Profile_ID = i.Profile_ID
            JOIN Profile_Bio pb ON pb.Profile_ID = p.Profile_ID
            WHERE i.Employ_Status = 'Active'
            ORDER BY pb.Given_Name, pb.Last_Name";
    return $conn->query($sql);
}

// Function to get all subjects with grade levels
function getAllSubjectsWithGrade($conn) {
    $sql = "SELECT 
                Subject_ID,
                Subject_Name,
                Grade_Level,
                Description
            FROM Subject
            ORDER BY Grade_Level, Subject_Name";
    return $conn->query($sql);
}

// Function to get instructor's assigned subjects
function getInstructorSubjects($conn, $instructor_id) {
    $sql = "SELECT 
                a.Instructor_ID,
                a.Subject_ID,
                s.Subject_Name,
                s.Grade_Level,
                s.Description
            FROM Assigned_Subject a
            JOIN Subject s ON s.Subject_ID = a.Subject_ID
            WHERE a.Instructor_ID = ?
            ORDER BY s.Grade_Level, s.Subject_Name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $instructor_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to assign subject to instructor
function assignSubjectToInstructor($conn, $instructor_id, $subject_id) {
    try {
        // Check if assignment already exists
        $check_sql = "SELECT * FROM Assigned_Subject WHERE Instructor_ID = ? AND Subject_ID = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $instructor_id, $subject_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return "This subject is already assigned to this instructor.";
        }
        
        // Insert new assignment
        $sql = "INSERT INTO Assigned_Subject (Instructor_ID, Subject_ID) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $instructor_id, $subject_id);
        
        if ($stmt->execute()) {
            return "Subject assigned successfully!";
        } else {
            return "Error assigning subject: " . $stmt->error;
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Function to unassign subject from instructor
function unassignSubjectFromInstructor($conn, $instructor_id, $subject_id) {
    try {
        $sql = "DELETE FROM Assigned_Subject WHERE Instructor_ID = ? AND Subject_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $instructor_id, $subject_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return "Subject unassigned successfully!";
            } else {
                return "Assignment not found.";
            }
        } else {
            return "Error unassigning subject: " . $stmt->error;
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Function to check schedule conflicts for instructor
function checkInstructorScheduleConflict($conn, $instructor_id, $subject_id) {
    $sql = "SELECT 
                sd.Day,
                sd.Start_Time,
                sd.End_Time,
                s2.Subject_Name as Conflicting_Subject
            FROM Schedule sc1
            JOIN Schedule_Details sd ON sd.Schedule_ID = sc1.Schedule_ID
            JOIN Schedule sc2 ON sc2.Class_ID = sc1.Class_ID
            JOIN Subject s2 ON s2.Subject_ID = sc2.Subject_ID
            WHERE sc1.Instructor_ID = ? 
            AND sc1.Subject_ID = ?
            AND sd.Status = 'Active'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $instructor_id, $subject_id);
    $stmt->execute();
    return $stmt->get_result();
}
?>
