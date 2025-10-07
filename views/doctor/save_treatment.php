<?php
require_once '../config/db.php';
require_once 'TreatmentController.php';
require_once '../includes/auth.php';
requireRole('Doctor');

$treatmentController = new TreatmentController($pdo);

// Validate essential fields
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['patient_id'], $_POST['episode_id'], $_POST['session_date'], $_POST['doctor_id'])
) {
    $data = [
        'patient_id' => $_POST['patient_id'],
        'episode_id' => $_POST['episode_id'],
        'session_date' => $_POST['session_date'],
        'doctor_id' => $_POST['doctor_id'],
        'remarks' => $_POST['remarks'] ?? '',
        'progress_notes' => $_POST['progress_notes'] ?? '',
        'exercises' => $_POST['exercises'] ?? []
    ];

    $result = $treatmentController->saveSession($data);

    if (is_array($result) && !empty($result['success'])) {
        header("Location: ../views/doctor/doctor_dashboard.php?msg=Treatment+saved");
        exit;
    } else {
        $errorMessage = is_array($result) && isset($result['error'])
            ? $result['error']
            : (is_string($result) ? $result : 'Unable to save the treatment session.');
        echo "Error saving treatment: " . htmlspecialchars($errorMessage);
    }
} else {
    echo "Invalid access or missing fields.";
}
?>
