<?php
require_once '../../includes/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../controllers/PatientController.php';
require_once '../../controllers/PaymentController.php';
require_once '../../controllers/TreatmentController.php';
requireRole('Patient', 'login.php');

$patientId = (int) ($_SESSION['patient_id'] ?? $_SESSION['user_id'] ?? 0);

$patientController = new PatientController($pdo);
$paymentController = new PaymentController($pdo);
$treatmentController = new TreatmentController($pdo);

$patient = $patientController->getPatientById($patientId);
if (!$patient) {
    exit('Patient not found.');
}

$pageSizeOptions = [10, 25, 50, 100];
$requestedPageSize = isset($_GET['page_size']) ? (int) $_GET['page_size'] : 10;
$pageSize = in_array($requestedPageSize, $pageSizeOptions, true) ? $requestedPageSize : 10;
$page = max(1, (int) ($_GET['page'] ?? 1));

$offset = ($page - 1) * $pageSize;
$paymentsPage = $paymentController->getPaymentsByPatientPaginated($patientId, $pageSize, $offset);
$totalPayments = $paymentsPage['total'] ?? 0;
$totalPages = max(1, (int) ceil($totalPayments / $pageSize));

if ($totalPayments > 0 && $page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $pageSize;
    $paymentsPage = $paymentController->getPaymentsByPatientPaginated($patientId, $pageSize, $offset);
}

$allPayments = $paymentsPage['data'] ?? [];

$paymentTotals = $paymentController->getPatientTotals($patientId);

$episodeStmt = $pdo->prepare(
    "SELECT id, start_date, status, initial_complaints FROM treatment_episodes WHERE patient_id = ? ORDER BY start_date DESC"
);
$episodeStmt->execute([$patientId]);
$patientEpisodes = $episodeStmt->fetchAll(PDO::FETCH_ASSOC);

$sessionLookupById = [];
$sessionLookupByDate = [];

foreach ($patientEpisodes as $episode) {
    $episodeId = (int) $episode['id'];
    $sessions = $treatmentController->getSessionsWithDetails($patientId, $episodeId);

    foreach ($sessions as $session) {
        $sessionId = (int) $session['id'];
        $sessionWithEpisode = $session + [
            'episode_id' => $episodeId,
            'episode' => [
                'id' => $episodeId,
                'start_date' => $episode['start_date'],
                'status' => $episode['status'] ?? 'Active',
                'initial_complaints' => $episode['initial_complaints'] ?? '',
            ],
        ];

        $sessionLookupById[$sessionId] = $sessionWithEpisode;
        $sessionLookupByDate[$session['session_date']][] = $sessionWithEpisode;
    }
}

function resolveSessionsForPayment(array $payment, array $sessionLookupById, array $sessionLookupByDate) {
    $matches = [];
    $seen = [];
    $episodeFilter = isset($payment['episode_id']) && $payment['episode_id'] !== ''
        ? (int) $payment['episode_id']
        : null;
    $sessionReference = $payment['session_reference'] ?? '';
    $transactionDate = $payment['transaction_date'] ?? null;

    $considerCandidate = function ($candidate) use (&$matches, &$seen, $episodeFilter) {
        $candidateId = (int) $candidate['id'];
        if ($episodeFilter !== null && isset($candidate['episode_id']) && (int) $candidate['episode_id'] !== $episodeFilter) {
            return;
        }

        if (!isset($seen[$candidateId])) {
            $matches[] = $candidate;
            $seen[$candidateId] = true;
        }
    };

    if ($sessionReference) {
        if (strpos($sessionReference, 'session:') === 0) {
            $sessionId = (int) substr($sessionReference, strlen('session:'));
            if (isset($sessionLookupById[$sessionId])) {
                $considerCandidate($sessionLookupById[$sessionId]);
            }
        } elseif (isset($sessionLookupByDate[$sessionReference])) {
            foreach ($sessionLookupByDate[$sessionReference] as $candidate) {
                $considerCandidate($candidate);
            }
        }
    }

    if ($transactionDate && isset($sessionLookupByDate[$transactionDate])) {
        foreach ($sessionLookupByDate[$transactionDate] as $candidate) {
            $considerCandidate($candidate);
        }
    }

    return array_values($matches);
}

