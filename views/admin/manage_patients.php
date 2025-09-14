<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Admin');

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

<div class="row">
  <div class="col-md-3"><?php include '../../layouts/admin_sidebar.php'; ?></div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4>Patient Onboarding</h4>
      <a href="patient_form.php" class="btn btn-success">+ Add New Patient</a>
    </div>

    <form method="GET" class="mb-3 row g-2">
      <div class="col-md-6">
        <input type="text" name="search" class="form-control" placeholder="Search by name..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary">Search</button>
      </div>
    </form>

    <table class="table table-bordered table-hover">
      <thead>
        <tr>
          <th>Name</th>
          <th>Gender</th>
          <th>DOB</th>
          <th>Contact</th>
          <th>Referral</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($patients as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
          <td><?= htmlspecialchars($p['gender']) ?></td>
          <td><?= htmlspecialchars($p['date_of_birth']) ?></td>
          <td><?= htmlspecialchars($p['contact_number']) ?></td>
          <td><?= htmlspecialchars($p['referral_source']) ?></td>
          <td>
            <a href="patient_form.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-info">Edit</a>
            <a href="../shared/manage_patients_files.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-warning">View Files</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <nav>
      <ul class="pagination">
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
