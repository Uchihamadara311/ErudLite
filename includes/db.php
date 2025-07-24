<?php

// START SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
if (!isset($conn) || $conn === null) {
    // OLD DATABASE = erudlite_2
    // NEW DATABASE = erudlite
    $conn = new mysqli("localhost", "root", "", "erudlite");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}
?>