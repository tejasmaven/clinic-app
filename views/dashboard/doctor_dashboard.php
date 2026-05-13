<?php
require_once '../../includes/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
requireLogin();
requireRole('Doctor');

$doctorId = (int) ($_SESSION['user_id'] ?? 0);

// Fetch dashboard stats
$totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$activePatients = $pdo->query("SELECT COUNT(DISTINCT patient_id) FROM treatment_episodes WHERE status = 'Active'")->fetchColumn();
$totalExercises = $pdo->query("SELECT COUNT(*) FROM exercises_master")->fetchColumn();
$totalMachines = $pdo->query("SELECT COUNT(*) FROM machines")->fetchColumn();

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
        DATE(ts.session_date) AS visit_date,
        p.first_name,
        p.last_name,
        COUNT(DISTINCT te.id) AS exercise_count,
        COUNT(DISTINCT tm.id) AS machine_count
     FROM treatment_sessions ts
     INNER JOIN patients p ON ts.patient_id = p.id
     LEFT JOIN treatment_exercises te ON ts.id = te.session_id
     LEFT JOIN treatment_machines tm ON ts.id = tm.session_id
     WHERE ts.doctor_id = ? AND ts.session_date >= ? AND ts.session_date < ?
     GROUP BY ts.id, ts.patient_id, ts.episode_id, DATE(ts.session_date), p.first_name, p.last_name
     ORDER BY DATE(ts.session_date) ASC, p.first_name ASC, p.last_name ASC, ts.id ASC"
);
$doctorSessionsStmt->execute([
    $doctorId,
    $monthStart->format('Y-m-d'),
    $nextMonthStart->format('Y-m-d'),
]);

$calendarVisits = [];
foreach ($doctorSessionsStmt->fetchAll(PDO::FETCH_ASSOC) as $sessionRow) {
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
        ];
    }

    $calendarVisits[$visitDate]['patients'][$patientId]['exercise_count'] += (int) $sessionRow['exercise_count'];
    $calendarVisits[$visitDate]['patients'][$patientId]['machine_count'] += (int) $sessionRow['machine_count'];
    $calendarVisits[$visitDate]['patients'][$patientId]['session_count']++;

    if ((int) $sessionRow['session_id'] > $calendarVisits[$visitDate]['patients'][$patientId]['session_id']) {
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
        <p class="workspace-page-subtitle">Welcome back, Dr. <?= htmlspecialchars($_SESSION['name']) ?>.</p>
      </div>
    </div>

    <div class="app-card patient-calendar-card mb-4">
      <div class="d-flex flex-column flex-sm-row gap-3 justify-content-between align-items-sm-center mb-3">
        <div>
          <div class="text-uppercase small text-muted fw-semibold">Patient Attendance Calendar</div>
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

      <div class="patient-calendar" role="grid" aria-label="Patient attendance calendar for <?= htmlspecialchars($monthStart->format('F Y')) ?>">
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
                          <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/views/doctor/start_treatment.php?episode_id=<?= (int) $patientVisit['episode_id'] ?>&patient_id=<?= (int) $patientVisit['patient_id'] ?>&edit_session_id=<?= (int) $patientVisit['session_id'] ?>">View</a>
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
