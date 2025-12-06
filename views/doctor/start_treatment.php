<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Doctor');

require_once '../../controllers/TreatmentController.php';
require_once '../../controllers/PatientController.php';
require_once '../../controllers/PaymentController.php';

$treatmentController = new TreatmentController($pdo);
$patientController = new PatientController($pdo);
$paymentController = new PaymentController($pdo);

$patient_id = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;
$episode_id = isset($_GET['episode_id']) ? (int) $_GET['episode_id'] : 0;

if ($patient_id <= 0 || $episode_id <= 0) {
    exit('Invalid treatment context.');
}

$patientData = $patientController->getPatientById($patient_id);
if (!$patientData) {
    exit('Patient not found.');
}

$patient_name = trim($patientData['first_name'] . ' ' . $patientData['last_name']);

$exercise_master = $pdo->query("SELECT id, name FROM exercises_master WHERE is_active = 1 ORDER BY name")
    ->fetchAll(PDO::FETCH_ASSOC);
$machine_master = $pdo->query("SELECT id, name, default_duration_minutes FROM machines ORDER BY name")
    ->fetchAll(PDO::FETCH_ASSOC);
$therapistStmt = $pdo->prepare("
    SELECT id, name
    FROM users
    WHERE role = 'Doctor' AND is_active = 1 AND is_deleted = 0
    ORDER BY name
");
$therapistStmt->execute();
$therapists = $therapistStmt->fetchAll(PDO::FETCH_ASSOC);

$therapistMap = [];
foreach ($therapists as $therapist) {
    $therapistMap[(int) $therapist['id']] = $therapist['name'];
}
$previousExercises = $treatmentController->getPreviousSessionExercises($patient_id, $episode_id);
$previousMachines = $treatmentController->getPreviousSessionMachines($patient_id, $episode_id);
$latestSessionData = $treatmentController->getLatestSessionWithDetails($patient_id, $episode_id);

$groupedSessions = [];
foreach ($previousExercises as $ex) {
    $sid = $ex['session_id'];
    if (!isset($groupedSessions[$sid])) {
        $groupedSessions[$sid] = [
            'session_id' => (int) $sid,
            'session_date' => $ex['session_date'],
            'remarks' => $ex['remarks'],
            'progress_notes' => $ex['progress_notes'],
            'advise' => $ex['advise'],
            'additional_treatment_notes' => $ex['additional_treatment_notes'] ?? null,
            'primary_therapist_name' => $ex['primary_therapist_name'] ?? null,
            'secondary_therapist_name' => $ex['secondary_therapist_name'] ?? null,
            'exercises' => [],
            'machines' => [],
        ];
    }

    if (!empty($ex['exercise_id'])) {
        $groupedSessions[$sid]['exercises'][] = $ex;
    }
}

foreach ($previousMachines as $machine) {
    $sid = $machine['session_id'];
    if (!isset($groupedSessions[$sid])) {
        $groupedSessions[$sid] = [
            'session_id' => (int) $sid,
            'session_date' => $machine['session_date'],
            'remarks' => $machine['remarks'],
            'progress_notes' => $machine['progress_notes'],
            'advise' => $machine['advise'],
            'additional_treatment_notes' => $machine['additional_treatment_notes'] ?? null,
            'primary_therapist_name' => $machine['primary_therapist_name'] ?? null,
            'secondary_therapist_name' => $machine['secondary_therapist_name'] ?? null,
            'exercises' => [],
            'machines' => [],
        ];
    }

    $groupedSessions[$sid]['machines'][] = $machine;
}

$editSessionId = isset($_GET['edit_session_id']) ? (int) $_GET['edit_session_id'] : 0;
$msg = null;

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'session_deleted') {
        $msg = 'Treatment session deleted successfully.';
    } elseif ($_GET['status'] === 'session_updated') {
        $msg = 'Treatment session updated successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_session';

    if ($action === 'update_session') {
        $editSessionId = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;
    }

    if ($action === 'delete_session') {
        $sessionId = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;

        if ($sessionId <= 0) {
            $msg = 'Invalid session selected for deletion.';
        } else {
            $sessionDetails = $treatmentController->getSessionById($sessionId);

            if (
                !$sessionDetails
                || $sessionDetails['patient_id'] !== $patient_id
                || $sessionDetails['episode_id'] !== $episode_id
            ) {
                $msg = 'Unable to delete the selected session.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $deleted = $treatmentController->deleteSessionById($sessionId);
                    if (!$deleted) {
                        throw new RuntimeException('Session deletion failed.');
                    }

                    $paymentController->removeSessionCharges(
                        $sessionDetails['patient_id'],
                        $sessionDetails['episode_id'],
                        $sessionId,
                        $sessionDetails['session_date']
                    );

                    $pdo->commit();

                    header('Location: start_treatment.php?episode_id=' . $episode_id . '&patient_id=' . $patient_id . '&status=session_deleted');
                    exit;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $msg = 'Failed to delete session: ' . $e->getMessage();
                }
            }
        }

    } else {
        $primaryTherapistId = isset($_POST['primary_therapist_id']) ? (int) $_POST['primary_therapist_id'] : 0;
        $secondaryTherapistId = isset($_POST['secondary_therapist_id']) && $_POST['secondary_therapist_id'] !== ''
            ? (int) $_POST['secondary_therapist_id']
            : null;

        if ($primaryTherapistId <= 0 || !isset($therapistMap[$primaryTherapistId])) {
            $msg = 'Please select a valid primary therapist.';
        } elseif ($secondaryTherapistId !== null && !isset($therapistMap[$secondaryTherapistId])) {
            $msg = 'Please select a valid secondary therapist.';
        } elseif ($secondaryTherapistId !== null && $secondaryTherapistId === $primaryTherapistId) {
            $msg = 'Primary and secondary therapist cannot be the same.';
        } else {
            $additionalNotes = trim($_POST['additional_treatment_notes'] ?? '');

            $exercises_data = [
                'exercise_id' => $_POST['exercises_exercise_id'] ?? [],
                'reps' => $_POST['exercises_reps'] ?? [],
                'duration_minutes' => $_POST['exercises_duration_minutes'] ?? [],
                'notes' => $_POST['exercises_notes'] ?? [],
                'new_name' => $_POST['new_exercise_name'] ?? [],
            ];

            $machines_data = [
                'machine_id' => $_POST['machines_machine_id'] ?? [],
                'duration_minutes' => $_POST['machines_duration_minutes'] ?? [],
                'notes' => $_POST['machines_notes'] ?? [],
                'new_name' => $_POST['new_machine_name'] ?? [],
            ];

            $data = [
                'patient_id' => $_POST['patient_id'] ?? $patient_id,
                'episode_id' => $_POST['episode_id'] ?? $episode_id,
                'session_date' => $_POST['session_date'] ?? date('Y-m-d'),
                'doctor_id' => $_SESSION['user_id'],
                'primary_therapist_id' => $primaryTherapistId,
                'secondary_therapist_id' => $secondaryTherapistId,
                'remarks' => $_POST['remarks'] ?? '',
                'progress_notes' => $_POST['progress_notes'] ?? '',
                'advise' => $_POST['advise'] ?? '',
                'additional_treatment_notes' => $additionalNotes,
                'exercises' => $exercises_data,
                'machines' => $machines_data,
                'file' => $_FILES['session_file'] ?? null,
            ];

            $amount = isset($_POST['session_amount']) ? (float) $_POST['session_amount'] : 0.0;

            if ($action === 'update_session') {
                $sessionId = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;

                if ($sessionId <= 0) {
                    $msg = 'Invalid session selected for update.';
                } else {
                    $existingSession = $treatmentController->getSessionWithDetails($sessionId);

                    if (
                        !$existingSession
                        || (int) $existingSession['patient_id'] !== $patient_id
                        || (int) $existingSession['episode_id'] !== $episode_id
                    ) {
                        $msg = 'Unable to update the selected session.';
                    } else {
                        $previousSessionDate = $existingSession['session_date'];

                        $result = $treatmentController->updateSession($sessionId, $data);

                        if (is_array($result) && !empty($result['success'])) {
                            try {
                                $paymentController->removeSessionCharges(
                                    $patient_id,
                                    $episode_id,
                                    $sessionId,
                                    $previousSessionDate
                                );

                                if ($amount > 0) {
                                    $paymentController->recordSessionPayment(
                                        $data['patient_id'],
                                        $data['episode_id'],
                                        $data['session_date'],
                                        $amount,
                                        $sessionId
                                    );
                                }
                            } catch (Exception $e) {
                                $msg = 'Session updated but payment update failed: ' . $e->getMessage();
                            }

                            if ($msg === null) {
                                header('Location: start_treatment.php?episode_id=' . $episode_id . '&patient_id=' . $patient_id . '&status=session_updated');
                                exit;
                            }
                        } else {
                            $errorMessage = is_array($result) && isset($result['error'])
                                ? $result['error']
                                : (is_string($result) ? $result : 'Unable to update the treatment session.');
                            $msg = 'Error: ' . $errorMessage;
                        }
                    }
                }
            } else {
                $result = $treatmentController->saveSession($data);

                if (is_array($result) && !empty($result['success'])) {
                    $sessionId = isset($result['session_id']) ? (int) $result['session_id'] : null;
                    if ($amount > 0) {
                        try {
                            $paymentController->recordSessionPayment(
                                $data['patient_id'],
                                $data['episode_id'],
                                $data['session_date'],
                                $amount,
                                $sessionId
                            );
                        } catch (Exception $e) {
                            $msg = 'Treatment saved, but payment entry failed: ' . $e->getMessage();
                        }
                    }

                    if ($msg === null) {
                        header('Location: start_treatment.php?episode_id=' . $episode_id . '&patient_id=' . $patient_id);
                        exit;
                    }
                } else {
                    $errorMessage = is_array($result) && isset($result['error'])
                        ? $result['error']
                        : (is_string($result) ? $result : 'Unable to save the treatment session.');
                    $msg = 'Error: ' . $errorMessage;
                }
            }
        }
    }
}

