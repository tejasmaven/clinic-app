<?php
class PaymentController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getPaymentsByPatient($patientId, $status = null) {
        $sql = "SELECT * FROM patient_payment_ledger WHERE patient_id = ?";
        $params = [$patientId];

        if ($status !== null && $status !== '') {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY transaction_date DESC, id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllPaymentsByPatient($patientId) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM patient_payment_ledger WHERE patient_id = ? ORDER BY transaction_date DESC, id DESC"
        );
        $stmt->execute([$patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPatientTotals($patientId) {
        $pendingStmt = $this->pdo->prepare(
            "SELECT SUM(amount) AS total FROM patient_payment_ledger WHERE patient_id = ? AND transaction_type = 'charge' AND status = 'pending'"
        );
        $pendingStmt->execute([$patientId]);
        $pending = $pendingStmt->fetchColumn();

        return [
            'pending' => $pending !== false ? (float) $pending : 0.0,
            'credit' => $this->getCreditBalance($patientId),
        ];
    }

    public function getPaymentById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM patient_payment_ledger WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function savePayment(array $data) {
        $patientId = (int) ($data['patient_id'] ?? 0);
        $amount = isset($data['amount']) ? (float) $data['amount'] : 0.0;
        $transactionDate = $data['payment_date'] ?? $data['transaction_date'] ?? date('Y-m-d');
        $transactionType = $data['transaction_type'] ?? 'payment';
        $episodeId = $data['episode_id'] ?? ($data['episodes_covered'] ?? null);
        $sessionReference = $data['session_reference'] ?? ($data['treatment_covered'] ?? null);
        $notes = $data['notes'] ?? null;

        if ($patientId <= 0 || $amount <= 0) {
            throw new InvalidArgumentException('Invalid payment data.');
        }

        if (!in_array($transactionType, ['charge', 'payment'], true)) {
            throw new InvalidArgumentException('Invalid transaction type.');
        }

        $initialStatus = $transactionType === 'payment' ? 'received' : 'pending';

        $this->pdo->beginTransaction();
        try {
            $sql = "INSERT INTO patient_payment_ledger (patient_id, transaction_date, amount, transaction_type, status, episode_id, session_reference, notes, created_at, updated_at) VALUES (:patient_id, :transaction_date, :amount, :transaction_type, :status, :episode_id, :session_reference, :notes, NOW(), NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
            $stmt->bindValue(':transaction_date', $transactionDate);
            $stmt->bindValue(':amount', $amount);
            $stmt->bindValue(':transaction_type', $transactionType);
            $stmt->bindValue(':status', $initialStatus);
            $this->bindNullable($stmt, ':episode_id', $episodeId, PDO::PARAM_INT);
            $this->bindNullable($stmt, ':session_reference', $sessionReference, PDO::PARAM_STR);
            $this->bindNullable($stmt, ':notes', $notes, PDO::PARAM_STR);
            $stmt->execute();

            $this->recalculateLedgerForPatient($patientId);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function recordSessionPayment($patientId, $episodeId, $sessionDate, $amount) {
        $data = [
            'patient_id' => $patientId,
            'payment_date' => $sessionDate,
            'amount' => $amount,
            'transaction_type' => 'charge',
            'episode_id' => $episodeId,
            'session_reference' => $sessionDate,
            'notes' => 'Session charge',
        ];

        $this->savePayment($data);
    }

    public function deletePayment($id) {
        $stmt = $this->pdo->prepare("SELECT patient_id FROM patient_payment_ledger WHERE id = ?");
        $stmt->execute([$id]);
        $patientId = $stmt->fetchColumn();

        if (!$patientId) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare("DELETE FROM patient_payment_ledger WHERE id = ?");
            $delete->execute([$id]);

            $this->recalculateLedgerForPatient((int) $patientId);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function recalculateLedgerForPatient($patientId) {
        $stmt = $this->pdo->prepare(
            "SELECT id, transaction_type, amount FROM patient_payment_ledger WHERE patient_id = ? ORDER BY transaction_date ASC, id ASC"
        );
        $stmt->execute([$patientId]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $credit = 0.0;
        $updateStmt = $this->pdo->prepare(
            "UPDATE patient_payment_ledger SET status = ?, updated_at = NOW() WHERE id = ?"
        );

        foreach ($entries as $entry) {
            $amount = (float) $entry['amount'];
            if ($entry['transaction_type'] === 'payment') {
                $status = 'received';
                $credit += $amount;
            } else {
                if ($credit + 1e-6 >= $amount) {
                    $status = 'received';
                    $credit -= $amount;
                } else {
                    $status = 'pending';
                }
            }

            $updateStmt->execute([$status, $entry['id']]);
        }

        $this->upsertCreditBalance($patientId, $credit);
    }

    private function getCreditBalance($patientId) {
        $stmt = $this->pdo->prepare("SELECT credit_amount FROM patient_credit_balances WHERE patient_id = ?");
        $stmt->execute([$patientId]);
        $credit = $stmt->fetchColumn();

        return $credit !== false ? (float) $credit : 0.0;
    }

    private function upsertCreditBalance($patientId, $amount) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO patient_credit_balances (patient_id, credit_amount, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE credit_amount = VALUES(credit_amount), updated_at = NOW()"
        );
        $stmt->execute([$patientId, max(0, $amount)]);
    }

    private function bindNullable($stmt, $param, $value, $type) {
        if ($value === '' || $value === null) {
            $stmt->bindValue($param, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($param, $value, $type);
        }
    }
}
?>
