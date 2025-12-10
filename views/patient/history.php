<?php
require_once '../../includes/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
requireRole('Patient', 'login.php');

$patientId = (int) ($_SESSION['patient_id'] ?? $_SESSION['user_id'] ?? 0);

$episodeStmt = $pdo->prepare(
    "SELECT id, start_date, status, initial_complaints FROM treatment_episodes WHERE patient_id = ? ORDER BY start_date DESC, id DESC"
);
$episodeStmt->execute([$patientId]);
$episodes = $episodeStmt->fetchAll();

include '../../includes/header.php';
?>
<div class="workspace-layout">
    <?php include '../../layouts/patient_sidebar.php'; ?>
    <div class="workspace-content">
        <div class="workspace-page-header">
            <div>
                <h1 class="workspace-page-title">My History</h1>
                <p class="workspace-page-subtitle">Review your treatment episodes.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="episode_history.php" class="btn btn-outline-primary">Episodes History</a>
            </div>
        </div>

        <div class="app-card mb-4" id="episodes">
            <h5 class="mb-3">Treatment Episodes</h5>
            <?php if (empty($episodes)): ?>
                <p class="text-muted mb-0">No treatment episodes found.</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($episodes as $episode): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold">Episode #<?= (int) $episode['id'] ?></div>
                                    <div class="text-muted small">Started <?= htmlspecialchars(format_display_date($episode['start_date'] ?? '')) ?></div>
                                    <?php if (!empty($episode['initial_complaints'])): ?>
                                        <div class="text-muted small">Notes: <?= htmlspecialchars($episode['initial_complaints']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-light text-dark">Status: <?= htmlspecialchars($episode['status'] ?? 'N/A') ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
