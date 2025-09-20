<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Receptionist');

require_once '../../controllers/PatientController.php';
$controller = new PatientController($pdo);

$id = $_GET['id'] ?? null;
$editing = false;
$patient = [];
$msg = '';
$files = [];

if ($id) {
    $patient = $controller->getPatientById($id);
    $editing = true;
    $files = $controller->getPatientFiles($id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    $data['id'] = $id ?? null;
    $msg = $controller->saveOrUpdatePatient($data);

    if (strpos($msg, 'successfully') !== false) {
        header("Location: manage_patients.php?msg=" . urlencode($msg));
        exit;
    }
}

$referrals = $controller->getReferralSources();
include '../../includes/header.php';
?>
<div class="workspace-layout">
  <?php include '../../layouts/receptionist_sidebar.php'; ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title"><?= $editing ? 'Edit Patient Details' : 'Add New Patient' ?></h1>
        <p class="workspace-page-subtitle">Capture contact information and referral details accurately for every patient.</p>
      </div>
      <div class="d-flex gap-2">
        <a href="manage_patients.php" class="btn btn-outline-secondary">Back to Patients</a>
      </div>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="app-card">
      <form method="POST" id="patientForm" enctype="multipart/form-data">
        <?php include '../../views/shared/patient_form_content.php'; ?>
      </form>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
