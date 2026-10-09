<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();
requireRole('Admin');

require_once '../../controllers/MedicalReportController.php';

$controller = new MedicalReportController($pdo);
$patients = $controller->getPatients();

$today = new DateTimeImmutable('today');
$defaultStart = $today->sub(new DateInterval('P6D'));

$patientId = (int) ($_GET['patient_id'] ?? 0);
$startInput = $_GET['start_date'] ?? $defaultStart->format('Y-m-d');
$endInput = $_GET['end_date'] ?? $today->format('Y-m-d');
$notes = $_GET['notes'] ?? '';
$reportGenerated = ($_GET['generate'] ?? '') === '1';
$errors = [];

$startDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $startInput) ?: $defaultStart;
$endDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $endInput) ?: $today;

if ($startDateObj > $endDateObj) {
    [$startDateObj, $endDateObj] = [$endDateObj, $startDateObj];
}

$startDate = $startDateObj->format('Y-m-d');
$endDate = $endDateObj->format('Y-m-d');
$selectedPatient = null;
$reportRows = [];
$firstAssessmentDate = null;
$firstAssessmentSummary = null;

if ($reportGenerated) {
    if ($patientId <= 0) {
        $errors[] = 'Please select a patient.';
    } else {
        $selectedPatient = $controller->getPatientById($patientId);
        if (!$selectedPatient) {
            $errors[] = 'Selected patient could not be found.';
        }
    }

    if (empty($errors) && $selectedPatient) {
        $firstAssessmentDate = $controller->getFirstAssessmentDate($patientId);
        $firstAssessmentSummary = $controller->getFirstAssessmentSummary($patientId);
        $reportRows = $controller->getTreatmentSummary($patientId, $startDate, $endDate);
    }
}

$selectedPatientName = $selectedPatient
    ? trim(($selectedPatient['first_name'] ?? '') . ' ' . ($selectedPatient['last_name'] ?? ''))
    : '';
$displayStart = $startDateObj->format('j M Y');
$displayEnd = $endDateObj->format('j M Y');
$printDate = (new DateTimeImmutable('now'))->format('j M Y');
$logoUrl = get_site_logo_url();
$assessmentFields = [];

if ($selectedPatient) {
    $assessmentFieldMap = [
        'allergy_medicines_in_use' => 'Allergy / Medicines in Use',
        'family_history' => 'Family History',
        'history' => 'History',
        'chief_complaints' => 'Chief Complaints',
        'assessment' => 'Assessment',
        'investigation' => 'Investigation',
        'diagnosis' => 'Diagnosis',
        'goal' => 'Goal',
    ];

    foreach ($assessmentFieldMap as $fieldKey => $fieldLabel) {
        $fieldValue = trim((string) ($selectedPatient[$fieldKey] ?? ''));
        if ($fieldValue !== '') {
            $assessmentFields[] = [
                'label' => $fieldLabel,
                'value' => $fieldValue,
            ];
        }
    }

    if ($firstAssessmentSummary !== null && $firstAssessmentSummary !== '') {
        array_unshift($assessmentFields, [
            'label' => 'Initial Complaint Summary',
            'value' => $firstAssessmentSummary,
        ]);
    }
}

function renderMedicalReportItems(array $items, string $emptyText): void {
    if (empty($items)) {
        echo '<span class="text-muted">' . htmlspecialchars($emptyText) . '</span>';
        return;
    }

    echo '<ul class="medical-report-item-list">';
    foreach ($items as $item) {
        $name = trim((string) ($item['name'] ?? ''));
        $notes = trim((string) ($item['notes'] ?? ''));

        echo '<li>';
        echo htmlspecialchars($name !== '' ? $name : 'Unnamed item');
        if ($notes !== '') {
            echo '<div class="medical-report-item-note">' . nl2br(htmlspecialchars($notes)) . '</div>';
        }
        echo '</li>';
    }
    echo '</ul>';
}

