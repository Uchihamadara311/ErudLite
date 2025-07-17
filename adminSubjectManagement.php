<?php 
require_once 'includes/db.php';

if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: quickAccess.php");
    exit();
}


if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = isset($_POST['operation']) ? $_POST['operation'] : 'add';
    $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;

    if ($operation == 'delete' && $subject_id > 0) {
        // Delete subject
        $sql = "DELETE FROM subjects WHERE subject_id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("i", $subject_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $success_message = "Subject deleted successfully!";
                } else {
                    $error_message = "Subject not found or already deleted.";
                }
            } else {
                $error_message = "Error deleting subject: " . $stmt->error;
            }
        } else {
            $error_message = "Error preparing delete statement: " . $conn->error;
        }
    } else {
        $subject_name = $_POST['subject_name'];
        $description = isset($_POST['description']) ? $_POST['description'] : '';
        $grade_level = isset($_POST['grade_level']) ? (int)$_POST['grade_level'] : 0;
        $requirements = isset($_POST['requirements']) ? $_POST['requirements'] : '';

        if ($operation == 'edit' && $subject_id > 0) {
            // Update existing subject using subject_id
            $sql = "UPDATE subjects SET subject_name = ?, description = ?, grade_level = ?, requirements = ? WHERE subject_id = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("ssisi", $subject_name, $description, $grade_level, $requirements, $subject_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success_message = "Subject updated successfully!";
                    } else {
                        $error_message = "No changes were made or subject not found.";
                    }
                } else {
                    $error_message = "Error executing update query: " . $stmt->error;
                }
            } else {
                $error_message = "Error preparing update statement: " . $conn->error;
            }
        } else {
            // Insert new subject
            $sql = "INSERT INTO subjects (subject_name, description, grade_level, requirements) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("ssis", $subject_name, $description, $grade_level, $requirements);
                if ($stmt->execute()) {
                    $success_message = "Subject added successfully!";
                } else {
                    $error_message = "Error executing insert query: " . $stmt->error;
                }
            } else {
                $error_message = "Error preparing insert statement: " . $conn->error;
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
    <link rel="stylesheet" href="css/adminManagement.css">
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="admin-container">
        <h1 class="page-title">Subject Management</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <section class="form-section">
            <h2 class="form-title" id="form-title">Add New Subject</h2>
            <form method="POST" action="adminSubjectManagement.php" id="subject-form">
                <input type="hidden" id="operation" name="operation" value="add">
                <input type="hidden" id="subject_id" name="subject_id" value="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="subject_name">Subject Name *</label>
                        <div class="autocomplete-container">
                            <input class="form-input" type="text" id="subject_name" name="subject_name" placeholder="Enter subject name" required autocomplete="off">
                            <div class="autocomplete-suggestions" id="subject-suggestions"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="grade_level">Grade Level *</label>
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
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-textarea" id="description" name="description" placeholder="Enter subject description (optional)"></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label" for="requirements">Requirements</label>
                        <textarea class="form-textarea" id="requirements" name="requirements" placeholder="Enter subject requirements (optional)"></textarea>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn" id="submit-btn">Add Subject</button>
                <button type="button" class="cancel-btn" id="cancel-btn" style="display: none; margin-left: 10px;" onclick="resetForm()">Cancel</button>
                <button type="button" class="delete-btn" id="delete-btn" style="display: none; margin-left: 10px;" onclick="deleteCurrentSubject()">Delete Subject</button>
            </form>
        </section>
        
        <section class="table-section">
            <div class="table-header">
                <span>Existing Subjects</span>
            </div>
            <div style="padding: 20px; background: white;">
                <input type="text" id="searchBar" class="form-input" placeholder="Search subjects..." style="width: 100%; margin-bottom: 10px;">
            </div>
            <div class="table-container">
                <table class="subjects-table" id="subjects-table">
                    <thead>
                        <tr>
                            <th>Subject Name</th>
                            <th>Description</th>
                            <th>Grade Level</th>
                            <th>Requirements</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT subject_id, subject_name, description, grade_level, requirements FROM subjects ORDER BY grade_level, subject_name";
                        $result = $conn->query($sql);
                        
                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr class='clickable-row' onclick='editSubject(" . 
                                     $row['subject_id'] . ", \"" . 
                                     htmlspecialchars($row['subject_name']) . "\", \"" . 
                                     htmlspecialchars($row['description']) . "\", " . 
                                     $row['grade_level'] . ", \"" . 
                                     htmlspecialchars($row['requirements']) . "\")'>";
                                echo "<td>" . htmlspecialchars($row['subject_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['description'] ?: 'No description') . "</td>";
                                echo "<td><span class='grade-badge'>Grade " . htmlspecialchars($row['grade_level']) . "</span></td>";
                                echo "<td>" . htmlspecialchars($row['requirements'] ?: 'No requirements') . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='no-data'>No subjects found. Add your first subject above!</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script src="js/adminManage.js"></script>
</body>
</html>