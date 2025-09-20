<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Admin');

require_once '../../controllers/FinancialReportController.php';

$defaultEnd = new DateTimeImmutable('today');
$defaultStart = $defaultEnd->sub(new DateInterval('P29D'));

$startInput = $_GET['start_date'] ?? $defaultStart->format('Y-m-d');
$endInput = $_GET['end_date'] ?? $defaultEnd->format('Y-m-d');

$startDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $startInput) ?: $defaultStart;
$endDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $endInput) ?: $defaultEnd;

if ($startDateObj > $endDateObj) {
    [$startDateObj, $endDateObj] = [$endDateObj, $startDateObj];
}

$startDate = $startDateObj->format('Y-m-d');
$endDate = $endDateObj->format('Y-m-d');

$reportController = new FinancialReportController($pdo);

if (isset($_GET['export']) && $_GET['export'] === '1') {
    $summary = $reportController->getSummary($startDate, $endDate);
    $patientReport = $reportController->getPatientReport($startDate, $endDate);

    $filename = 'financial_report_' . $startDate . '_to_' . $endDate . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    if ($output) {
        fputcsv($output, ['Financial Report']);
        fputcsv($output, ['Start Date', $startDate, 'End Date', $endDate]);
        fputcsv($output, []);
        fputcsv($output, ['Summary']);
        fputcsv($output, ['Total Payments Received', number_format($summary['total_received'], 2, '.', '')]);
        fputcsv($output, ['Total Charges', number_format($summary['total_charges'], 2, '.', '')]);
        fputcsv($output, ['Total Pending Charges', number_format($summary['total_pending'], 2, '.', '')]);
        fputcsv($output, ['Patient Balances']);
        fputcsv($output, ['Patient', 'Contact Number', 'Payments (Range)', 'Charges (Range)', 'Pending Balance', 'Credit Balance']);
        foreach ($patientReport as $patient) {
            fputcsv($output, [
                $patient['patient_name'],
                $patient['contact_number'],
                number_format($patient['payments_received'], 2, '.', ''),
                number_format($patient['charges_incurred'], 2, '.', ''),
                number_format($patient['pending_balance'], 2, '.', ''),
                number_format($patient['credit_balance'], 2, '.', ''),
            ]);
        }

        fclose($output);
    }
    exit;
}

$summary = $reportController->getSummary($startDate, $endDate);
$dailyTotals = $reportController->getDailyTotals($startDate, $endDate);
$patientBalances = $reportController->getPatientReport($startDate, $endDate);

$chartLabels = [];
$chartReceived = [];
$chartCharges = [];
$chartPending = [];
foreach ($dailyTotals as $day) {
    $chartLabels[] = date('M d', strtotime($day['date']));
    $chartReceived[] = (float) $day['total_received'];
    $chartCharges[] = (float) $day['total_charges'];
    $chartPending[] = (float) $day['total_pending'];
}

$chartPayload = [
    'labels'   => $chartLabels,
    'received' => $chartReceived,
    'charges'  => $chartCharges,
    'pending'  => $chartPending,
];

$statusChartData = [
    'received' => (float) ($summary['total_received'] ?? 0),
    'pending'  => (float) ($summary['total_pending'] ?? 0),
];

$downloadQuery = http_build_query([
    'start_date' => $startDate,
    'end_date'   => $endDate,
    'export'     => '1',
]);

$displayStart = $startDateObj->format('M d, Y');
$displayEnd = $endDateObj->format('M d, Y');

