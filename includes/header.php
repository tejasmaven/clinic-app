<?php
// includes/header.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['user_id']);
$currentRole = $_SESSION['role'] ?? null;

$roleDashboards = [
    'Admin' => BASE_URL . '/views/admin/index.php',
    'Doctor' => BASE_URL . '/views/dashboard/doctor_dashboard.php',
    'Receptionist' => BASE_URL . '/views/dashboard/receptionist_dashboard.php',
    'Patient' => BASE_URL . '/views/patient/dashboard.php',
];

$dashboardUrl = $roleDashboards[$currentRole] ?? BASE_URL . '/index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hiral Physiotherapy Clinic</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand fw-semibold" href="<?= BASE_URL ?>/index.php">Hiral Physiotherapy Clinic</a>
        <?php if ($isLoggedIn): ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <?php endif; ?>
        <div class="collapse navbar-collapse justify-content-end" id="mainNavbar">
            <?php if ($isLoggedIn): ?>
            <ul class="navbar-nav align-items-lg-center gap-lg-3">
                <li class="nav-item">
                    <a class="nav-link" href="<?= htmlspecialchars($dashboardUrl) ?>">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/views/shared/logout.php">Logout</a>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main class="app-main container-fluid py-4 px-3 px-lg-4">
