<?php
require_once '../../includes/auth.php';
requireRole('Receptionist');
require_once '../../includes/db.php';

$search = $_GET['search'] ?? '';
$query = "SELECT * FROM patients WHERE CONCAT(first_name, ' ', last_name, contact_number) LIKE ? ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute(['%' . $search . '%']);
$patients = $stmt->fetchAll();
?>

<?php include '../../includes/header.php'; ?>
<div class="row">
  <div class="col-md-3"><?php include '../../layouts/receptionist_sidebar.php'; ?></div>
  <div class="col-md-9">
    <h4>Receptionist - Manage Patients</h4>
    <form method="get" class="mb-3">
      <div class="input-group">
        <input type="text" name="search" class="form-control" placeholder="Search by name or contact..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-outline-primary">Search</button>
        <a href="patient_form.php" class="btn btn-success ms-2">+ Add Patient</a>
      </div>
    </form>

    <table class="table table-bordered">
      <thead><tr><th>Name</th><th>Gender</th><th>Contact</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($patients as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
            <td><?= htmlspecialchars($p['gender']) ?></td>
            <td><?= htmlspecialchars($p['contact_number']) ?></td>
            <td>
              <a href="patient_form.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
              <a href="../shared/manage_payments.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary">Payments</a>
              <a href="../shared/manage_patients_files.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-warning">View Files</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