include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Financial Reports</h1>
                <p class="admin-page-subtitle">Showing transactions from <?= htmlspecialchars($displayStart) ?> to <?= htmlspecialchars($displayEnd) ?></p>
            </div>
            <a class="btn btn-outline-secondary" href="?<?= htmlspecialchars($downloadQuery) ?>">Download Excel</a>
        </div>

        <div class="app-card">
            <form method="get" class="row g-3 align-items-end mb-0">
                <div class="col-12 col-sm-6 col-lg-4">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="col-12 col-sm-12 col-lg-3">
                    <button class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </form>
        </div>

        <div class="row g-3 g-lg-4">
            <div class="col-12 col-md-4">
                <div class="app-card h-100">
                    <h6 class="text-uppercase text-muted mb-2">Payments Received</h6>
                    <div class="display-6 text-success mb-0">R <?= number_format($summary['total_received'], 2) ?></div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="app-card h-100">
                    <h6 class="text-uppercase text-muted mb-2">Pending Charges</h6>
                    <div class="display-6 text-warning mb-0">R <?= number_format($summary['total_pending'], 2) ?></div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="app-card h-100">
                    <h6 class="text-uppercase text-muted mb-2">Total Charges</h6>
                    <div class="display-6 text-primary mb-0">R <?= number_format($summary['total_charges'], 2) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-3 g-lg-4">
            <div class="col-12 col-lg-8">
                <div class="app-card h-100">
                    <h5 class="mb-3">Daily Payments &amp; Charges</h5>
                    <div class="position-relative" style="min-height: 320px;">
                        <canvas id="dailyBreakdownChart" height="280"></canvas>
                        <p id="dailyChartEmpty" class="text-muted text-center mb-0 position-absolute top-50 start-50 translate-middle<?= !empty($dailyTotals) ? ' d-none' : '' ?>">No transactions found for the selected period.</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="app-card h-100">
                    <h5 class="mb-3">Received vs Pending</h5>
                    <div class="position-relative" style="min-height: 320px;">
                        <canvas id="statusSummaryChart" height="280"></canvas>
                        <p id="statusChartEmpty" class="text-muted text-center mb-0 position-absolute top-50 start-50 translate-middle<?= ($summary['total_received'] + $summary['total_pending']) > 0 ? ' d-none' : '' ?>">No payment totals available.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-card">
            <h5 class="mb-3">Patient Balances</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Contact</th>
                            <th class="text-end">Payments (Range)</th>
                            <th class="text-end">Charges (Range)</th>
                            <th class="text-end">Pending Balance</th>
                            <th class="text-end">Credit Balance</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($patientBalances)): ?>
                            <?php foreach ($patientBalances as $patient): ?>
                                <tr>
                                    <td><?= htmlspecialchars($patient['patient_name']) ?></td>
                                    <td>
                                        <?php if (!empty($patient['contact_number'])): ?>
                                            <?= htmlspecialchars($patient['contact_number']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">R <?= number_format($patient['payments_received'], 2) ?></td>
                                    <td class="text-end">R <?= number_format($patient['charges_incurred'], 2) ?></td>
                                    <td class="text-end text-danger">R <?= number_format($patient['pending_balance'], 2) ?></td>
                                    <td class="text-end text-success">R <?= number_format($patient['credit_balance'], 2) ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="../shared/manage_payments.php?patient_id=<?= urlencode((string) $patient['patient_id']) ?>">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No patient balances to display for the selected period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartData = <?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const statusData = <?= json_encode($statusChartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    const dailyCtx = document.getElementById('dailyBreakdownChart');
    const dailyEmpty = document.getElementById('dailyChartEmpty');
    const hasDailyData = chartData.labels.length > 0 && (
        chartData.received.some(val => val > 0) ||
        chartData.charges.some(val => val > 0) ||
        chartData.pending.some(val => val > 0)
    );

    if (dailyCtx && hasDailyData) {
        if (dailyEmpty) {
            dailyEmpty.classList.add('d-none');
        }
        new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Payments Received',
                        data: chartData.received,
                        backgroundColor: 'rgba(25, 135, 84, 0.7)',
                        borderColor: 'rgba(25, 135, 84, 1)',
                        borderWidth: 1,
                    },
                    {
                        label: 'Charges',
                        data: chartData.charges,
                        backgroundColor: 'rgba(13, 110, 253, 0.7)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1,
                    },
                    {
                        label: 'Pending Charges',
                        data: chartData.pending,
                        backgroundColor: 'rgba(255, 193, 7, 0.7)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return 'R ' + value;
                            }
                        }
                    }
                }
            }
        });
    } else {
        if (dailyCtx) {
            dailyCtx.classList.add('d-none');
        }
        if (dailyEmpty) {
            dailyEmpty.classList.remove('d-none');
        }
    }

    const statusCtx = document.getElementById('statusSummaryChart');
    const statusEmpty = document.getElementById('statusChartEmpty');
    const totalStatus = (statusData.received || 0) + (statusData.pending || 0);

    if (statusCtx && totalStatus > 0) {
        if (statusEmpty) {
            statusEmpty.classList.add('d-none');
        }
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Received', 'Pending'],
                datasets: [
                    {
                        data: [statusData.received, statusData.pending],
                        backgroundColor: ['#198754', '#ffc107'],
                        borderWidth: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    } else {
        if (statusCtx) {
            statusCtx.classList.add('d-none');
        }
        if (statusEmpty) {
            statusEmpty.classList.remove('d-none');
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
