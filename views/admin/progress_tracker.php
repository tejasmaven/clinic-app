<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Admin');

require_once '../../controllers/ProgressTrackerController.php';

$today = new DateTimeImmutable('today');
$defaultStart = $today->modify('first day of this month');
$defaultEnd = $today->modify('last day of this month');
$error = '';

$startInput = trim($_GET['start_date'] ?? $defaultStart->format('Y-m-d'));
$endInput = trim($_GET['end_date'] ?? $defaultEnd->format('Y-m-d'));
$startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $startInput);
$endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $endInput);

if (!$startDate || $startDate->format('Y-m-d') !== $startInput || !$endDate || $endDate->format('Y-m-d') !== $endInput) {
    $startDate = $defaultStart;
    $endDate = $defaultEnd;
    $error = 'Invalid dates were replaced with the current month.';
}

if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$latestAllowedEnd = $startDate->modify('+6 months')->modify('-1 day');
if ($endDate > $latestAllowedEnd) {
    $endDate = $latestAllowedEnd;
    $error = 'The date range is limited to a maximum of six months. The end date was adjusted.';
}

$controller = new ProgressTrackerController($pdo);
$reports = $controller->getReports($startDate, $endDate);
$startValue = $startDate->format('Y-m-d');
$endValue = $endDate->format('Y-m-d');
$chartPayload = [];

foreach ($reports as $key => $report) {
    $chartPayload[$key] = [
        'format' => $report['format'],
        'labels' => $report['labels'],
        'series' => $report['series'],
    ];
}

function formatTrackerValue(float $value, string $format): string
{
    return $format === 'currency' ? 'R ' . number_format($value, 2) : number_format($value, 0);
}

