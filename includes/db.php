<?php

// START SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
if (!isset($conn) || $conn === null) {
    $conn = new mysqli("localhost", "root", "", "erudlite_2");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}
?>