<?php
require_once '../../includes/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
requireLogin();
requireRole('Doctor');

$doctorId = (int) ($_SESSION['user_id'] ?? 0);
$doctorName = trim((string) ($_SESSION['name'] ?? ''));
$doctorDisplayName = $doctorName !== ''
    ? (preg_match('/^dr\.?\s/i', $doctorName) ? $doctorName : 'Dr. ' . $doctorName)
    : 'Doctor';

function getTodaysCompletedPatients(PDO $pdo, int $doctorId): array {
    $todayStart = (new DateTimeImmutable('today'))->format('Y-m-d');
    $tomorrowStart = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');

    $stmt = $pdo->prepare(
        "SELECT
            p.id AS patient_id,
            TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))) AS patient_name,
            MAX(ts.session_date) AS latest_session_date
         FROM treatment_sessions ts
         INNER JOIN patients p ON ts.patient_id = p.id
         WHERE ts.doctor_id = ? AND ts.session_date >= ? AND ts.session_date < ?
         GROUP BY p.id, p.first_name, p.last_name
         ORDER BY patient_name ASC, p.id ASC"
    );
    $stmt->execute([$doctorId, $todayStart, $tomorrowStart]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch dashboard stats
$totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$activePatients = $pdo->query("SELECT COUNT(DISTINCT patient_id) FROM treatment_episodes WHERE status = 'Active'")->fetchColumn();
$totalExercises = $pdo->query("SELECT COUNT(*) FROM exercises_master")->fetchColumn();
$totalMachines = $pdo->query("SELECT COUNT(*) FROM machines")->fetchColumn();
$todaysCompletedPatients = getTodaysCompletedPatients($pdo, $doctorId);

$requestedMonth = $_GET['month'] ?? date('Y-m');
$monthStart = preg_match('/^\d{4}-\d{2}$/', $requestedMonth)
    ? DateTimeImmutable::createFromFormat('!Y-m-d', $requestedMonth . '-01')
    : false;
$monthStartErrors = DateTimeImmutable::getLastErrors();
if (!$monthStart || ($monthStartErrors && ($monthStartErrors['warning_count'] || $monthStartErrors['error_count']))) {
    $monthStart = new DateTimeImmutable('first day of this month');
}

$monthEnd = $monthStart->modify('last day of this month');
$nextMonthStart = $monthStart->modify('first day of next month');
$calendarStart = $monthStart->modify('-' . (int) $monthStart->format('w') . ' days');
$calendarEnd = $monthEnd->modify('+' . (6 - (int) $monthEnd->format('w')) . ' days');
$previousMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
$currentMonth = (new DateTimeImmutable('first day of this month'))->format('Y-m');
$selectedMonth = $monthStart->format('Y-m');

$doctorSessionsStmt = $pdo->prepare(
    "SELECT
        ts.id AS session_id,
        ts.patient_id,
        ts.episode_id,
        ts.session_date,
        DATE(ts.session_date) AS visit_date,
        ts.remarks,
        ts.progress_notes,
        ts.advise,
        ts.additional_treatment_notes,
        primary_user.name AS primary_therapist_name,
        secondary_user.name AS secondary_therapist_name,
        p.first_name,
        p.last_name,
        COUNT(DISTINCT te.id) AS exercise_count,
        COUNT(DISTINCT tm.id) AS machine_count
     FROM treatment_sessions ts
     INNER JOIN patients p ON ts.patient_id = p.id
     LEFT JOIN users primary_user ON ts.primary_therapist_id = primary_user.id
     LEFT JOIN users secondary_user ON ts.secondary_therapist_id = secondary_user.id
     LEFT JOIN treatment_exercises te ON ts.id = te.session_id
     LEFT JOIN treatment_machines tm ON ts.id = tm.session_id
     WHERE ts.doctor_id = ? AND ts.session_date >= ? AND ts.session_date < ?
     GROUP BY ts.id, ts.patient_id, ts.episode_id, ts.session_date, DATE(ts.session_date), ts.remarks, ts.progress_notes, ts.advise, ts.additional_treatment_notes, primary_user.name, secondary_user.name, p.first_name, p.last_name
     ORDER BY DATE(ts.session_date) ASC, p.first_name ASC, p.last_name ASC, ts.id ASC"
);
$doctorSessionsStmt->execute([
    $doctorId,
    $monthStart->format('Y-m-d'),
    $nextMonthStart->format('Y-m-d'),
]);

$sessionRows = $doctorSessionsStmt->fetchAll(PDO::FETCH_ASSOC);
$sessionIds = array_values(array_unique(array_map(static function ($sessionRow) {
    return (int) $sessionRow['session_id'];
}, $sessionRows)));
$sessionDetailsById = [];

foreach ($sessionIds as $sessionId) {
    $sessionDetailsById[$sessionId] = [
        'exercises' => [],
        'machines' => [],
        'files' => [],
    ];
}

if (!empty($sessionIds)) {
    $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));

    $exerciseDetailsStmt = $pdo->prepare(
        "SELECT
            te.session_id,
            te.exercise_id,
            te.exercise_name,
            te.reps,
            te.duration_minutes,
            te.notes,
            em.name AS master_name
         FROM treatment_exercises te
         LEFT JOIN exercises_master em ON te.exercise_id = em.id
         WHERE te.session_id IN ($placeholders)
         ORDER BY te.session_id ASC, te.id ASC"
    );
    $exerciseDetailsStmt->execute($sessionIds);

    foreach ($exerciseDetailsStmt->fetchAll(PDO::FETCH_ASSOC) as $exerciseRow) {
        $sessionId = (int) $exerciseRow['session_id'];
        if (!isset($sessionDetailsById[$sessionId])) {
            continue;
        }

        $sessionDetailsById[$sessionId]['exercises'][] = [
            'name' => $exerciseRow['master_name'] ?? $exerciseRow['exercise_name'] ?? 'Exercise',
            'reps' => $exerciseRow['reps'],
            'duration_minutes' => $exerciseRow['duration_minutes'],
            'notes' => $exerciseRow['notes'],
        ];
    }

    $machineDetailsStmt = $pdo->prepare(
        "SELECT
            tm.session_id,
            tm.machine_id,
            tm.machine_name,
            tm.duration_minutes,
            tm.notes,
            m.name AS master_name
         FROM treatment_machines tm
         LEFT JOIN machines m ON tm.machine_id = m.id
         WHERE tm.session_id IN ($placeholders)
         ORDER BY tm.session_id ASC, tm.id ASC"
    );
    $machineDetailsStmt->execute($sessionIds);

    foreach ($machineDetailsStmt->fetchAll(PDO::FETCH_ASSOC) as $machineRow) {
        $sessionId = (int) $machineRow['session_id'];
        if (!isset($sessionDetailsById[$sessionId])) {
            continue;
        }

        $sessionDetailsById[$sessionId]['machines'][] = [
            'name' => $machineRow['master_name'] ?? $machineRow['machine_name'] ?? 'Machine',
            'duration_minutes' => $machineRow['duration_minutes'],
            'notes' => $machineRow['notes'],
        ];
    }

    $fileDetailsStmt = $pdo->prepare(
        "SELECT
            fm.file_id,
            fm.file_name,
            fm.file_type_id,
            fm.treatment_session_id,
            fm.upload_date,
            prft.name AS file_type_name
         FROM file_master fm
         LEFT JOIN patient_report_file_types prft ON prft.id = fm.file_type_id
         WHERE fm.treatment_session_id IN ($placeholders)
         ORDER BY fm.treatment_session_id ASC, fm.upload_date DESC, fm.file_id DESC"
    );
    $fileDetailsStmt->execute($sessionIds);

    foreach ($fileDetailsStmt->fetchAll(PDO::FETCH_ASSOC) as $fileRow) {
        $sessionId = (int) $fileRow['treatment_session_id'];
        if (!isset($sessionDetailsById[$sessionId])) {
            continue;
        }

        $sessionDetailsById[$sessionId]['files'][] = [
            'file_id' => (int) $fileRow['file_id'],
            'file_name' => $fileRow['file_name'],
            'file_type_name' => $fileRow['file_type_name'],
            'upload_date' => $fileRow['upload_date'],
        ];
    }
}

