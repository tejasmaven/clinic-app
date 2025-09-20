<?php
class FinancialReportController {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getSummary(string $startDate, string $endDate): array {
        $sql = "SELECT
                    SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) AS total_received,
                    SUM(CASE WHEN transaction_type = 'charge' THEN amount ELSE 0 END) AS total_charges,
                    SUM(CASE WHEN transaction_type = 'charge' AND status = 'pending' THEN amount ELSE 0 END) AS total_pending
                FROM patient_payment_ledger
                WHERE transaction_date BETWEEN :start AND :end";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':start' => $startDate,
            ':end'   => $endDate,
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_received' => isset($result['total_received']) ? (float) $result['total_received'] : 0.0,
            'total_charges'  => isset($result['total_charges']) ? (float) $result['total_charges'] : 0.0,
            'total_pending'  => isset($result['total_pending']) ? (float) $result['total_pending'] : 0.0,
        ];
    }

    public function getDailyTotals(string $startDate, string $endDate): array {
        $sql = "SELECT transaction_date,
                       SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) AS total_received,
                       SUM(CASE WHEN transaction_type = 'charge' THEN amount ELSE 0 END) AS total_charges,
                       SUM(CASE WHEN transaction_type = 'charge' AND status = 'pending' THEN amount ELSE 0 END) AS total_pending
                FROM patient_payment_ledger
                WHERE transaction_date BETWEEN :start AND :end
                GROUP BY transaction_date
                ORDER BY transaction_date";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':start' => $startDate,
            ':end'   => $endDate,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $row) {
            $date = $row['transaction_date'];
            $map[$date] = [
                'total_received' => (float) $row['total_received'],
                'total_charges'  => (float) $row['total_charges'],
                'total_pending'  => (float) $row['total_pending'],
            ];
        }

        $data = [];
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);

        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $data[] = [
                'date'           => $key,
                'total_received' => $map[$key]['total_received'] ?? 0.0,
                'total_charges'  => $map[$key]['total_charges'] ?? 0.0,
                'total_pending'  => $map[$key]['total_pending'] ?? 0.0,
            ];
        }

        return $data;
    }

    public function getPaymentHistory(string $startDate, string $endDate): array {
        $sql = "SELECT l.id, l.transaction_date, l.transaction_type, l.amount, l.status, l.notes,
                       p.id AS patient_id, p.first_name, p.last_name, p.contact_number
                FROM patient_payment_ledger l
                INNER JOIN patients p ON l.patient_id = p.id
                WHERE l.transaction_date BETWEEN :start AND :end
                ORDER BY l.transaction_date DESC, l.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':start' => $startDate,
            ':end'   => $endDate,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['patient_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $row['amount'] = isset($row['amount']) ? (float) $row['amount'] : 0.0;
        }
        unset($row);

        return $rows;
    }

    public function getPatientReport(string $startDate, string $endDate): array {
        $sql = "SELECT p.id,
                       CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                       p.contact_number,
                       COALESCE(range_totals.payments_received, 0) AS payments_received,
                       COALESCE(range_totals.charges_incurred, 0) AS charges_incurred,
                       COALESCE(overall_totals.total_payments, 0) AS total_payments,
                       COALESCE(overall_totals.total_charges, 0) AS total_charges
                FROM patients p
                LEFT JOIN (
                    SELECT patient_id,
                           SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) AS payments_received,
                           SUM(CASE WHEN transaction_type = 'charge' THEN amount ELSE 0 END) AS charges_incurred
                    FROM patient_payment_ledger
                    WHERE transaction_date BETWEEN :start AND :end
                    GROUP BY patient_id
                ) AS range_totals ON p.id = range_totals.patient_id
                LEFT JOIN (
                    SELECT patient_id,
                           SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) AS total_payments,
                           SUM(CASE WHEN transaction_type = 'charge' THEN amount ELSE 0 END) AS total_charges
                    FROM patient_payment_ledger
                    GROUP BY patient_id
                ) AS overall_totals ON p.id = overall_totals.patient_id
                WHERE range_totals.patient_id IS NOT NULL
                   OR overall_totals.total_payments IS NOT NULL
                   OR overall_totals.total_charges IS NOT NULL
                ORDER BY patient_name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':start' => $startDate,
            ':end'   => $endDate,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $report = [];
        foreach ($rows as $row) {
            $totalCharges = isset($row['total_charges']) ? (float) $row['total_charges'] : 0.0;
            $totalPayments = isset($row['total_payments']) ? (float) $row['total_payments'] : 0.0;

            $pendingBalance = max($totalCharges - $totalPayments, 0.0);
            $creditBalance = max($totalPayments - $totalCharges, 0.0);

            $report[] = [
                'patient_id'        => (int) $row['id'],
                'patient_name'      => $row['patient_name'] ?? '',
                'contact_number'    => $row['contact_number'] ?? '',
                'payments_received' => isset($row['payments_received']) ? (float) $row['payments_received'] : 0.0,
                'charges_incurred'  => isset($row['charges_incurred']) ? (float) $row['charges_incurred'] : 0.0,
                'pending_balance'   => $pendingBalance,
                'credit_balance'    => $creditBalance,
            ];
        }

        return $report;
    }
}
?>
