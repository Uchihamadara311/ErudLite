<?php 
require_once 'includes/db.php';

if(!isset($_SESSION['email']) || $_SESSION['permissions'] != 'Admin') {
    header("Location: quickAccess.php");
    exit();
}


if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if this is actually user data and not other data
    if (!isset($_POST['first_name']) || !isset($_POST['last_name']) || !isset($_POST['email'])) {
        echo "Invalid form data for user management.";
        exit();
    }
    
    // Instructor fields (Essential)
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $nationality = $_POST['nationality'];
    $gender = $_POST['gender'];
    $contact_number = $_POST['contact_number'];
    $emergency_contact = $_POST['emergency_contact'];
    $permissions = $_POST['permissions'];
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Instructor fields (Optional)
    $specialization = !empty($_POST['specialization']) ? $_POST['specialization'] : '';

    // Instructor Info (Essential)
    $sql = "INSERT INTO users (first_name, last_name, email, address, nationality, gender, contact_number, emergency_contact, permissions, password_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", $first_name, $last_name, $email, $address, $nationality, $gender, $contact_number, $emergency_contact, $permissions, $password_hash);
    $stmt->execute();

    $user_id = $conn->insert_id;
    
    // Instructor Info (Optional)
    if ($permissions === 'Instructor') {
        $sql2 = "INSERT INTO instructors (instructor_id, hire_date, employ_status, specialization)
                 VALUES (?, CURRENT_DATE, 'Active', ?)";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("is", $user_id, $specialization);
        $stmt2->execute();
    }
    
    echo "Registration successful!";
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMS</title>
    <link rel="stylesheet" href="css/essential.css">
    <link rel="stylesheet" href="css/quickAccess.css">
    <style>.hidden { display:none; }</style>
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="background-change">test</div>
    <main>
        <form method="POST" action="adminUserManagement.php">
            <h3>Personal Info</h3>
            <input name="first_name" placeholder="First Name" required>
            <input name="last_name" placeholder="Last Name" required>
            <input name="email" type="email" placeholder="Email" required>
            <input name="address" placeholder="Address" required>
            <input name="nationality" placeholder="Nationality" required>
            <label for="gender">Gender:</label>
            <select name="gender" placeholder="Gender" required>
                <option value="" selected>...</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>
            <input name="contact_number" placeholder="Contact Number" required>
            <input name="emergency_contact" placeholder="Emergency Contact" required>
            <label for="permissions">Role:</label>
            <select name="permissions" id="permissions" required>
                <option value="" selected>...</option>
                <option value="Admin">Admin</option>
                <option value="Instructor">Instructor</option>
                <option value="Student">Student</option>
            </select>
            <input name="password" type="password" placeholder="Password" required>


            <button type="submit">Register</button>
            
            <div id="instructorFields" class="hidden">
                <h3>Instructor Info (optional):</h3>
                <input name="specialization" placeholder="Specialization">
            </div>

        </form>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
    <script>
        document.getElementById('permissions').addEventListener('change', function() {
            var instructorFields = document.getElementById('instructorFields');
            instructorFields.classList.add('hidden');
            if (this.value === 'Instructor') instructorFields.classList.remove('hidden');
        });
        document.addEventListener('DOMContentLoaded', function() {
            var role = document.getElementById('permissions').value;
            var instructorFields = document.getElementById('instructorFields');
            if(role === 'Instructor') instructorFields.classList.remove('hidden');
        });
    </script>
</body>
</html>