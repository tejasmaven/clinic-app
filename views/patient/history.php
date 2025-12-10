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

$sessionStmt = $pdo->prepare(
    "SELECT ts.id, ts.session_date, ts.remarks, ts.progress_notes, ts.advise, ts.additional_treatment_notes, ts.episode_id, te.status AS episode_status, te.initial_complaints
     FROM treatment_sessions ts
     LEFT JOIN treatment_episodes te ON ts.episode_id = te.id
     WHERE ts.patient_id = ?
     ORDER BY ts.session_date DESC, ts.id DESC"
);
$sessionStmt->execute([$patientId]);
$sessions = $sessionStmt->fetchAll();

include '../../includes/header.php';
?>
<div class="workspace-layout">
    <?php include '../../layouts/patient_sidebar.php'; ?>
    <div class="workspace-content">
        <div class="workspace-page-header">
            <div>
                <h1 class="workspace-page-title">My History</h1>
                <p class="workspace-page-subtitle">Review your treatment episodes and session notes.</p>
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

        <div class="app-card" id="sessions">
            <h5 class="mb-3">Session Notes</h5>
            <?php if (empty($sessions)): ?>
                <p class="text-muted mb-0">No session records available.</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($sessions as $session): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold">Session on <?= htmlspecialchars(format_display_date($session['session_date'] ?? '')) ?></div>
                                    <?php if (!empty($session['episode_id'])): ?>
                                        <div class="text-muted small">Episode #<?= (int) $session['episode_id'] ?> (<?= htmlspecialchars($session['episode_status'] ?? 'N/A') ?>)</div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($session['advise'])): ?>
                                    <span class="badge bg-primary-subtle text-primary">Advice Given</span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($session['remarks'])): ?>
                                <p class="mb-1"><strong>Doctor's Remarks:</strong> <?= htmlspecialchars($session['remarks']) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($session['progress_notes'])): ?>
                                <p class="mb-1"><strong>Progress Notes:</strong> <?= htmlspecialchars($session['progress_notes']) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($session['advise'])): ?>
                                <p class="mb-1"><strong>Advice:</strong> <?= htmlspecialchars($session['advise']) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($session['additional_treatment_notes'])): ?>
                                <p class="mb-0"><strong>Additional Notes:</strong> <?= htmlspecialchars($session['additional_treatment_notes']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
