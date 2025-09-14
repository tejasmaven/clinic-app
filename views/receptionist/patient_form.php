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

if ($id) {
    $patient = $controller->getPatientById($id);
    $editing = true;
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
<div class="row">
  <div class="col-md-3"><?php include '../../layouts/receptionist_sidebar.php'; ?></div>
  <div class="col-md-9">
    <h4><?= $editing ? 'Edit' : 'Add' ?> Patient</h4>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="POST" id="patientForm" enctype="multipart/form-data">
      <?php include '../../views/shared/patient_form_content.php'; ?>
    </form>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>

