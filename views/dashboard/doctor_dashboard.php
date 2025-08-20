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
<div class="row">
  <div class="col-md-3"><?php include '../../layouts/doctor_sidebar.php'; ?></div>
  <div class="col-md-9">
    <h4>Doctor Dashboard</h4>
    <p>Welcome, Dr. <?= htmlspecialchars($_SESSION['name']) ?>!</p>
    <div class="row g-4 mt-3">
      <div class="col-md-4">
        <div class="card text-bg-primary">
          <div class="card-body">
            <h5 class="card-title">Total Patients</h5>
            <p class="display-6"><?= $totalPatients ?></p>
            <a href="<?= BASE_URL ?>/views/doctor/manage_patients.php" class="btn btn-light btn-sm">View Patients</a>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card text-bg-success">
          <div class="card-body">
            <h5 class="card-title">Active Patients</h5>
            <p class="display-6"><?= $activePatients ?></p>
            <a href="<?= BASE_URL ?>/views/doctor/active_patients.php" class="btn btn-light btn-sm">View Active</a>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card text-bg-info">
          <div class="card-body">
            <h5 class="card-title">Total Exercises</h5>
            <p class="display-6"><?= $totalExercises ?></p>
            <a href="<?= BASE_URL ?>/views/doctor/exercises_list.php" class="btn btn-light btn-sm">View Exercises</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
