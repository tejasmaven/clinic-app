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
<style>
  .voice-textarea-control {
    align-items: stretch;
    display: flex;
    gap: 0.5rem;
  }

  .voice-textarea-control textarea {
    min-height: 4.25rem;
    resize: vertical;
  }

  .voice-textarea-button {
    align-items: center;
    border-radius: 0.75rem;
    display: inline-flex;
    flex: 0 0 2.75rem;
    justify-content: center;
    min-height: 2.75rem;
    padding: 0;
  }

  .voice-textarea-icon {
    background-color: currentColor;
    display: inline-block;
    height: 1.15rem;
    width: 1.15rem;
  }

  .voice-textarea-icon-mic {
    -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z'/%3E%3Cpath d='M19 10v2a7 7 0 0 1-14 0v-2'/%3E%3Cpath d='M12 19v3'/%3E%3Cpath d='M8 22h8'/%3E%3C/svg%3E") center / contain no-repeat;
    mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z'/%3E%3Cpath d='M19 10v2a7 7 0 0 1-14 0v-2'/%3E%3Cpath d='M12 19v3'/%3E%3Cpath d='M8 22h8'/%3E%3C/svg%3E") center / contain no-repeat;
  }

  .voice-textarea-icon-stop {
    border-radius: 0.2rem;
    display: none;
    height: 0.9rem;
    width: 0.9rem;
  }

  .voice-textarea-button.is-listening .voice-textarea-icon-mic {
    display: none;
  }

  .voice-textarea-button.is-listening .voice-textarea-icon-stop {
    display: inline-block;
  }
</style>
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
            <div class="voice-textarea-control">
              <textarea name="initial_complaints" id="edit_initial_complaints" class="form-control" rows="3" placeholder="Summarise the patient's presentation" required><?= htmlspecialchars($editInitialComplaintsValue) ?></textarea>
              <button type="button" class="btn btn-outline-primary voice-textarea-button" data-voice-target="edit_initial_complaints" aria-label="Start voice typing for Initial Complaint Summary" title="Start voice typing">
                <span class="voice-textarea-icon voice-textarea-icon-mic" aria-hidden="true"></span>
                <span class="voice-textarea-icon voice-textarea-icon-stop" aria-hidden="true"></span>
              </button>
            </div>
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
          <div class="voice-textarea-control">
            <textarea name="initial_complaints" id="initial_complaints" class="form-control" rows="3" placeholder="Summarise the patient's presentation" required><?= htmlspecialchars($newInitialComplaintsValue) ?></textarea>
            <button type="button" class="btn btn-outline-primary voice-textarea-button" data-voice-target="initial_complaints" aria-label="Start voice typing for Initial Complaint Summary" title="Start voice typing">
              <span class="voice-textarea-icon voice-textarea-icon-mic" aria-hidden="true"></span>
              <span class="voice-textarea-icon voice-textarea-icon-stop" aria-hidden="true"></span>
            </button>
          </div>
        </div>
        <div class="col-12 col-md-4 col-lg-3">
          <button type="submit" class="btn btn-success w-100">Create &amp; Proceed</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
const voiceButtons = document.querySelectorAll('.voice-textarea-button');
let activeRecognition = null;
let activeVoiceBaseText = '';
let activeVoiceFinalText = '';
let shouldKeepListening = false;

function joinVoiceText(...parts) {
  return parts
    .map((part) => part.trim())
    .filter(Boolean)
    .join(' ');
}

function updateVoiceText(textarea, interimText = '') {
  textarea.value = joinVoiceText(activeVoiceBaseText, activeVoiceFinalText, interimText);
  textarea.dispatchEvent(new Event('input', { bubbles: true }));
  textarea.focus();
}

function resetVoiceButton(button) {
  if (!button) return;
  button.classList.remove('is-listening');
  button.classList.remove('btn-danger');
  button.classList.add('btn-outline-primary');
  button.setAttribute('aria-label', button.dataset.startLabel || 'Start voice typing');
  button.title = 'Start voice typing';
  button.disabled = false;
}

function resetVoiceState(button) {
  resetVoiceButton(button);
  voiceButtons.forEach((voiceButton) => {
    const textarea = document.getElementById(voiceButton.dataset.voiceTarget);
    voiceButton.disabled = !textarea || textarea.disabled;
  });
  activeRecognition = null;
  activeVoiceBaseText = '';
  activeVoiceFinalText = '';
  shouldKeepListening = false;
}

if (!SpeechRecognition) {
  voiceButtons.forEach((button) => {
    button.disabled = true;
    button.title = 'Voice typing is not supported in this browser. Please use Chrome or Edge.';
  });
} else {
  voiceButtons.forEach((button) => {
    button.dataset.startLabel = button.getAttribute('aria-label') || 'Start voice typing';

    button.addEventListener('click', () => {
      const textarea = document.getElementById(button.dataset.voiceTarget);
      if (!textarea || textarea.disabled) return;

      if (activeRecognition) {
        shouldKeepListening = false;
        activeRecognition.stop();
        return;
      }

      const recognition = new SpeechRecognition();
      activeRecognition = recognition;
      shouldKeepListening = true;
      activeVoiceBaseText = textarea.value.trim();
      activeVoiceFinalText = '';

      recognition.lang = 'en-IN';
      recognition.continuous = true;
      recognition.interimResults = true;

      voiceButtons.forEach((voiceButton) => {
        voiceButton.disabled = voiceButton !== button;
      });
      button.disabled = false;
      button.classList.add('is-listening');
      button.classList.remove('btn-outline-primary');
      button.classList.add('btn-danger');
      button.setAttribute('aria-label', 'Stop voice typing');
      button.title = 'Stop voice typing';

      recognition.onresult = (event) => {
        let interimText = '';

        for (let i = event.resultIndex; i < event.results.length; i++) {
          const transcript = event.results[i][0].transcript;
          if (event.results[i].isFinal) {
            activeVoiceFinalText = joinVoiceText(activeVoiceFinalText, transcript);
          } else {
            interimText = joinVoiceText(interimText, transcript);
          }
        }

        updateVoiceText(textarea, interimText);
      };

      recognition.onerror = (event) => {
        if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
          shouldKeepListening = false;
          alert('Microphone access is blocked. Please allow microphone permission and try again.');
        }
      };

      recognition.onend = () => {
        if (shouldKeepListening && activeRecognition === recognition) {
          activeVoiceBaseText = textarea.value.trim();
          activeVoiceFinalText = '';
          recognition.start();
          return;
        }

        resetVoiceState(button);
      };

      try {
        recognition.start();
      } catch (error) {
        shouldKeepListening = false;
        resetVoiceState(button);
        alert('Voice typing could not be started. Please allow microphone access and try again.');
      }
    });
  });
}
</script>

<?php include '../../includes/footer.php'; ?>
