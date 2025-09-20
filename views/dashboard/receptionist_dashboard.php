<?php
require_once '../../includes/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
requireLogin();
requireRole('Receptionist');

$totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();

include '../../includes/header.php';
?>
<div class="workspace-layout">
  <?php include '../../layouts/receptionist_sidebar.php'; ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">Reception Dashboard</h1>
        <p class="workspace-page-subtitle">Keep patient onboarding running smoothly, <?= htmlspecialchars($_SESSION['name']) ?>.</p>
      </div>
    </div>

    <div class="row g-3 g-lg-4">
      <div class="col-12 col-sm-6 col-xl-4">
        <div class="stat-card bg-primary text-white h-100">
          <div class="text-uppercase small text-white-50 fw-semibold">Total Patients</div>
          <div class="display-6 my-2"><?= number_format((int) $totalPatients) ?></div>
          <a href="<?= BASE_URL ?>/views/receptionist/manage_patients.php" class="btn btn-light btn-sm mt-2">Manage Patients</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
