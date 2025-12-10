<?php
require_once '../../includes/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../controllers/PatientController.php';
requireRole('Patient', 'login.php');

$patientId = (int) ($_SESSION['patient_id'] ?? $_SESSION['user_id'] ?? 0);
$controller = new PatientController($pdo);
$patient = $controller->getPatientById($patientId);

$episodeCountStmt = $pdo->prepare("SELECT COUNT(*) FROM treatment_episodes WHERE patient_id = ?");
$episodeCountStmt->execute([$patientId]);
$episodeCount = (int) $episodeCountStmt->fetchColumn();

$sessionCountStmt = $pdo->prepare("SELECT COUNT(*) FROM treatment_sessions WHERE patient_id = ?");
$sessionCountStmt->execute([$patientId]);
$sessionCount = (int) $sessionCountStmt->fetchColumn();

$lastSessionStmt = $pdo->prepare(
    "SELECT session_date FROM treatment_sessions WHERE patient_id = ? ORDER BY session_date DESC, id DESC LIMIT 1"
);
$lastSessionStmt->execute([$patientId]);
$lastSessionDate = $lastSessionStmt->fetchColumn();

include '../../includes/header.php';
?>
<div class="workspace-layout">
    <?php include '../../layouts/patient_sidebar.php'; ?>
    <div class="workspace-content">
        <div class="workspace-page-header">
            <div>
                <h1 class="workspace-page-title">Patient Dashboard</h1>
                <p class="workspace-page-subtitle">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? '') ?>.</p>
            </div>
        </div>

        <?php if (!$patient): ?>
            <div class="alert alert-danger">Unable to load your profile. Please contact the clinic.</div>
        <?php else: ?>
            <div class="row g-3 g-lg-4 mb-4">
                <div class="col-12 col-md-4">
                    <div class="stat-card bg-primary text-white h-100">
                        <div class="text-uppercase small text-white-50 fw-semibold">Treatment Episodes</div>
                        <div class="display-6 my-2"><?= number_format($episodeCount) ?></div>
                        <a href="<?= BASE_URL ?>/views/patient/history.php" class="btn btn-light btn-sm mt-2">View History</a>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="stat-card bg-success text-white h-100">
                        <div class="text-uppercase small text-white-50 fw-semibold">Total Sessions</div>
                        <div class="display-6 my-2"><?= number_format($sessionCount) ?></div>
                        <a href="<?= BASE_URL ?>/views/patient/history.php#sessions" class="btn btn-light btn-sm mt-2">View Sessions</a>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="stat-card bg-info text-white h-100">
                        <div class="text-uppercase small text-white-50 fw-semibold">Last Visit</div>
                        <div class="fs-4 my-2">
                            <?= $lastSessionDate ? htmlspecialchars(format_display_date($lastSessionDate)) : 'Not recorded' ?>
                        </div>
                        <span class="text-white-50">Contact: <?= htmlspecialchars($patient['contact_number'] ?? '') ?></span>
                    </div>
                </div>
            </div>

            <div class="app-card">
                <h5 class="mb-3">Profile Snapshot</h5>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="fw-semibold text-muted">Name</div>
                        <div><?= htmlspecialchars(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')) ?></div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="fw-semibold text-muted">Mobile</div>
                        <div><?= htmlspecialchars($patient['contact_number'] ?? '') ?></div>
                    </div>
                    <?php if (!empty($patient['email'])): ?>
                        <div class="col-12 col-md-6">
                            <div class="fw-semibold text-muted">Email</div>
                            <div><?= htmlspecialchars($patient['email']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($patient['address'])): ?>
                        <div class="col-12">
                            <div class="fw-semibold text-muted">Address</div>
                            <div><?= nl2br(htmlspecialchars($patient['address'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
