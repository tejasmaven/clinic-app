<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
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
        $reportRows = $controller->getTreatmentSummary($patientId, $startDate, $endDate);
    }
}

$selectedPatientName = $selectedPatient
    ? trim(($selectedPatient['first_name'] ?? '') . ' ' . ($selectedPatient['last_name'] ?? ''))
    : '';
$displayStart = $startDateObj->format('j M Y');
$displayEnd = $endDateObj->format('j M Y');
$printDate = (new DateTimeImmutable('now'))->format('j M Y');
$logoUrl = BASE_URL . '/assets/img/logo.jpg';

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
                <div class="col-12">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Enter additional notes to show on the printed report."><?= htmlspecialchars($notes) ?></textarea>
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

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Exercises</th>
                                <th>Machines</th>
                                <th>Fees</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($reportRows)): ?>
                                <?php foreach ($reportRows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(date('j M Y', strtotime($row['treatment_date']))) ?></td>
                                        <td><?= (int) $row['exercise_count'] ?> <?= (int) $row['exercise_count'] === 1 ? 'Exercise' : 'Exercises' ?></td>
                                        <td><?= (int) $row['machine_count'] ?> <?= (int) $row['machine_count'] === 1 ? 'Machine' : 'Machines' ?></td>
                                        <td><?= number_format((float) $row['total_fees'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No treatment sessions found for the selected date range.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($reportGenerated && empty($errors) && !empty($reportRows)): ?>
    <section class="medical-report-print print-only" aria-label="Printable medical report">
        <div class="text-center mb-4">
            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Clinic Logo" class="medical-report-logo">
        </div>

        <p><strong>Print Date:</strong> <?= htmlspecialchars($printDate) ?></p>
        <p><strong>Subject:</strong> To Whomever It May Concern</p>
        <p>
            This is to certify that <?= htmlspecialchars($selectedPatientName) ?> received treatment from
            <?= htmlspecialchars($displayStart) ?> to <?= htmlspecialchars($displayEnd) ?> at our clinic with the following details:
        </p>

        <table class="table table-bordered align-middle medical-report-print-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Exercises</th>
                    <th>Machines</th>
                    <th>Fees</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('j M Y', strtotime($row['treatment_date']))) ?></td>
                        <td><?= (int) $row['exercise_count'] ?> <?= (int) $row['exercise_count'] === 1 ? 'Exercise' : 'Exercises' ?></td>
                        <td><?= (int) $row['machine_count'] ?> <?= (int) $row['machine_count'] === 1 ? 'Machine' : 'Machines' ?></td>
                        <td><?= number_format((float) $row['total_fees'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-4">
            <strong>Additional Notes</strong>
            <p id="printNotes" class="medical-report-notes"><?= nl2br(htmlspecialchars($notes !== '' ? $notes : 'None')) ?></p>
        </div>
    </section>
<?php endif; ?>

<style>
.medical-report-logo {
    max-height: 110px;
    max-width: 260px;
}

.print-only {
    display: none;
}

@media print {
    body {
        background: #fff !important;
    }

    body > nav,
    .toast-container,
    .no-print {
        display: none !important;
    }

    .app-main {
        max-width: none !important;
        padding: 0 !important;
    }

    .medical-report-print {
        display: block !important;
        color: #000;
        font-size: 12pt;
        padding: 0.25in;
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
            printNotes.textContent = notes.value.trim() || 'None';
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