function renderMedicalReportSessionNotes(array $sessions): void {
    if (empty($sessions)) {
        return;
    }

    echo '<div class="medical-report-session-notes">';
    foreach ($sessions as $index => $notes) {
        if (empty($notes)) {
            continue;
        }

        echo '<div class="medical-report-session-note-group">';
        if (count($sessions) > 1) {
            echo '<div class="medical-report-session-note-title">Session ' . htmlspecialchars((string) ($index + 1)) . '</div>';
        }

        foreach ($notes as $note) {
            $label = trim((string) ($note['label'] ?? ''));
            $value = trim((string) ($note['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }

            echo '<div class="medical-report-session-note">';
            echo '<strong>' . htmlspecialchars($label) . ':</strong> ';
            echo '<span>' . nl2br(htmlspecialchars($value)) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}

include '../../includes/header.php';
?>

<div class="admin-layout medical-report-page">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header no-print">
            <div>
                <h1 class="admin-page-title">Medical Report</h1>
                <p class="admin-page-subtitle">Generate a printable treatment summary for any patient and date range.</p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger no-print" role="alert">
                <?= htmlspecialchars(implode(' ', $errors)) ?>
            </div>
        <?php endif; ?>

        <div class="app-card no-print">
            <form method="get" class="row g-3 align-items-end mb-0">
                <input type="hidden" name="generate" value="1">
                <div class="col-12 col-lg-4">
                    <label for="patient_id" class="form-label">Patient</label>
                    <select class="form-select searchable-patient-select" id="patient_id" name="patient_id" required>
                        <option value="">Search and select patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <?php
                                $fullName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
                                $label = $fullName;
                                if (!empty($patient['contact_number'])) {
                                    $label .= ' - ' . $patient['contact_number'];
                                }
                            ?>
                            <option value="<?= (int) $patient['id'] ?>" <?= $patientId === (int) $patient['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
                </div>
                <div class="col-12 col-lg-2">
                    <button class="btn btn-primary w-100">Generate</button>
                </div>
            </form>
        </div>

        <?php if ($reportGenerated && empty($errors)): ?>
            <div class="app-card no-print">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
                    <div>
                        <h5 class="mb-1">Report Preview</h5>
                        <p class="text-muted mb-0">
                            <?= htmlspecialchars($selectedPatientName) ?> &middot;
                            <?= htmlspecialchars($displayStart) ?> to <?= htmlspecialchars($displayEnd) ?>
                        </p>
                    </div>
                    <button type="button" class="btn btn-outline-primary align-self-md-start" onclick="window.print()" <?= empty($reportRows) ? 'disabled' : '' ?>>Print</button>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <div class="voice-textarea-control">
                        <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Enter additional notes to show on the printed report."><?= htmlspecialchars($notes) ?></textarea>
                        <button type="button" class="btn btn-outline-primary voice-textarea-button" data-voice-target="notes" aria-label="Start voice typing for Notes" title="Start voice typing">
                            <span class="voice-textarea-icon voice-textarea-icon-mic" aria-hidden="true"></span>
                            <span class="voice-textarea-icon voice-textarea-icon-stop" aria-hidden="true"></span>
                        </button>
                    </div>
                    <div class="form-text">Notes are only shown after generating the report and are included on the printed report.</div>
                </div>

                <div class="medical-report-preview">
                    <div class="medical-report-letterhead">
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Clinic Logo" class="medical-report-logo">
                        <div>
                            <h4 class="mb-1">Patient Medical Report</h4>
                            <div class="text-muted"><?= htmlspecialchars($selectedPatientName) ?></div>
                        </div>
                    </div>

                    <div class="medical-report-section">
                        <h6>Assessment Summary</h6>
                        <div class="medical-report-meta-grid">
                            <?php if ($firstAssessmentDate): ?>
                                <div>
                                    <span>First Assessment Date</span>
                                    <strong><?= htmlspecialchars(date('j M Y', strtotime($firstAssessmentDate))) ?></strong>
                                </div>
                            <?php endif; ?>
                            <div>
                                <span>Patient</span>
                                <strong><?= htmlspecialchars($selectedPatientName) ?></strong>
                            </div>
                            <?php if (!empty($selectedPatient['contact_number'])): ?>
                                <div>
                                    <span>Contact</span>
                                    <strong><?= htmlspecialchars($selectedPatient['contact_number']) ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($assessmentFields)): ?>
                            <table class="table table-bordered medical-report-assessment-table mb-0">
                                <tbody>
                                    <?php foreach ($assessmentFields as $field): ?>
                                        <tr>
                                            <th><?= htmlspecialchars($field['label']) ?></th>
                                            <td><?= nl2br(htmlspecialchars($field['value'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-muted mb-0">No assessment fields have been filled for this patient.</p>
                        <?php endif; ?>
                    </div>

                    <div class="medical-report-section">
                        <h6>Treatment Details</h6>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 medical-report-treatment-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Exercises</th>
                                        <th>Machines</th>
                                        <th class="fees-print-only">Fees</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($reportRows)): ?>
                                        <?php foreach ($reportRows as $row): ?>
                                            <tr>
                                                <td class="medical-report-date-cell"><?= htmlspecialchars(date('j M Y', strtotime($row['treatment_date']))) ?></td>
                                                <td><?php renderMedicalReportItems($row['exercises'] ?? [], 'No exercises recorded.'); ?></td>
                                                <td><?php renderMedicalReportItems($row['machines'] ?? [], 'No machines recorded.'); ?></td>
                                                <td class="fees-print-only"><?= number_format((float) $row['total_fees'], 2) ?></td>
                                            </tr>
                                            <?php if (!empty($row['session_notes'])): ?>
                                                <tr class="medical-report-notes-row">
                                                    <td colspan="3">
                                                        <div class="medical-report-notes-heading">Session Notes</div>
                                                        <?php renderMedicalReportSessionNotes($row['session_notes']); ?>
                                                    </td>
                                                    <td class="fees-print-only"></td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No treatment sessions found for the selected date range.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($reportGenerated && empty($errors) && !empty($reportRows)): ?>
    <section class="medical-report-print print-only" aria-label="Printable medical report">
        <div class="text-center mb-4 medical-report-print-logo-wrap">
            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Clinic Logo" class="medical-report-logo">
        </div>

        <h2>Patient Medical Report</h2>
        <p><strong>Patient:</strong> <?= htmlspecialchars($selectedPatientName) ?></p>
        <?php if (!empty($selectedPatient['contact_number'])): ?>
            <p><strong>Contact:</strong> <?= htmlspecialchars($selectedPatient['contact_number']) ?></p>
        <?php endif; ?>
        <?php if ($firstAssessmentDate): ?>
            <p><strong>First Assessment Date:</strong> <?= htmlspecialchars(date('j M Y', strtotime($firstAssessmentDate))) ?></p>
        <?php endif; ?>
        <p><strong>Report Period:</strong> <?= htmlspecialchars($displayStart) ?> to <?= htmlspecialchars($displayEnd) ?></p>
        <p><strong>Print Date:</strong> <?= htmlspecialchars($printDate) ?></p>

        <?php if (!empty($assessmentFields)): ?>
            <h4>Assessment Summary</h4>
            <table class="table table-bordered medical-report-assessment-table medical-report-print-assessment">
                <tbody>
                    <?php foreach ($assessmentFields as $field): ?>
                        <tr>
                            <th><?= htmlspecialchars($field['label']) ?></th>
                            <td><?= nl2br(htmlspecialchars($field['value'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h4>Treatment Details</h4>
        <table class="table table-bordered align-middle medical-report-print-table medical-report-treatment-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Exercises</th>
                    <th>Machines</th>
                    <th class="fees-print-only">Fees</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportRows as $row): ?>
                    <tr>
                        <td class="medical-report-date-cell"><?= htmlspecialchars(date('j M Y', strtotime($row['treatment_date']))) ?></td>
                        <td><?php renderMedicalReportItems($row['exercises'] ?? [], 'No exercises recorded.'); ?></td>
                        <td><?php renderMedicalReportItems($row['machines'] ?? [], 'No machines recorded.'); ?></td>
                        <td class="fees-print-only"><?= number_format((float) $row['total_fees'], 2) ?></td>
                    </tr>
                    <?php if (!empty($row['session_notes'])): ?>
                        <tr class="medical-report-notes-row">
                            <td colspan="3">
                                <div class="medical-report-notes-heading">Session Notes</div>
                                <?php renderMedicalReportSessionNotes($row['session_notes']); ?>
                            </td>
                            <td class="fees-print-only"></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-4">
            <strong>Additional Notes</strong>
            <p id="printNotes" class="medical-report-notes"><?= nl2br(htmlspecialchars($notes)) ?></p>
        </div>
    </section>
<?php endif; ?>

<style>
.medical-report-logo {
    max-height: 110px;
    max-width: 260px;
}

.medical-report-preview {
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 0.75rem;
    padding: 1.25rem;
}

.medical-report-letterhead {
    align-items: center;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    display: flex;
    gap: 1rem;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
}

.medical-report-section {
    margin-top: 1.25rem;
}

.medical-report-section h6 {
    color: #0f172a;
    font-weight: 700;
    margin-bottom: 0.85rem;
}

.medical-report-meta-grid {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.medical-report-meta-grid > div {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 0.65rem;
    padding: 0.85rem;
}

.medical-report-meta-grid span {
    color: #64748b;
    display: block;
    font-size: 0.82rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
}

.medical-report-assessment-table th {
    color: #334155;
    font-weight: 700;
    width: 14rem;
}

.medical-report-assessment-table th,
.medical-report-assessment-table td,
.medical-report-treatment-table th,
.medical-report-treatment-table td {
    vertical-align: top;
}

.medical-report-treatment-table {
    table-layout: fixed;
}

.medical-report-date-cell {
    width: 8.5rem;
    white-space: nowrap;
}

.medical-report-item-list {
    margin: 0;
    padding-left: 1rem;
}

.medical-report-item-list li + li {
    margin-top: 0.45rem;
}

.medical-report-item-note {
    color: #475569;
    font-size: 0.92rem;
}

.medical-report-notes-row th,
.medical-report-notes-row td {
    background: #f8fafc;
}

.medical-report-notes-heading {
    color: #334155;
    font-weight: 700;
    margin-bottom: 0.4rem;
}

.medical-report-session-note-group + .medical-report-session-note-group {
    border-top: 1px solid rgba(15, 23, 42, 0.12);
    margin-top: 0.65rem;
    padding-top: 0.65rem;
}

.medical-report-session-note-title {
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
}

.medical-report-session-note + .medical-report-session-note {
    margin-top: 0.35rem;
}

.medical-report-session-note span {
    color: #475569;
}

.voice-textarea-control {
    align-items: stretch;
    display: flex;
    gap: 0.5rem;
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

.fees-print-only {
    display: none;
}

.print-only {
    display: none;
}

@media print {
    @page {
        margin: 0.5in;
    }

    body {
        background: #fff !important;
    }

    body > nav,
    .admin-layout,
    .toast-container,
    .no-print {
        display: none !important;
    }

    .app-main {
        max-width: none !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }

    .medical-report-print {
        display: block !important;
        color: #000;
        font-size: 12pt;
        padding: 0.25in;
    }

    .medical-report-print h2 {
        font-size: 18pt;
        margin-bottom: 0.2in;
        text-align: center;
    }

    .medical-report-print h4 {
        font-size: 13pt;
        margin-top: 0.22in;
    }

    .medical-report-print-logo-wrap {
        border-bottom: 1px solid #000;
        padding-bottom: 0.15in;
    }

    .medical-report-print-assessment {
        margin-bottom: 0.18in;
    }

    .medical-report-assessment-table th {
        width: 1.8in;
    }

    .fees-print-only {
        display: table-cell !important;
    }

    .medical-report-treatment-table {
        table-layout: auto;
    }

    .medical-report-date-cell {
        width: 1.2in;
    }

    .medical-report-item-list {
        margin-bottom: 0;
        padding-left: 0.18in;
    }

    .medical-report-item-note {
        color: #000;
        font-size: 10.5pt;
    }

    .medical-report-session-note-group + .medical-report-session-note-group {
        border-top: 1px solid #000;
    }

    .medical-report-notes-row th,
    .medical-report-notes-row td {
        background: #fff !important;
    }

    .medical-report-session-note-title,
    .medical-report-session-note span {
        color: #000;
    }

    .medical-report-print-table th,
    .medical-report-print-table td {
        border: 1px solid #000 !important;
        padding: 0.4rem !important;
    }

    .medical-report-notes {
        white-space: pre-wrap;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.searchable-patient-select').select2({
            placeholder: 'Search and select patient',
            width: '100%'
        });
    }

    const notes = document.getElementById('notes');
    const printNotes = document.getElementById('printNotes');

    if (notes && printNotes) {
        notes.addEventListener('input', function () {
            printNotes.textContent = notes.value.trim();
        });
    }

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
});
</script>

<?php include '../../includes/footer.php'; ?>