include '../../includes/header.php';
?>
<div class="workspace-layout">
    <?php include '../../layouts/patient_sidebar.php'; ?>
    <div class="workspace-content">
        <div class="workspace-page-header">
            <div>
                <h1 class="workspace-page-title">My Payments</h1>
                <p class="workspace-page-subtitle">View your outstanding charges, available credit, and payment history.</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-4">
                <div class="app-card h-100 border-warning border-2">
                    <div class="text-muted fw-semibold">Total Pending Charges</div>
                    <div class="fs-3 mt-2">R <?= number_format($paymentTotals['pending'] ?? 0, 2) ?></div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="app-card h-100 border-success border-2">
                    <div class="text-muted fw-semibold">Available Credit</div>
                    <div class="fs-3 mt-2">R <?= number_format($paymentTotals['credit'] ?? 0, 2) ?></div>
                </div>
            </div>
        </div>

        <div class="app-card">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3 gap-2">
                <div>
                    <h5 class="mb-1">Payment Ledger</h5>
                    <small class="text-muted">Read-only view of your charges and payments. Contact the clinic for billing questions.</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Type</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Status</th>
                            <th scope="col">Session</th>
                            <th scope="col">Notes</th>
                            <th scope="col">Exercises</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($allPayments)): ?>
                            <?php foreach ($allPayments as $pay): ?>
                                <?php
                                    $type = $pay['transaction_type'] === 'charge' ? 'Charge' : 'Payment';
                                    $typeClass = $pay['transaction_type'] === 'charge' ? 'bg-warning text-dark' : 'bg-info text-dark';
                                    $statusClass = $pay['status'] === 'received' ? 'bg-success' : 'bg-secondary';
                                    if ($pay['transaction_type'] === 'charge' && $pay['status'] === 'pending') {
                                        $statusClass = 'bg-warning text-dark';
                                    }
                                    $sessionDisplay = $pay['session_reference'] ? $pay['session_reference'] : '-';
                                    $noteDisplay = $pay['notes'] ? $pay['notes'] : '-';
                                    $matchingSessions = resolveSessionsForPayment($pay, $sessionLookupById, $sessionLookupByDate);
                                    $detailRowId = 'session-detail-' . $pay['id'];
                                ?>
                                <tr data-payment-id="<?= $pay['id'] ?>">
                                    <td><?= htmlspecialchars(format_display_date($pay['transaction_date'])) ?></td>
                                    <td><span class="badge <?= $typeClass ?>"><?= htmlspecialchars($type) ?></span></td>
                                    <td>R <?= number_format((float) $pay['amount'], 2) ?></td>
                                    <td><span class="badge <?= $statusClass ?>"><?= ucfirst(htmlspecialchars($pay['status'])) ?></span></td>
                                    <td><?= htmlspecialchars($sessionDisplay) ?></td>
                                    <td><?= htmlspecialchars($noteDisplay) ?></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-info d-flex align-items-center gap-2 exercise-toggle"
                                            data-target="#<?= $detailRowId ?>"
                                            aria-expanded="false"
                                            aria-controls="<?= $detailRowId ?>"
                                        >
                                            <span class="exercise-indicator">▼</span>
                                            <span>Exercises</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="exercise-detail-row" id="<?= $detailRowId ?>" style="display: none;">
                                    <td colspan="7">
                                        <?php if (!empty($matchingSessions)): ?>
                                            <div class="d-flex flex-column gap-3">
                                                <?php foreach ($matchingSessions as $session): ?>
                                                    <div class="session-detail-card border rounded p-3">
                                                        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-3">
                                                            <div>
                                                                <div class="fw-semibold">Episode #<?= htmlspecialchars($session['episode']['id'] ?? ($session['episode_id'] ?? '')) ?></div>
                                                                <div class="text-muted small">Started <?= htmlspecialchars(format_display_date($session['episode']['start_date'] ?? $session['session_date'])) ?></div>
                                                                <?php if (!empty($session['episode']['initial_complaints'])): ?>
                                                                    <div class="text-muted small">Notes: <?= htmlspecialchars($session['episode']['initial_complaints']) ?></div>
                                                                <?php endif; ?>
                                                                <?php if (!empty($session['episode']['status'])): ?>
                                                                    <span class="badge bg-light text-dark mt-1">Status: <?= htmlspecialchars($session['episode']['status']) ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="text-end">
                                                                <div class="fw-semibold">Session on <?= htmlspecialchars(format_display_date($session['session_date'])) ?></div>
                                                                <?php if (!empty($session['remarks'])): ?>
                                                                    <div class="text-muted small">Doctor's Remarks: <?= htmlspecialchars($session['remarks']) ?></div>
                                                                <?php endif; ?>
                                                                <?php if (!empty($session['progress_notes'])): ?>
                                                                    <div class="text-muted small">Progress Notes: <?= htmlspecialchars($session['progress_notes']) ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <?php if (!empty($session['advise'])): ?>
                                                            <p class="mb-2"><strong>Advise:</strong> <?= htmlspecialchars($session['advise']) ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($session['additional_treatment_notes'])): ?>
                                                            <p class="mb-3"><strong>Additional Treatment Notes:</strong> <?= htmlspecialchars($session['additional_treatment_notes']) ?></p>
                                                        <?php endif; ?>
                                                        <div class="row g-3">
                                                            <div class="col-12 col-md-6">
                                                                <div class="fw-semibold mb-2">Exercises</div>
                                                                <?php if (!empty($session['exercises'])): ?>
                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-hover align-middle mb-0">
                                                                            <thead class="table-light">
                                                                                <tr>
                                                                                    <th scope="col">Exercise</th>
                                                                                    <th scope="col">Reps</th>
                                                                                    <th scope="col">Duration</th>
                                                                                    <th scope="col">Notes</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php foreach ($session['exercises'] as $exercise): ?>
                                                                                    <tr>
                                                                                        <td><?= htmlspecialchars($exercise['name'] ?: ($exercise['exercise_name'] ?? 'Exercise')) ?></td>
                                                                                        <td><?= htmlspecialchars($exercise['reps'] ?? '') ?></td>
                                                                                        <td><?= htmlspecialchars($exercise['duration_minutes'] ?? '') ?></td>
                                                                                        <td><?= htmlspecialchars($exercise['notes'] ?? '') ?></td>
                                                                                    </tr>
                                                                                <?php endforeach; ?>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <p class="text-muted mb-0">No exercises recorded for this session.</p>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="col-12 col-md-6">
                                                                <div class="fw-semibold mb-2">Machines</div>
                                                                <?php if (!empty($session['machines'])): ?>
                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-hover align-middle mb-0">
                                                                            <thead class="table-light">
                                                                                <tr>
                                                                                    <th scope="col">Machine</th>
                                                                                    <th scope="col">Duration</th>
                                                                                    <th scope="col">Notes</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php foreach ($session['machines'] as $machine): ?>
                                                                                    <tr>
                                                                                        <td><?= htmlspecialchars($machine['name'] ?: ($machine['machine_name'] ?? 'Machine')) ?></td>
                                                                                        <td><?= htmlspecialchars($machine['duration_minutes'] ?? '') ?></td>
                                                                                        <td><?= htmlspecialchars($machine['notes'] ?? '') ?></td>
                                                                                    </tr>
                                                                                <?php endforeach; ?>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <p class="text-muted mb-0">No machines recorded for this session.</p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted">No exercise or machine history found for this entry.</div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No payment records available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php
                $startItem = $totalPayments > 0 ? $offset + 1 : 0;
                $endItem = $totalPayments > 0 ? min($offset + $pageSize, $totalPayments) : 0;
                $queryBase = http_build_query(['page_size' => $pageSize]);
            ?>
            <div class="d-flex flex-column gap-3 mt-3">
                <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-center gap-2">
                    <div class="text-muted small">
                        Showing <?= $startItem ?> to <?= $endItem ?> of <?= $totalPayments ?> entries
                    </div>
                    <form method="get" class="d-flex flex-wrap align-items-center gap-2">
                        <label class="form-label mb-0" for="page_size">Rows per page</label>
                        <input type="hidden" name="page" value="1">
                        <select id="page_size" name="page_size" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                            <?php foreach ($pageSizeOptions as $option): ?>
                                <option value="<?= $option ?>" <?= $pageSize === $option ? 'selected' : '' ?>><?= $option ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <nav aria-label="Payment pagination" class="d-flex justify-content-center justify-content-md-end">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= $queryBase ?>&page=<?= max(1, $page - 1) ?>" aria-label="Previous">&laquo;</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= $queryBase ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= $queryBase ?>&page=<?= min($totalPages, $page + 1) ?>" aria-label="Next">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<style>
    .session-detail-card {
        background-color: #f9fbfd;
    }

    .exercise-toggle .exercise-indicator {
        font-size: 0.85rem;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        function updateToggleIndicator(button, expanded) {
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            const indicator = button.querySelector('.exercise-indicator');
            if (indicator) {
                indicator.textContent = expanded ? '▲' : '▼';
            }
        }

        document.querySelectorAll('.exercise-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const targetSelector = btn.getAttribute('data-target');
                const target = document.querySelector(targetSelector);
                if (!target) {
                    return;
                }

                const shouldOpen = target.style.display === 'none' || target.style.display === '';

                document.querySelectorAll('.exercise-detail-row').forEach(function (row) {
                    row.style.display = 'none';
                });

                document.querySelectorAll('.exercise-toggle').forEach(function (button) {
                    button.classList.remove('active');
                    updateToggleIndicator(button, false);
                });

                if (shouldOpen) {
                    target.style.display = 'table-row';
                    btn.classList.add('active');
                    updateToggleIndicator(btn, true);
                }
            });
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>
