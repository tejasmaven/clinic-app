<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
if (!in_array($_SESSION['role'] ?? '', ['Admin','Doctor','Receptionist'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../../controllers/PatientController.php';
$controller = new PatientController($pdo);

$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
if ($patientId <= 0) {
    exit('Invalid patient ID.');
}

$patient = $controller->getPatientById($patientId);
if (!$patient) {
    exit('Patient not found.');
}
$files = $controller->getPatientFiles($patientId);

include '../../includes/header.php';
?>

<div class="row">
  <div class="col-md-3">
    <?php
      switch ($_SESSION['role']) {
        case 'Doctor':
          include '../../layouts/doctor_sidebar.php';
          break;
        case 'Receptionist':
          include '../../layouts/receptionist_sidebar.php';
          break;
        default:
          include '../../layouts/admin_sidebar.php';
      }
    ?>
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4>Files for <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></h4>
      <a href="javascript:history.back()" class="btn btn-secondary">Back</a>
    </div>
    <p>
      <strong>Gender:</strong> <?= htmlspecialchars($patient['gender']) ?> |
      <strong>DOB:</strong> <?= htmlspecialchars($patient['date_of_birth']) ?> |
      <strong>Contact:</strong> <?= htmlspecialchars($patient['contact_number']) ?>
    </p>

    <?php if (!empty($files)): ?>
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>File Name</th>
          <th>Uploaded On</th>
          <th>Download</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($files as $file): ?>
        <tr>
          <td><?= htmlspecialchars($file['file_name']) ?></td>
          <td><?= date('d M Y', strtotime($file['upload_date'])) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/views/shared/download_file.php?patient_id=<?= $patientId ?>&file=<?= urlencode($file['file_name']) ?>" class="btn btn-sm btn-primary">Download</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p>No files uploaded for this patient.</p>
    <?php endif; ?>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
