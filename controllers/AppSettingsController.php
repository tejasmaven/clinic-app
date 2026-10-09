<?php

class AppSettingsController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureSettingsTable();
    }

    private function ensureSettingsTable(): void {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS app_settings (
                setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
                setting_value TEXT NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        );
    }

    public function getSiteName(): string {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'site_name' LIMIT 1");
        $stmt->execute();
        $siteName = trim((string) $stmt->fetchColumn());

        return $siteName !== '' ? $siteName : 'Hiral Physiotherapy Clinic';
    }

    public function updateSiteName(string $siteName): string {
        $siteName = trim($siteName);

        if ($siteName === '') {
            return 'Site name is required.';
        }

        if (strlen($siteName) > 120) {
            return 'Site name must be 120 characters or fewer.';
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO app_settings (setting_key, setting_value)
             VALUES ('site_name', ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $stmt->execute([$siteName]);

        return 'Site name updated successfully.';
    }

    public function updateLogo(array $file): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'Please select a JPG logo to upload.';
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return 'Logo upload failed. Please try again.';
        }

        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($extension !== 'jpg') {
            return 'Logo must be uploaded as a .jpg file.';
        }

        $tmpName = $file['tmp_name'] ?? '';
        if (!is_uploaded_file($tmpName)) {
            return 'Invalid logo upload.';
        }

        $imageInfo = getimagesize($tmpName);
        if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== 'image/jpeg') {
            return 'Logo must be a valid JPG image.';
        }

        $targetPath = __DIR__ . '/../assets/logo.jpg';
        if (!move_uploaded_file($tmpName, $targetPath)) {
            return 'Logo could not be saved. Please check file permissions for the assets folder.';
        }

        return 'Logo updated successfully.';
    }
}
