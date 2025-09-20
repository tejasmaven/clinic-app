<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Admin');

// Fetch counts
$totalDoctors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Doctor' AND is_deleted = 0")->fetchColumn();
$totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$activePatients = $pdo->query("SELECT COUNT(DISTINCT patient_id) FROM treatment_episodes WHERE status = 'Active'")->fetchColumn();
$totalExercises = $pdo->query("SELECT COUNT(*) FROM exercises_master")->fetchColumn();
$totalReferrals = $pdo->query("SELECT COUNT(*) FROM referral_sources")->fetchColumn();

include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Admin Dashboard</h1>
                <p class="admin-page-subtitle">A quick snapshot of clinic activity across teams.</p>
            </div>
        </div>

        <div class="row g-3 g-lg-4">
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-3">
                <div class="stat-card bg-primary text-white h-100">
                    <div class="text-uppercase small text-white-50 fw-semibold">Total Doctors</div>
                    <div class="display-6 my-2"><?= number_format((int) $totalDoctors) ?></div>
                    <a href="<?= BASE_URL ?>/views/admin/manage_users.php" class="btn btn-light btn-sm mt-2">Manage Users</a>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-3">
                <div class="stat-card bg-success text-white h-100">
                    <div class="text-uppercase small text-white-50 fw-semibold">Total Patients</div>
                    <div class="display-6 my-2"><?= number_format((int) $totalPatients) ?></div>
                    <a href="<?= BASE_URL ?>/views/admin/manage_patients.php" class="btn btn-light btn-sm mt-2">View Patients</a>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-3">
                <div class="stat-card bg-warning text-dark h-100">
                    <div class="text-uppercase small text-dark fw-semibold">Active Patients</div>
                    <div class="display-6 my-2"><?= number_format((int) $activePatients) ?></div>
                    <a href="<?= BASE_URL ?>/views/admin/manage_patients.php" class="btn btn-outline-dark btn-sm mt-2">View Active</a>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-3">
                <div class="stat-card bg-info text-white h-100">
                    <div class="text-uppercase small text-white-50 fw-semibold">Total Exercises</div>
                    <div class="display-6 my-2"><?= number_format((int) $totalExercises) ?></div>
                    <a href="<?= BASE_URL ?>/views/admin/manage_exercises.php" class="btn btn-light btn-sm mt-2">Manage Exercises</a>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-3">
                <div class="stat-card bg-secondary text-white h-100">
                    <div class="text-uppercase small text-white-50 fw-semibold">Referral Sources</div>
                    <div class="display-6 my-2"><?= number_format((int) $totalReferrals) ?></div>
                    <a href="<?= BASE_URL ?>/views/admin/manage_referrals.php" class="btn btn-light btn-sm mt-2">Manage Referrals</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