$editingSessionData = null;
if ($editSessionId > 0) {
    $editingSessionData = $treatmentController->getSessionWithDetails($editSessionId);

    if (
        !$editingSessionData
        || (int) $editingSessionData['patient_id'] !== $patient_id
        || (int) $editingSessionData['episode_id'] !== $episode_id
    ) {
        if ($msg === null) {
            $msg = 'The requested session could not be loaded for editing.';
        }
        $editingSessionData = null;
        $editSessionId = 0;
    }
}

$isEditingSession = $editingSessionData !== null;

$selectedPrimaryTherapistId = '';
if (isset($_POST['primary_therapist_id'])) {
    $selectedPrimaryTherapistId = (string) $_POST['primary_therapist_id'];
} elseif ($isEditingSession && $editingSessionData['primary_therapist_id'] !== null) {
    $selectedPrimaryTherapistId = (string) $editingSessionData['primary_therapist_id'];
} elseif (isset($_SESSION['user_id']) && isset($therapistMap[(int) $_SESSION['user_id']])) {
    $selectedPrimaryTherapistId = (string) $_SESSION['user_id'];
}

if (isset($_POST['secondary_therapist_id'])) {
    $selectedSecondaryTherapistId = (string) $_POST['secondary_therapist_id'];
} elseif ($isEditingSession && $editingSessionData['secondary_therapist_id'] !== null) {
    $selectedSecondaryTherapistId = (string) $editingSessionData['secondary_therapist_id'];
} else {
    $selectedSecondaryTherapistId = '';
}

