<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Admin');

function getDateRangeStart(string $date): string {
    return $date . ' 00:00:00';
}

function getDateRangeEndExclusive(string $date): string {
    return (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
}

function parseDashboardMonth(?string $requestedMonth): DateTimeImmutable {
    $monthStart = $requestedMonth && preg_match('/^\d{4}-\d{2}$/', $requestedMonth)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $requestedMonth . '-01')
        : false;
    $monthStartErrors = DateTimeImmutable::getLastErrors();

    if (!$monthStart || ($monthStartErrors && ($monthStartErrors['warning_count'] || $monthStartErrors['error_count']))) {
        return new DateTimeImmutable('first day of this month');
    }

    return $monthStart;
}

function parseReportDate(?string $requestedDate, string $fallback): string {
    $date = $requestedDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $requestedDate)
        : false;
    $dateErrors = DateTimeImmutable::getLastErrors();

    if (!$date || ($dateErrors && ($dateErrors['warning_count'] || $dateErrors['error_count']))) {
        return $fallback;
    }

    return $date->format('Y-m-d');
}

function parseCompletedTreatmentDate(?string $requestedDate): DateTimeImmutable {
    $date = $requestedDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $requestedDate)
        : false;
    $dateErrors = DateTimeImmutable::getLastErrors();

    if (!$date || ($dateErrors && ($dateErrors['warning_count'] || $dateErrors['error_count']))) {
        return new DateTimeImmutable('today');
    }

    return $date;
}

function getCompletedPatientsForDate(PDO $pdo, DateTimeImmutable $selectedDate): array {
    $selectedDateStart = $selectedDate->format('Y-m-d');
    $nextDateStart = $selectedDate->modify('+1 day')->format('Y-m-d');

    $stmt = $pdo->prepare(
        "SELECT
            p.id AS patient_id,
            TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))) AS patient_name,
            u.id AS doctor_id,
            u.name AS doctor_name,
            COUNT(ts.id) AS session_count,
            MAX(ts.session_date) AS latest_session_date
         FROM treatment_sessions ts
         INNER JOIN patients p ON ts.patient_id = p.id
         INNER JOIN users u ON ts.doctor_id = u.id
         WHERE ts.session_date >= ? AND ts.session_date < ?
         GROUP BY p.id, p.first_name, p.last_name, u.id, u.name
         ORDER BY latest_session_date DESC, patient_name ASC, doctor_name ASC"
    );
    $stmt->execute([$selectedDateStart, $nextDateStart]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPatientAttendanceCalendar(PDO $pdo, DateTimeImmutable $monthStart): array {
    $nextMonthStart = $monthStart->modify('first day of next month');
    $stmt = $pdo->prepare(
        "SELECT
            DATE(ts.session_date) AS visit_date,
            ts.id AS session_id,
            ts.patient_id,
            TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))) AS patient_name,
            ts.doctor_id,
            u.name AS doctor_name
         FROM treatment_sessions ts
         INNER JOIN patients p ON ts.patient_id = p.id
         INNER JOIN users u ON ts.doctor_id = u.id
         WHERE ts.session_date >= ? AND ts.session_date < ?
         ORDER BY DATE(ts.session_date) ASC, u.name ASC, patient_name ASC, ts.id ASC"
    );
    $stmt->execute([$monthStart->format('Y-m-d'), $nextMonthStart->format('Y-m-d')]);

    $calendarVisits = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $visitDate = $row['visit_date'];
        $doctorId = (int) $row['doctor_id'];
        $patientId = (int) $row['patient_id'];
        $attendanceKey = $doctorId . ':' . $patientId;

        if (!isset($calendarVisits[$visitDate])) {
            $calendarVisits[$visitDate] = [
                'attendances' => [],
                'doctors' => [],
                'patients' => [],
                'patient_count' => 0,
                'doctor_count' => 0,
                'session_count' => 0,
            ];
        }

        $calendarVisits[$visitDate]['session_count']++;
        $calendarVisits[$visitDate]['doctors'][$doctorId] = $row['doctor_name'];
        $calendarVisits[$visitDate]['patients'][$patientId] = $row['patient_name'];

        if (!isset($calendarVisits[$visitDate]['attendances'][$attendanceKey])) {
            $calendarVisits[$visitDate]['attendances'][$attendanceKey] = [
                'patient_id' => $patientId,
                'patient_name' => $row['patient_name'],
                'doctor_id' => $doctorId,
                'doctor_name' => $row['doctor_name'],
                'session_count' => 0,
            ];
        }

        $calendarVisits[$visitDate]['attendances'][$attendanceKey]['session_count']++;
    }

    foreach ($calendarVisits as &$visitData) {
        $visitData['patient_count'] = count($visitData['patients']);
        $visitData['doctor_count'] = count($visitData['doctors']);
        $visitData['attendances'] = array_values($visitData['attendances']);
        usort($visitData['attendances'], static function (array $left, array $right): int {
            return [$left['doctor_name'], $left['patient_name']] <=> [$right['doctor_name'], $right['patient_name']];
        });
    }
    unset($visitData);

    return $calendarVisits;
}

