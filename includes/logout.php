<?php
require 'db.php';
session_unset();
session_destroy();
header("Location: ../login.php");
exit();
?>
