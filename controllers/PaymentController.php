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

    public function getPaymentById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function savePayment($data) {
        if (!empty($data['id'])) {
            $sql = "UPDATE payments SET payment_date = :payment_date, amount = :amount, episodes_covered = :episodes_covered, treatment_covered = :treatment_covered, status = :status, updated_at = NOW() WHERE id = :id";
        } else {
            $sql = "INSERT INTO payments (patient_id, payment_date, amount, episodes_covered, treatment_covered, status, created_at, updated_at) VALUES (:patient_id, :payment_date, :amount, :episodes_covered, :treatment_covered, :status, NOW(), NOW())";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':payment_date', $data['payment_date']);
        $stmt->bindValue(':amount', $data['amount']);
        $stmt->bindValue(':episodes_covered', $data['episodes_covered']);
        $stmt->bindValue(':treatment_covered', $data['treatment_covered']);
        $stmt->bindValue(':status', $data['status']);
        if (!empty($data['id'])) {
            $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':patient_id', $data['patient_id'], PDO::PARAM_INT);
        }
        $stmt->execute();
    }

    /**
     * Record payment information for a treatment session. If a payment entry
     * already exists for the given patient/episode/session date, update the
     * amount while retaining its status. Otherwise insert a new payment marked
     * as pending.
     */
    public function recordSessionPayment($patientId, $episodeId, $sessionDate, $amount) {
        $stmt = $this->pdo->prepare(
            "SELECT id, status FROM payments WHERE patient_id = ? AND episodes_covered = ? AND treatment_covered = ?"
        );
        $stmt->execute([$patientId, $episodeId, $sessionDate]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $update = $this->pdo->prepare(
                "UPDATE payments SET payment_date = ?, amount = ?, updated_at = NOW() WHERE id = ?"
            );
            $update->execute([$sessionDate, $amount, $existing['id']]);
            return $existing['status'];
        } else {
            $insert = $this->pdo->prepare(
                "INSERT INTO payments (patient_id, payment_date, amount, episodes_covered, treatment_covered, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())"
            );
            $insert->execute([$patientId, $sessionDate, $amount, $episodeId, $sessionDate]);
            return 'pending';
        }
    }

    public function deletePayment($id) {
        $stmt = $this->pdo->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([$id]);
    }
}
?>
