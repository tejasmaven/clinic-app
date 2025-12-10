<?php
require_once '../../includes/session.php';
require_once '../../includes/db.php';
require_once '../../controllers/PatientController.php';

$patientController = new PatientController($pdo);
$contactNumber = '';
$patients = [];
$selectedPatientId = 0;
$msg = '';

if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'Patient') {
    header('Location: ' . BASE_URL . '/views/patient/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'lookup';
    $contactNumber = preg_replace('/\D/', '', $_POST['contact_number'] ?? '');

    if (!preg_match('/^[0-9]{10}$/', $contactNumber)) {
        $msg = 'Please enter a valid 10-digit mobile number.';
    } else {
        $patients = $patientController->findPatientsByContactNumber($contactNumber);

        if (empty($patients)) {
            $msg = 'No patient found with this mobile number.';
        } elseif ($action === 'authenticate') {
            $selectedPatientId = (int) ($_POST['patient_id'] ?? 0);
            $password = $_POST['password'] ?? '';

            if ($selectedPatientId <= 0) {
                $msg = 'Please select the correct patient profile.';
            } elseif ($password === '') {
                $msg = 'Password is required to continue.';
            } else {
                $result = $patientController->verifyPatientLogin($selectedPatientId, $contactNumber, $password);

                if ($result['success']) {
                    $patient = $result['patient'];
                    $_SESSION['user_id'] = $patient['id'];
                    $_SESSION['patient_id'] = $patient['id'];
                    $_SESSION['name'] = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
                    $_SESSION['role'] = 'Patient';
                    $_SESSION['contact_number'] = $contactNumber;
                    session_write_close();

                    header('Location: ' . BASE_URL . '/views/patient/dashboard.php');
                    exit;
                }

                $msg = $result['message'] ?? 'Unable to login with those details.';
            }
        }
    }
}

include '../../includes/header.php';
?>

<div class="container" style="max-width: 520px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h3 class="mb-3 text-center">Patient Login</h3>
            <p class="text-muted text-center">Verify your mobile number to continue.</p>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-warning" role="alert">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="mb-3">
                    <label for="contact_number" class="form-label">Mobile Number</label>
                    <input
                        type="tel"
                        class="form-control"
                        id="contact_number"
                        name="contact_number"
                        inputmode="numeric"
                        pattern="[0-9]{10}"
                        minlength="10"
                        maxlength="10"
                        required
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)"
                        value="<?= htmlspecialchars($contactNumber) ?>"
                    >
                    <div class="form-text">Enter your 10-digit registered mobile number.</div>
                </div>

                <?php if (!empty($patients)): ?>
                    <div class="mb-3">
                        <label class="form-label">Select Patient</label>
                        <div class="list-group">
                            <?php foreach ($patients as $patient): ?>
                                <?php $fullName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')); ?>
                                <label class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                                    <input
                                        class="form-check-input flex-shrink-0"
                                        type="radio"
                                        name="patient_id"
                                        value="<?= (int) $patient['id'] ?>"
                                        <?= ($selectedPatientId === (int) $patient['id']) ? 'checked' : '' ?>
                                        required
                                    >
                                    <span class="fw-semibold"><?= htmlspecialchars($fullName) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">If multiple profiles appear, choose the correct one.</div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <input type="hidden" name="action" value="authenticate">
                <?php else: ?>
                    <input type="hidden" name="action" value="lookup">
                <?php endif; ?>

                <button type="submit" class="btn btn-primary w-100">
                    <?= !empty($patients) ? 'Login' : 'Continue' ?>
                </button>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
