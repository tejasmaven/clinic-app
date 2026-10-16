<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['Admin', 'Super Admin', 'Doctor', 'Receptionist'], true)) {
    header('Location: ../login.php');
    exit;
}

require_once '../../controllers/PatientController.php';
require_once '../../controllers/AssessmentController.php';

$patientId = (int) ($_GET['patient_id'] ?? 0);
if ($patientId <= 0) {
    exit('Invalid patient ID.');
}

$patientController = new PatientController($pdo);
$assessmentController = new AssessmentController($pdo);
$patient = $patientController->getPatientById($patientId);
if (!$patient) {
    exit('Patient not found.');
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $msg = $assessmentController->savePatientAssessment($patientId, (int) ($_SESSION['user_id'] ?? 0), $_POST);
    } catch (Throwable $e) {
        $msg = 'Unable to save assessment: ' . $e->getMessage();
    }
}

$sections = $assessmentController->getSectionsWithItems(true);
$patientAssessment = $assessmentController->getPatientAssessment($patientId);
$assessment = $patientAssessment['assessment'];
$answers = $patientAssessment['answers'];
$isAdmin = in_array($_SESSION['role'] ?? '', ['Admin', 'Super Admin'], true);
$isDoctor = ($_SESSION['role'] ?? '') === 'Doctor';
$layoutClass = $isAdmin ? 'admin-layout' : 'workspace-layout';
$contentClass = $isAdmin ? 'admin-content' : 'workspace-content';
$headerClass = $isAdmin ? 'admin-page-header' : 'workspace-page-header';
$titleClass = $isAdmin ? 'admin-page-title' : 'workspace-page-title';
$subtitleClass = $isAdmin ? 'admin-page-subtitle' : 'workspace-page-subtitle';
$backUrl = $isAdmin ? '../admin/manage_patients.php' : ($isDoctor ? '../doctor/manage_patients.php' : '../receptionist/manage_patients.php');

include '../../includes/header.php';
?>

