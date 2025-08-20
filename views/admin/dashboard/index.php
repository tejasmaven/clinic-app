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

<div class="row">
    <div class="col-md-3">
        <?php include '../../layouts/admin_sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <h4>Admin Dashboard</h4>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card text-bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Total Doctors</h5>
                        <p class="display-6"><?= $totalDoctors ?></p>
                        <a href="<?= BASE_URL ?>/views/admin/manage_users.php" class="btn btn-light btn-sm">Manage Users</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Total Patients</h5>
                        <p class="display-6"><?= $totalPatients ?></p>
                        <a href="<?= BASE_URL ?>/views/admin/manage_patients.php" class="btn btn-light btn-sm">View Patients</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Active Patients</h5>
                        <p class="display-6"><?= $activePatients ?></p>
                        <a href="<?= BASE_URL ?>/views/admin/manage_patients.php" class="btn btn-light btn-sm">View Active</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Total Exercises</h5>
                        <p class="display-6"><?= $totalExercises ?></p>
                        <a href="<?= BASE_URL ?>/views/admin/manage_exercises.php" class="btn btn-light btn-sm">Manage Exercises</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-bg-secondary">
                    <div class="card-body">
                        <h5 class="card-title">Referral Sources</h5>
                        <p class="display-6"><?= $totalReferrals ?></p>
                        <a href="<?= BASE_URL ?>/views/admin/manage_referrals.php" class="btn btn-light btn-sm">Manage Referrals</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
