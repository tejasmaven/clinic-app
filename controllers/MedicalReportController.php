<?php
class MedicalReportController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getPatients(): array {
        $stmt = $this->pdo->query(
            "SELECT id, first_name, last_name, contact_number
             FROM patients
             ORDER BY first_name ASC, last_name ASC, id ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPatientById(int $patientId): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT id, first_name, last_name, contact_number
             FROM patients
             WHERE id = ?"
        );
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        return $patient ?: null;
    }

    public function getTreatmentSummary(int $patientId, string $startDate, string $endDate): array {
        $sql = "
            SELECT
                session_totals.treatment_date,
                session_totals.exercise_count,
                session_totals.machine_count,
                COALESCE(fee_totals.total_fees, 0) AS total_fees
            FROM (
                SELECT
                    ts.session_date AS treatment_date,
                    COUNT(DISTINCT te.id) AS exercise_count,
                    COUNT(DISTINCT tm.id) AS machine_count
                FROM treatment_sessions ts
                LEFT JOIN treatment_exercises te ON te.session_id = ts.id
                LEFT JOIN treatment_machines tm ON tm.session_id = ts.id
                WHERE ts.patient_id = ?
                  AND ts.session_date BETWEEN ? AND ?
                GROUP BY ts.session_date
            ) session_totals
            LEFT JOIN (
                SELECT
                    transaction_date,
                    SUM(amount) AS total_fees
                FROM patient_payment_ledger
                WHERE patient_id = ?
                  AND transaction_type = 'charge'
                  AND transaction_date BETWEEN ? AND ?
                GROUP BY transaction_date
            ) fee_totals ON fee_totals.transaction_date = session_totals.treatment_date
            ORDER BY session_totals.treatment_date ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$patientId, $startDate, $endDate, $patientId, $startDate, $endDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $row): array {
            return [
                'treatment_date' => $row['treatment_date'],
                'exercise_count' => (int) ($row['exercise_count'] ?? 0),
                'machine_count' => (int) ($row['machine_count'] ?? 0),
                'total_fees' => (float) ($row['total_fees'] ?? 0),
            ];
        }, $rows);
    }
}