<div class="<?= $layoutClass ?>">
    <?php
        if ($isAdmin) {
            include '../../layouts/admin_sidebar.php';
        } elseif ($isDoctor) {
            include '../../layouts/doctor_sidebar.php';
        } else {
            include '../../layouts/receptionist_sidebar.php';
        }
    ?>
    <div class="<?= $contentClass ?>">
        <div class="<?= $headerClass ?>">
            <div>
                <h1 class="<?= $titleClass ?>">Patient Assessment</h1>
                <p class="<?= $subtitleClass ?>"><?= htmlspecialchars(trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''))) ?></p>
            </div>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($backUrl) ?>">Back</a>
        </div>

        <?php if ($msg !== ''): ?>
            <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <?php if (empty($sections)): ?>
            <div class="app-card">
                <p class="text-muted mb-0">No active assessment setup found. Admin or Doctor can create sections and questions from Assessment Setup.</p>
            </div>
        <?php else: ?>
            <form method="post" class="d-flex flex-column gap-4">
                <div class="app-card">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="assessment_date">Assessment date</label>
                            <input class="form-control" type="date" id="assessment_date" name="assessment_date" value="<?= htmlspecialchars($assessment['assessment_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label" for="notes">Overall notes</label>
                            <input class="form-control" id="notes" name="notes" value="<?= htmlspecialchars($assessment['notes'] ?? '') ?>" placeholder="Optional summary or therapy direction">
                        </div>
                    </div>
                </div>

                <?php foreach ($sections as $section): ?>
                    <div class="app-card">
                        <h5 class="mb-1"><?= htmlspecialchars($section['title']) ?></h5>
                        <?php if (!empty($section['description'])): ?>
                            <p class="text-muted mb-3"><?= htmlspecialchars($section['description']) ?></p>
                        <?php endif; ?>

                        <div class="assessment-answer-list">
                            <?php foreach ($section['items'] as $item): ?>
                                <?php
                                    $itemId = (int) $item['id'];
                                    $inputName = 'answers[' . $itemId . ']';
                                    $currentValue = $answers[$itemId] ?? '';
                                ?>
                                <div class="assessment-answer-row">
                                    <label class="assessment-answer-label" for="answer_<?= $itemId ?>">
                                        <?= htmlspecialchars($item['label']) ?>
                                        <span class="badge bg-light text-dark"><?= $item['item_type'] === 'examination' ? 'Exam' : 'Question' ?></span>
                                    </label>

                                    <div class="assessment-answer-control">
                                    <?php if (($item['item_type'] ?? '') === 'question' && $item['input_type'] === 'yes_no'): ?>
                                        <div class="assessment-radio-group">
                                            <?php foreach (['Yes', 'No'] as $option): ?>
                                                <?php $radioId = 'answer_' . $itemId . '_' . strtolower($option); ?>
                                                <div class="form-check form-check-inline mb-0">
                                                    <input class="form-check-input" type="radio" id="<?= $radioId ?>" name="<?= htmlspecialchars($inputName) ?>" value="<?= $option ?>" <?= $currentValue === $option ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="<?= $radioId ?>"><?= $option ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif (($item['item_type'] ?? '') === 'question' && $item['input_type'] === 'textarea'): ?>
                                        <div class="patient-voice-control">
                                            <textarea class="form-control assessment-answer-textarea" id="answer_<?= $itemId ?>" name="<?= htmlspecialchars($inputName) ?>" rows="2"><?= htmlspecialchars($currentValue) ?></textarea>
                                            <button type="button" class="btn btn-outline-primary patient-voice-button" data-voice-target="answer_<?= $itemId ?>" aria-label="Start voice typing for assessment answer" title="Start voice typing">
                                                <span class="patient-voice-icon patient-voice-icon-mic" aria-hidden="true"></span>
                                                <span class="patient-voice-icon patient-voice-icon-stop" aria-hidden="true"></span>
                                            </button>
                                        </div>
                                    <?php elseif (($item['item_type'] ?? '') === 'question' && $item['input_type'] === 'number'): ?>
                                        <input class="form-control" type="number" step="0.01" id="answer_<?= $itemId ?>" name="<?= htmlspecialchars($inputName) ?>" value="<?= htmlspecialchars($currentValue) ?>">
                                    <?php elseif (($item['item_type'] ?? '') === 'question' && $item['input_type'] === 'select'): ?>
                                        <select class="form-select" id="answer_<?= $itemId ?>" name="<?= htmlspecialchars($inputName) ?>">
                                            <option value="">Select</option>
                                            <?php foreach (array_filter(array_map('trim', explode(',', (string) $item['option_values']))) as $option): ?>
                                                <option value="<?= htmlspecialchars($option) ?>" <?= $currentValue === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <div class="patient-voice-control">
                                            <input class="form-control" type="text" id="answer_<?= $itemId ?>" name="<?= htmlspecialchars($inputName) ?>" value="<?= htmlspecialchars($currentValue) ?>">
                                            <button type="button" class="btn btn-outline-primary patient-voice-button" data-voice-target="answer_<?= $itemId ?>" aria-label="Start voice typing for assessment answer" title="Start voice typing">
                                                <span class="patient-voice-icon patient-voice-icon-mic" aria-hidden="true"></span>
                                                <span class="patient-voice-icon patient-voice-icon-stop" aria-hidden="true"></span>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="d-flex justify-content-end">
                    <button class="btn btn-success">Save Assessment</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
.assessment-answer-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.assessment-answer-row {
    align-items: center;
    display: grid;
    gap: 1rem;
    grid-template-columns: minmax(220px, 0.9fr) minmax(280px, 1.1fr);
}

.assessment-answer-label {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    font-weight: 600;
    gap: 0.45rem;
    margin-bottom: 0;
}

.patient-voice-control {
    align-items: stretch;
    display: flex;
    gap: 0.5rem;
}

.patient-voice-button {
    align-items: center;
    border-radius: 0.75rem;
    display: inline-flex;
    flex: 0 0 2.75rem;
    justify-content: center;
    min-height: 2.75rem;
    padding: 0;
}

.patient-voice-icon {
    background-color: currentColor;
    display: inline-block;
    height: 1.15rem;
    width: 1.15rem;
}

