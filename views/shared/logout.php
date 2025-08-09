<?php
session_start();
session_unset();
session_destroy();

// Redirect to shared login page
header("Location: ../../views/login.php");
exit;
