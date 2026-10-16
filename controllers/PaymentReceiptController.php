<?php

require_once __DIR__ . '/PaymentSlipTemplateController.php';

class PaymentReceiptController {
    private PDO $pdo;
    private PaymentSlipTemplateController $templateController;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->templateController = new PaymentSlipTemplateController($pdo);
    }

    public function getReceiptData(int $paymentId, int $patientId): array {
        $payment = $this->getPayment($paymentId, $patientId);
        if (!$payment || ($payment['transaction_type'] ?? '') !== 'payment') {
            throw new InvalidArgumentException('Payment receipt is available only for received payment entries.');
        }

        $patient = $this->getPatient($patientId);
        if (!$patient) {
            throw new InvalidArgumentException('Patient not found.');
        }

        $template = $this->templateController->getTemplate();
        if (empty($template['image_path'])) {
            throw new RuntimeException('Please upload the payment slip template before generating receipts.');
        }

        $fields = $this->templateController->getFields($template);
        $coveredCharges = $this->getChargesCoveredByPayment($patientId, $paymentId);
        $treatmentSummary = $this->summarizeTreatment($coveredCharges, $patient);
        $amount = (float) $payment['amount'];
        $purpose = $this->resolvePurpose($payment, $treatmentSummary, $patient);
        [$purposeLine1, $purposeLine2] = $this->splitPurpose($purpose);

        return [
            'payment' => $payment,
            'patient' => $patient,
            'template' => $template,
            'fields' => $fields,
            'values' => [
                'received_from' => trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')),
                'amount_words' => $this->amountToWords($amount),
                'amount_number' => $this->formatReceiptAmount($amount),
                'purpose_line_1' => $purposeLine1,
                'purpose_line_2' => $purposeLine2,
                'treatment_start_date' => $treatmentSummary['start_date'] ? format_display_date($treatmentSummary['start_date'], 'd/m/y') : '',
                'treatment_end_date' => $treatmentSummary['end_date'] ? format_display_date($treatmentSummary['end_date'], 'd/m/y') : '',
                'treatment_days' => $treatmentSummary['days'] > 0 ? (string) $treatmentSummary['days'] : '',
            ],
        ];
    }

    private function getPayment(int $paymentId, int $patientId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM patient_payment_ledger WHERE id = ? AND patient_id = ? LIMIT 1");
        $stmt->execute([$paymentId, $patientId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        return $payment ?: null;
    }

    private function getPatient(int $patientId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM patients WHERE id = ? LIMIT 1");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        return $patient ?: null;
    }

    private function getChargesCoveredByPayment(int $patientId, int $paymentId): array {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM patient_payment_ledger
             WHERE patient_id = ?
             ORDER BY transaction_date ASC, id ASC"
        );
        $stmt->execute([$patientId]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $chargeQueue = [];
        $paymentQueue = [];
        $coveredChargeIds = [];

        foreach ($entries as $entry) {
            $entryId = (int) $entry['id'];
            $amount = (float) $entry['amount'];

            if ($entry['transaction_type'] === 'charge') {
                $charge = [
                    'entry' => $entry,
                    'remaining' => $amount,
                ];

                while ($charge['remaining'] > 0.0001 && !empty($paymentQueue)) {
                    $credit = &$paymentQueue[0];
                    $applied = min($charge['remaining'], $credit['remaining']);

                    if ((int) $credit['entry']['id'] === $paymentId) {
                        $coveredChargeIds[$entryId] = true;
                    }

                    $charge['remaining'] -= $applied;
                    $credit['remaining'] -= $applied;

                    if ($credit['remaining'] <= 0.0001) {
                        array_shift($paymentQueue);
                    }
                    unset($credit);
                }

                if ($charge['remaining'] > 0.0001) {
                    $chargeQueue[] = $charge;
                }
            } else {
                $credit = [
                    'entry' => $entry,
                    'remaining' => $amount,
                ];

                while ($credit['remaining'] > 0.0001 && !empty($chargeQueue)) {
                    $charge = &$chargeQueue[0];
                    $applied = min($credit['remaining'], $charge['remaining']);

                    if ($entryId === $paymentId) {
                        $coveredChargeIds[(int) $charge['entry']['id']] = true;
                    }

                    $credit['remaining'] -= $applied;
                    $charge['remaining'] -= $applied;

                    if ($charge['remaining'] <= 0.0001) {
                        array_shift($chargeQueue);
                    }
                    unset($charge);
                }

                if ($credit['remaining'] > 0.0001) {
                    $paymentQueue[] = $credit;
                }
            }
        }

        if (empty($coveredChargeIds)) {
            return [];
        }

        $ids = array_keys($coveredChargeIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $chargeStmt = $this->pdo->prepare(
            "SELECT *
             FROM patient_payment_ledger
             WHERE id IN ($placeholders)
             ORDER BY transaction_date ASC, id ASC"
        );
        $chargeStmt->execute($ids);

        return $chargeStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function summarizeTreatment(array $charges, array $patient): array {
        $dates = [];
        $purposeParts = [];

        foreach ($charges as $charge) {
            $session = $this->getSessionForCharge($charge);
            if ($session) {
                if (!empty($session['session_date'])) {
                    $dates[] = $session['session_date'];
                }
                if (!empty($session['initial_complaints'])) {
                    $purposeParts[] = $session['initial_complaints'];
                }
                continue;
            }

            if (!empty($charge['transaction_date'])) {
                $dates[] = $charge['transaction_date'];
            }
        }

        $dates = array_values(array_unique(array_filter($dates)));
        sort($dates);

        return [
            'start_date' => $dates[0] ?? null,
            'end_date' => !empty($dates) ? $dates[count($dates) - 1] : null,
            'days' => count($dates),
            'purpose_parts' => array_values(array_unique(array_filter($purposeParts))),
        ];
    }

    private function getSessionForCharge(array $charge): ?array {
        $reference = (string) ($charge['session_reference'] ?? '');
        $params = [];
        $where = '';

        if (strpos($reference, 'session:') === 0) {
            $where = 'ts.id = ?';
            $params[] = (int) substr($reference, strlen('session:'));
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $reference)) {
            $where = 'ts.patient_id = ? AND ts.session_date = ?';
            $params[] = (int) $charge['patient_id'];
            $params[] = $reference;
        } else {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT ts.session_date, te.initial_complaints
             FROM treatment_sessions ts
             LEFT JOIN treatment_episodes te ON te.id = ts.episode_id
             WHERE $where
             ORDER BY ts.session_date ASC, ts.id ASC
             LIMIT 1"
        );
        $stmt->execute($params);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        return $session ?: null;
    }

    private function resolvePurpose(array $payment, array $treatmentSummary, array $patient): string {
        $notes = trim((string) ($payment['notes'] ?? ''));
        if ($notes !== '') {
            return $notes;
        }

        if (!empty($treatmentSummary['purpose_parts'])) {
            return implode(' - ', $treatmentSummary['purpose_parts']);
        }

        foreach (['diagnosis', 'chief_complaints', 'assessment'] as $field) {
            $value = trim((string) ($patient[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'Physiotherapy Charges';
    }

    private function splitPurpose(string $purpose): array {
        $purpose = trim(preg_replace('/\s+/', ' ', $purpose));
        if (strlen($purpose) <= 55) {
            return [$purpose, ''];
        }

        $line1 = substr($purpose, 0, 55);
        $breakAt = strrpos($line1, ' ');
        if ($breakAt !== false && $breakAt >= 25) {
            $line1 = substr($purpose, 0, $breakAt);
            $line2 = substr($purpose, $breakAt + 1);
        } else {
            $line2 = substr($purpose, 55);
        }

        return [trim($line1), trim($line2)];
    }

    private function amountToWords(float $amount): string {
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);
        $words = $this->numberToWords($rupees);

        if ($paise > 0) {
            $words .= ' and ' . $this->numberToWords($paise) . ' Paise';
        }

        return $words;
    }

    private function numberToWords(int $number): string {
        if ($number === 0) {
            return 'Zero';
        }

        $ones = [
            '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
            'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
            'Seventeen', 'Eighteen', 'Nineteen',
        ];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $levels = [
            10000000 => 'Crore',
            100000 => 'Lakh',
            1000 => 'Thousand',
            100 => 'Hundred',
        ];

        $parts = [];

        foreach ($levels as $value => $label) {
            if ($number >= $value) {
                $count = intdiv($number, $value);
                $parts[] = $this->numberToWords($count) . ' ' . $label;
                $number %= $value;
            }
        }

        if ($number >= 20) {
            $parts[] = $tens[intdiv($number, 10)];
            $number %= 10;
        }

        if ($number > 0) {
            $parts[] = $ones[$number];
        }

        return implode(' ', $parts);
    }

    private function formatReceiptAmount(float $amount): string {
        if (abs($amount - round($amount)) < 0.0001) {
            return number_format($amount, 0) . '/-';
        }

        return number_format($amount, 2);
    }
}