function getDoctorAttendanceReport(PDO $pdo, string $startDate, string $endDate): array {
    $stmt = $pdo->prepare(
        "SELECT
            u.id AS doctor_id,
            u.name AS doctor_name,
            COUNT(DISTINCT ts.patient_id) AS patient_count,
            COUNT(ts.id) AS session_count
         FROM users u
         LEFT JOIN treatment_sessions ts
            ON ts.doctor_id = u.id
           AND ts.session_date >= ?
           AND ts.session_date < ?
         WHERE u.role = 'Doctor' AND u.is_deleted = 0
         GROUP BY u.id, u.name
         ORDER BY patient_count DESC, session_count DESC, u.name ASC"
    );
    $stmt->execute([getDateRangeStart($startDate), getDateRangeEndExclusive($endDate)]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch counts
$totalDoctors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Doctor' AND is_deleted = 0")->fetchColumn();
$totalPatients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$activePatients = $pdo->query("SELECT COUNT(DISTINCT patient_id) FROM treatment_episodes WHERE status = 'Active'")->fetchColumn();
$totalExercises = $pdo->query("SELECT COUNT(*) FROM exercises_master")->fetchColumn();
$totalReferrals = $pdo->query("SELECT COUNT(*) FROM referral_sources")->fetchColumn();

$completedTreatmentDate = parseCompletedTreatmentDate($_GET['completed_date'] ?? null);
$completedTreatmentDateValue = $completedTreatmentDate->format('Y-m-d');
$previousCompletedTreatmentDate = $completedTreatmentDate->modify('-1 day')->format('Y-m-d');
$nextCompletedTreatmentDate = $completedTreatmentDate->modify('+1 day')->format('Y-m-d');
$isCompletedTreatmentToday = $completedTreatmentDateValue === (new DateTimeImmutable('today'))->format('Y-m-d');
$todaysCompletedPatients = getCompletedPatientsForDate($pdo, $completedTreatmentDate);

$monthStart = parseDashboardMonth($_GET['attendance_month'] ?? null);
$monthEnd = $monthStart->modify('last day of this month');
$calendarStart = $monthStart->modify('-' . (int) $monthStart->format('w') . ' days');
$calendarEnd = $monthEnd->modify('+' . (6 - (int) $monthEnd->format('w')) . ' days');
$previousMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
$currentMonth = (new DateTimeImmutable('first day of this month'))->format('Y-m');
$selectedMonth = $monthStart->format('Y-m');
$calendarVisits = getPatientAttendanceCalendar($pdo, $monthStart);
$calendarDays = [];
for ($day = $calendarStart; $day <= $calendarEnd; $day = $day->modify('+1 day')) {
    $calendarDays[] = $day;
}

$defaultReportStart = $monthStart->format('Y-m-d');
$defaultReportEnd = min((new DateTimeImmutable('today'))->format('Y-m-d'), $monthEnd->format('Y-m-d'));
$reportStartDate = parseReportDate($_GET['report_start'] ?? null, $defaultReportStart);
$reportEndDate = parseReportDate($_GET['report_end'] ?? null, $defaultReportEnd);
if ($reportStartDate > $reportEndDate) {
    [$reportStartDate, $reportEndDate] = [$reportEndDate, $reportStartDate];
}
$doctorAttendanceReport = getDoctorAttendanceReport($pdo, $reportStartDate, $reportEndDate);

include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Admin Dashboard</h1>
                <p class="admin-page-subtitle">A quick snapshot of clinic activity across teams.</p>
            </div>
        </div>

        <div class="row g-3 g-lg-4 mb-4">
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-3">
                <div class="stat-card bg-primary text-white h-100">
                    <div class="text-uppercase small text-white-50 fw-semibold">Total Doctors</div>
                    <div class="display-6 my-2"><?= number_format((int) $totalDoctors) ?></div>
                    <a href="<?= BASE_URL ?>/views/admin/manage_users.php" class="btn btn-light btn-sm mt-2">Manage Users</a>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-3">
                <div class="stat-card bg-success text-white h-100">
                    <div class="text-uppercase small text-white-50 fw-semibold">Total Patients</div>
                    <div class="display-6 my-2"><?= number_format((int) $totalPatients) ?></div>
                    <a href="<?= BASE_URL ?>/views/admin/manage_patients.php" class="btn btn-light btn-sm mt-2">View Patients</a>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-3">
                <div class="stat-card bg-warning text-dark h-100">
                    <div class="text-uppercase small text-dark fw-semibold">Active Patients</div>
                    <div class="display-6 my-2"><?= number_format((int) $activePatients) ?></div>
                    <a href="<?= BASE_URL ?>/views/admin/manage_patients.php" class="btn btn-outline-dark btn-sm mt-2">View Active</a>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-3">
                <div class="stat-card bg-info text-white h-100">
                    <div class="text-uppercase small text-white-50 fw-semibold">Total Exercises</div>
                    <div class="display-6 my-2"><?= number_format((int) $totalExercises) ?></div>
                    <a href="<?= BASE_URL ?>/views/admin/manage_exercises.php" class="btn btn-light btn-sm mt-2">Manage Exercises</a>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-3">
                <div class="stat-card bg-secondary text-white h-100">
                    <div class="text-uppercase small text-white-50 fw-semibold">Referral Sources</div>
                    <div class="display-6 my-2"><?= number_format((int) $totalReferrals) ?></div>
                    <a href="<?= BASE_URL ?>/views/admin/manage_referrals.php" class="btn btn-light btn-sm mt-2">Manage Referrals</a>
                </div>
            </div>
        </div>

        <div class="app-card mb-4">
            <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-start mb-3">
                <div>
                    <div class="text-uppercase small text-muted fw-semibold">Selected Patient List</div>
                    <h5 class="mb-1">Completed Treatment <?= $isCompletedTreatmentToday ? 'Today' : 'on ' . htmlspecialchars($completedTreatmentDate->format('M j, Y')) ?></h5>
                    <p class="text-muted mb-0">Patients with completed treatment sessions recorded for <?= htmlspecialchars($completedTreatmentDate->format('F j, Y')) ?>, grouped by the doctor who attended them.</p>
                </div>
                <div class="d-flex flex-column align-items-lg-end gap-2">
                    <span class="badge bg-success-subtle text-success border border-success-subtle align-self-start align-self-lg-end">
                        <?= number_format(count($todaysCompletedPatients)) ?> <?= count($todaysCompletedPatients) === 1 ? 'record' : 'records' ?>
                    </span>
                    <form class="d-flex flex-wrap gap-2 align-items-center" method="get">
                        <input type="hidden" name="attendance_month" value="<?= htmlspecialchars($selectedMonth) ?>">
                        <input type="hidden" name="report_start" value="<?= htmlspecialchars($reportStartDate) ?>">
                        <input type="hidden" name="report_end" value="<?= htmlspecialchars($reportEndDate) ?>">
                        <a class="btn btn-outline-primary btn-sm" href="?attendance_month=<?= htmlspecialchars($selectedMonth) ?>&amp;report_start=<?= htmlspecialchars($reportStartDate) ?>&amp;report_end=<?= htmlspecialchars($reportEndDate) ?>&amp;completed_date=<?= htmlspecialchars($previousCompletedTreatmentDate) ?>">&larr; Previous Date</a>
                        <label class="visually-hidden" for="completed-date-admin">Select completed treatment date</label>
                        <input class="form-control form-control-sm" style="max-width: 170px;" type="date" id="completed-date-admin" name="completed_date" value="<?= htmlspecialchars($completedTreatmentDateValue) ?>">
                        <button class="btn btn-primary btn-sm" type="submit">View Date</button>
                        <a class="btn btn-outline-primary btn-sm" href="?attendance_month=<?= htmlspecialchars($selectedMonth) ?>&amp;report_start=<?= htmlspecialchars($reportStartDate) ?>&amp;report_end=<?= htmlspecialchars($reportEndDate) ?>&amp;completed_date=<?= htmlspecialchars($nextCompletedTreatmentDate) ?>">Next Date &rarr;</a>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 80px;">#</th>
                            <th scope="col">Patient Name</th>
                            <th scope="col">Attended By</th>
                            <th scope="col" class="text-center">Sessions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($todaysCompletedPatients)): ?>
                            <?php foreach ($todaysCompletedPatients as $index => $todaysPatient): ?>
                                <tr>
                                    <td><?= number_format($index + 1) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($todaysPatient['patient_name'] !== '' ? $todaysPatient['patient_name'] : 'Unnamed patient') ?></td>
                                    <td><?= htmlspecialchars($todaysPatient['doctor_name'] ?: 'Unassigned doctor') ?></td>
                                    <td class="text-center"><?= number_format((int) $todaysPatient['session_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No patients have completed treatment on this date.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="app-card patient-calendar-card mb-4">
            <div class="d-flex flex-column flex-sm-row gap-3 justify-content-between align-items-sm-center mb-3">
                <div>
                    <div class="text-uppercase small text-muted fw-semibold">Patient Attendance Calendar for All Doctors</div>
                    <h5 class="mb-0"><?= htmlspecialchars($monthStart->format('F Y')) ?></h5>
                </div>
                <div class="d-flex flex-wrap gap-2" aria-label="Calendar month navigation">
                    <a class="btn btn-outline-primary btn-sm" href="?attendance_month=<?= htmlspecialchars($previousMonth) ?>&amp;report_start=<?= htmlspecialchars($reportStartDate) ?>&amp;report_end=<?= htmlspecialchars($reportEndDate) ?>">&larr; Previous</a>
                    <?php if ($selectedMonth !== $currentMonth): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="?attendance_month=<?= htmlspecialchars($currentMonth) ?>&amp;report_start=<?= htmlspecialchars($reportStartDate) ?>&amp;report_end=<?= htmlspecialchars($reportEndDate) ?>">Current Month</a>
                    <?php endif; ?>
                    <a class="btn btn-outline-primary btn-sm" href="?attendance_month=<?= htmlspecialchars($nextMonth) ?>&amp;report_start=<?= htmlspecialchars($reportStartDate) ?>&amp;report_end=<?= htmlspecialchars($reportEndDate) ?>">Next &rarr;</a>
                </div>
            </div>

            <div class="patient-calendar-legend mb-3">
                <span class="patient-calendar-legend-dot"></span>
                <span>Green dates show days where any doctor attended patients. Select a date to view patient and doctor details.</span>
            </div>

            <div class="patient-calendar" role="grid" aria-label="Patient attendance calendar for all doctors, <?= htmlspecialchars($monthStart->format('F Y')) ?>">
                <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
                    <div class="patient-calendar-weekday" role="columnheader"><?= htmlspecialchars($weekday) ?></div>
                <?php endforeach; ?>

                <?php foreach ($calendarDays as $calendarDay): ?>
                    <?php
                        $calendarDate = $calendarDay->format('Y-m-d');
                        $modalId = 'adminAttendanceModal' . $calendarDay->format('Ymd');
                        $isCurrentMonthDay = $calendarDay->format('Y-m') === $selectedMonth;
                        $visitData = $calendarVisits[$calendarDate] ?? null;
                        $patientCount = $visitData['patient_count'] ?? 0;
                        $doctorCount = $visitData['doctor_count'] ?? 0;
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
                        <button type="button" class="<?= htmlspecialchars(implode(' ', $dayClasses)) ?> patient-calendar-button" data-bs-toggle="modal" data-bs-target="#<?= htmlspecialchars($modalId) ?>" role="gridcell" aria-label="<?= htmlspecialchars($calendarDay->format('F j, Y') . ' - ' . $patientCount . ' ' . ($patientCount === 1 ? 'patient' : 'patients') . ', ' . $doctorCount . ' ' . ($doctorCount === 1 ? 'doctor' : 'doctors')) ?>">
                            <span class="patient-calendar-date"><?= htmlspecialchars($calendarDay->format('j M')) ?></span>
                            <span class="patient-calendar-status"><?= htmlspecialchars($patientCount . ' ' . ($patientCount === 1 ? 'patient' : 'patients')) ?></span>
                            <span class="patient-calendar-status"><?= htmlspecialchars($doctorCount . ' ' . ($doctorCount === 1 ? 'doctor' : 'doctors')) ?></span>
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
                $modalId = 'adminAttendanceModal' . ($visitDateObj ? $visitDateObj->format('Ymd') : preg_replace('/[^0-9]/', '', $visitDate));
            ?>
            <div class="modal fade" id="<?= htmlspecialchars($modalId) ?>" tabindex="-1" aria-labelledby="<?= htmlspecialchars($modalId) ?>Label" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title" id="<?= htmlspecialchars($modalId) ?>Label"><?= htmlspecialchars($visitDateObj ? $visitDateObj->format('j F Y') : $visitDate) ?></h5>
                                <div class="text-muted small">
                                    <?= htmlspecialchars($visitData['patient_count'] . ' ' . ($visitData['patient_count'] === 1 ? 'patient' : 'patients') . ' attended by ' . $visitData['doctor_count'] . ' ' . ($visitData['doctor_count'] === 1 ? 'doctor' : 'doctors')) ?>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">Doctor</th>
                                            <th scope="col">Patient</th>
                                            <th scope="col" class="text-center">Sessions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($visitData['attendances'] as $attendance): ?>
                                            <tr>
                                                <td class="fw-semibold"><?= htmlspecialchars($attendance['doctor_name'] ?: 'Unassigned doctor') ?></td>
                                                <td><?= htmlspecialchars($attendance['patient_name'] !== '' ? $attendance['patient_name'] : 'Unnamed patient') ?></td>
                                                <td class="text-center"><?= number_format((int) $attendance['session_count']) ?></td>
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

        <div class="app-card mb-4">
            <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-end mb-3">
                <div>
                    <div class="text-uppercase small text-muted fw-semibold">Doctor Attendance Report</div>
                    <h5 class="mb-1">Patients Attended Between Dates</h5>
                    <p class="text-muted mb-0">Shows how many unique patients each doctor attended in the selected date range.</p>
                </div>
                <form class="row g-2 align-items-end" method="get">
                    <input type="hidden" name="attendance_month" value="<?= htmlspecialchars($selectedMonth) ?>">
                    <div class="col-12 col-sm-auto">
                        <label for="report_start" class="form-label small text-muted mb-1">Start date</label>
                        <input type="date" class="form-control form-control-sm" id="report_start" name="report_start" value="<?= htmlspecialchars($reportStartDate) ?>">
                    </div>
                    <div class="col-12 col-sm-auto">
                        <label for="report_end" class="form-label small text-muted mb-1">End date</label>
                        <input type="date" class="form-control form-control-sm" id="report_end" name="report_end" value="<?= htmlspecialchars($reportEndDate) ?>">
                    </div>
                    <div class="col-12 col-sm-auto">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Apply</button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 80px;">#</th>
                            <th scope="col">Doctor</th>
                            <th scope="col" class="text-center">Patients Attended</th>
                            <th scope="col" class="text-center">Treatment Sessions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($doctorAttendanceReport)): ?>
                            <?php foreach ($doctorAttendanceReport as $index => $reportRow): ?>
                                <tr>
                                    <td><?= number_format($index + 1) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($reportRow['doctor_name'] ?: 'Unnamed doctor') ?></td>
                                    <td class="text-center"><?= number_format((int) $reportRow['patient_count']) ?></td>
                                    <td class="text-center"><?= number_format((int) $reportRow['session_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No doctors found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
