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
<div class="workspace-layout">
  <?php include '../../layouts/doctor_sidebar.php'; ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">Active Treatment Episodes</h1>
        <p class="workspace-page-subtitle">Patients currently receiving care under your supervision.</p>
      </div>
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
            <th scope="col" class="text-nowrap">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($patients)): ?>
            <?php foreach ($patients as $p): ?>
              <tr>
                <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                <td><?= htmlspecialchars($p['gender']) ?></td>
                <td><?= htmlspecialchars($p['date_of_birth']) ?></td>
                <td><?= htmlspecialchars($p['contact_number']) ?></td>
                <td><?= htmlspecialchars($p['referral_source']) ?></td>
                <td>
                  <a href="select_or_create_episode.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">Open Episode</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-4">There are no active treatment episodes right now.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
