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

    <div class="mb-3">
      <label class="form-label">Exercises</label>
      <div id="exerciseContainer">

        <table class="table table-bordered table-hover">
        <thead>
          <tr>
            <th>Exercise</th>
            <th>Reps</th>
            <th>Duration</th>
            <th>Notes</th>
            
          </tr>
        </thead>
        <tbody>
          <?php foreach ($previousExercises as $ex): ?>
          <tr>
            <td><?= $ex['name'] ?></td>
            <td><?= $ex['reps'] ?></td>
            <td><?= $ex['duration_minutes'] ?></td>
            <td><?= $ex['notes'] ?></td>
           
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>





        
      </div>
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

  function addExercise() {
    const container = document.getElementById('exerciseContainer');
    const row = document.createElement('div');
    row.className = 'exercise-row';

    const select = document.createElement('select');
    select.name = "exercises_exercise_id[]";
    select.className = "form-select";
    select.innerHTML = '<option value="">-- Select Exercise --</option>' +
      exerciseMaster.map(ex => `<option value="${ex.id}">${ex.name}</option>`).join('');

    row.innerHTML = `
      <input type="number" name="exercises_reps[]" class="form-control" placeholder="Reps">
      <input type="number" name="exercises_duration_minutes[]" class="form-control" placeholder="Duration (min)">
      <input type="text" name="exercises_notes[]" class="form-control" placeholder="Notes">
      <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button>
    `;

    row.prepend(select);
    container.appendChild(row);
  }

  function removeRow(btn) {
    btn.closest('.exercise-row').remove();
  }
</script>
<?php include '../../includes/footer.php'; ?>
