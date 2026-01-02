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
        </div>

        <div class="app-card mb-4" id="episodes">
            <h5 class="mb-3">Treatment Episodes</h5>
            <?php if (empty($episodes)): ?>
                <p class="text-muted mb-0">No treatment episodes found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Start Date</th>
                                <th scope="col">Notes</th>
                                <th scope="col" class="text-end">View History</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($episodes as $episode): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars(format_display_date($episode['start_date'] ?? '')) ?></div>
                                        <div class="text-muted small">Episode #<?= (int) $episode['id'] ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($episode['initial_complaints'])): ?>
                                            <?= htmlspecialchars($episode['initial_complaints']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">No notes recorded.</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="episode_history.php?episode_id=<?= (int) $episode['id'] ?>">View History</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
