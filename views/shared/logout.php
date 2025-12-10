<?php
session_start();
$role = $_SESSION['role'] ?? null;
session_unset();
session_destroy();

// Redirect to the appropriate login page
$redirect = '../../views/login.php';
if ($role === 'Patient') {
    $redirect = '../../views/patient/login.php';
}

header("Location: $redirect");
exit;
