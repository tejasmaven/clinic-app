<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Doctor');

$exercises = $pdo->query("SELECT name, default_reps, default_duration_minutes FROM exercises_master ORDER BY name" )->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>
<div class="workspace-layout">
  <?php include '../../layouts/doctor_sidebar.php'; ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">Exercise Library</h1>
        <p class="workspace-page-subtitle">Reference the standard exercise programs available to prescribe.</p>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Name</th>
            <th scope="col">Default Reps</th>
            <th scope="col">Default Duration (min)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($exercises)): ?>
            <?php foreach ($exercises as $ex): ?>
              <tr>
                <td><?= htmlspecialchars($ex['name']) ?></td>
                <td><?= htmlspecialchars($ex['default_reps']) ?></td>
                <td><?= htmlspecialchars($ex['default_duration_minutes']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="3" class="text-center text-muted py-4">No exercises configured in the master list yet.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
