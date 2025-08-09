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
<div class="row">
  <div class="col-md-3"><?php include '../../layouts/doctor_sidebar.php'; ?></div>
  <div class="col-md-9">
    <h4>Select or Create Treatment Episode</h4>
    <p><strong>Patient:</strong> <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></p>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <h5>Existing Episodes</h5>
    <?php if ($episodes): ?>
        <ul class="list-group mb-4">
            <?php foreach ($episodes as $ep): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?= htmlspecialchars($ep['start_date']) ?> - <?= htmlspecialchars($ep['initial_complaints']) ?>
                    <a class="btn btn-sm btn-primary" href="start_treatment.php?patient_id=<?= $patient_id ?>&episode_id=<?= $ep['id'] ?>">Log Session</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-muted">No episodes found.</p>
    <?php endif; ?>

    <h5>Start New Episode</h5>
    <form method="post">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <div class="mb-3">
            <label for="start_date" class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="mb-3">
            <label for="initial_complaints" class="form-label">Complaint Summary</label>
            <textarea name="initial_complaints" class="form-control" required></textarea>
        </div>
        <button type="submit" class="btn btn-success">Create & Proceed</button>
    </form>
  </div>
</div>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
 <?php include '../../includes/footer.php'; ?>   
