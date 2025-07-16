<?php 
require_once 'includes/db.php';

if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: quickAccess.php");
    exit();
}


if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_name = $_POST['subject_name'];
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $grade_level = isset($_POST['grade_level']) ? (int)$_POST['grade_level'] : 0;
    $requirements = isset($_POST['requirements']) ? $_POST['requirements'] : '';

    $sql = "INSERT INTO subjects (subject_name, description, grade_level, requirements) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("ssis", $subject_name, $description, $grade_level, $requirements);
        if ($stmt->execute()) {
            $success_message = "Subject added successfully!";
        } else {
            $error_message = "Error executing query: " . $stmt->error;
        }
    } else {
        $error_message = "Error preparing statement: " . $conn->error;
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
    <style>.hidden { display:none; }</style>
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
            <h2 class="form-title">Add New Subject</h2>
            <form method="POST" action="adminSubjectManagement.php">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="subject_name">Subject Name *</label>
                        <input class="form-input" type="text" id="subject_name" name="subject_name" placeholder="Enter subject name" required>
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
                
                <button type="submit" class="submit-btn">Add Subject</button>
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
                        $sql = "SELECT subject_name, description, grade_level, requirements FROM subjects ORDER BY grade_level, subject_name";
                        $result = $conn->query($sql);
                        
                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr>";
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
    <script src="js/search.js" defer></script>
</body>
</html>