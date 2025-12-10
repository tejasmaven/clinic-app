<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(?string $redirect = null) {
    if (empty($_SESSION['user_id'])) {
        $redirectTo = $redirect ?? '../login.php';
        header("Location: $redirectTo"); // Adjust path if needed
        exit;
    }
}

function requireRole($role, ?string $redirect = null) {
    $redirectTo = $redirect ?? '../login.php';
    requireLogin($redirectTo);
    if ($_SESSION['role'] !== $role) {
        header("Location: $redirectTo");
        exit;
    }
}
