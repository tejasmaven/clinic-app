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
        $summary = $this->computeLedgerSummary($patientId, false);

        return [
            'pending' => $summary['pending'],
            'credit' => $summary['credit'],
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
        $stmt = $this->pdo->prepare("SELECT patient_id, transaction_type FROM patient_payment_ledger WHERE id = ?");
        $stmt->execute([$id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            return false;
        }

        if ($payment['transaction_type'] !== 'payment') {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare("DELETE FROM patient_payment_ledger WHERE id = ?");
            $delete->execute([$id]);

            $this->recalculateLedgerForPatient((int) $payment['patient_id']);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return true;
    }

    public function updatePayment($id, array $data) {
        $existing = $this->getPaymentById($id);

        if (!$existing) {
            throw new InvalidArgumentException('Payment record not found.');
        }

        if ($existing['transaction_type'] !== 'payment') {
            throw new RuntimeException('Only credit entries can be updated.');
        }

        $patientId = (int) $existing['patient_id'];
        if (isset($data['patient_id']) && (int) $data['patient_id'] !== $patientId) {
            throw new RuntimeException('Patient mismatch for payment update.');
        }

        $transactionDate = $data['payment_date'] ?? $data['transaction_date'] ?? $existing['transaction_date'];
        $amount = isset($data['amount']) ? (float) $data['amount'] : (float) $existing['amount'];
        $notes = array_key_exists('notes', $data) ? $data['notes'] : $existing['notes'];

        if ($amount <= 0) {
            throw new InvalidArgumentException('Please provide a valid payment amount.');
        }

        if (!$transactionDate) {
            throw new InvalidArgumentException('Please provide a valid transaction date.');
        }

        $this->pdo->beginTransaction();
        try {
            $sql = "UPDATE patient_payment_ledger SET transaction_date = :transaction_date, amount = :amount, notes = :notes, updated_at = NOW() WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':transaction_date', $transactionDate);
            $stmt->bindValue(':amount', $amount);
            $this->bindNullable($stmt, ':notes', $notes, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $this->recalculateLedgerForPatient($patientId);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function recalculateLedgerForPatient($patientId) {
        $summary = $this->computeLedgerSummary($patientId, true);
        $this->upsertCreditBalance($patientId, $summary['credit']);
    }

    private function computeLedgerSummary($patientId, $updateStatuses = false) {
        $stmt = $this->pdo->prepare(
            "SELECT id, transaction_type, amount, status FROM patient_payment_ledger WHERE patient_id = ? ORDER BY transaction_date ASC, id ASC"
        );
        $stmt->execute([$patientId]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $credit = 0.0;
        $outstanding = 0.0;
        $pendingCharges = [];
        $epsilon = 1e-6;
        $updateStmt = null;

        if ($updateStatuses) {
            $updateStmt = $this->pdo->prepare(
                "UPDATE patient_payment_ledger SET status = ?, updated_at = NOW() WHERE id = ?"
            );
        }

        foreach ($entries as $entry) {
            $id = (int) $entry['id'];
            $amount = (float) $entry['amount'];
            $status = $entry['status'];

            if ($entry['transaction_type'] === 'payment') {
                $credit += $amount;

                if ($updateStatuses && $status !== 'received') {
                    $updateStmt->execute(['received', $id]);
                }

                $this->applyCreditToPendingCharges($pendingCharges, $credit, $outstanding, $updateStatuses, $updateStmt, $epsilon);
            } else {
                if ($credit + $epsilon >= $amount) {
                    $credit -= $amount;
                    if ($updateStatuses && $status !== 'received') {
                        $updateStmt->execute(['received', $id]);
                    }
                } else {
                    if ($credit > $epsilon) {
                        $amount -= $credit;
                        $credit = 0.0;
                    }

                    $outstanding += $amount;

                    if ($updateStatuses && $status !== 'pending') {
                        $updateStmt->execute(['pending', $id]);
                        $status = 'pending';
                    }

                    $pendingCharges[] = [
                        'id' => $id,
                        'remaining' => $amount,
                        'status' => $status,
                    ];
                }
            }
        }

        return [
            'credit' => max($credit, 0.0),
            'pending' => max($outstanding, 0.0),
        ];
    }

    private function applyCreditToPendingCharges(array &$pendingCharges, float &$credit, float &$outstanding, bool $updateStatuses, $updateStmt, float $epsilon) {
        while ($credit > $epsilon && !empty($pendingCharges)) {
            $current = &$pendingCharges[0];
            $needed = $current['remaining'];

            if ($credit + $epsilon >= $needed) {
                $credit -= $needed;
                $outstanding -= $needed;
                if ($updateStatuses && $current['status'] !== 'received') {
                    $updateStmt->execute(['received', $current['id']]);
                }
                array_shift($pendingCharges);
            } else {
                $current['remaining'] = $needed - $credit;
                $outstanding -= $credit;
                $credit = 0.0;
            }

            unset($current);
        }

        if ($outstanding < 0 && $outstanding > -$epsilon) {
            $outstanding = 0.0;
        }
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
