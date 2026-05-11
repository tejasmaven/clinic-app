<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Doctor');

$machines = $pdo->query("SELECT name, default_duration_minutes FROM machines ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>
<div class="workspace-layout">
  <?php include '../../layouts/doctor_sidebar.php'; ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">Machine Library</h1>
        <p class="workspace-page-subtitle">Reference the standard therapy machines available to use in treatment sessions.</p>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Name</th>
            <th scope="col">Default Duration (min)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($machines)): ?>
            <?php foreach ($machines as $machine): ?>
              <tr>
                <td><?= htmlspecialchars($machine['name']) ?></td>
                <td><?= htmlspecialchars((string) $machine['default_duration_minutes']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="2" class="text-center text-muted py-4">No machines configured in the master list yet.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
