<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Doctor');

require_once '../../controllers/TreatmentController.php';
require_once '../../controllers/PatientController.php';

$treatmentController = new TreatmentController($pdo);
$patientController = new PatientController($pdo);
$patient_id = $_GET['patient_id'] ?? null;
$episode_id = $_GET['episode_id'] ?? null;
$patientData = $patientController->getPatientById($patient_id);
$patient_name = $patientData['first_name']." ".$patientData['last_name'] ;

$exercise_master = $pdo->query("SELECT id, name  FROM exercises_master WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$previousExercises = $patient_id ? $treatmentController->getPreviousSessionExercises($patient_id) : [];

$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  
    $exercises_data = [
      'exercise_id' => $_POST['exercises_exercise_id'],
      'reps' => $_POST['exercises_reps'],
      'duration_minutes' => $_POST['exercises_duration_minutes'],
      'notes' => $_POST['exercises_notes']
    ];
    
    $data = [
        'patient_id' => $_POST['patient_id'],
        'session_date' => $_POST['session_date'],
        'doctor_id' => $_SESSION['user_id'],
        'remarks' => $_POST['remarks'] ?? '',
        'progress_notes' => $_POST['progress_notes'] ?? '',
        'exercises' => $exercises_data
    ];
    $result = $treatmentController->saveSession($data);
    header("Location: start_treatment.php?episode_id=" . $episode_id."&patient_id=".$patient_id);
    exit;
    $msg = $result === true ? 'Treatment saved successfully.' : 'Error: ' . $result;
}
include '../../includes/header.php';

?>
<div class="row">
  <div class="col-md-3"><?php include '../../layouts/doctor_sidebar.php'; ?></div>
  <div class="col-md-9">
    <h4>Start Treatment for <?= htmlspecialchars($patient_name) ?></h4>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    




  <form method="POST">
    <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
    <input type="hidden" name="episode_id" value="<?= $episode_id ?>">

    <div class="mb-3">
      <label class="form-label">Session Date</label>
      <input type="date" name="session_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
    </div>

    <?php
      $groupedSessions = [];
      foreach ($previousExercises as $ex) {
          $sid = $ex['session_id'];
          if (!isset($groupedSessions[$sid])) {
              $groupedSessions[$sid] = [
                  'session_date' => $ex['session_date'],
                  'remarks' => $ex['remarks'],
                  'progress_notes' => $ex['progress_notes'],
                  'exercises' => []
              ];
          }
          if (!empty($ex['exercise_id'])) {
              $groupedSessions[$sid]['exercises'][] = $ex;
          }
      }
      if (!empty($groupedSessions)):
    ?>
    <div class="mb-3">
      <label class="form-label">Previous Sessions</label>
      <div class="accordion" id="previousExercisesAccordion">
        <?php $idx = 0; foreach ($groupedSessions as $session): $idx++; ?>
        <div class="accordion-item">
          <h2 class="accordion-header" id="heading<?= $idx ?>">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $idx ?>" aria-expanded="false" aria-controls="collapse<?= $idx ?>">
              <?= htmlspecialchars($session['session_date']) ?>
            </button>
          </h2>
          <div id="collapse<?= $idx ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $idx ?>" data-bs-parent="#previousExercisesAccordion">
            <div class="accordion-body p-0">
              <div class="p-3">
                <strong>Doctor's Remarks:</strong> <?= htmlspecialchars($session['remarks']) ?><br>
                <strong>Progress Notes:</strong> <?= htmlspecialchars($session['progress_notes']) ?>
              </div>
              <?php if (!empty($session['exercises'])): ?>
              <table class="table table-bordered table-hover mb-0">
                <thead>
                  <tr>
                    <th>Exercise</th>
                    <th>Reps</th>
                    <th>Duration</th>
                    <th>Notes</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($session['exercises'] as $ex): ?>
                  <tr>
                    <td><?= htmlspecialchars($ex['name']) ?></td>
                    <td><?= htmlspecialchars($ex['reps']) ?></td>
                    <td><?= htmlspecialchars($ex['duration_minutes']) ?></td>
                    <td><?= htmlspecialchars($ex['notes']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="mb-3">
      <label class="form-label">Exercises</label>
      <div id="exerciseContainer"></div>
      <button type="button" class="btn btn-secondary mt-2" onclick="addExercise()">+ Add Exercise</button>
    </div>

    <div class="mb-3">
      <label class="form-label">Doctor's Remarks</label>
      <textarea name="remarks" class="form-control" rows="3" required></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Progress Notes</label>
      <textarea name="progress_notes" class="form-control" rows="2"></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Save Treatment</button>
  </form>
  </div>
</div>

<script>
  const exerciseMaster = <?= json_encode($exercise_master) ?>;

  document.addEventListener('DOMContentLoaded', () => {
    addExercise();
  });

  function addExercise() {
    const container = document.getElementById('exerciseContainer');
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-end mb-2 exercise-row';

    row.innerHTML = `
      <div class="col-md-4">
        <select name="exercises_exercise_id[]" class="form-select exercise-select"></select>
      </div>
      <div class="col-md-2">
        <input type="number" name="exercises_reps[]" class="form-control" placeholder="Reps">
      </div>
      <div class="col-md-2">
        <input type="number" name="exercises_duration_minutes[]" class="form-control" placeholder="Duration (min)">
      </div>
      <div class="col-md-3">
        <input type="text" name="exercises_notes[]" class="form-control" placeholder="Notes">
      </div>
      <div class="col-md-1 text-center">
        <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeRow(this)">X</button>
      </div>
    `;

    container.appendChild(row);
    updateExerciseOptions();
    row.querySelector('.exercise-select').addEventListener('change', updateExerciseOptions);
  }

  function updateExerciseOptions() {
    const selects = document.querySelectorAll('.exercise-select');
    const selectedValues = Array.from(selects).map(s => s.value).filter(v => v);
    selects.forEach(sel => {
      const currentValue = sel.value;
      if ($(sel).data('select2')) {
        $(sel).select2('destroy');
      }
      sel.innerHTML = '<option value="">-- Select Exercise --</option>' +
        exerciseMaster.map(ex => {
          const disabled = selectedValues.includes(String(ex.id)) && String(ex.id) !== currentValue;
          return `<option value="${ex.id}" ${disabled ? 'disabled' : ''}>${ex.name}</option>`;
        }).join('');
      sel.value = currentValue;
      $(sel).select2({width: '100%'});
    });
  }

  function removeRow(btn) {
    btn.closest('.exercise-row').remove();
    updateExerciseOptions();
  }
</script>
<?php include '../../includes/footer.php'; ?>
