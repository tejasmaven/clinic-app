<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Doctor');

require_once '../../controllers/TreatmentController.php';
$treatmentController = new TreatmentController($pdo);

// Get patient_id from query string
$patient_id = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;
if (!$patient_id) {
    die("Invalid Patient ID");
}

// Fetch patient details
$patientStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$patientStmt->execute([$patient_id]);
$patient = $patientStmt->fetch();
if (!$patient) {
    die("Patient not found.");
}

// Fetch existing episodes
$episodesStmt = $pdo->prepare("SELECT * FROM treatment_episodes WHERE patient_id = ? ORDER BY start_date DESC");
$episodesStmt->execute([$patient_id]);
$episodes = $episodesStmt->fetchAll();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = isset($_POST['patient_id']) ? (int) $_POST['patient_id'] : 0;
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $initial_complaints = trim($_POST['initial_complaints'] ?? '');
    $doctor_id = $_SESSION['user_id'] ?? null;
    try {
        $stmt = $pdo->prepare("INSERT INTO treatment_episodes 
            (patient_id, start_date, initial_complaints, created_by, status) 
            VALUES (?, ?, ?, ?, 'Active')");
        $stmt->execute([$patient_id, $start_date, $initial_complaints, $doctor_id]);

        $episode_id = $pdo->lastInsertId();

        // Redirect to treatment screen
        header("Location: start_treatment.php?episode_id=" . $episode_id."&patient_id=".$patient_id);
        exit;
    } catch (Exception $e) {
        die("Error creating episode: " . $e->getMessage());
    }
}
include '../../includes/header.php';

?>
<div class="workspace-layout">
  <?php include '../../layouts/doctor_sidebar.php'; ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">Treatment Episodes</h1>
        <p class="workspace-page-subtitle">Manage care plans for <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>.</p>
      </div>
      <div class="d-flex gap-2">
        <a href="manage_patients.php" class="btn btn-outline-secondary">Back to Patients</a>
      </div>
    </div>

    <div class="app-card mb-4">
      <h5 class="mb-3">Existing Episodes</h5>
      <?php if ($episodes): ?>
        <div class="list-group">
          <?php foreach ($episodes as $ep): ?>
            <div class="list-group-item d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
              <div>
                <div class="fw-semibold">Started on <?= htmlspecialchars(format_display_date($ep['start_date'])) ?></div>
                <div class="text-muted small"><?= htmlspecialchars($ep['initial_complaints']) ?></div>
              </div>
              <a class="btn btn-sm btn-primary" href="start_treatment.php?patient_id=<?= $patient_id ?>&episode_id=<?= $ep['id'] ?>">Log Session</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="text-muted mb-0">No treatment episodes recorded yet.</p>
      <?php endif; ?>
    </div>

    <div class="app-card">
      <h5 class="mb-3">Start New Episode</h5>
      <form method="post" class="row g-3">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <div class="col-12 col-md-4">
          <label for="start_date" class="form-label">Start Date</label>
          <input type="date" name="start_date" id="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-12">
          <label for="initial_complaints" class="form-label">Initial Complaint Summary</label>
          <textarea name="initial_complaints" id="initial_complaints" class="form-control" rows="3" placeholder="Summarise the patient's presentation" required></textarea>
        </div>
        <div class="col-12 col-md-4 col-lg-3">
          <button type="submit" class="btn btn-success w-100">Create &amp; Proceed</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
