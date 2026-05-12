<?php
require_once '../../includes/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../controllers/PatientController.php';
requireRole('Patient', 'login.php');

$patientId = (int) ($_SESSION['patient_id'] ?? $_SESSION['user_id'] ?? 0);
$controller = new PatientController($pdo);
$patient = $controller->getPatientById($patientId);

$episodeCountStmt = $pdo->prepare("SELECT COUNT(*) FROM treatment_episodes WHERE patient_id = ?");
$episodeCountStmt->execute([$patientId]);
$episodeCount = (int) $episodeCountStmt->fetchColumn();

$sessionCountStmt = $pdo->prepare("SELECT COUNT(*) FROM treatment_sessions WHERE patient_id = ?");
$sessionCountStmt->execute([$patientId]);
$sessionCount = (int) $sessionCountStmt->fetchColumn();

$lastSessionStmt = $pdo->prepare(
    "SELECT session_date FROM treatment_sessions WHERE patient_id = ? ORDER BY session_date DESC, id DESC LIMIT 1"
);
$lastSessionStmt->execute([$patientId]);
$lastSessionDate = $lastSessionStmt->fetchColumn();

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

$presentDatesStmt = $pdo->prepare(
    "SELECT DATE(session_date) AS visit_date, COUNT(*) AS visit_count
     FROM treatment_sessions
     WHERE patient_id = ? AND session_date >= ? AND session_date < ?
     GROUP BY DATE(session_date)"
);
$presentDatesStmt->execute([
    $patientId,
    $monthStart->format('Y-m-d'),
    $nextMonthStart->format('Y-m-d'),
]);
$presentDates = [];
foreach ($presentDatesStmt->fetchAll(PDO::FETCH_ASSOC) as $presentDateRow) {
    $presentDates[$presentDateRow['visit_date']] = (int) $presentDateRow['visit_count'];
}

$calendarDays = [];
for ($day = $calendarStart; $day <= $calendarEnd; $day = $day->modify('+1 day')) {
    $calendarDays[] = $day;
}

include '../../includes/header.php';
?>
<div class="workspace-layout">
    <?php include '../../layouts/patient_sidebar.php'; ?>
    <div class="workspace-content">
        <div class="workspace-page-header">
            <div>
                <h1 class="workspace-page-title">Patient Dashboard</h1>
                <p class="workspace-page-subtitle">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? '') ?>.</p>
            </div>
        </div>

        <?php if (!$patient): ?>
            <div class="alert alert-danger">Unable to load your profile. Please contact the clinic.</div>
        <?php else: ?>
            <div class="app-card patient-calendar-card">
                <div class="d-flex flex-column flex-sm-row gap-3 justify-content-between align-items-sm-center mb-3">
                    <div>
                        <div class="text-uppercase small text-muted fw-semibold">Attendance Calendar</div>
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
                    <span>Green dates show days you were marked present for treatment.</span>
                </div>

                <div class="patient-calendar" role="grid" aria-label="Attendance calendar for <?= htmlspecialchars($monthStart->format('F Y')) ?>">
                    <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
                        <div class="patient-calendar-weekday" role="columnheader"><?= htmlspecialchars($weekday) ?></div>
                    <?php endforeach; ?>

                    <?php foreach ($calendarDays as $calendarDay): ?>
                        <?php
                            $calendarDate = $calendarDay->format('Y-m-d');
                            $isCurrentMonthDay = $calendarDay->format('Y-m') === $selectedMonth;
                            $isPresent = isset($presentDates[$calendarDate]);
                            $dayClasses = ['patient-calendar-day'];
                            if (!$isCurrentMonthDay) {
                                $dayClasses[] = 'is-muted';
                            }
                            if ($isPresent) {
                                $dayClasses[] = 'is-present';
                            }
                        ?>
                        <div class="<?= htmlspecialchars(implode(' ', $dayClasses)) ?>" role="gridcell" aria-label="<?= htmlspecialchars($calendarDay->format('F j, Y') . ($isPresent ? ' - Present' : '')) ?>">
                            <span class="patient-calendar-date"><?= htmlspecialchars($calendarDay->format('j')) ?></span>
                            <?php if ($isPresent): ?>
                                <span class="patient-calendar-status">Present</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="row g-3 g-lg-4 mb-4">
                <div class="col-12 col-md-4">
                    <div class="stat-card bg-primary text-white h-100">
                        <div class="text-uppercase small text-white-50 fw-semibold">Treatment Episodes</div>
                        <div class="display-6 my-2"><?= number_format($episodeCount) ?></div>
                        <a href="<?= BASE_URL ?>/views/patient/history.php" class="btn btn-light btn-sm mt-2">View History</a>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="stat-card bg-success text-white h-100">
                        <div class="text-uppercase small text-white-50 fw-semibold">Total Sessions</div>
                        <div class="display-6 my-2"><?= number_format($sessionCount) ?></div>
                        <a href="<?= BASE_URL ?>/views/patient/history.php#sessions" class="btn btn-light btn-sm mt-2">View Sessions</a>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="stat-card bg-info text-white h-100">
                        <div class="text-uppercase small text-white-50 fw-semibold">Last Visit</div>
                        <div class="fs-4 my-2">
                            <?= $lastSessionDate ? htmlspecialchars(format_display_date($lastSessionDate)) : 'Not recorded' ?>
                        </div>
                        <span class="text-white-50">Contact: <?= htmlspecialchars($patient['contact_number'] ?? '') ?></span>
                    </div>
                </div>
            </div>

            <div class="app-card">
                <h5 class="mb-3">Profile Snapshot</h5>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="fw-semibold text-muted">Name</div>
                        <div><?= htmlspecialchars(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')) ?></div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="fw-semibold text-muted">Mobile</div>
                        <div><?= htmlspecialchars($patient['contact_number'] ?? '') ?></div>
                    </div>
                    <?php if (!empty($patient['email'])): ?>
                        <div class="col-12 col-md-6">
                            <div class="fw-semibold text-muted">Email</div>
                            <div><?= htmlspecialchars($patient['email']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($patient['address'])): ?>
                        <div class="col-12">
                            <div class="fw-semibold text-muted">Address</div>
                            <div><?= nl2br(htmlspecialchars($patient['address'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
