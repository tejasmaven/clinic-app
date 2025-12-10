<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
if (!in_array($_SESSION['role'] ?? '', ['Admin','Doctor','Receptionist'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../../controllers/PatientController.php';
require_once '../../controllers/PaymentController.php';
require_once '../../controllers/TreatmentController.php';
$patientController = new PatientController($pdo);
$paymentController = new PaymentController($pdo);
$treatmentController = new TreatmentController($pdo);

$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
if ($patientId <= 0) {
    exit('Invalid patient ID.');
}

$patient = $patientController->getPatientById($patientId);
if (!$patient) {
    exit('Patient not found.');
}

$msg = null;
$msgType = 'warning';
$patientEpisodes = [];
$sessionLookupById = [];
$sessionLookupByDate = [];
$paymentDateValue = date('Y-m-d');
$amountValue = '';
$notesValue = $_POST['notes'] ?? '';
$isEditing = false;
$editingPaymentId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

if ($editingPaymentId > 0) {
    $editingPayment = $paymentController->getPaymentById($editingPaymentId);
    if ($editingPayment && (int) $editingPayment['patient_id'] === $patientId && $editingPayment['transaction_type'] === 'payment') {
        $isEditing = true;
        $paymentDateValue = $editingPayment['transaction_date'];
        $amountValue = number_format((float) $editingPayment['amount'], 2, '.', '');
        $notesValue = $editingPayment['notes'] ?? '';
    } else {
        $msg = 'Only credit entries can be edited.';
        $editingPaymentId = 0;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    if ($paymentController->deletePayment((int)$_GET['delete'])) {
        header('Location: manage_payments.php?patient_id=' . $patientId);
        exit;
    }

    $msg = 'Only credit entries can be deleted.';
}

// Gather episode and session details for exercise/machine lookups
$episodeStmt = $pdo->prepare("SELECT id, start_date, status, initial_complaints FROM treatment_episodes WHERE patient_id = ? ORDER BY start_date DESC");
$episodeStmt->execute([$patientId]);
$patientEpisodes = $episodeStmt->fetchAll(PDO::FETCH_ASSOC);

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_fee_edit']) && $_POST['bulk_fee_edit'] === '1') {
        $bulkFeeData = $_POST['fees'] ?? [];
        $updates = [];

        foreach ($bulkFeeData as $feeId => $fee) {
            if (!isset($fee['selected'])) {
                continue;
            }

            $updates[] = [
                'id' => (int) $feeId,
                'amount' => isset($fee['amount']) ? (float) $fee['amount'] : 0,
                'notes' => trim((string) ($fee['notes'] ?? '')),
            ];
        }

        if (empty($updates)) {
            $msg = 'Select at least one charge to update.';
        } else {
            try {
                $updatedCount = $paymentController->bulkUpdateCharges($patientId, $updates);
                $msg = $updatedCount . ' charge' . ($updatedCount === 1 ? '' : 's') . ' updated successfully.';
                $msgType = 'success';
                header('Location: manage_payments.php?patient_id=' . $patientId);
                exit;
            } catch (Exception $e) {
                $msg = 'Unable to update charges: ' . $e->getMessage();
            }
        }
    } else {
        $paymentDateValue = $_POST['payment_date'] ?? $paymentDateValue;
        $amountValue = $_POST['amount'] ?? $amountValue;
        $notesValue = $_POST['notes'] ?? $notesValue;

        $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
        $transactionType = $_POST['transaction_type'] ?? 'payment';
        $editingPaymentIdFromPost = isset($_POST['payment_id']) ? (int) $_POST['payment_id'] : 0;

        if ($amount <= 0) {
            $msg = 'Please enter a valid payment amount.';
        } elseif ($transactionType !== 'payment') {
            $msg = 'Invalid transaction type selected.';
        } elseif ($editingPaymentIdFromPost > 0) {
            try {
                $paymentController->updatePayment($editingPaymentIdFromPost, [
                    'patient_id' => $patientId,
                    'payment_date' => $paymentDateValue ?: date('Y-m-d'),
                    'amount' => $amount,
                    'notes' => trim((string) ($_POST['notes'] ?? '')),
                ]);
                header('Location: manage_payments.php?patient_id=' . $patientId);
                exit;
            } catch (Exception $e) {
                $msg = 'Unable to update payment: ' . $e->getMessage();
                $isEditing = true;
                $editingPaymentId = $editingPaymentIdFromPost;
            }
        } else {
            $data = [
                'patient_id' => $patientId,
                'payment_date' => $paymentDateValue ?: date('Y-m-d'),
                'amount' => $amount,
                'transaction_type' => $transactionType,
                'notes' => trim((string) ($_POST['notes'] ?? '')),
            ];

            try {
                $paymentController->savePayment($data);
                header('Location: manage_payments.php?patient_id=' . $patientId);
                exit;
            } catch (Exception $e) {
                $msg = 'Unable to save payment: ' . $e->getMessage();
            }
        }
    }
}

$paymentTotals = $paymentController->getPatientTotals($patientId);
$allPayments = $paymentController->getAllPaymentsByPatient($patientId);

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
<style>
  .session-detail-card {
    background-color: #f9fbfd;
  }

  .exercise-toggle .exercise-indicator {
    font-size: 0.85rem;
  }
</style>
<div class="workspace-layout">
  <?php
    switch ($_SESSION['role']) {
      case 'Doctor':
        include '../../layouts/doctor_sidebar.php';
        break;
      case 'Receptionist':
        include '../../layouts/receptionist_sidebar.php';
        break;
      default:
        include '../../layouts/admin_sidebar.php';
    }
  ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">Payments for <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></h1>
        <p class="workspace-page-subtitle">Track charges and credit balances applied to this patient.</p>
      </div>
      <div class="d-flex gap-2">
        <a href="javascript:history.back()" class="btn btn-outline-secondary">Back</a>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="row g-3">
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
      <form method="post" class="row g-3 align-items-end">
        <input type="hidden" name="transaction_type" value="payment">
        <?php if ($isEditing && $editingPaymentId > 0): ?>
          <input type="hidden" name="payment_id" value="<?= $editingPaymentId ?>">
        <?php endif; ?>

        <div class="col-12 col-sm-6 col-lg-3">
          <label class="form-label" for="payment_date">Date</label>
          <input type="date" id="payment_date" name="payment_date" class="form-control" value="<?= htmlspecialchars($paymentDateValue) ?>" required>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
          <label class="form-label" for="payment_amount">Amount</label>
          <input type="number" step="0.01" min="0" id="payment_amount" name="amount" class="form-control" value="<?= htmlspecialchars($amountValue) ?>" placeholder="0.00" required>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" for="payment_notes">Notes</label>
          <input type="text" id="payment_notes" name="notes" class="form-control" value="<?= htmlspecialchars($notesValue) ?>" placeholder="Optional description">
        </div>
        <div class="col-12 col-lg-2 d-grid gap-2">
          <button class="btn btn-primary"><?= $isEditing ? 'Update Credit' : 'Add Credit' ?></button>
          <?php if ($isEditing): ?>
            <a href="?patient_id=<?= $patientId ?>" class="btn btn-outline-secondary">Cancel</a>
          <?php endif; ?>
        </div>
        <div class="col-12">
          <small class="text-muted">Use this form to add or adjust credit entries for the patient. Treatment charges are generated automatically.</small>
        </div>
      </form>
    </div>

    <div class="app-card">
      <form method="post" id="bulkFeeForm" data-start-active="<?= (isset($_POST['bulk_fee_edit']) && $_POST['bulk_fee_edit'] === '1') ? '1' : '0' ?>">
        <input type="hidden" name="bulk_fee_edit" value="0" id="bulkFeeModeInput">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
          <div>
            <h5 class="mb-1">Payment Ledger</h5>
            <small class="text-muted">Toggle bulk edit to adjust multiple charge fees and notes at once.</small>
          </div>
          <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleBulkEdit">Enable Bulk Fee Edit</button>
            <button type="submit" class="btn btn-primary btn-sm d-none" id="saveBulkEdits">Save Fee Updates</button>
            <button type="button" class="btn btn-link btn-sm text-decoration-none d-none" id="cancelBulkEdit">Cancel</button>
          </div>
        </div>
        <div class="alert alert-info d-none" id="bulkEditInstructions">
          <div class="fw-semibold mb-1">Bulk fee edit enabled</div>
          <ul class="mb-0 ps-3">
            <li>Select the checkbox next to each charge you want to update.</li>
            <li>Edit the fee or note fields for the selected rows.</li>
            <li>Click <strong>Save Fee Updates</strong> to apply your changes.</li>
          </ul>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col">Select</th>
                <th scope="col">Date</th>
                <th scope="col">Type</th>
                <th scope="col">Amount</th>
                <th scope="col">Status</th>
                <th scope="col">Session</th>
                <th scope="col">Notes</th>
                <th scope="col">Exercises</th>
                <th scope="col">Action</th>
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
                    $isCharge = $pay['transaction_type'] === 'charge';
                  ?>
                  <tr data-payment-id="<?= $pay['id'] ?>">
                    <td>
                      <?php if ($isCharge): ?>
                        <div class="form-check bulk-control d-none mb-0">
                          <input class="form-check-input bulk-fee-checkbox" type="checkbox" name="fees[<?= $pay['id'] ?>][selected]" value="1" disabled>
                        </div>
                        <span class="text-muted fee-display">-</span>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars(format_display_date($pay['transaction_date'])) ?></td>
                    <td><span class="badge <?= $typeClass ?>"><?= htmlspecialchars($type) ?></span></td>
                    <td>
                      <div class="fee-display">R <?= number_format((float) $pay['amount'], 2) ?></div>
                      <?php if ($isCharge): ?>
                        <input type="number" step="0.01" min="0" name="fees[<?= $pay['id'] ?>][amount]" value="<?= number_format((float) $pay['amount'], 2, '.', '') ?>" class="form-control form-control-sm fee-amount-input bulk-control d-none" data-default="<?= number_format((float) $pay['amount'], 2, '.', '') ?>" disabled>
                      <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $statusClass ?>"><?= ucfirst(htmlspecialchars($pay['status'])) ?></span></td>
                    <td><?= htmlspecialchars($sessionDisplay) ?></td>
                    <td>
                      <div class="fee-display"><?= htmlspecialchars($noteDisplay) ?></div>
                      <?php if ($isCharge): ?>
                        <input type="text" name="fees[<?= $pay['id'] ?>][notes]" value="<?= htmlspecialchars($pay['notes'] ?? '') ?>" class="form-control form-control-sm fee-note-input bulk-control d-none" data-default="<?= htmlspecialchars($pay['notes'] ?? '') ?>" placeholder="Update note" disabled>
                      <?php endif; ?>
                    </td>
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
                    <td class="text-nowrap">
                      <?php if ($pay['transaction_type'] === 'payment'): ?>
                        <a href="?patient_id=<?= $patientId ?>&edit=<?= $pay['id'] ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                        <a href="?patient_id=<?= $patientId ?>&delete=<?= $pay['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete credit entry?');">Delete</a>
                      <?php else: ?>
                        <span class="text-muted small">Not available</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <tr class="exercise-detail-row" id="<?= $detailRowId ?>" style="display: none;">
                    <td colspan="9">
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
                  <td colspan="9" class="text-center text-muted py-4">No payment records available.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('toggleBulkEdit');
    const saveBtn = document.getElementById('saveBulkEdits');
    const cancelBtn = document.getElementById('cancelBulkEdit');
    const bulkModeInput = document.getElementById('bulkFeeModeInput');
    const bulkForm = document.getElementById('bulkFeeForm');
    const bulkInstructions = document.getElementById('bulkEditInstructions');
    const startActive = bulkForm && bulkForm.getAttribute('data-start-active') === '1';

    function setBulkMode(active) {
      if (!bulkForm || !bulkModeInput || !toggleBtn || !saveBtn || !cancelBtn) {
        return;
      }

      bulkModeInput.value = active ? '1' : '0';
      saveBtn.classList.toggle('d-none', !active);
      cancelBtn.classList.toggle('d-none', !active);
      toggleBtn.textContent = active ? 'Bulk Fee Edit Enabled' : 'Enable Bulk Fee Edit';
      toggleBtn.classList.toggle('btn-outline-secondary', !active);
      toggleBtn.classList.toggle('btn-secondary', active);

      document.querySelectorAll('.bulk-control').forEach(function (el) {
        el.classList.toggle('d-none', !active);
      });

      document.querySelectorAll('.bulk-fee-checkbox').forEach(function (cb) {
        cb.disabled = !active;
        if (!active) {
          cb.checked = false;
        }
      });

      document.querySelectorAll('.fee-display').forEach(function (el) {
        el.classList.toggle('d-none', active);
      });

      document.querySelectorAll('.fee-amount-input, .fee-note-input').forEach(function (input) {
        input.disabled = !active;
        if (!active) {
          input.value = input.getAttribute('data-default') || '';
        }
      });

      if (bulkInstructions) {
        bulkInstructions.classList.toggle('d-none', !active);
      }
    }

    if (toggleBtn) {
      toggleBtn.addEventListener('click', function () {
        setBulkMode(true);
      });
    }

    if (cancelBtn) {
      cancelBtn.addEventListener('click', function () {
        setBulkMode(false);
      });
    }

    if (startActive) {
      setBulkMode(true);
    } else {
      setBulkMode(false);
    }

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
