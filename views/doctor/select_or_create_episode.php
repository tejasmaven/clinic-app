<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole(['Doctor', 'Admin']);

require_once '../../controllers/TreatmentController.php';
$treatmentController = new TreatmentController($pdo);

$isAdmin = ($_SESSION['role'] ?? '') === 'Admin';
$patientsUrl = $isAdmin ? '../admin/manage_patients.php' : 'manage_patients.php';
$layoutClass = $isAdmin ? 'admin-layout' : 'workspace-layout';
$contentClass = $isAdmin ? 'admin-content' : 'workspace-content';
$headerClass = $isAdmin ? 'admin-page-header' : 'workspace-page-header';
$titleClass = $isAdmin ? 'admin-page-title' : 'workspace-page-title';
$subtitleClass = $isAdmin ? 'admin-page-subtitle' : 'workspace-page-subtitle';
$msg = null;
$msgClass = 'alert-warning';
$newFeeAmountValue = '';
$newInitialComplaintsValue = '';
$editFeeAmountValue = '';
$editStartDateValue = '';
$editInitialComplaintsValue = '';
$editEpisodeId = $isAdmin ? (int) ($_GET['edit_episode_id'] ?? 0) : 0;
$editingEpisode = null;

// Get patient_id from query string
$patient_id = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = isset($_POST['patient_id']) ? (int) $_POST['patient_id'] : $patient_id;
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create_episode';
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $initial_complaints = trim($_POST['initial_complaints'] ?? '');
    $doctor_id = $_SESSION['user_id'] ?? null;
    $submittedFeeAmountValue = trim((string) ($_POST['fee_amount'] ?? ''));
    $feeAmount = 0.0;

    if ($action === 'update_episode') {
        $editEpisodeId = (int) ($_POST['episode_id'] ?? 0);
        $editFeeAmountValue = $submittedFeeAmountValue;
    } else {
        $newFeeAmountValue = $submittedFeeAmountValue;
        $newInitialComplaintsValue = $initial_complaints;
    }

    if ($action === 'update_episode' && !$isAdmin) {
        $msg = 'Only admin users can edit episode details.';
    }

    if ($msg === null && ($action === 'create_episode' || $action === 'update_episode') && $isAdmin) {
        if ($submittedFeeAmountValue === '' || !is_numeric($submittedFeeAmountValue) || (float) $submittedFeeAmountValue < 0) {
            $msg = 'Please enter a valid numeric fees amount.';
        } else {
            $feeAmount = (float) $submittedFeeAmountValue;
        }
    }

    if ($msg === null) {
        try {
            if ($action === 'update_episode') {
                $episodeId = (int) ($_POST['episode_id'] ?? 0);
                $episodeCheckStmt = $pdo->prepare("SELECT id FROM treatment_episodes WHERE id = ? AND patient_id = ?");
                $episodeCheckStmt->execute([$episodeId, $patient_id]);

                if (!$episodeCheckStmt->fetch()) {
                    $msg = 'Episode not found for this patient.';
                } else {
                    $stmt = $pdo->prepare("UPDATE treatment_episodes
                        SET start_date = ?, initial_complaints = ?, fee_amount = ?
                        WHERE id = ? AND patient_id = ?");
                    $stmt->execute([$start_date, $initial_complaints, $feeAmount, $episodeId, $patient_id]);

                    header("Location: select_or_create_episode.php?patient_id=" . $patient_id . "&updated=1");
                    exit;
                }
            } elseif ($action === 'create_episode') {
                $stmt = $pdo->prepare("INSERT INTO treatment_episodes
                    (patient_id, start_date, initial_complaints, created_by, status, fee_amount)
                    VALUES (?, ?, ?, ?, 'Active', ?)");
                $stmt->execute([$patient_id, $start_date, $initial_complaints, $doctor_id, $feeAmount]);

                $episode_id = (int) $pdo->lastInsertId();

                // Redirect to treatment screen
                header("Location: start_treatment.php?episode_id=" . $episode_id . "&patient_id=" . $patient_id);
                exit;
            } else {
                $msg = 'Invalid episode action.';
            }
        } catch (Exception $e) {
            $msg = $action === 'update_episode'
                ? "Error updating episode: " . $e->getMessage()
                : "Error creating episode: " . $e->getMessage();
        }
    }
}

if (isset($_GET['updated'])) {
    $msg = 'Episode details updated successfully.';
    $msgClass = 'alert-success';
}

// Fetch existing episodes
$episodesStmt = $pdo->prepare("SELECT * FROM treatment_episodes WHERE patient_id = ? ORDER BY start_date DESC");
$episodesStmt->execute([$patient_id]);
$episodes = $episodesStmt->fetchAll();

if ($editEpisodeId > 0) {
    foreach ($episodes as $episode) {
        if ((int) $episode['id'] === $editEpisodeId) {
            $editingEpisode = $episode;
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_episode') {
                $editStartDateValue = $_POST['start_date'] ?? $episode['start_date'];
                $editInitialComplaintsValue = $_POST['initial_complaints'] ?? $episode['initial_complaints'];
            } else {
                $editFeeAmountValue = number_format((float) ($episode['fee_amount'] ?? 0), 2, '.', '');
                $editStartDateValue = $episode['start_date'];
                $editInitialComplaintsValue = $episode['initial_complaints'];
            }
            break;
        }
    }

    if ($editingEpisode === null) {
        $msg = 'Episode not found for this patient.';
        $editEpisodeId = 0;
    }
}

