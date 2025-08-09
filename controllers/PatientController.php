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

    if (!empty($errors)) {
        return implode("<br>", $errors);
    }

    $sqlFields = [
        'first_name', 'last_name', 'date_of_birth', 'gender', 'contact_number',
        'email', 'address', 'emergency_contact_name', 'emergency_contact_number',
        'referral_source', 'medical_history', 'allergies'
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
}
