<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Doctor');

// Fetch active patients
$sql = "SELECT DISTINCT p.* FROM patients p
        JOIN treatment_episodes te ON te.patient_id = p.id
        WHERE te.status = 'Active'
        ORDER BY p.id DESC";
$patients = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>
<div class="row">
  <div class="col-md-3"><?php include '../../layouts/doctor_sidebar.php'; ?></div>
  <div class="col-md-9">
    <h4>Active Patients</h4>
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
            <a href="select_or_create_episode.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-info">View</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
