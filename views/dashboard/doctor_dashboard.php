<?php
require_once '../../includes/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
requireLogin();
requireRole('Doctor');

// Fetch dashboard stats
$totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$activePatients = $pdo->query("SELECT COUNT(DISTINCT patient_id) FROM treatment_episodes WHERE status = 'Active'")->fetchColumn();
$totalExercises = $pdo->query("SELECT COUNT(*) FROM exercises_master")->fetchColumn();

include '../../includes/header.php';
?>
<div class="workspace-layout">
  <?php include '../../layouts/doctor_sidebar.php'; ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">Doctor Dashboard</h1>
        <p class="workspace-page-subtitle">Welcome back, Dr. <?= htmlspecialchars($_SESSION['name']) ?>.</p>
      </div>
    </div>

    <div class="row g-3 g-lg-4">
      <div class="col-12 col-sm-6 col-xl-4">
        <div class="stat-card bg-primary text-white h-100">
          <div class="text-uppercase small text-white-50 fw-semibold">Total Patients</div>
          <div class="display-6 my-2"><?= number_format((int) $totalPatients) ?></div>
          <a href="<?= BASE_URL ?>/views/doctor/manage_patients.php" class="btn btn-light btn-sm mt-2">View Patients</a>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-4">
        <div class="stat-card bg-success text-white h-100">
          <div class="text-uppercase small text-white-50 fw-semibold">Active Patients</div>
          <div class="display-6 my-2"><?= number_format((int) $activePatients) ?></div>
          <a href="<?= BASE_URL ?>/views/doctor/active_patients.php" class="btn btn-light btn-sm mt-2">View Active</a>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-4">
        <div class="stat-card bg-info text-white h-100">
          <div class="text-uppercase small text-white-50 fw-semibold">Total Exercises</div>
          <div class="display-6 my-2"><?= number_format((int) $totalExercises) ?></div>
          <a href="<?= BASE_URL ?>/views/doctor/exercises_list.php" class="btn btn-light btn-sm mt-2">View Exercises</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