.patient-voice-icon-mic {
    -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z'/%3E%3Cpath d='M19 10v2a7 7 0 0 1-14 0v-2'/%3E%3Cpath d='M12 19v3'/%3E%3Cpath d='M8 22h8'/%3E%3C/svg%3E") center / contain no-repeat;
    mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z'/%3E%3Cpath d='M19 10v2a7 7 0 0 1-14 0v-2'/%3E%3Cpath d='M12 19v3'/%3E%3Cpath d='M8 22h8'/%3E%3C/svg%3E") center / contain no-repeat;
}

.patient-voice-icon-stop {
    border-radius: 0.2rem;
    display: none;
    height: 0.9rem;
    width: 0.9rem;
}

.patient-voice-button.is-listening .patient-voice-icon-mic {
    display: none;
}

.patient-voice-button.is-listening .patient-voice-icon-stop {
    display: inline-block;
}

.assessment-answer-textarea {
    min-height: 2.75rem;
    resize: vertical;
}

@media (max-width: 767.98px) {
    .assessment-answer-row {
        align-items: stretch;
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const voiceButtons = document.querySelectorAll('.patient-voice-button');
    let activeRecognition = null;
    let activeVoiceButton = null;
    let shouldKeepListening = false;
    let activeVoiceBaseText = '';
    let activeVoiceFinalText = '';

    function joinVoiceText(...parts) {
        return parts.map((part) => part.trim()).filter(Boolean).join(' ');
    }

    function updateVoiceText(input, interimText = '') {
        input.value = joinVoiceText(activeVoiceBaseText, activeVoiceFinalText, interimText);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.focus();
    }

    function resetVoiceButton(button) {
        if (!button) return;
        button.classList.remove('is-listening', 'btn-danger');
        button.classList.add('btn-outline-primary');
        button.setAttribute('aria-label', button.dataset.startLabel || 'Start voice typing');
        button.title = 'Start voice typing';
        button.disabled = false;
    }

    if (!SpeechRecognition) {
        voiceButtons.forEach((button) => {
            button.disabled = true;
            button.title = 'Voice typing is not supported in this browser. Please use Chrome or Edge.';
        });
        return;
    }

    voiceButtons.forEach((button) => {
        button.dataset.startLabel = button.getAttribute('aria-label') || 'Start voice typing';
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.voiceTarget);
            if (!input) return;

            if (activeRecognition) {
                shouldKeepListening = false;
                activeRecognition.stop();
                return;
            }

            const recognition = new SpeechRecognition();
            activeRecognition = recognition;
            activeVoiceButton = button;
            shouldKeepListening = true;
            activeVoiceBaseText = input.value.trim();
            activeVoiceFinalText = '';

            recognition.lang = 'en-IN';
            recognition.continuous = true;
            recognition.interimResults = true;

            voiceButtons.forEach((voiceButton) => {
                voiceButton.disabled = voiceButton !== button;
            });
            button.disabled = false;
            button.classList.add('is-listening', 'btn-danger');
            button.classList.remove('btn-outline-primary');
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
                updateVoiceText(input, interimText);
            };

            recognition.onerror = (event) => {
                if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                    shouldKeepListening = false;
                    alert('Microphone access is blocked. Please allow microphone permission and try again.');
                }
            };

            recognition.onend = () => {
                if (shouldKeepListening && activeRecognition === recognition) {
                    activeVoiceBaseText = input.value.trim();
                    activeVoiceFinalText = '';
                    recognition.start();
                    return;
                }

                resetVoiceButton(button);
                voiceButtons.forEach((voiceButton) => {
                    voiceButton.disabled = false;
                });
                activeRecognition = null;
                activeVoiceButton = null;
                shouldKeepListening = false;
                activeVoiceBaseText = '';
                activeVoiceFinalText = '';
            };

            try {
                recognition.start();
            } catch (error) {
                shouldKeepListening = false;
                resetVoiceButton(button);
                voiceButtons.forEach((voiceButton) => {
                    voiceButton.disabled = false;
                });
                activeRecognition = null;
                activeVoiceButton = null;
                activeVoiceBaseText = '';
                activeVoiceFinalText = '';
                alert('Voice typing could not be started. Please allow microphone access and try again.');
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
