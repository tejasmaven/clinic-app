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
            "SELECT *
             FROM patients
             WHERE id = ?"
        );
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        return $patient ?: null;
    }

    public function getFirstAssessmentDate(int $patientId): ?string {
        $stmt = $this->pdo->prepare(
            "SELECT MIN(start_date) AS first_assessment_date
             FROM treatment_episodes
             WHERE patient_id = ?"
        );
        $stmt->execute([$patientId]);
        $date = $stmt->fetchColumn();

        if ($date) {
            return $date;
        }

        $stmt = $this->pdo->prepare(
            "SELECT DATE(created_at)
             FROM patients
             WHERE id = ?"
        );
        $stmt->execute([$patientId]);
        $fallbackDate = $stmt->fetchColumn();

        return $fallbackDate ?: null;
    }

    public function getFirstAssessmentSummary(int $patientId): ?string {
        $stmt = $this->pdo->prepare(
            "SELECT initial_complaints
             FROM treatment_episodes
             WHERE patient_id = ?
             ORDER BY start_date ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$patientId]);
        $summary = $stmt->fetchColumn();

        return $summary !== false ? trim((string) $summary) : null;
    }

    public function getTreatmentSummary(int $patientId, string $startDate, string $endDate): array {
        $sessionSql = "
            SELECT
                ts.id,
                ts.session_date AS treatment_date,
                ts.remarks,
                ts.progress_notes,
                ts.advise,
                ts.additional_treatment_notes
            FROM treatment_sessions ts
            WHERE ts.patient_id = ?
              AND ts.session_date BETWEEN ? AND ?
            ORDER BY ts.session_date ASC, ts.id ASC
        ";

        $stmt = $this->pdo->prepare($sessionSql);
        $stmt->execute([$patientId, $startDate, $endDate]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$sessions) {
            return [];
        }

        $sessionIds = array_map(static function (array $session): int {
            return (int) $session['id'];
        }, $sessions);
        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));

        $exerciseStmt = $this->pdo->prepare(
            "SELECT
                te.session_id,
                COALESCE(NULLIF(te.exercise_name, ''), em.name) AS name,
                te.reps,
                te.duration_minutes,
                te.notes
             FROM treatment_exercises te
             LEFT JOIN exercises_master em ON te.exercise_id = em.id
             WHERE te.session_id IN ($placeholders)
             ORDER BY te.session_id ASC, te.id ASC"
        );
        $exerciseStmt->execute($sessionIds);
        $exercisesBySession = $this->groupItemsBySession($exerciseStmt->fetchAll(PDO::FETCH_ASSOC));

        $machineStmt = $this->pdo->prepare(
            "SELECT
                tm.session_id,
                COALESCE(NULLIF(tm.machine_name, ''), m.name) AS name,
                tm.duration_minutes,
                tm.notes
             FROM treatment_machines tm
             LEFT JOIN machines m ON tm.machine_id = m.id
             WHERE tm.session_id IN ($placeholders)
             ORDER BY tm.session_id ASC, tm.id ASC"
        );
        $machineStmt->execute($sessionIds);
        $machinesBySession = $this->groupItemsBySession($machineStmt->fetchAll(PDO::FETCH_ASSOC));

        $feeStmt = $this->pdo->prepare(
            "SELECT
                transaction_date,
                SUM(amount) AS total_fees
             FROM patient_payment_ledger
             WHERE patient_id = ?
               AND transaction_type = 'charge'
               AND transaction_date BETWEEN ? AND ?
             GROUP BY transaction_date"
        );
        $feeStmt->execute([$patientId, $startDate, $endDate]);

        $feesByDate = [];
        foreach ($feeStmt->fetchAll(PDO::FETCH_ASSOC) as $feeRow) {
            $feesByDate[$feeRow['transaction_date']] = (float) ($feeRow['total_fees'] ?? 0);
        }

        $rowsByDate = [];
        foreach ($sessions as $session) {
            $date = $session['treatment_date'];
            $sessionId = (int) $session['id'];

            if (!isset($rowsByDate[$date])) {
                $rowsByDate[$date] = [
                    'treatment_date' => $date,
                    'exercises' => [],
                    'machines' => [],
                    'session_notes' => [],
                    'total_fees' => $feesByDate[$date] ?? 0.0,
                ];
            }

            $sessionNotes = $this->getSessionNotes($session);
            if (!empty($sessionNotes)) {
                $rowsByDate[$date]['session_notes'][] = $sessionNotes;
            }

            foreach ($exercisesBySession[$sessionId] ?? [] as $exercise) {
                $rowsByDate[$date]['exercises'][] = $exercise;
            }

            foreach ($machinesBySession[$sessionId] ?? [] as $machine) {
                $rowsByDate[$date]['machines'][] = $machine;
            }
        }

        return array_values($rowsByDate);
    }

    private function getSessionNotes(array $session): array {
        $noteMap = [
            'remarks' => "Doctor's Remarks",
            'progress_notes' => 'Progress Notes',
            'advise' => 'Advise',
            'additional_treatment_notes' => 'Additional Treatment Notes',
        ];
        $notes = [];

        foreach ($noteMap as $field => $label) {
            $value = trim((string) ($session[$field] ?? ''));
            if ($value !== '') {
                $notes[] = [
                    'label' => $label,
                    'value' => $value,
                ];
            }
        }

        return $notes;
    }

    private function groupItemsBySession(array $items): array {
        $grouped = [];

        foreach ($items as $item) {
            $sessionId = (int) $item['session_id'];
            unset($item['session_id']);

            if (!isset($grouped[$sessionId])) {
                $grouped[$sessionId] = [];
            }

            $grouped[$sessionId][] = $item;
        }

        return $grouped;
    }
}
