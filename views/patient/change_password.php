<?php
require_once '../../includes/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../controllers/PatientController.php';
requireRole('Patient', 'login.php');

$patientId = (int) ($_SESSION['patient_id'] ?? $_SESSION['user_id'] ?? 0);
$controller = new PatientController($pdo);

$flash = '';
$flashType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === '' || strlen($newPassword) < 8) {
        $flash = 'New password must be at least 8 characters long.';
        $flashType = 'danger';
    } elseif ($newPassword !== $confirmPassword) {
        $flash = 'New password and confirmation do not match.';
        $flashType = 'danger';
    } else {
        $result = $controller->changePatientPassword($patientId, $currentPassword, $newPassword);
        $flash = $result['message'];
        $flashType = $result['success'] ? 'success' : 'danger';
    }
}

include '../../includes/header.php';
?>
<div class="workspace-layout">
    <?php include '../../layouts/patient_sidebar.php'; ?>
    <div class="workspace-content">
        <div class="workspace-page-header">
            <div>
                <h1 class="workspace-page-title">Change Password</h1>
                <p class="workspace-page-subtitle">Update your portal password securely.</p>
            </div>
        </div>

        <div class="app-card">
            <?php if (!empty($flash)): ?>
                <div class="alert alert-<?= htmlspecialchars($flashType) ?>" role="alert">
                    <?= htmlspecialchars($flash) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="row g-3" novalidate>
                <div class="col-12">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                <div class="col-12 col-md-6">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                    <div class="form-text">Must be at least 8 characters.</div>
                </div>
                <div class="col-12 col-md-6">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
