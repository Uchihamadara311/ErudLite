<?php 
require_once 'includes/db.php';
session_start();

// Function to clean input data
function cleanInput($data) {
    return trim(htmlspecialchars($data));
}

// Debug function
function debug_to_console($data, $context = 'Debug') {
    ob_start();
    var_dump($data);
    $output = ob_get_clean();
    echo "<script>console.log('$context:', " . json_encode($output) . ");</script>";
}

// Function to get subject details
function getSubjectDetails($conn, $subject_id) {
    $sql = "SELECT * FROM Subject WHERE Subject_ID = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        debug_to_console($conn->error, 'Prepare Error');
        return null;
    }
    $stmt->bind_param("i", $subject_id);
    if (!$stmt->execute()) {
        debug_to_console($stmt->error, 'Execute Error');
        return null;
    }
    return $stmt->get_result()->fetch_assoc();
}

// Ensure user is logged in and has admin permissions
if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: quickAccess.php");
    exit();
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = isset($_POST['operation']) ? $_POST['operation'] : 'add';
    $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;

    if ($operation == 'delete' && $subject_id > 0) {
        $sql = "DELETE FROM Subject WHERE Subject_ID = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $error_message = "Error preparing delete statement: " . $conn->error;
        } else {
            $stmt->bind_param("i", $subject_id);
            if ($stmt->execute()) {
                $success_message = "Subject deleted successfully!";
            } else {
                $error_message = "Error deleting subject: " . $stmt->error;
            }
        }
    } else {
        $subject_name = cleanInput($_POST['subject_name']);
        $description = cleanInput($_POST['description'] ?? '');
        $grade_level = cleanInput($_POST['grade_level'] ?? '');
        
        // Get or create clearance ID for the grade level
        $clearance_id = null;
        if (!empty($grade_level)) {
            $current_year = date('Y') . '-' . (date('Y') + 1);
            
            // First, try to find an existing clearance record
            $clearance_sql = "SELECT Clearance_ID FROM Clearance WHERE Grade_Level = ? AND School_Year = ? AND Term = 'First Semester' LIMIT 1";
            $clearance_stmt = $conn->prepare($clearance_sql);
            $clearance_stmt->bind_param("ss", $grade_level, $current_year);
            $clearance_stmt->execute();
            $clearance_result = $clearance_stmt->get_result();
            
            if ($clearance_result->num_rows > 0) {
                $clearance_id = $clearance_result->fetch_assoc()['Clearance_ID'];
            } else {
                // Create new clearance record
                $insert_clearance_sql = "INSERT INTO Clearance (School_Year, Term, Grade_Level) VALUES (?, 'First Semester', ?)";
                $insert_clearance_stmt = $conn->prepare($insert_clearance_sql);
                $insert_clearance_stmt->bind_param("ss", $current_year, $grade_level);
                if ($insert_clearance_stmt->execute()) {
                    $clearance_id = $conn->insert_id;
                }
            }
        }
        
        if ($operation == 'edit' && $subject_id > 0) {
            // Update existing subject
            $sql = "UPDATE Subject SET Subject_Name = ?, Description = ?, Clearance_ID = ? WHERE Subject_ID = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $error_message = "Error preparing update statement: " . $conn->error;
            } else {
                $stmt->bind_param("ssii", $subject_name, $description, $clearance_id, $subject_id);
                if ($stmt->execute()) {
                    $success_message = "Subject updated successfully!";
                } else {
                    $error_message = "Error updating subject: " . $stmt->error;
                }
            }
        } else {
            // Insert new subject
            $sql = "INSERT INTO Subject (Subject_Name, Description, Clearance_ID) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $error_message = "Error preparing insert statement: " . $conn->error;
            } else {
                $stmt->bind_param("ssi", $subject_name, $description, $clearance_id);
                if ($stmt->execute()) {
                    $success_message = "Subject added successfully!";
                } else {
                    $error_message = "Error adding subject: " . $stmt->error;
                }
            }
        }
    }
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Management - ErudLite</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/adminLinks.css">
    <link rel="stylesheet" href="css/adminManagement.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
        <div class="admin-back-btn-wrap admin-back-btn-upperleft">
            <a href="adminLinks.php" class="admin-back-btn"><i class="fa fa-arrow-left"></i> Back to Admin Dashboard</a>
        </div>
        <h1 class="page-title">Subject Management</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <section class="form-section">
            <h2 class="form-title" id="form-title"><i class="fas fa-book-open"></i> Add New Subject</h2>
            <form method="POST" action="adminSubjectManagement.php" id="subject-form">
                <input type="hidden" id="operation" name="operation" value="add">
                <input type="hidden" id="subject_id" name="subject_id" value="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="subject_name"><i class="fas fa-graduation-cap"></i> Subject Name *</label>
                        <div class="autocomplete-container">
                            <input class="form-select" type="text" id="subject_name" name="subject_name" placeholder="Enter subject name" required autocomplete="off">
                            <div class="autocomplete-suggestions" id="subject-suggestions"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="grade_level"><i class="fas fa-layer-group"></i> Grade Level *</label>
                        <select class="form-select" name="grade_level" id="grade_level" required>
                            <option value="">Select Grade Level</option>
                            <option value="1">Grade 1</option>
                            <option value="2">Grade 2</option>
                            <option value="3">Grade 3</option>
                            <option value="4">Grade 4</option>
                            <option value="5">Grade 5</option>
                            <option value="6">Grade 6</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label" for="description"><i class="fas fa-align-left"></i> Description</label>
                        <textarea class="form-select" id="description" name="description" placeholder="Enter subject description (optional)" rows="4"></textarea>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="submit-btn" id="submit-btn"><i class="fas fa-plus"></i> Add Subject</button>
                    <button type="button" class="cancel-btn" id="cancel-btn" style="display: none;" onclick="resetForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="delete-btn" id="delete-btn" style="display: none;" onclick="deleteCurrentSubject()">
                        <i class="fas fa-trash-alt"></i> Delete Subject
                    </button>
                </div>
            </form>
        </section>
        
        <section class="table-section">
            <div class="section-header">
                <div class="header-icon-title">
                    <i class="fas fa-book"></i>
                    <h2>Existing Subjects</h2>
                </div>
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchBar" class="search-input" placeholder="Search subjects...">
                </div>
            </div>
            <div class="table-container">
                <table class="data-table" id="subjects-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-book"></i> Subject Name</th>
                            <th><i class="fas fa-align-left"></i> Description</th>
                            <th><i class="fas fa-layer-group"></i> Grade Level</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT s.Subject_ID, s.Subject_Name, s.Description, c.Grade_Level 
                                FROM Subject s 
                                LEFT JOIN Clearance c ON s.Clearance_ID = c.Clearance_ID 
                                ORDER BY c.Grade_Level, s.Subject_Name";
                        $result = $conn->query($sql);
                        
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                $grade_level = $row['Grade_Level'] ?? '';
                                echo "<tr class='clickable-row' onclick='editSubject(" . 
                                     $row['Subject_ID'] . ", " .
                                     json_encode(array(
                                         "subject_name" => $row['Subject_Name'],
                                         "description" => $row['Description'] ?? '',
                                         "grade_level" => $grade_level
                                     )) . ")'>";
                                echo "<td><i class='fas fa-book'></i> " . htmlspecialchars($row['Subject_Name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['Description'] ?: 'No description') . "</td>";
                                if ($grade_level) {
                                    echo "<td><span class='grade-badge grade-level-{$grade_level}'><i class='fas fa-layer-group'></i> Grade {$grade_level}</span></td>";
                                } else {
                                    echo "<td><span class='grade-badge grade-level-none'><i class='fas fa-layer-group'></i> No Grade Assigned</span></td>";
                                }
                                echo "<td class='action-buttons'>";
                                echo "<button class='edit-btn' onclick='event.stopPropagation(); editSubject(" . 
                                     $row['Subject_ID'] . ", " .
                                     json_encode(array(
                                         "subject_name" => $row['Subject_Name'],
                                         "description" => $row['Description'] ?? '',
                                         "grade_level" => $grade_level
                                     )) . ")'><i class='fas fa-edit'></i> Edit</button>";
                                echo "<button class='delete-btn' onclick='event.stopPropagation(); deleteSubject(" . 
                                     $row['Subject_ID'] . ")'><i class='fas fa-trash-alt'></i> Delete</button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='no-data'><i class='fas fa-info-circle'></i> No subjects found. Add your first subject above!</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script src="js/subjectManage.js" defer></script>
</body>
</html>