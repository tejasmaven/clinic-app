<?php

class PaymentSlipTemplateController {
    private PDO $pdo;
    private string $uploadDir;
    private string $uploadRelativeDir = '_uploads/payment_slip_templates';

    private array $fieldLabels = [
        'received_from' => 'Received from',
        'amount_words' => 'Amount received in word',
        'amount_number' => 'Amount received in number',
        'purpose_line_1' => 'Purpose line 1',
        'purpose_line_2' => 'Purpose line 2',
        'treatment_start_date' => 'Treatment Period start date',
        'treatment_end_date' => 'Treatment Period end date',
        'treatment_days' => 'Treatment Period days',
    ];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . $this->uploadRelativeDir;
        $this->ensureTable();
    }

    private function ensureTable(): void {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS payment_slip_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                template_name VARCHAR(120) NOT NULL DEFAULT 'Default Payment Slip',
                image_path VARCHAR(255) DEFAULT NULL,
                original_filename VARCHAR(255) DEFAULT NULL,
                image_width INT DEFAULT NULL,
                image_height INT DEFAULT NULL,
                fields_json LONGTEXT DEFAULT NULL,
                updated_by INT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        );
    }

    public function getFieldLabels(): array {
        return $this->fieldLabels;
    }

    public function getTemplate(): array {
        $stmt = $this->pdo->query("SELECT * FROM payment_slip_templates ORDER BY id ASC LIMIT 1");
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            return [
                'id' => null,
                'template_name' => 'Default Payment Slip',
                'image_path' => null,
                'original_filename' => null,
                'image_width' => null,
                'image_height' => null,
                'fields_json' => null,
            ];
        }

        return $template;
    }

    public function getFields(array $template): array {
        $saved = [];
        $rawJson = (string) ($template['fields_json'] ?? '');

        if ($rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded)) {
                $saved = $decoded;
            }
        }

        $fields = [];
        foreach ($this->fieldLabels as $key => $label) {
            $value = is_array($saved[$key] ?? null) ? $saved[$key] : [];
            $fields[$key] = [
                'x' => $this->sanitizeNumber($value['x'] ?? 0),
                'y' => $this->sanitizeNumber($value['y'] ?? 0),
                'width' => $this->sanitizeNumber($value['width'] ?? 180, 20),
                'height' => $this->sanitizeNumber($value['height'] ?? 26, 12),
                'font_size' => $this->sanitizeNumber($value['font_size'] ?? 18, 6),
                'line_height' => $this->sanitizeNumber($value['line_height'] ?? 22, 8),
                'enabled' => !empty($value['enabled']) ? 1 : 0,
            ];
        }

        return $fields;
    }

    public function save(array $post, array $file, int $userId): string {
        $template = $this->getTemplate();
        $imageData = $this->resolveImageData($template, $file);
        $fields = $this->normalizeFields($post['fields'] ?? []);
        $fieldsJson = json_encode($fields, JSON_UNESCAPED_SLASHES);

        if ($fieldsJson === false) {
            return 'Unable to save field coordinates.';
        }

        if ($template['id']) {
            $stmt = $this->pdo->prepare(
                "UPDATE payment_slip_templates
                 SET image_path = :image_path,
                     original_filename = :original_filename,
                     image_width = :image_width,
                     image_height = :image_height,
                     fields_json = :fields_json,
                     updated_by = :updated_by
                 WHERE id = :id"
            );
            $stmt->execute([
                ':image_path' => $imageData['image_path'],
                ':original_filename' => $imageData['original_filename'],
                ':image_width' => $imageData['image_width'],
                ':image_height' => $imageData['image_height'],
                ':fields_json' => $fieldsJson,
                ':updated_by' => $userId,
                ':id' => (int) $template['id'],
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO payment_slip_templates
                    (template_name, image_path, original_filename, image_width, image_height, fields_json, updated_by)
                 VALUES
                    ('Default Payment Slip', :image_path, :original_filename, :image_width, :image_height, :fields_json, :updated_by)"
            );
            $stmt->execute([
                ':image_path' => $imageData['image_path'],
                ':original_filename' => $imageData['original_filename'],
                ':image_width' => $imageData['image_width'],
                ':image_height' => $imageData['image_height'],
                ':fields_json' => $fieldsJson,
                ':updated_by' => $userId,
            ]);
        }

        return 'Payment slip template saved successfully.';
    }

    private function resolveImageData(array $template, array $file): array {
        $imageData = [
            'image_path' => $template['image_path'] ?? null,
            'original_filename' => $template['original_filename'] ?? null,
            'image_width' => $template['image_width'] ?? null,
            'image_height' => $template['image_height'] ?? null,
        ];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            if (empty($imageData['image_path'])) {
                throw new InvalidArgumentException('Please upload a payment slip template image.');
            }

            return $imageData;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Template image upload failed. Please try again.');
        }

        $tmpName = $file['tmp_name'] ?? '';
        if (!is_uploaded_file($tmpName)) {
            throw new InvalidArgumentException('Invalid template image upload.');
        }

        $imageInfo = getimagesize($tmpName);
        if ($imageInfo === false) {
            throw new InvalidArgumentException('Template must be a valid image.');
        }

        $mime = $imageInfo['mime'] ?? '';
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowed[$mime])) {
            throw new InvalidArgumentException('Template image must be JPG, PNG, or WEBP.');
        }

        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0775, true)) {
            throw new RuntimeException('Template upload folder could not be created.');
        }

        $safeBaseName = preg_replace('/[^A-Za-z0-9_-]+/', '_', pathinfo($file['name'] ?? 'template', PATHINFO_FILENAME));
        $safeBaseName = trim((string) $safeBaseName, '_');
        if ($safeBaseName === '') {
            $safeBaseName = 'template';
        }

        $fileName = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeBaseName . '.' . $allowed[$mime];
        $targetPath = $this->uploadDir . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('Template image could not be saved. Please check upload folder permissions.');
        }

        return [
            'image_path' => $this->uploadRelativeDir . '/' . $fileName,
            'original_filename' => basename((string) ($file['name'] ?? $fileName)),
            'image_width' => (int) $imageInfo[0],
            'image_height' => (int) $imageInfo[1],
        ];
    }

    private function normalizeFields($postedFields): array {
        $postedFields = is_array($postedFields) ? $postedFields : [];
        $fields = [];

        foreach ($this->fieldLabels as $key => $label) {
            $field = is_array($postedFields[$key] ?? null) ? $postedFields[$key] : [];
            $fields[$key] = [
                'x' => $this->sanitizeNumber($field['x'] ?? 0),
                'y' => $this->sanitizeNumber($field['y'] ?? 0),
                'width' => $this->sanitizeNumber($field['width'] ?? 180, 20),
                'height' => $this->sanitizeNumber($field['height'] ?? 26, 12),
                'font_size' => $this->sanitizeNumber($field['font_size'] ?? 18, 6),
                'line_height' => $this->sanitizeNumber($field['line_height'] ?? 22, 8),
                'enabled' => isset($field['enabled']) ? 1 : 0,
            ];
        }

        return $fields;
    }

    private function sanitizeNumber($value, float $min = 0): float {
        $number = is_numeric($value) ? (float) $value : 0.0;

        return max($min, round($number, 2));
    }
}
