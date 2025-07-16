<?php 
require_once 'includes/db.php';

// Only process if POST request
if($_SERVER['REQUEST_METHOD'] == 'POST') {

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

// Instructor fields (optional)
$hire_date = $_POST['hire_date'];
$employ_status = $_POST['employ_status'];
$specialization = $_POST['specialization'];

// 2. Insert into users table
$sql = "INSERT INTO users (first_name, last_name, email, address, nationality, gender, contact_number, emergency_contact, permissions, password_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssssss", $first_name, $last_name, $email, $address, $nationality, $gender, $contact_number, $emergency_contact, $permissions, $password_hash);
$stmt->execute();


$user_id = $conn->insert_id;
// 3. If instructor info provided, insert into instructors table
if ($hire_date || $employ_status || $specialization) {
    $sql2 = "INSERT INTO instructors (instructor_id, hire_date, employ_status, specialization)
             VALUES (?, ?, ?, ?)";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("isss", $user_id, $hire_date, $employ_status, $specialization);
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
</head>
<body>
    <div id="header-placeholder"></div>
    <div class="background-change">test</div>
    <main>
        <form method="POST" action="register.php">
            <h3>Personal Info</h3>
            <input name="first_name" placeholder="First Name" required>
            <input name="last_name" placeholder="Last Name" required>
            <input name="email" type="email" placeholder="Email" required>
            <input name="address" placeholder="Address">
            <input name="nationality" placeholder="Nationality">
            <input name="gender" placeholder="Gender">
            <input name="contact_number" placeholder="Contact Number">
            <input name="emergency_contact" placeholder="Emergency Contact">
            <input name="permissions" placeholder="Permissions">
            <input name="password" type="password" placeholder="Password" required>

            <h3>Instructor Info (if applicable)</h3>
            <input name="hire_date" type="date" placeholder="Hire Date">
            <input name="employ_status" placeholder="Employment Status">
            <input name="specialization" placeholder="Specialization">

            <button type="submit">Register</button>
        </form>
    </main>
    <footer id="footer-placeholder"></footer>
    <script src="js/layout-loader.js"></script>
</body>
</html>