include '../../includes/header.php';

?>
<div class="<?= $layoutClass ?>">
  <?php include $isAdmin ? '../../layouts/admin_sidebar.php' : '../../layouts/doctor_sidebar.php'; ?>
  <div class="<?= $contentClass ?>">
    <div class="<?= $headerClass ?>">
      <div>
        <h1 class="<?= $titleClass ?>">Treatment Episodes</h1>
        <p class="<?= $subtitleClass ?>">Manage care plans for <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>.</p>
      </div>
      <div class="d-flex gap-2">
        <a href="<?= $patientsUrl ?>" class="btn btn-outline-secondary">Back to Patients</a>
      </div>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert <?= $msgClass ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($isAdmin && $editingEpisode): ?>
      <div class="app-card mb-4">
        <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-start mb-3">
          <div>
            <h5 class="mb-1">Edit Episode Details</h5>
            <p class="text-muted mb-0">Update the start date, fees amount, and complaint summary for this episode.</p>
          </div>
          <a href="select_or_create_episode.php?patient_id=<?= $patient_id ?>" class="btn btn-sm btn-outline-secondary">Cancel Edit</a>
        </div>
        <form method="post" class="row g-3">
          <input type="hidden" name="action" value="update_episode">
          <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
          <input type="hidden" name="episode_id" value="<?= (int) $editingEpisode['id'] ?>">
          <div class="col-12 col-md-4">
            <label for="edit_start_date" class="form-label">Start Date</label>
            <input type="date" name="start_date" id="edit_start_date" class="form-control" value="<?= htmlspecialchars($editStartDateValue) ?>" required>
          </div>
          <div class="col-12 col-md-4">
            <label for="edit_fee_amount" class="form-label">Fees Amount</label>
            <input type="number" step="0.01" min="0" name="fee_amount" id="edit_fee_amount" class="form-control" value="<?= htmlspecialchars($editFeeAmountValue) ?>" placeholder="0.00" required>
          </div>
          <div class="col-12">
            <label for="edit_initial_complaints" class="form-label">Initial Complaint Summary</label>
            <textarea name="initial_complaints" id="edit_initial_complaints" class="form-control" rows="3" placeholder="Summarise the patient's presentation" required><?= htmlspecialchars($editInitialComplaintsValue) ?></textarea>
          </div>
          <div class="col-12 col-md-4 col-lg-3">
            <button type="submit" class="btn btn-success w-100">Save Episode</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <div class="app-card mb-4">
      <h5 class="mb-3">Existing Episodes</h5>
      <?php if ($episodes): ?>
        <div class="list-group">
          <?php foreach ($episodes as $ep): ?>
            <div class="list-group-item d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
              <div>
                <div class="fw-semibold">Started on <?= htmlspecialchars(format_display_date($ep['start_date'])) ?></div>
                <?php if ($isAdmin): ?>
                  <div class="text-muted small">Fees: <?= htmlspecialchars(number_format((float) ($ep['fee_amount'] ?? 0), 2)) ?></div>
                <?php endif; ?>
                <div class="text-muted small"><?= htmlspecialchars($ep['initial_complaints']) ?></div>
              </div>
              <div class="d-flex gap-2 flex-wrap">
                <?php if ($isAdmin): ?>
                  <a class="btn btn-sm btn-outline-primary" href="select_or_create_episode.php?patient_id=<?= $patient_id ?>&edit_episode_id=<?= (int) $ep['id'] ?>">Edit</a>
                <?php endif; ?>
                <a class="btn btn-sm btn-primary" href="start_treatment.php?patient_id=<?= $patient_id ?>&episode_id=<?= (int) $ep['id'] ?>">Log Session</a>
              </div>
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
        <input type="hidden" name="action" value="create_episode">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <div class="col-12 col-md-4">
          <label for="start_date" class="form-label">Start Date</label>
          <input type="date" name="start_date" id="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <?php if ($isAdmin): ?>
          <div class="col-12 col-md-4">
            <label for="fee_amount" class="form-label">Fees Amount</label>
            <input type="number" step="0.01" min="0" name="fee_amount" id="fee_amount" class="form-control" value="<?= htmlspecialchars($newFeeAmountValue) ?>" placeholder="0.00" required>
          </div>
        <?php endif; ?>
        <div class="col-12">
          <label for="initial_complaints" class="form-label">Initial Complaint Summary</label>
          <textarea name="initial_complaints" id="initial_complaints" class="form-control" rows="3" placeholder="Summarise the patient's presentation" required><?= htmlspecialchars($newInitialComplaintsValue) ?></textarea>
        </div>
        <div class="col-12 col-md-4 col-lg-3">
          <button type="submit" class="btn btn-success w-100">Create &amp; Proceed</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