include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Progress Tracker</h1>
                <p class="admin-page-subtitle"><?= htmlspecialchars($startDate->format('d M Y')) ?> to <?= htmlspecialchars($endDate->format('d M Y')) ?>, grouped into Monday-based weeks.</p>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-warning" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="app-card">
            <form method="get" class="row g-3 align-items-end" id="progressFilter">
                <div class="col-12 col-sm-5 col-lg-4">
                    <label class="form-label" for="start_date">Start Date</label>
                    <input class="form-control" type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startValue) ?>" required>
                </div>
                <div class="col-12 col-sm-5 col-lg-4">
                    <label class="form-label" for="end_date">End Date</label>
                    <input class="form-control" type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endValue) ?>" required>
                </div>
                <div class="col-12 col-sm-2 col-lg-3">
                    <button class="btn btn-primary w-100" type="submit">Apply</button>
                </div>
            </form>
        </div>

        <div class="app-card">
            <ul class="nav nav-tabs progress-report-tabs" id="progressReportTabs" role="tablist">
                <?php $tabIndex = 0; ?>
                <?php foreach ($reports as $key => $report): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link<?= $tabIndex === 0 ? ' active' : '' ?>" id="<?= htmlspecialchars($key) ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= htmlspecialchars($key) ?>-panel" type="button" role="tab" aria-controls="<?= htmlspecialchars($key) ?>-panel" aria-selected="<?= $tabIndex === 0 ? 'true' : 'false' ?>">
                            <?= htmlspecialchars($report['title']) ?>
                        </button>
                    </li>
                    <?php $tabIndex++; ?>
                <?php endforeach; ?>
            </ul>

            <div class="tab-content pt-4" id="progressReportContent">
                <?php $panelIndex = 0; ?>
                <?php foreach ($reports as $key => $report): ?>
                    <section class="tab-pane fade<?= $panelIndex === 0 ? ' show active' : '' ?>" id="<?= htmlspecialchars($key) ?>-panel" role="tabpanel" aria-labelledby="<?= htmlspecialchars($key) ?>-tab" tabindex="0">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                            <div>
                                <h5 class="mb-1"><?= htmlspecialchars($report['title']) ?></h5>
                                <p class="text-muted mb-0">Weekly progression for the selected period</p>
                            </div>
                            <div class="progress-totals">
                                <?php foreach ($report['totals'] as $seriesName => $total): ?>
                                    <div class="progress-total">
                                        <span class="text-muted small"><?= htmlspecialchars($seriesName) ?> total</span>
                                        <strong><?= htmlspecialchars(formatTrackerValue((float) $total, $report['format'])) ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="progress-chart-wrap mb-4">
                            <canvas id="<?= htmlspecialchars($key) ?>-chart" aria-label="<?= htmlspecialchars($report['title']) ?> weekly chart"></canvas>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Week Dates</th>
                                        <?php foreach (array_keys($report['series']) as $seriesName): ?>
                                            <th class="text-end"><?= htmlspecialchars($seriesName) ?></th>
                                            <th class="text-end"><?= htmlspecialchars($seriesName) ?> Change</th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report['rows'] as $row): ?>
                                        <tr>
                                            <td class="text-nowrap"><?= htmlspecialchars($row['week']) ?></td>
                                            <?php foreach ($row['series_values'] as $seriesName => $seriesValue): ?>
                                                <td class="text-end fw-semibold"><?= htmlspecialchars(formatTrackerValue((float) $seriesValue, $report['format'])) ?></td>
                                                <td class="text-end">
                                                    <?php $change = $row['series_changes'][$seriesName]; ?>
                                                    <?php if ($change === null): ?>
                                                        <span class="text-muted">Baseline</span>
                                                    <?php else: ?>
                                                        <?php
                                                        $change = (float) $change;
                                                        $lowerIsBetter = in_array($seriesName, $report['lower_is_better'], true);
                                                        $isFavorable = $lowerIsBetter ? $change < 0 : $change > 0;
                                                        $isUnfavorable = $lowerIsBetter ? $change > 0 : $change < 0;
                                                        ?>
                                                        <span class="<?= $isFavorable ? 'text-success' : ($isUnfavorable ? 'text-danger' : 'text-muted') ?> fw-semibold">
                                                            <?= $change > 0 ? '+' : '' ?><?= htmlspecialchars(number_format($change, 1)) ?>%
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <?php $panelIndex++; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
.progress-report-tabs { gap: .25rem; overflow-x: auto; flex-wrap: nowrap; }
.progress-report-tabs .nav-link { white-space: nowrap; }
.progress-chart-wrap { position: relative; width: 100%; height: 340px; }
.progress-totals { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 1.5rem; }
.progress-total { display: flex; flex-direction: column; align-items: flex-end; }
.progress-total strong { font-size: 1.4rem; }
@media (max-width: 575.98px) {
    .progress-chart-wrap { height: 280px; }
    .progress-totals { justify-content: flex-start; }
    .progress-total { align-items: flex-start; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const reports = <?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const colors = [
        { border: '#0d6efd', background: 'rgba(13, 110, 253, 0.12)' },
        { border: '#198754', background: 'rgba(25, 135, 84, 0.12)' }
    ];
    const charts = {};

    function renderChart(key) {
        if (charts[key] || !reports[key]) return;
        const canvas = document.getElementById(key + '-chart');
        if (!canvas) return;

        const report = reports[key];
        const datasets = Object.entries(report.series).map(function (entry, index) {
            const color = colors[index % colors.length];
            return {
                label: entry[0],
                data: entry[1],
                borderColor: color.border,
                backgroundColor: color.background,
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.28
            };
        });

        charts[key] = new Chart(canvas, {
            type: 'line',
            data: { labels: report.labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: report.format === 'count' ? 0 : 2,
                            callback: function (value) {
                                return report.format === 'currency' ? 'R ' + Number(value).toLocaleString() : value;
                            }
                        }
                    }
                },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const value = Number(context.parsed.y || 0);
                                return context.dataset.label + ': ' + (report.format === 'currency' ? 'R ' + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : value.toLocaleString());
                            }
                        }
                    }
                }
            }
        });
    }

    const firstKey = Object.keys(reports)[0];
    if (firstKey) renderChart(firstKey);

    document.querySelectorAll('#progressReportTabs button[data-bs-toggle="tab"]').forEach(function (button) {
        button.addEventListener('shown.bs.tab', function (event) {
            renderChart(event.target.id.replace(/-tab$/, ''));
        });
    });

    const startInput = document.getElementById('start_date');
    const endInput = document.getElementById('end_date');
    function updateEndLimit() {
        if (!startInput.value) return;
        const start = new Date(startInput.value + 'T00:00:00');
        const limit = new Date(start);
        limit.setMonth(limit.getMonth() + 6);
        limit.setDate(limit.getDate() - 1);
        const year = limit.getFullYear();
        const month = String(limit.getMonth() + 1).padStart(2, '0');
        const day = String(limit.getDate()).padStart(2, '0');
        endInput.min = startInput.value;
        endInput.max = year + '-' + month + '-' + day;
    }
    startInput.addEventListener('change', updateEndLimit);
    updateEndLimit();
});
</script>

<?php include '../../includes/footer.php'; ?>
