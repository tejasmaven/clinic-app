<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Doctor');

$exercises = $pdo->query("SELECT name, default_reps, default_duration_minutes FROM exercises_master ORDER BY name" )->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>
<div class="row">
  <div class="col-md-3"><?php include '../../layouts/doctor_sidebar.php'; ?></div>
  <div class="col-md-9">
    <h4>Exercises</h4>
    <table class="table table-bordered table-hover">
      <thead>
        <tr>
          <th>Name</th>
          <th>Default Reps</th>
          <th>Default Duration (min)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($exercises as $ex): ?>
        <tr>
          <td><?= htmlspecialchars($ex['name']) ?></td>
          <td><?= htmlspecialchars($ex['default_reps']) ?></td>
          <td><?= htmlspecialchars($ex['default_duration_minutes']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