$calendarVisits = [];
foreach ($sessionRows as $sessionRow) {
    $visitDate = $sessionRow['visit_date'];
    $patientId = (int) $sessionRow['patient_id'];

    if (!isset($calendarVisits[$visitDate])) {
        $calendarVisits[$visitDate] = [
            'patients' => [],
            'patient_count' => 0,
        ];
    }

    if (!isset($calendarVisits[$visitDate]['patients'][$patientId])) {
        $calendarVisits[$visitDate]['patients'][$patientId] = [
            'patient_id' => $patientId,
            'episode_id' => (int) $sessionRow['episode_id'],
            'session_id' => (int) $sessionRow['session_id'],
            'name' => trim(($sessionRow['first_name'] ?? '') . ' ' . ($sessionRow['last_name'] ?? '')),
            'exercise_count' => 0,
            'machine_count' => 0,
            'session_count' => 0,
            'sessions' => [],
        ];
    }

    $sessionId = (int) $sessionRow['session_id'];
    $calendarVisits[$visitDate]['patients'][$patientId]['exercise_count'] += (int) $sessionRow['exercise_count'];
    $calendarVisits[$visitDate]['patients'][$patientId]['machine_count'] += (int) $sessionRow['machine_count'];
    $calendarVisits[$visitDate]['patients'][$patientId]['session_count']++;
    $calendarVisits[$visitDate]['patients'][$patientId]['sessions'][] = [
        'session_id' => $sessionId,
        'session_date' => $sessionRow['session_date'],
        'remarks' => $sessionRow['remarks'],
        'progress_notes' => $sessionRow['progress_notes'],
        'advise' => $sessionRow['advise'],
        'additional_treatment_notes' => $sessionRow['additional_treatment_notes'],
        'primary_therapist_name' => $sessionRow['primary_therapist_name'],
        'secondary_therapist_name' => $sessionRow['secondary_therapist_name'],
        'exercises' => $sessionDetailsById[$sessionId]['exercises'] ?? [],
        'machines' => $sessionDetailsById[$sessionId]['machines'] ?? [],
        'files' => $sessionDetailsById[$sessionId]['files'] ?? [],
    ];

    if ($sessionId > $calendarVisits[$visitDate]['patients'][$patientId]['session_id']) {
        $calendarVisits[$visitDate]['patients'][$patientId]['session_id'] = (int) $sessionRow['session_id'];
        $calendarVisits[$visitDate]['patients'][$patientId]['episode_id'] = (int) $sessionRow['episode_id'];
    }
}

