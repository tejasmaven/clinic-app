<?php
require_once '../../includes/auth.php';
requireRole('Receptionist');
require_once '../../includes/db.php';

$search = $_GET['search'] ?? '';
$query = "SELECT * FROM patients WHERE CONCAT(first_name, ' ', last_name, contact_number) LIKE ? ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute(['%' . $search . '%']);
$patients = $stmt->fetchAll();

include '../../includes/header.php';
?>
<div class="workspace-layout">
  <?php include '../../layouts/receptionist_sidebar.php'; ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">Manage Patients</h1>
        <p class="workspace-page-subtitle">Assist with onboarding and maintain accurate patient records.</p>
      </div>
      <div>
        <a href="patient_form.php" class="btn btn-success">
          <span class="d-none d-sm-inline">Add Patient</span>
          <span class="d-sm-none">Add</span>
        </a>
      </div>
    </div>

    <div class="app-card">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-6 col-lg-5">
          <label for="patientSearch" class="form-label">Search patients</label>
          <input type="text" id="patientSearch" name="search" class="form-control" placeholder="Search by name or contact" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
          <button type="submit" class="btn btn-primary w-100">Search</button>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
          <a href="manage_patients.php" class="btn btn-outline-secondary w-100">Reset</a>
        </div>
      </form>
    </div>

    <div class="app-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Gender</th>
              <th scope="col">Contact</th>
              <th scope="col" class="text-nowrap">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($patients)): ?>
              <?php foreach ($patients as $p): ?>
                <tr>
                  <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                  <td><?= htmlspecialchars($p['gender']) ?></td>
                  <td><?= htmlspecialchars($p['contact_number']) ?></td>
                  <td class="text-nowrap">
                    <div class="d-flex flex-wrap gap-1">
                      <a href="patient_form.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                      <a href="../shared/manage_payments.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">Payments</a>
                      <a href="../shared/manage_patients_files.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-warning">Files</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" class="text-center text-muted py-4">No patients found for the current search.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
