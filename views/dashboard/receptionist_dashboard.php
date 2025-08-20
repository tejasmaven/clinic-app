<?php
require_once '../../includes/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
requireLogin();
requireRole('Receptionist');

$totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();

include '../../includes/header.php';
?>
<div class="row">
  <div class="col-md-3"><?php include '../../layouts/receptionist_sidebar.php'; ?></div>
  <div class="col-md-9">
    <h4>Receptionist Dashboard</h4>
    <p>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!</p>
    <div class="row g-4 mt-3">
      <div class="col-md-4">
        <div class="card text-bg-primary">
          <div class="card-body">
            <h5 class="card-title">Total Patients</h5>
            <p class="display-6"><?= $totalPatients ?></p>
            <a href="<?= BASE_URL ?>/views/receptionist/manage_patients.php" class="btn btn-light btn-sm">Manage Patients</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