foreach ($calendarVisits as &$visitData) {
    $visitData['patient_count'] = count($visitData['patients']);
    $visitData['patients'] = array_values($visitData['patients']);
}
unset($visitData);

$calendarDays = [];
for ($day = $calendarStart; $day <= $calendarEnd; $day = $day->modify('+1 day')) {
    $calendarDays[] = $day;
}

include '../../includes/header.php';
?>
<div class="workspace-layout">
  <?php include '../../layouts/doctor_sidebar.php'; ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">Doctor Dashboard</h1>
        <p class="workspace-page-subtitle">Welcome back, <?= htmlspecialchars($doctorDisplayName) ?>.</p>
      </div>
    </div>

    <div class="app-card mb-4">
      <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between align-items-sm-start mb-3">
        <div>
          <div class="text-uppercase small text-muted fw-semibold">Today's Patient List</div>
          <h5 class="mb-1">Completed Treatment Today</h5>
          <p class="text-muted mb-0">Patients with completed treatment sessions recorded today by <?= htmlspecialchars($doctorDisplayName) ?>.</p>
        </div>
        <span class="badge bg-success-subtle text-success border border-success-subtle">
          <?= number_format(count($todaysCompletedPatients)) ?> <?= count($todaysCompletedPatients) === 1 ? 'patient' : 'patients' ?>
        </span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col" style="width: 80px;">#</th>
              <th scope="col">Patient Name</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($todaysCompletedPatients)): ?>
              <?php foreach ($todaysCompletedPatients as $index => $todaysPatient): ?>
                <tr>
                  <td><?= number_format($index + 1) ?></td>
                  <td class="fw-semibold">
                    <?= htmlspecialchars($todaysPatient['patient_name'] !== '' ? $todaysPatient['patient_name'] : 'Unnamed patient') ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="2" class="text-center text-muted py-4">No patients have completed treatment today.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="app-card patient-calendar-card mb-4">
      <div class="d-flex flex-column flex-sm-row gap-3 justify-content-between align-items-sm-center mb-3">
        <div>
          <div class="text-uppercase small text-muted fw-semibold">Patient Attendance Calendar for <?= htmlspecialchars($doctorDisplayName) ?></div>
          <h5 class="mb-0"><?= htmlspecialchars($monthStart->format('F Y')) ?></h5>
        </div>
        <div class="d-flex flex-wrap gap-2" aria-label="Calendar month navigation">
          <a class="btn btn-outline-primary btn-sm" href="?month=<?= htmlspecialchars($previousMonth) ?>">&larr; Previous</a>
          <?php if ($selectedMonth !== $currentMonth): ?>
            <a class="btn btn-outline-secondary btn-sm" href="?month=<?= htmlspecialchars($currentMonth) ?>">Current Month</a>
          <?php endif; ?>
          <a class="btn btn-outline-primary btn-sm" href="?month=<?= htmlspecialchars($nextMonth) ?>">Next &rarr;</a>
        </div>
      </div>

      <div class="patient-calendar-legend mb-3">
        <span class="patient-calendar-legend-dot"></span>
        <span>Green dates show days where you attended patients. Select a date to view patient treatment counts.</span>
      </div>

      <div class="patient-calendar" role="grid" aria-label="Patient attendance calendar for <?= htmlspecialchars($doctorDisplayName) ?>, <?= htmlspecialchars($monthStart->format('F Y')) ?>">
        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
          <div class="patient-calendar-weekday" role="columnheader"><?= htmlspecialchars($weekday) ?></div>
        <?php endforeach; ?>

        <?php foreach ($calendarDays as $calendarDay): ?>
          <?php
            $calendarDate = $calendarDay->format('Y-m-d');
            $modalId = 'doctorAttendanceModal' . $calendarDay->format('Ymd');
            $isCurrentMonthDay = $calendarDay->format('Y-m') === $selectedMonth;
            $visitData = $calendarVisits[$calendarDate] ?? null;
            $patientCount = $visitData['patient_count'] ?? 0;
            $hasVisits = $patientCount > 0;
            $dayClasses = ['patient-calendar-day'];
            if (!$isCurrentMonthDay) {
                $dayClasses[] = 'is-muted';
            }
            if ($hasVisits) {
                $dayClasses[] = 'is-present';
            }
          ?>
          <?php if ($hasVisits): ?>
            <button type="button" class="<?= htmlspecialchars(implode(' ', $dayClasses)) ?> patient-calendar-button" data-bs-toggle="modal" data-bs-target="#<?= htmlspecialchars($modalId) ?>" role="gridcell" aria-label="<?= htmlspecialchars($calendarDay->format('F j, Y') . ' - ' . $patientCount . ' ' . ($patientCount === 1 ? 'patient' : 'patients')) ?>">
              <span class="patient-calendar-date"><?= htmlspecialchars($calendarDay->format('j M')) ?></span>
              <span class="patient-calendar-status"><?= htmlspecialchars($patientCount . ' ' . ($patientCount === 1 ? 'patient' : 'patients')) ?></span>
            </button>
          <?php else: ?>
            <div class="<?= htmlspecialchars(implode(' ', $dayClasses)) ?>" role="gridcell" aria-label="<?= htmlspecialchars($calendarDay->format('F j, Y')) ?>">
              <span class="patient-calendar-date"><?= htmlspecialchars($calendarDay->format('j')) ?></span>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <?php foreach ($calendarVisits as $visitDate => $visitData): ?>
      <?php
        $visitDateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $visitDate);
        $modalId = 'doctorAttendanceModal' . ($visitDateObj ? $visitDateObj->format('Ymd') : preg_replace('/[^0-9]/', '', $visitDate));
      ?>
      <div class="modal fade" id="<?= htmlspecialchars($modalId) ?>" tabindex="-1" aria-labelledby="<?= htmlspecialchars($modalId) ?>Label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <div>
                <h5 class="modal-title" id="<?= htmlspecialchars($modalId) ?>Label">
                  <?= htmlspecialchars($visitDateObj ? $visitDateObj->format('j F Y') : $visitDate) ?>
                </h5>
                <div class="text-muted small"><?= htmlspecialchars($visitData['patient_count'] . ' ' . ($visitData['patient_count'] === 1 ? 'patient' : 'patients') . ' attended') ?></div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th scope="col">Patient</th>
                      <th scope="col" class="text-center">Exercises</th>
                      <th scope="col" class="text-center">Machines</th>
                      <th scope="col" class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($visitData['patients'] as $patientVisit): ?>
                      <?php
                        $patientDetailsId = $modalId . 'Patient' . (int) $patientVisit['patient_id'];
                        $patientAccordionId = $patientDetailsId . 'Accordion';
                      ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= htmlspecialchars($patientVisit['name'] !== '' ? $patientVisit['name'] : 'Unnamed patient') ?></div>
                          <?php if ($patientVisit['session_count'] > 1): ?>
                            <div class="text-muted small"><?= (int) $patientVisit['session_count'] ?> sessions on this date</div>
                          <?php endif; ?>
                        </td>
                        <td class="text-center"><?= number_format((int) $patientVisit['exercise_count']) ?></td>
                        <td class="text-center"><?= number_format((int) $patientVisit['machine_count']) ?></td>
                        <td class="text-end">
                          <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($patientDetailsId) ?>" aria-expanded="false" aria-controls="<?= htmlspecialchars($patientDetailsId) ?>">
                            View
                          </button>
                        </td>
                      </tr>
                      <tr>
                        <td colspan="4" class="bg-light p-0">
                          <div class="collapse p-3" id="<?= htmlspecialchars($patientDetailsId) ?>">
                            <div class="accordion" id="<?= htmlspecialchars($patientAccordionId) ?>">
                            <?php foreach ($patientVisit['sessions'] as $sessionIndex => $session): ?>
                              <?php
                                $sessionCollapseId = $patientDetailsId . 'Session' . (int) $session['session_id'];
                                $sessionHeadingId = $sessionCollapseId . 'Heading';
                                $sessionDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $session['session_date'])
                                    ?: DateTimeImmutable::createFromFormat('!Y-m-d', (string) $session['session_date']);
                              ?>
                              <div class="accordion-item">
                                <h2 class="accordion-header" id="<?= htmlspecialchars($sessionHeadingId) ?>">
                                  <button class="accordion-button<?= $sessionIndex === 0 ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($sessionCollapseId) ?>" aria-expanded="<?= $sessionIndex === 0 ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($sessionCollapseId) ?>">
                                    Treatment Session #<?= (int) $session['session_id'] ?><?= $sessionDate ? ' - ' . htmlspecialchars($sessionDate->format('j M Y')) : '' ?>
                                  </button>
                                </h2>
                                <div id="<?= htmlspecialchars($sessionCollapseId) ?>" class="accordion-collapse collapse<?= $sessionIndex === 0 ? ' show' : '' ?>" aria-labelledby="<?= htmlspecialchars($sessionHeadingId) ?>" data-bs-parent="#<?= htmlspecialchars($patientAccordionId) ?>">
                                  <div class="accordion-body">
                                    <div class="row g-3 mb-3">
                                      <?php if (!empty($session['primary_therapist_name'])): ?>
                                        <div class="col-md-6"><strong>Primary Therapist:</strong> <?= htmlspecialchars($session['primary_therapist_name']) ?></div>
                                      <?php endif; ?>
                                      <?php if (!empty($session['secondary_therapist_name'])): ?>
                                        <div class="col-md-6"><strong>Secondary Therapist:</strong> <?= htmlspecialchars($session['secondary_therapist_name']) ?></div>
                                      <?php endif; ?>
                                      <div class="col-12"><strong>Doctor's Remarks:</strong> <?= htmlspecialchars($session['remarks'] ?: 'No remarks recorded.') ?></div>
                                      <div class="col-12"><strong>Progress Notes:</strong> <?= htmlspecialchars($session['progress_notes'] ?: 'No progress notes recorded.') ?></div>
                                      <div class="col-12"><strong>Advise:</strong> <?= htmlspecialchars($session['advise'] ?: 'No advise recorded.') ?></div>
                                      <?php if (!empty($session['additional_treatment_notes'])): ?>
                                        <div class="col-12"><strong>Additional Treatment Notes:</strong> <?= htmlspecialchars($session['additional_treatment_notes']) ?></div>
                                      <?php endif; ?>
                                    </div>

                                    <h6 class="mb-2">Exercises</h6>
                                    <?php if (!empty($session['exercises'])): ?>
                                      <div class="table-responsive mb-3">
                                        <table class="table table-sm table-bordered align-middle mb-0">
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
                                                <td><?= htmlspecialchars($exercise['name'] ?: 'Exercise') ?></td>
                                                <td><?= htmlspecialchars($exercise['reps'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($exercise['duration_minutes'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($exercise['notes'] ?: '-') ?></td>
                                              </tr>
                                            <?php endforeach; ?>
                                          </tbody>
                                        </table>
                                      </div>
                                    <?php else: ?>
                                      <p class="text-muted mb-3">No exercises recorded.</p>
                                    <?php endif; ?>

                                    <h6 class="mb-2">Machines</h6>
                                    <?php if (!empty($session['machines'])): ?>
                                      <div class="table-responsive mb-3">
                                        <table class="table table-sm table-bordered align-middle mb-0">
                                          <thead class="table-light">
                                            <tr>
                                              <th scope="col">Machine</th>
                                              <th scope="col">Duration</th>
                                              <th scope="col">Notes</th>
                                            </tr>
                                          </thead>
                                          <tbody>
                                            <?php foreach ($session['machines'] as $machine): ?>
                                              <tr>
                                                <td><?= htmlspecialchars($machine['name'] ?: 'Machine') ?></td>
                                                <td><?= htmlspecialchars($machine['duration_minutes'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($machine['notes'] ?: '-') ?></td>
                                              </tr>
                                            <?php endforeach; ?>
                                          </tbody>
                                        </table>
                                      </div>
                                    <?php else: ?>
                                      <p class="text-muted mb-3">No machines recorded.</p>
                                    <?php endif; ?>

                                    <?php if (!empty($session['files'])): ?>
                                      <h6 class="mb-2">Files</h6>
                                      <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0">
                                          <thead class="table-light">
                                            <tr>
                                              <th scope="col">File Name</th>
                                              <th scope="col">File Type</th>
                                              <th scope="col">Uploaded On</th>
                                              <th scope="col">Download</th>
                                            </tr>
                                          </thead>
                                          <tbody>
                                            <?php foreach ($session['files'] as $file): ?>
                                              <tr>
                                                <td><?= htmlspecialchars($file['file_name']) ?></td>
                                                <td><?= htmlspecialchars($file['file_type_name'] ?: 'Uncategorized') ?></td>
                                                <td><?= htmlspecialchars($file['upload_date']) ?></td>
                                                <td><a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/views/shared/download_file.php?file_id=<?= (int) $file['file_id'] ?>">Download</a></td>
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
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="row g-3 g-lg-4">
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card bg-primary text-white h-100">
          <div class="text-uppercase small text-white-50 fw-semibold">Total Patients</div>
          <div class="display-6 my-2"><?= number_format((int) $totalPatients) ?></div>
          <a href="<?= BASE_URL ?>/views/doctor/manage_patients.php" class="btn btn-light btn-sm mt-2">View Patients</a>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card bg-success text-white h-100">
          <div class="text-uppercase small text-white-50 fw-semibold">Active Patients</div>
          <div class="display-6 my-2"><?= number_format((int) $activePatients) ?></div>
          <a href="<?= BASE_URL ?>/views/doctor/active_patients.php" class="btn btn-light btn-sm mt-2">View Active</a>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card bg-info text-white h-100">
          <div class="text-uppercase small text-white-50 fw-semibold">Total Exercises</div>
          <div class="display-6 my-2"><?= number_format((int) $totalExercises) ?></div>
          <a href="<?= BASE_URL ?>/views/doctor/exercises_list.php" class="btn btn-light btn-sm mt-2">View Exercises</a>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card bg-warning text-dark h-100">
          <div class="text-uppercase small text-black-50 fw-semibold">Total Machines</div>
          <div class="display-6 my-2"><?= number_format((int) $totalMachines) ?></div>
          <a href="<?= BASE_URL ?>/views/doctor/machines_list.php" class="btn btn-light btn-sm mt-2">View Machines</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
