<?php
require_once 'db.php';

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Function to get all users
function getAllUsers($conn) {
    $sql = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.address, 
                   u.nationality, u.gender, u.contact_number, u.emergency_contact, 
                   u.permissions, i.specialization 
            FROM users u 
            LEFT JOIN instructors i ON u.user_id = i.instructor_id 
            ORDER BY u.first_name, u.last_name";
    return $conn->query($sql);
}

// Function to get all subjects
function getAllSubjects($conn) {
    $sql = "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name";
    return $conn->query($sql);
}

// Function to get subject count
function getSubjectCount($conn) {
    $sql = "SELECT COUNT(*) as count FROM subjects";
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
function deleteUser($conn, $user_id) {
    $conn->begin_transaction();
    try {
        // Delete from instructor and student tables first
        $conn->prepare("DELETE FROM instructors WHERE instructor_id = ?")->execute([$user_id]);
        $conn->prepare("DELETE FROM students WHERE student_id = ?")->execute([$user_id]);
        
        // Delete from users table
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $conn->commit();
            return "User deleted successfully!";
        } else {
            $conn->rollback();
            return "User not found or already deleted.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        return "Error deleting user: " . $e->getMessage();
    }
}

// Function to update a user
function updateUser($conn, $user_id, $userData) {
    $conn->begin_transaction();
    try {
        // Fetch current user data
        $sql = "SELECT first_name, last_name, email, address, nationality, gender, contact_number, emergency_contact, permissions, password_hash FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
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

        $sql = "UPDATE users SET first_name=?, last_name=?, email=?, address=?, nationality=?, gender=?, contact_number=?, emergency_contact=?, permissions=?, password_hash=? WHERE user_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssssi", $userData['first_name'], $userData['last_name'], $userData['email'], $userData['address'], $userData['nationality'], $userData['gender'], $userData['contact_number'], $userData['emergency_contact'], $userData['permissions'], $password_hash, $user_id);
        $stmt->execute();
        $user_updated = $stmt->affected_rows > 0;

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

        if ($user_updated || $role_updated) {
            $conn->commit();
            return "User updated successfully!";
        } else {
            $conn->rollback();
            return "No changes were made.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        return "Error updating user: " . $e->getMessage();
    }
}

// Function to add a new user
function addUser($conn, $userData) {
    $conn->begin_transaction();
    try {
        // Insert into users table
        $password_hash = password_hash($userData['password'], PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (first_name, last_name, email, address, nationality, gender, contact_number, emergency_contact, permissions, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssss", $userData['first_name'], $userData['last_name'], $userData['email'], $userData['address'], $userData['nationality'], $userData['gender'], $userData['contact_number'], $userData['emergency_contact'], $userData['permissions'], $password_hash);
        
        if (!$stmt->execute()) {
            throw new Exception("Error adding user");
        }
        
        $user_id = $conn->insert_id;
        
        // Add to role-specific table
        if ($userData['permissions'] === 'Instructor') {
            $stmt2 = $conn->prepare("INSERT INTO instructors (instructor_id, hire_date, employ_status, specialization) VALUES (?, CURRENT_DATE, 'Active', ?)");
            $stmt2->bind_param("is", $user_id, $userData['specialization']);
            $stmt2->execute();
        } elseif ($userData['permissions'] === 'Student') {
            $stmt2 = $conn->prepare("INSERT INTO students (student_id) VALUES (?)");
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
        }
        
        $conn->commit();
        return "User registered successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        return "Error: " . $e->getMessage();
    }
}
?>
