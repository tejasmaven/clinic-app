<?php
class PaymentController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getPaymentsByPatient($patientId, $status) {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE patient_id = ? AND status = ? ORDER BY payment_date DESC, id DESC");
        $stmt->execute([$patientId, $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllPaymentsByPatient($patientId) {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE patient_id = ? ORDER BY payment_date DESC, id DESC");
        $stmt->execute([$patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPatientTotals($patientId) {
        $stmt = $this->pdo->prepare("SELECT status, SUM(amount) AS total FROM payments WHERE patient_id = ? GROUP BY status");
        $stmt->execute([$patientId]);
        $totals = [
            'pending' => 0.0,
            'credit' => 0.0,
        ];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($totals[$row['status']])) {
                $totals[$row['status']] = (float) $row['total'];
            }
        }
        return $totals;
    }

    public function getPaymentById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function savePayment($data) {
        $this->pdo->beginTransaction();
        try {
            if (!empty($data['id'])) {
                $sql = "UPDATE payments SET payment_date = :payment_date, amount = :amount, episodes_covered = :episodes_covered, treatment_covered = :treatment_covered, status = :status, updated_at = NOW() WHERE id = :id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':payment_date', $data['payment_date']);
                $stmt->bindValue(':amount', $data['amount']);
                $this->bindNullable($stmt, ':episodes_covered', $data['episodes_covered'], PDO::PARAM_INT);
                $this->bindNullable($stmt, ':treatment_covered', $data['treatment_covered'], PDO::PARAM_STR);
                $stmt->bindValue(':status', $data['status']);
                $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);
                $stmt->execute();
                $this->pdo->commit();
                return;
            }

            $sql = "INSERT INTO payments (patient_id, payment_date, amount, episodes_covered, treatment_covered, status, created_at, updated_at) VALUES (:patient_id, :payment_date, :amount, :episodes_covered, :treatment_covered, 'received', NOW(), NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':patient_id', $data['patient_id'], PDO::PARAM_INT);
            $stmt->bindValue(':payment_date', $data['payment_date']);
            $stmt->bindValue(':amount', $data['amount']);
            $this->bindNullable($stmt, ':episodes_covered', $data['episodes_covered'], PDO::PARAM_INT);
            $this->bindNullable($stmt, ':treatment_covered', $data['treatment_covered'], PDO::PARAM_STR);
            $stmt->execute();

            $amount = (float) $data['amount'];
            $remaining = $this->applyAmountToPending($data['patient_id'], $amount);
            if ($remaining > 0) {
                $this->storeCredit($data['patient_id'], $remaining, $data['payment_date']);
                $this->applyCreditsToPending($data['patient_id']);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Record payment information for a treatment session and automatically
     * apply available credit to mark the session as received when possible.
     */
    public function recordSessionPayment($patientId, $episodeId, $sessionDate, $amount) {
        $this->pdo->beginTransaction();
        $committed = false;
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM payments WHERE patient_id = ? AND episodes_covered = ? AND treatment_covered = ?"
            );
            $stmt->execute([$patientId, $episodeId, $sessionDate]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $update = $this->pdo->prepare(
                    "UPDATE payments SET payment_date = ?, amount = ?, status = 'pending', updated_at = NOW() WHERE id = ?"
                );
                $update->execute([$sessionDate, $amount, $existing['id']]);
            } else {
                $insert = $this->pdo->prepare(
                    "INSERT INTO payments (patient_id, payment_date, amount, episodes_covered, treatment_covered, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())"
                );
                $insert->execute([$patientId, $sessionDate, $amount, $episodeId, $sessionDate]);
            }

            $this->applyCreditsToPending($patientId);
            $this->pdo->commit();
            $committed = true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        if ($committed) {
            $this->rebuildPatientLedger($patientId);
        }
    }

    public function deletePayment($id) {
        $stmt = $this->pdo->prepare("SELECT patient_id FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        $patientId = $stmt->fetchColumn();

        if (!$patientId) {
            return;
        }

        $delete = $this->pdo->prepare("DELETE FROM payments WHERE id = ?");
        $delete->execute([$id]);

        $this->rebuildPatientLedger($patientId);
    }

    private function bindNullable($stmt, $param, $value, $type) {
        if ($value === '' || $value === null) {
            $stmt->bindValue($param, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($param, $value, $type);
        }
    }

    private function applyAmountToPending($patientId, $amount) {
        $amount = (float) $amount;
        if ($amount <= 0) {
            return 0.0;
        }

        $pendingStmt = $this->pdo->prepare(
            "SELECT id, amount FROM payments WHERE patient_id = ? AND status = 'pending' ORDER BY payment_date ASC, id ASC"
        );
        $pendingStmt->execute([$patientId]);
        $pending = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($pending as $row) {
            if ($amount <= 0) {
                break;
            }
            $due = (float) $row['amount'];
            if ($due <= 0) {
                continue;
            }

            if ($amount + 1e-6 >= $due) {
                $update = $this->pdo->prepare("UPDATE payments SET status = 'received', updated_at = NOW() WHERE id = ?");
                $update->execute([$row['id']]);
                $amount -= $due;
            }
        }

        return $amount;
    }

    private function storeCredit($patientId, $amount, $paymentDate) {
        if ($amount <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT id, amount FROM payments WHERE patient_id = ? AND status = 'credit' ORDER BY payment_date ASC, id ASC LIMIT 1");
        $stmt->execute([$patientId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newAmount = (float) $existing['amount'] + $amount;
            $update = $this->pdo->prepare("UPDATE payments SET amount = ?, payment_date = ?, updated_at = NOW() WHERE id = ?");
            $update->execute([$newAmount, $paymentDate, $existing['id']]);
        } else {
            $insert = $this->pdo->prepare("INSERT INTO payments (patient_id, payment_date, amount, episodes_covered, treatment_covered, status, created_at, updated_at) VALUES (?, ?, ?, NULL, NULL, 'credit', NOW(), NOW())");
            $insert->execute([$patientId, $paymentDate, $amount]);
        }
    }

    private function applyCreditsToPending($patientId) {
        $creditStmt = $this->pdo->prepare("SELECT id, amount, payment_date FROM payments WHERE patient_id = ? AND status = 'credit' ORDER BY payment_date ASC, id ASC");
        $creditStmt->execute([$patientId]);
        $credits = $creditStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($credits as $credit) {
            $available = (float) $credit['amount'];
            if ($available <= 0) {
                $delete = $this->pdo->prepare("DELETE FROM payments WHERE id = ?");
                $delete->execute([$credit['id']]);
                continue;
            }

            $remaining = $this->applyAmountToPending($patientId, $available);
            if ($remaining <= 1e-6) {
                $delete = $this->pdo->prepare("DELETE FROM payments WHERE id = ?");
                $delete->execute([$credit['id']]);
            } else {
                $update = $this->pdo->prepare("UPDATE payments SET amount = ?, updated_at = NOW() WHERE id = ?");
                $update->execute([$remaining, $credit['id']]);
            }
        }
    }

    private function rebuildPatientLedger($patientId) {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM payments WHERE patient_id = ? AND status = 'credit'")->execute([$patientId]);

            $reset = $this->pdo->prepare("UPDATE payments SET status = 'pending' WHERE patient_id = ? AND treatment_covered IS NOT NULL");
            $reset->execute([$patientId]);

            $paymentsStmt = $this->pdo->prepare("SELECT payment_date, amount FROM payments WHERE patient_id = ? AND treatment_covered IS NULL AND status != 'credit' ORDER BY payment_date ASC, id ASC");
            $paymentsStmt->execute([$patientId]);
            $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

            $credit = 0.0;
            foreach ($payments as $payment) {
                $credit += $this->applyPaymentAndReturnRemaining($patientId, (float) $payment['amount']);
            }

            if ($credit > 0) {
                $lastDate = !empty($payments) ? end($payments)['payment_date'] : date('Y-m-d');
                $this->storeCredit($patientId, $credit, $lastDate);
                $this->applyCreditsToPending($patientId);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function applyPaymentAndReturnRemaining($patientId, $amount) {
        $remaining = $this->applyAmountToPending($patientId, $amount);
        return $remaining;
    }
}
?>