$sessionDateValue = $_POST['session_date']
    ?? ($isEditingSession ? ($editingSessionData['session_date'] ?? date('Y-m-d')) : date('Y-m-d'));
$sessionAmountValue = $_POST['session_amount']
    ?? ($isEditingSession ? ($editingSessionData['amount'] ?? '') : '');
$remarksValue = $_POST['remarks']
    ?? ($isEditingSession ? ($editingSessionData['remarks'] ?? '') : '');
$progressNotesValue = $_POST['progress_notes']
    ?? ($isEditingSession ? ($editingSessionData['progress_notes'] ?? '') : '');
$adviseValue = $_POST['advise']
    ?? ($isEditingSession ? ($editingSessionData['advise'] ?? '') : '');
$additionalTreatmentNotesValue = $_POST['additional_treatment_notes']
    ?? ($isEditingSession ? ($editingSessionData['additional_treatment_notes'] ?? '') : '');

if ($isEditingSession) {
    $copyLastSessionValue = 'no';
} else {
    $copyLastSessionValue = (isset($_POST['copy_last_session']) && $_POST['copy_last_session'] === 'yes' && $latestSessionData)
        ? 'yes'
        : 'no';
}

include '../../includes/header.php';
?>
<div class="workspace-layout">
  <?php include '../../layouts/doctor_sidebar.php'; ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">
          <?= $isEditingSession ? 'Update Treatment Session' : 'Log Treatment Session' ?>
        </h1>
        <p class="workspace-page-subtitle">Patient: <?= htmlspecialchars($patient_name) ?></p>
      </div>
      <div class="d-flex gap-2">
        <?php if ($isEditingSession): ?>
          <a href="start_treatment.php?episode_id=<?= $episode_id ?>&patient_id=<?= $patient_id ?>" class="btn btn-outline-primary">Cancel Editing</a>
        <?php endif; ?>
        <a href="select_or_create_episode.php?patient_id=<?= $patient_id ?>" class="btn btn-outline-secondary">Back to Episodes</a>
      </div>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if (!empty($groupedSessions)): ?>
      <div class="app-card">
        <h5 class="mb-3">Previous Sessions</h5>
        <div class="accordion" id="previousExercisesAccordion">
          <?php $idx = 0; foreach ($groupedSessions as $session): $idx++; ?>
            <?php
              $currentSessionId = isset($session['session_id']) ? (int) $session['session_id'] : 0;
              $isCurrentEditingSession = $isEditingSession && $editingSessionData && isset($editingSessionData['id']) && (int) $editingSessionData['id'] === $currentSessionId;
            ?>
            <div class="accordion-item<?= $isCurrentEditingSession ? ' border border-primary' : '' ?>">
              <h2 class="accordion-header" id="heading<?= $idx ?>">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $idx ?>" aria-expanded="false" aria-controls="collapse<?= $idx ?>">
                  <?= htmlspecialchars(format_display_date($session['session_date'])) ?>
                  <?php if ($isCurrentEditingSession): ?>
                    <span class="badge bg-primary ms-2">Editing</span>
                  <?php endif; ?>
                </button>
              </h2>
              <div id="collapse<?= $idx ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $idx ?>" data-bs-parent="#previousExercisesAccordion">
                <div class="accordion-body">
                  <?php if (!empty($session['session_id'])): ?>
                    <div class="d-flex justify-content-end flex-wrap gap-2 mb-3">
                      <?php if ($isCurrentEditingSession): ?>
                        <span class="badge bg-primary align-self-center">Currently editing</span>
                      <?php else: ?>
                        <a href="start_treatment.php?episode_id=<?= $episode_id ?>&patient_id=<?= $patient_id ?>&edit_session_id=<?= (int) $session['session_id'] ?>" class="btn btn-sm btn-outline-primary">Edit Session</a>
                      <?php endif; ?>
                      <form method="POST" class="delete-session-form">
                        <input type="hidden" name="action" value="delete_session">
                        <input type="hidden" name="session_id" value="<?= (int) $session['session_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete Session</button>
                      </form>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($session['primary_therapist_name'])): ?>
                    <p class="mb-2"><strong>Primary Therapist:</strong> <?= htmlspecialchars($session['primary_therapist_name']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($session['secondary_therapist_name'])): ?>
                    <p class="mb-2"><strong>Secondary Therapist:</strong> <?= htmlspecialchars($session['secondary_therapist_name']) ?></p>
                  <?php endif; ?>
                  <p class="mb-2"><strong>Doctor's Remarks:</strong> <?= htmlspecialchars($session['remarks']) ?></p>
                  <p class="mb-2"><strong>Progress Notes:</strong> <?= htmlspecialchars($session['progress_notes']) ?></p>
                  <p class="mb-2"><strong>Advise:</strong> <?= htmlspecialchars($session['advise']) ?></p>
                  <?php if (!empty($session['additional_treatment_notes'])): ?>
                    <p class="mb-3"><strong>Additional Treatment Notes:</strong> <?= htmlspecialchars($session['additional_treatment_notes']) ?></p>
                  <?php endif; ?>
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
                              <td><?= htmlspecialchars($exercise['name']) ?></td>
                              <td><?= htmlspecialchars($exercise['reps']) ?></td>
                              <td><?= htmlspecialchars($exercise['duration_minutes']) ?></td>
                              <td><?= htmlspecialchars($exercise['notes']) ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($session['machines'])): ?>
                    <div class="table-responsive mt-3">
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
                              <td><?= htmlspecialchars($machine['name']) ?></td>
                              <td><?= htmlspecialchars($machine['duration_minutes']) ?></td>
                              <td><?= htmlspecialchars($machine['notes']) ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="app-card">
      <?php if ($isEditingSession && $editingSessionData): ?>
        <div class="alert alert-warning mb-3">
          Editing session dated <?= htmlspecialchars(format_display_date($editingSessionData['session_date'])) ?>.
        </div>
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-3">
        <input type="hidden" name="action" value="<?= $isEditingSession ? 'update_session' : 'save_session' ?>">
        <?php if ($isEditingSession && isset($editingSessionData['id'])): ?>
          <input type="hidden" name="session_id" value="<?= (int) $editingSessionData['id'] ?>">
        <?php endif; ?>
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="episode_id" value="<?= $episode_id ?>">

        <?php if (!$isEditingSession): ?>
          <div class="row g-3">
            <div class="col-12">
              <div class="fw-bold mb-2">Copy last session data of this patient?</div>
              <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="copy_last_session" id="copy_last_session_yes" value="yes" <?= $copyLastSessionValue === 'yes' ? 'checked' : '' ?> <?= $latestSessionData ? '' : 'disabled' ?>>
                  <label class="form-check-label" for="copy_last_session_yes">Yes</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="copy_last_session" id="copy_last_session_no" value="no" <?= $copyLastSessionValue !== 'yes' ? 'checked' : '' ?>>
                  <label class="form-check-label" for="copy_last_session_no">No</label>
                </div>
              </div>
              <?php if (!$latestSessionData): ?>
                <small class="text-muted">No previous session data available to copy.</small>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label class="form-label" for="session_date">Session Date</label>
            <input type="date" name="session_date" id="session_date" class="form-control" value="<?= htmlspecialchars($sessionDateValue) ?>" required>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="session_amount">Session Amount</label>
            <input type="number" step="0.01" min="0" name="session_amount" id="session_amount" class="form-control" value="<?= htmlspecialchars($sessionAmountValue) ?>" required>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="session_file">Upload File (optional)</label>
            <input type="file" name="session_file" id="session_file" class="form-control">
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label" for="primary_therapist_id">Primary Therapist</label>
            <select name="primary_therapist_id" id="primary_therapist_id" class="form-select" required>
              <option value="">Select Primary Therapist</option>
              <?php if (empty($therapists)): ?>
                <option value="" disabled>No active doctors available</option>
              <?php else: ?>
                <?php foreach ($therapists as $therapist): ?>
                  <option value="<?= (int) $therapist['id'] ?>" <?= ((string) $therapist['id'] === $selectedPrimaryTherapistId) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($therapist['name']) ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="secondary_therapist_id">Secondary Therapist</label>
            <select name="secondary_therapist_id" id="secondary_therapist_id" class="form-select">
              <option value="">Select Secondary Therapist (optional)</option>
              <?php foreach ($therapists as $therapist): ?>
                <option value="<?= (int) $therapist['id'] ?>" <?= ((string) $therapist['id'] === $selectedSecondaryTherapistId) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($therapist['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Prescribed Exercises</h5>
            <button type="button" class="btn btn-outline-secondary" onclick="addExercise()">+ Add Exercise</button>
          </div>
          <div id="exerciseContainer" class="mt-3 d-flex flex-column gap-3"></div>
        </div>

        <div>
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Used Machines</h5>
            <button type="button" class="btn btn-outline-secondary" onclick="addMachine()">+ Add Machine</button>
          </div>
          <div id="machineContainer" class="mt-3 d-flex flex-column gap-3"></div>
        </div>

        <div class="row g-3">
          <div class="col-12">
            <label class="form-label" for="remarks">Doctor's Remarks</label>
            <textarea name="remarks" id="remarks" class="form-control" rows="3"><?= htmlspecialchars($remarksValue) ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label" for="progress_notes">Progress Notes</label>
            <textarea name="progress_notes" id="progress_notes" class="form-control" rows="2"><?= htmlspecialchars($progressNotesValue) ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label" for="advise">Advise</label>
            <textarea name="advise" id="advise" class="form-control" rows="2"><?= htmlspecialchars($adviseValue) ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label" for="additional_treatment_notes">Additional Treatment Notes</label>
            <textarea name="additional_treatment_notes" id="additional_treatment_notes" class="form-control" rows="2"><?= htmlspecialchars($additionalTreatmentNotesValue) ?></textarea>
          </div>
        </div>

        <div class="mt-2">
          <button type="submit" class="btn btn-primary"><?= $isEditingSession ? 'Update Treatment' : 'Save Treatment' ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const exerciseMaster = <?= json_encode($exercise_master) ?>;
  const machineMaster = <?= json_encode($machine_master) ?>;
  const latestSessionData = <?= json_encode($latestSessionData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const editingSession = <?= json_encode($editingSessionData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const isEditingSession = Boolean(editingSession && editingSession.id);
  const primaryTherapistSelect = document.getElementById('primary_therapist_id');
  const secondaryTherapistSelect = document.getElementById('secondary_therapist_id');
  const copyLastSessionInputs = document.querySelectorAll('input[name="copy_last_session"]');

  document.addEventListener('DOMContentLoaded', () => {
    if (isEditingSession) {
      populateSessionForEditing(editingSession);
    } else {
      addExercise();
      addMachine();
    }
    syncTherapistSelections();
    if (!isEditingSession) {
      setupCopyLastSession();
    }
    setupDeleteSessionForms();
  });

  if (primaryTherapistSelect && secondaryTherapistSelect) {
    primaryTherapistSelect.addEventListener('change', syncTherapistSelections);
    secondaryTherapistSelect.addEventListener('change', () => {
      if (secondaryTherapistSelect.value && secondaryTherapistSelect.value === primaryTherapistSelect.value) {
        secondaryTherapistSelect.value = '';
      }
      syncTherapistSelections();
    });
  }

  function populateSessionForEditing(session) {
    if (!session) {
      addExercise();
      addMachine();
      return;
    }

    const sessionDateInput = document.getElementById('session_date');
    if (sessionDateInput && session.session_date) {
      sessionDateInput.value = session.session_date;
    }

    const amountInput = document.getElementById('session_amount');
    if (amountInput) {
      amountInput.value = session.amount ?? '';
    }

    if (primaryTherapistSelect) {
      primaryTherapistSelect.value = session.primary_therapist_id
        ? String(session.primary_therapist_id)
        : '';
    }
    if (secondaryTherapistSelect) {
      secondaryTherapistSelect.value = session.secondary_therapist_id
        ? String(session.secondary_therapist_id)
        : '';
    }
    syncTherapistSelections();

    const remarksInput = document.getElementById('remarks');
    if (remarksInput) {
      remarksInput.value = session.remarks ?? '';
    }
    const progressNotesInput = document.getElementById('progress_notes');
    if (progressNotesInput) {
      progressNotesInput.value = session.progress_notes ?? '';
    }
    const adviseInput = document.getElementById('advise');
    if (adviseInput) {
      adviseInput.value = session.advise ?? '';
    }
    const additionalNotesInput = document.getElementById('additional_treatment_notes');
    if (additionalNotesInput) {
      additionalNotesInput.value = session.additional_treatment_notes ?? '';
    }

    populateExercises(Array.isArray(session.exercises) ? session.exercises : []);
    populateMachines(Array.isArray(session.machines) ? session.machines : []);
  }

  function setupCopyLastSession() {
    if (!copyLastSessionInputs.length) {
      return;
    }

    copyLastSessionInputs.forEach(input => {
      input.addEventListener('change', event => {
        if (event.target.value === 'yes' && event.target.checked) {
          populateFromLastSession();
        } else if (event.target.value === 'no' && event.target.checked) {
          clearCopiedSessionData();
        }
      });
    });
  }

  function setupDeleteSessionForms() {
    const deleteForms = document.querySelectorAll('.delete-session-form');
    deleteForms.forEach(form => {
      form.addEventListener('submit', event => {
        const confirmed = confirm('Are you sure you want to permanently delete this treatment session? This action cannot be undone.');
        if (!confirmed) {
          event.preventDefault();
        }
      });
    });
  }

  function resetExerciseContainer() {
    const container = document.getElementById('exerciseContainer');
    if (!container) {
      return null;
    }
    $(container).find('.exercise-select').each(function () {
      if ($(this).data('select2')) {
        $(this).select2('destroy');
      }
    });
    container.innerHTML = '';
    return container;
  }

  function populateFromLastSession() {
    if (!latestSessionData) {
      return;
    }

    const amountInput = document.getElementById('session_amount');
    if (amountInput) {
      amountInput.value = latestSessionData.amount ?? '';
    }

    if (primaryTherapistSelect) {
      primaryTherapistSelect.value = latestSessionData.primary_therapist_id
        ? String(latestSessionData.primary_therapist_id)
        : '';
    }
    if (secondaryTherapistSelect) {
      secondaryTherapistSelect.value = latestSessionData.secondary_therapist_id
        ? String(latestSessionData.secondary_therapist_id)
        : '';
    }
    syncTherapistSelections();

    const remarksInput = document.getElementById('remarks');
    if (remarksInput) {
      remarksInput.value = latestSessionData.remarks ?? '';
    }
    const progressNotesInput = document.getElementById('progress_notes');
    if (progressNotesInput) {
      progressNotesInput.value = latestSessionData.progress_notes ?? '';
    }
    const adviseInput = document.getElementById('advise');
    if (adviseInput) {
      adviseInput.value = latestSessionData.advise ?? '';
    }
    const additionalNotesInput = document.getElementById('additional_treatment_notes');
    if (additionalNotesInput) {
      additionalNotesInput.value = latestSessionData.additional_treatment_notes ?? '';
    }

    populateExercises(latestSessionData.exercises || []);
    populateMachines(latestSessionData.machines || []);
  }

  function populateExercises(exercises) {
    const container = resetExerciseContainer();
    if (!container) {
      return;
    }

    if (!exercises.length) {
      addExercise();
      return;
    }

    exercises.forEach(exercise => {
      addExercise();
      const row = container.lastElementChild;
      if (!row) {
        return;
      }

      const select = row.querySelector('.exercise-select');
      const repsInput = row.querySelector('.reps-input');
      const durationInput = row.querySelector('.duration-input');
      const notesInput = row.querySelector('input[name="exercises_notes[]"]');
      const otherNameInput = row.querySelector('.other-name');

      const exerciseId = exercise.exercise_id !== null && exercise.exercise_id !== undefined
        ? String(exercise.exercise_id)
        : '';
      const exerciseName = exercise.name || exercise.exercise_name || '';

      if (select) {
        const hasOption = Array.from(select.options).some(opt => opt.value === exerciseId);
        if (exerciseId && hasOption) {
          select.value = exerciseId;
        } else if (exerciseName) {
          select.value = 'other';
          if (otherNameInput) {
            otherNameInput.value = exerciseName;
          }
        } else {
          select.value = '';
        }
        $(select).trigger('change');
      }

      if (repsInput) {
        repsInput.value = exercise.reps ?? '';
      }
      if (durationInput) {
        durationInput.value = exercise.duration_minutes ?? '';
      }
      if (notesInput) {
        notesInput.value = exercise.notes ?? '';
      }

      if (select && select.value !== 'other' && otherNameInput) {
        otherNameInput.value = '';
      }
    });
    updateExerciseOptions();
  }

  function populateMachines(machines) {
    const container = resetMachineContainer();
    if (!container) {
      return;
    }

    if (!machines.length) {
      addMachine();
      return;
    }

    machines.forEach(machine => {
      addMachine();
      const row = container.lastElementChild;
      if (!row) {
        return;
      }

      const select = row.querySelector('.machine-select');
      const otherInput = row.querySelector('.other-machine-name');
      const durationInput = row.querySelector('.machine-duration-input');
      const notesInput = row.querySelector('.machine-notes-input');

      if (select) {
        const machineId = machine.machine_id !== null ? String(machine.machine_id) : '';
        const optionExists = Array.from(select.options).some(opt => opt.value === machineId);
        if (machineId && optionExists) {
          select.value = machineId;
          $(select).trigger('change');
        } else {
          select.value = 'other';
          $(select).trigger('change');
          if (otherInput) {
            otherInput.value = machine.name || '';
          }
        }
      }

      if (durationInput) {
        durationInput.value = machine.duration_minutes ?? '';
        durationInput.dataset.autofilled = 'false';
      }

      if (notesInput) {
        notesInput.value = machine.notes ?? '';
      }
    });
  }

  function clearCopiedSessionData() {
    const amountInput = document.getElementById('session_amount');
    if (amountInput) {
      amountInput.value = '';
    }
    if (primaryTherapistSelect) {
      primaryTherapistSelect.value = '';
    }
    if (secondaryTherapistSelect) {
      secondaryTherapistSelect.value = '';
    }
    syncTherapistSelections();

    const remarksInput = document.getElementById('remarks');
    if (remarksInput) {
      remarksInput.value = '';
    }
    const progressNotesInput = document.getElementById('progress_notes');
    if (progressNotesInput) {
      progressNotesInput.value = '';
    }
    const adviseInput = document.getElementById('advise');
    if (adviseInput) {
      adviseInput.value = '';
    }
    const additionalNotesInput = document.getElementById('additional_treatment_notes');
    if (additionalNotesInput) {
      additionalNotesInput.value = '';
    }

    const exerciseContainer = resetExerciseContainer();
    if (exerciseContainer) {
      addExercise();
    }

    const machineContainer = resetMachineContainer();
    if (machineContainer) {
      addMachine();
    }
  }

  function addExercise() {
    const container = document.getElementById('exerciseContainer');
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-end exercise-row';

    row.innerHTML = `
      <div class="col-12 col-md-4">
        <select name="exercises_exercise_id[]" class="form-select exercise-select"></select>
        <input type="text" name="new_exercise_name[]" class="form-control mt-2 d-none other-name" placeholder="Exercise Name">
      </div>
      <div class="col-6 col-md-2">
        <input type="number" name="exercises_reps[]" class="form-control reps-input" placeholder="Reps">
      </div>
      <div class="col-6 col-md-2">
        <input type="number" name="exercises_duration_minutes[]" class="form-control duration-input" placeholder="Duration (min)">
      </div>
      <div class="col-12 col-md-3">
        <input type="text" name="exercises_notes[]" class="form-control" placeholder="Notes">
      </div>
      <div class="col-12 col-md-1 text-md-center">
        <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeRow(this)">Remove</button>
      </div>
    `;

    container.appendChild(row);
    updateExerciseOptions();
  }

  function updateExerciseOptions() {
    const selects = document.querySelectorAll('.exercise-select');
    const selectedValues = Array.from(selects).map(s => s.value).filter(v => v && v !== 'other');
    selects.forEach(sel => {
      const currentValue = sel.value;
      $(sel).off('change.exercise');
      if ($(sel).data('select2')) {
        $(sel).select2('destroy');
      }
      sel.innerHTML = '<option value="">-- Select Exercise --</option>' +
        exerciseMaster.map(ex => {
          const disabled = selectedValues.includes(String(ex.id)) && String(ex.id) !== currentValue;
          return `<option value="${ex.id}" ${disabled ? 'disabled' : ''}>${ex.name}</option>`;
        }).join('') + '<option value="other">Others</option>';
      sel.value = currentValue;
      $(sel).select2({width: '100%'});
      $(sel).on('change.exercise', function(){
        handleExerciseChange(this);
        updateExerciseOptions();
      });
      handleExerciseChange(sel);
    });
  }

  function handleExerciseChange(sel){
    const row = sel.closest('.exercise-row');
    const isOther = sel.value === 'other';
    const nameInput = row.querySelector('.other-name');
    const repsInput = row.querySelector('.reps-input');
    const durationInput = row.querySelector('.duration-input');
    if(isOther){
      nameInput.classList.remove('d-none');
      nameInput.required = true;
      repsInput.required = true;
      durationInput.required = true;
      repsInput.placeholder = 'Default Reps';
      durationInput.placeholder = 'Default Duration (min)';
    } else {
      nameInput.classList.add('d-none');
      nameInput.required = false;
      repsInput.required = false;
      durationInput.required = false;
      repsInput.placeholder = 'Reps';
      durationInput.placeholder = 'Duration (min)';
    }
  }

  function removeRow(btn) {
    btn.closest('.exercise-row').remove();
    updateExerciseOptions();
  }

  function resetMachineContainer() {
    const container = document.getElementById('machineContainer');
    if (!container) {
      return null;
    }
    $(container).find('.machine-select').each(function () {
      if ($(this).data('select2')) {
        $(this).select2('destroy');
      }
    });
    container.innerHTML = '';
    return container;
  }

  function addMachine() {
    const container = document.getElementById('machineContainer');
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-end machine-row';

    row.innerHTML = `
      <div class="col-12 col-md-4">
        <select name="machines_machine_id[]" class="form-select machine-select"></select>
        <input type="text" name="new_machine_name[]" class="form-control mt-2 d-none other-machine-name" placeholder="Machine Name">
      </div>
      <div class="col-6 col-md-3">
        <input type="number" name="machines_duration_minutes[]" class="form-control machine-duration-input" placeholder="Duration (min)">
      </div>
      <div class="col-12 col-md-4">
        <input type="text" name="machines_notes[]" class="form-control machine-notes-input" placeholder="Notes">
      </div>
      <div class="col-12 col-md-1 text-md-center">
        <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeMachineRow(this)">Remove</button>
      </div>
    `;

    container.appendChild(row);
    const durationInput = row.querySelector('.machine-duration-input');
    if (durationInput) {
      durationInput.addEventListener('input', () => {
        durationInput.dataset.autofilled = 'false';
      });
    }
    updateMachineOptions();
  }

  function updateMachineOptions() {
    const selects = document.querySelectorAll('.machine-select');
    selects.forEach(sel => {
      const currentValue = sel.value;
      $(sel).off('change.machine');
      if ($(sel).data('select2')) {
        $(sel).select2('destroy');
      }
      sel.innerHTML = '<option value="">-- Select Machine --</option>' +
        machineMaster.map(machine => `<option value="${machine.id}" data-default-duration="${machine.default_duration_minutes ?? ''}">${machine.name}</option>`).join('') +
        '<option value="other">Others</option>';
      sel.value = currentValue;
      $(sel).select2({width: '100%'});
      $(sel).on('change.machine', function(){
        handleMachineChange(this);
      });
      handleMachineChange(sel);
    });
  }

  function handleMachineChange(sel) {
    const row = sel.closest('.machine-row');
    const isOther = sel.value === 'other';
    const nameInput = row.querySelector('.other-machine-name');
    const durationInput = row.querySelector('.machine-duration-input');

    if (isOther) {
      nameInput.classList.remove('d-none');
      nameInput.required = true;
    } else {
      nameInput.classList.add('d-none');
      nameInput.required = false;
    }

    if (durationInput) {
      if (!isOther && sel.value) {
        const option = sel.selectedOptions[0];
        const defaultDuration = option ? option.getAttribute('data-default-duration') : '';
        if (durationInput.value === '' || durationInput.dataset.autofilled === 'true') {
          durationInput.value = defaultDuration || '';
          durationInput.dataset.autofilled = 'true';
        }
      } else {
        durationInput.dataset.autofilled = 'false';
        if (durationInput.value === '' && isOther) {
          durationInput.placeholder = 'Duration (min)';
        }
      }
      durationInput.required = !!sel.value;
    }
  }

  function removeMachineRow(btn) {
    const row = btn.closest('.machine-row');
    if (!row) {
      return;
    }
    const select = row.querySelector('.machine-select');
    if (select && $(select).data('select2')) {
      $(select).select2('destroy');
    }
    row.remove();
    updateMachineOptions();
  }

  function syncTherapistSelections() {
    if (!primaryTherapistSelect || !secondaryTherapistSelect) {
      return;
    }
    const primaryValue = primaryTherapistSelect.value;
    Array.from(secondaryTherapistSelect.options).forEach(option => {
      if (!option.value) {
        option.disabled = false;
        return;
      }
      option.disabled = option.value === primaryValue;
    });
    if (secondaryTherapistSelect.value && secondaryTherapistSelect.value === primaryValue) {
      secondaryTherapistSelect.value = '';
    }
  }
</script>

<?php include '../../includes/footer.php'; ?>
