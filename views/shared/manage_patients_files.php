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
<div class="workspace-layout">
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
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">Patient Files</h1>
        <p class="workspace-page-subtitle">Review uploaded documents for <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>.</p>
      </div>
      <div class="d-flex gap-2">
        <a href="javascript:history.back()" class="btn btn-outline-secondary">Back</a>
      </div>
    </div>

    <div class="app-card">
      <div class="row g-3">
        <div class="col-12 col-md-4">
          <strong>Gender:</strong> <?= htmlspecialchars($patient['gender']) ?>
        </div>
        <div class="col-12 col-md-4">
          <strong>DOB:</strong> <?= htmlspecialchars($patient['date_of_birth']) ?>
        </div>
        <div class="col-12 col-md-4">
          <strong>Contact:</strong> <?= htmlspecialchars($patient['contact_number']) ?>
        </div>
      </div>
    </div>

    <div class="app-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col">File Name</th>
              <th scope="col">Uploaded On</th>
              <th scope="col">Download</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($files)): ?>
              <?php foreach ($files as $file): ?>
                <tr>
                  <td><?= htmlspecialchars($file['file_name']) ?></td>
                  <td><?= date('d M Y', strtotime($file['upload_date'])) ?></td>
                  <td>
                    <a href="<?= BASE_URL ?>/views/shared/download_file.php?patient_id=<?= $patientId ?>&file=<?= urlencode($file['file_name']) ?>" class="btn btn-sm btn-outline-primary">Download</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="3" class="text-center text-muted py-4">No files uploaded for this patient.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
