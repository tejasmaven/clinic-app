<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Admin');

require_once '../../controllers/AdminController.php';
$admin = new AdminController($pdo);

$msg = $admin->handleExercisesActions();

$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 10;

$exercises = $admin->getExercisesSources($search, $page, $limit);
$total = $admin->countExercisesSources($search);
$totalPages = ceil($total / $limit);

include '../../includes/header.php';
?>

<div class="row">
  <div class="col-md-3"><?php include '../../layouts/admin_sidebar.php'; ?></div>
  <div class="col-md-9">
    <h4>Manage Exercises</h4>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="GET" class="row g-2 mb-3">
      <div class="col-md-6">
        <input type="text" name="search" class="form-control" placeholder="Search exercises..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary">Search</button>
      </div>
    </form>

    <form method="POST" class="row g-2 mb-4">
      <input type="hidden" name="action" value="add_exercise">
      <div class="col-md-3">
        <input type="text" name="name" class="form-control" placeholder="Exercises Name" required>
      </div>
      <div class="col-md-3">
        <input type="text" name="default_reps" class="form-control" placeholder="Default Repetations" required>
      </div>
      <div class="col-md-3">
        <input type="text" name="default_duration_minutes" class="form-control" placeholder="Default Duration in Minutes" required>
      </div>
      <div class="col-md-3">
        <select name="is_active" class="form-select" required>
          <option value="1">Active</option>
          <option value="0">In Active</option>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-success w-100">Add</button>
      </div>
    </form>

    <table class="table table-bordered table-hover">
      <thead>
        <tr>
          <th>Name</th>
          <th>Default Repetations</th>
          <th>Default Duration in Minutes</th>
          <th>Active</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($exercises as $ex): ?>
        <tr>
          <td><?= htmlspecialchars($ex['name']) ?></td>
          <td><?= htmlspecialchars($ex['default_reps']) ?></td>
          <td><?= htmlspecialchars($ex['default_duration_minutes']) ?></td>
          <td><?= $ex['is_active'] ? 'Active' : 'Inactive' ?></td>
          <td>
            <button class="btn btn-sm btn-info" onclick="openEditModal(<?= $ex['id'] ?>, '<?= addslashes($ex['name']) ?>', '<?= $ex['default_reps'] ?>', '<?= $ex['default_duration_minutes'] ?>')">Edit</button>
            <form method="POST" style="display:inline-block">
                  <input type="hidden" name="action" value="toggle_exercise">
                  <input type="hidden" name="id" value="<?= $ex['id'] ?>">
                  <button class="btn btn-sm <?= $ex['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                      <?= $ex['is_active'] ? 'Deactivate' : 'Activate' ?>
                  </button>
              </form>

            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this exercises?')">
              <input type="hidden" name="action" value="delete_exercise">
              <input type="hidden" name="id" value="<?= $ex['id'] ?>">
              <button class="btn btn-sm btn-danger">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <nav><ul class="pagination">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
          <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="action" value="edit_exercise">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-header">
        <h5 class="modal-title">Edit Exercises</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="text" name="name" id="edit_name" class="form-control mb-3" required>
        <input type="text" name="default_reps" id="edit_default_reps" class="form-control mb-3" required>
        <input type="text" name="default_duration_minutes" id="edit_default_duration_minutes" class="form-control mb-3" required>

        
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(id, name, default_reps,default_duration_minutes) {
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_name').value = name;
  document.getElementById('edit_default_reps').value = default_reps;
  document.getElementById('edit_default_duration_minutes').value = default_duration_minutes;
  new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php include '../../includes/footer.php'; ?>
