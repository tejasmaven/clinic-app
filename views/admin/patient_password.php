<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Admin');

require_once '../../controllers/PatientController.php';
$controller = new PatientController($pdo);

$patientId = (int) ($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);

if ($patientId <= 0) {
    header('Location: manage_patients.php?msg=' . urlencode('Patient not specified.') . '&type=danger');
    exit;
}

$patient = $controller->getPatientById($patientId);

if (!$patient) {
    header('Location: manage_patients.php?msg=' . urlencode('Patient not found.') . '&type=danger');
    exit;
}

$flash = '';
$flashType = 'info';
$hadPassword = !empty($patient['password_hash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $errors = [];

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($confirmPassword === '') {
        $errors[] = 'Please confirm the password.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Password and confirmation do not match.';
    }

    if (!empty($errors)) {
        $flash = implode('<br>', $errors);
        $flashType = 'danger';
    } else {
        $result = $controller->updatePatientPassword($patientId, $password);
        $flash = $result['message'];
        $flashType = $result['success'] ? 'success' : 'danger';
        $hadPassword = $result['hadPassword'] ?? $hadPassword;
        if ($result['success']) {
            $patient['password_hash'] = 'set';
        }
    }
}

include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Set Patient Password</h1>
                <p class="admin-page-subtitle">Create or update a portal password for <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>.</p>
            </div>
            <a href="manage_patients.php" class="btn btn-outline-secondary">&larr; Back to Patients</a>
        </div>

        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?= htmlspecialchars($flashType) ?>" role="alert"><?= $flash ?></div>
        <?php endif; ?>

        <?php if ($hadPassword): ?>
            <div class="alert alert-warning" role="alert">
                A password already exists for this patient. Submitting this form will replace the existing password.
            </div>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                No password is currently set for this patient. Use the form below to create one.
            </div>
        <?php endif; ?>

        <div class="app-card">
            <form method="POST" class="row g-3" novalidate>
                <input type="hidden" name="patient_id" value="<?= (int) $patientId ?>">
                <div class="col-12 col-md-6">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" name="password" id="password" class="form-control" required minlength="8" placeholder="Enter new password">
                </div>
                <div class="col-12 col-md-6">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="8" placeholder="Re-enter new password">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Save Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
