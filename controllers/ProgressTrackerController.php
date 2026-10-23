<?php

class ProgressTrackerController
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getReports(DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $weeks = $this->buildWeeks($startDate, $endDate);
        $start = $startDate->format('Y-m-d');
        $end = $endDate->format('Y-m-d');

        $patients = $this->getDailyValues(
            "SELECT DATE(created_at) AS metric_date, COUNT(*) AS metric_value
             FROM patients
             WHERE created_at >= :start AND created_at < DATE_ADD(:end, INTERVAL 1 DAY)
             GROUP BY DATE(created_at)",
            $start,
            $end
        );

        $sessions = $this->getDailyValues(
            "SELECT session_date AS metric_date, COUNT(*) AS metric_value
             FROM treatment_sessions
             WHERE session_date BETWEEN :start AND :end
             GROUP BY session_date",
            $start,
            $end
        );

        $activeSessions = $this->getDailyValues(
            "SELECT ts.session_date AS metric_date, COUNT(*) AS metric_value
             FROM treatment_sessions ts
             INNER JOIN treatment_episodes te ON te.id = ts.episode_id
             WHERE ts.session_date BETWEEN :start AND :end AND te.status = 'Active'
             GROUP BY ts.session_date",
            $start,
            $end
        );

        $payments = $this->getDailyPaymentValues($start, $end);

        return [
            'patients' => $this->makeReport('Patients Onboarded', 'count', $weeks, [
                'Patients' => $patients,
            ]),
            'sessions' => $this->makeReport('Treatment Sessions', 'count', $weeks, [
                'Sessions' => $sessions,
            ]),
            'received' => $this->makeReport('Payments Received', 'currency', $weeks, [
                'Received' => $payments['received'],
            ]),
            'pending' => $this->makeReport('Payments Pending', 'currency', $weeks, [
                'Pending' => $payments['pending'],
            ], ['Pending']),
            'payment_summary' => $this->makeReport('Payment Report', 'currency', $weeks, [
                'Received' => $payments['received'],
                'Outstanding' => $payments['pending'],
            ], ['Outstanding']),
            'active_sessions' => $this->makeReport('Active Treatment Sessions', 'count', $weeks, [
                'Active sessions' => $activeSessions,
            ]),
        ];
    }

    private function buildWeeks(DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $weeks = [];
        $cursor = $startDate;

        while ($cursor <= $endDate) {
            $weekEnd = $cursor->modify('sunday this week');
            if ($weekEnd < $cursor) {
                $weekEnd = $cursor;
            }
            if ($weekEnd > $endDate) {
                $weekEnd = $endDate;
            }

            $weeks[] = [
                'start' => $cursor,
                'end' => $weekEnd,
                'label' => $cursor->format('d M') . ' - ' . $weekEnd->format('d M Y'),
                'short_label' => $cursor->format('d M'),
            ];

            $cursor = $weekEnd->modify('+1 day');
        }

        return $weeks;
    }

    private function getDailyValues(string $sql, string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':start' => $startDate, ':end' => $endDate]);

        $values = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $values[$row['metric_date']] = (float) $row['metric_value'];
        }

        return $values;
    }

    private function getDailyPaymentValues(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT transaction_date AS metric_date,
                    SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) AS received,
                    SUM(CASE WHEN transaction_type = 'charge' AND status = 'pending' THEN amount ELSE 0 END) AS pending
             FROM patient_payment_ledger
             WHERE transaction_date BETWEEN :start AND :end
             GROUP BY transaction_date"
        );
        $stmt->execute([':start' => $startDate, ':end' => $endDate]);

        $values = ['received' => [], 'pending' => []];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $values['received'][$row['metric_date']] = (float) $row['received'];
            $values['pending'][$row['metric_date']] = (float) $row['pending'];
        }

        return $values;
    }

    private function makeReport(string $title, string $format, array $weeks, array $series, array $lowerIsBetter = []): array
    {
        $rows = [];
        $seriesTotals = [];

        foreach ($series as $name => $dailyValues) {
            $seriesTotals[$name] = [];
            foreach ($weeks as $week) {
                $seriesTotals[$name][] = $this->sumRange($dailyValues, $week['start'], $week['end']);
            }
        }

        foreach ($weeks as $index => $week) {
            $seriesValues = [];
            $seriesChanges = [];
            foreach ($seriesTotals as $name => $totals) {
                $current = $totals[$index] ?? 0.0;
                $previous = $index > 0 ? ($totals[$index - 1] ?? 0.0) : null;
                $seriesValues[$name] = $current;
                $seriesChanges[$name] = $this->percentageChange($current, $previous);
            }

            $rows[] = [
                'week' => $week['label'],
                'series_values' => $seriesValues,
                'series_changes' => $seriesChanges,
            ];
        }

        return [
            'title' => $title,
            'format' => $format,
            'labels' => array_column($weeks, 'short_label'),
            'series' => $seriesTotals,
            'rows' => $rows,
            'totals' => array_map('array_sum', $seriesTotals),
            'lower_is_better' => $lowerIsBetter,
        ];
    }

    private function sumRange(array $dailyValues, DateTimeImmutable $start, DateTimeImmutable $end): float
    {
        $total = 0.0;
        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            $total += $dailyValues[$date->format('Y-m-d')] ?? 0.0;
        }
        return $total;
    }

    private function percentageChange(float $current, ?float $previous): ?float
    {
        if ($previous === null) {
            return null;
        }
        if ($previous == 0.0) {
            return $current == 0.0 ? 0.0 : 100.0;
        }
        return (($current - $previous) / abs($previous)) * 100;
    }
}

