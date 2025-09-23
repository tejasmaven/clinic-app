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

$groupedSessions = [];
foreach ($previousExercises as $ex) {
    $sid = $ex['session_id'];
    if (!isset($groupedSessions[$sid])) {
        $groupedSessions[$sid] = [
            'session_date' => $ex['session_date'],
            'remarks' => $ex['remarks'],
            'progress_notes' => $ex['progress_notes'],
            'advise' => $ex['advise'],
            'additional_treatment_notes' => $ex['additional_treatment_notes'] ?? null,
            'primary_therapist_name' => $ex['primary_therapist_name'] ?? null,
            'secondary_therapist_name' => $ex['secondary_therapist_name'] ?? null,
            'exercises' => [],
        ];
    }

    if (!empty($ex['exercise_id'])) {
        $groupedSessions[$sid]['exercises'][] = $ex;
    }
}

$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            'file' => $_FILES['session_file'] ?? null,
        ];

        $amount = isset($_POST['session_amount']) ? (float) $_POST['session_amount'] : 0.0;
        $result = $treatmentController->saveSession($data);

        if ($result === true) {
            if ($amount > 0) {
                try {
                    $paymentController->recordSessionPayment(
                        $data['patient_id'],
                        $data['episode_id'],
                        $data['session_date'],
                        $amount
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
            $msg = 'Error: ' . $result;
        }
    }
}

$selectedPrimaryTherapistId = '';
if (isset($_POST['primary_therapist_id'])) {
    $selectedPrimaryTherapistId = (string) $_POST['primary_therapist_id'];
} elseif (isset($_SESSION['user_id']) && isset($therapistMap[(int) $_SESSION['user_id']])) {
    $selectedPrimaryTherapistId = (string) $_SESSION['user_id'];
}

$selectedSecondaryTherapistId = isset($_POST['secondary_therapist_id']) ? (string) $_POST['secondary_therapist_id'] : '';
$sessionDateValue = $_POST['session_date'] ?? date('Y-m-d');
$sessionAmountValue = $_POST['session_amount'] ?? '';
$remarksValue = $_POST['remarks'] ?? '';
$progressNotesValue = $_POST['progress_notes'] ?? '';
$adviseValue = $_POST['advise'] ?? '';
$additionalTreatmentNotesValue = $_POST['additional_treatment_notes'] ?? '';

include '../../includes/header.php';
?>
<div class="workspace-layout">
  <?php include '../../layouts/doctor_sidebar.php'; ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">Log Treatment Session</h1>
        <p class="workspace-page-subtitle">Patient: <?= htmlspecialchars($patient_name) ?></p>
      </div>
      <div class="d-flex gap-2">
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
            <div class="accordion-item">
              <h2 class="accordion-header" id="heading<?= $idx ?>">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $idx ?>" aria-expanded="false" aria-controls="collapse<?= $idx ?>">
                  <?= htmlspecialchars($session['session_date']) ?>
                </button>
              </h2>
              <div id="collapse<?= $idx ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $idx ?>" data-bs-parent="#previousExercisesAccordion">
                <div class="accordion-body">
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
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="app-card">
      <form method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-3">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="episode_id" value="<?= $episode_id ?>">

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
          <button type="submit" class="btn btn-primary">Save Treatment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const exerciseMaster = <?= json_encode($exercise_master) ?>;
  const primaryTherapistSelect = document.getElementById('primary_therapist_id');
  const secondaryTherapistSelect = document.getElementById('secondary_therapist_id');

  document.addEventListener('DOMContentLoaded', () => {
    addExercise();
    syncTherapistSelections();
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
