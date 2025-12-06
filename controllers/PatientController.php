<?php
class PatientController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Add or update patient record
    public function saveOrUpdatePatient($data) {
    $errors = [];

    // Server-side validation
    if (empty($data['first_name'])) $errors[] = "First Name is required.";
    if (empty($data['last_name'])) $errors[] = "Last Name is required.";
    if (empty($data['date_of_birth'])) $errors[] = "Date of Birth is required.";
    if (empty($data['gender'])) $errors[] = "Gender is required.";
    if (empty($data['contact_number']) || !preg_match('/^[0-9]{10}$/', $data['contact_number'])) {
        $errors[] = "Valid Contact Number is required (10 digits).";
    }
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid Email address.";
    }
    if (empty($data['emergency_contact_name'])) $errors[] = "Emergency Contact Name is required.";
    if (empty($data['emergency_contact_number']) || !preg_match('/^[0-9]{10}$/', $data['emergency_contact_number'])) {
        $errors[] = "Valid Emergency Contact Number is required (10 digits).";
    }

    if (!empty($_FILES['reports']['name'][0]) && count($_FILES['reports']['name']) > 5) {
        $errors[] = "You can upload a maximum of 5 files.";
    }

    if (!empty($errors)) {
        return implode("<br>", $errors);
    }

    $sqlFields = [
        'first_name', 'last_name', 'date_of_birth', 'gender', 'contact_number',
        'email', 'address', 'emergency_contact_name', 'emergency_contact_number',
        'referral_source',
        'allergy_medicines_in_use', 'family_history', 'history', 'chief_complaints',
        'assessment', 'investigation', 'diagnosis', 'goal'
    ];

    $placeholders = implode(", ", array_map(function($f) {
        return "`$f` = :$f";
    }, $sqlFields));


    if (!empty($data['id'])) {
        $sql = "UPDATE patients SET $placeholders, updated_at = NOW() WHERE id = :id";
    } else {
        $sql = "INSERT INTO patients SET $placeholders, created_at = NOW(), updated_at = NOW()";
    }

    $stmt = $this->pdo->prepare($sql);

    foreach ($sqlFields as $field) {
        $stmt->bindValue(":$field", $data[$field] ?? null);
    }

    if (!empty($data['id'])) {
        $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);
    }

    try {
        $stmt->execute();
        $patientId = !empty($data['id']) ? $data['id'] : $this->pdo->lastInsertId();

        if (!empty($_FILES['reports']['name'][0])) {
            $uploadDir = dirname(__DIR__) . '/uploads/patient_docs/' . $patientId . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $count = count($_FILES['reports']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['reports']['error'][$i] === UPLOAD_ERR_OK) {
                    $original = $_FILES['reports']['name'][$i];
                    $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '_', $original);
                    move_uploaded_file($_FILES['reports']['tmp_name'][$i], $uploadDir . $safeName);
                    $stmtFile = $this->pdo->prepare("INSERT INTO file_master (patient_id, file_name, upload_date) VALUES (:pid, :fname, NOW())");
                    $stmtFile->execute([':pid' => $patientId, ':fname' => $safeName]);
                }
            }
        }

        return !empty($data['id']) ? "Patient updated successfully." : "Patient added successfully.";
    } catch (PDOException $e) {
        return "Error: " . $e->getMessage();
    }
}


    public function getPatientById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getReferralSources() {
        $stmt = $this->pdo->query("SELECT id, name FROM referral_sources ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPatientFiles($patientId) {
        $stmt = $this->pdo->prepare("SELECT file_id, file_name, upload_date FROM file_master WHERE patient_id = ? ORDER BY upload_date DESC");
        $stmt->execute([$patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPatients($search = '', $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM patients WHERE CONCAT(first_name, ' ', last_name) LIKE ? ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(["%$search%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPatients($search = '') {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM patients WHERE CONCAT(first_name, ' ', last_name) LIKE ?");
        $stmt->execute(["%$search%"]);
        return $stmt->fetchColumn();
    }

    public function deletePatient($patientId) {
        $patientStmt = $this->pdo->prepare("SELECT id, first_name, last_name FROM patients WHERE id = ?");
        $patientStmt->execute([$patientId]);
        $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient) {
            return ['success' => false, 'message' => 'Patient not found.'];
        }

        try {
            $this->pdo->beginTransaction();

            // Fetch files before deletion for cleanup
            $filesStmt = $this->pdo->prepare("SELECT file_name FROM file_master WHERE patient_id = ?");
            $filesStmt->execute([$patientId]);
            $files = $filesStmt->fetchAll(PDO::FETCH_COLUMN);

            // Delete financial records
            $this->pdo->prepare("DELETE FROM patient_payment_ledger WHERE patient_id = ?")
                ->execute([$patientId]);
            $this->pdo->prepare("DELETE FROM patient_credit_balances WHERE patient_id = ?")
                ->execute([$patientId]);

            // Gather treatment episodes and sessions
            $episodeStmt = $this->pdo->prepare("SELECT id FROM treatment_episodes WHERE patient_id = ?");
            $episodeStmt->execute([$patientId]);
            $episodeIds = $episodeStmt->fetchAll(PDO::FETCH_COLUMN);

            $sessionQuery = "SELECT id FROM treatment_sessions WHERE patient_id = ?";
            $params = [$patientId];
            if (!empty($episodeIds)) {
                $placeholders = implode(', ', array_fill(0, count($episodeIds), '?'));
                $sessionQuery .= " OR episode_id IN ($placeholders)";
                $params = array_merge($params, $episodeIds);
            }

            $sessionStmt = $this->pdo->prepare($sessionQuery);
            $sessionStmt->execute($params);
            $sessionIds = $sessionStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($sessionIds)) {
                $sessionPlaceholders = implode(', ', array_fill(0, count($sessionIds), '?'));
                $this->pdo->prepare("DELETE FROM treatment_exercises WHERE session_id IN ($sessionPlaceholders)")
                    ->execute($sessionIds);
                $this->pdo->prepare("DELETE FROM treatment_machines WHERE session_id IN ($sessionPlaceholders)")
                    ->execute($sessionIds);

                $this->pdo->prepare("DELETE FROM treatment_sessions WHERE id IN ($sessionPlaceholders)")
                    ->execute($sessionIds);
            } else {
                // Ensure sessions tied by patient are cleared even if no IDs were found above
                $this->pdo->prepare("DELETE FROM treatment_sessions WHERE patient_id = ?")
                    ->execute([$patientId]);
            }

            if (!empty($episodeIds)) {
                $episodePlaceholders = implode(', ', array_fill(0, count($episodeIds), '?'));
                $this->pdo->prepare("DELETE FROM treatment_episodes WHERE id IN ($episodePlaceholders)")
                    ->execute($episodeIds);
            }

            // Delete uploaded file records
            $this->pdo->prepare("DELETE FROM file_master WHERE patient_id = ?")
                ->execute([$patientId]);

            // Delete patient record
            $this->pdo->prepare("DELETE FROM patients WHERE id = ?")
                ->execute([$patientId]);

            $this->pdo->commit();

            // Remove files from the filesystem after DB commit
            if (!empty($files)) {
                $uploadDir = dirname(__DIR__) . '/uploads/patient_docs/' . $patientId . '/';
                foreach ($files as $fileName) {
                    $filePath = $uploadDir . $fileName;
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    }
                }

                if (is_dir($uploadDir)) {
                    @rmdir($uploadDir);
                }
            }

            return [
                'success' => true,
                'message' => 'Patient "' . $patient['first_name'] . ' ' . $patient['last_name'] . '" deleted successfully.'
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'message' => 'Error deleting patient: ' . $e->getMessage()
            ];
        }
    }
}
