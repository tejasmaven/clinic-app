<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Doctor');

require_once '../../controllers/PatientController.php';
$controller = new PatientController($pdo);

// Pagination + Search Setup
$search = $_GET['search'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;

$patients = $controller->getPatients($search, $page, $limit);
$total = $controller->countPatients($search);
$totalPages = ceil($total / $limit);

include '../../includes/header.php';
?>
<div class="workspace-layout">
  <?php include '../../layouts/doctor_sidebar.php'; ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">Patient Onboarding</h1>
        <p class="workspace-page-subtitle">Search, admit, and update patient information.</p>
      </div>
      <div>
        <a href="patient_form.php" class="btn btn-success">
          <span class="d-none d-sm-inline">Add New Patient</span>
          <span class="d-sm-none">New Patient</span>
        </a>
      </div>
    </div>

    <div class="app-card">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-md-6 col-lg-5">
          <label for="patientSearch" class="form-label">Search patients</label>
          <input id="patientSearch" type="text" name="search" class="form-control" placeholder="Search by name or contact" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
          <button class="btn btn-primary w-100">Search</button>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
          <a href="manage_patients.php" class="btn btn-outline-secondary w-100">Reset</a>
        </div>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Name</th>
            <th scope="col">Gender</th>
            <th scope="col">DOB</th>
            <th scope="col">Contact</th>
            <th scope="col">Referral</th>
            <th scope="col">Files</th>
            <th scope="col" class="text-nowrap">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($patients)): ?>
            <?php foreach ($patients as $p): ?>
              <tr>
                <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                <td><?= htmlspecialchars($p['gender']) ?></td>
                <td><?= htmlspecialchars(format_display_date($p['date_of_birth'])) ?></td>
                <td><?= htmlspecialchars($p['contact_number']) ?></td>
                <td><?= htmlspecialchars($p['referral_source']) ?></td>
                <td>
                  <a href="../shared/manage_patients_files.php?patient_id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-warning">Files</a>
                </td>
                <td class="text-nowrap">
                  <div class="d-flex flex-wrap gap-1">
                    <a href="select_or_create_episode.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">Start Treatment</a>
                    <a href="patient_form.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                    <a href="../shared/manage_payments.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">Payments</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-4">No patients found for the current search.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav aria-label="Patient pagination">
        <ul class="pagination mt-3 justify-content-center justify-content-md-start">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
              <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
