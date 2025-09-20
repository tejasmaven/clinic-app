<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Admin');

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

    // Fallback for PHP 7: use strpos
    if (strpos($msg, 'successfully') !== false) {
        header("Location: manage_patients.php?msg=" . urlencode($msg));
        exit;
    }
}

$referrals = $controller->getReferralSources();
include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title"><?= $editing ? 'Edit' : 'Add' ?> Patient</h1>
                <p class="admin-page-subtitle">Complete the multi-step intake to capture patient information.</p>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-info" role="alert"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="app-card">
            <form method="POST" id="patientForm" enctype="multipart/form-data">
                <?php include '../../views/shared/patient_form_content.php'; ?>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
