<?php
require_once '../../includes/auth.php';
requireRole('Receptionist');
require_once '../../includes/db.php';
require_once '../../controllers/PatientController.php';

$controller = new PatientController($pdo);
$patient = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $controller->savePatient($_POST);
    if ($result === true) {
        header("Location: manage_patients.php?success=1");
        exit;
    } else {
        $error = $result;
    }
}

if (isset($_GET['id'])) {
    $patient = $controller->getPatientById($_GET['id']);
}
?>

<?php include '../../includes/header.php'; ?>
<div class="container mt-4">
  <h4>Receptionist - <?= isset($patient) ? 'Edit' : 'Add' ?> Patient</h4>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php include '../../views/shared/patient_form_content.php'; ?>
</div>
<?php include '../../includes/footer.php'; ?>
