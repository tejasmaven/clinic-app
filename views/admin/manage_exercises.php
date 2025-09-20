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
$totalPages = (int) ceil($total / $limit);

include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Manage Exercises</h1>
                <p class="admin-page-subtitle">Maintain the master list of treatment exercises.</p>
            </div>
        </div>

        <div class="app-card">
            <?php if (!empty($msg)): ?>
                <div class="alert alert-info mb-4" role="alert"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="GET" class="row g-3 align-items-end mb-0">
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="search" class="form-label">Search exercises</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Exercise name" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <button class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>

        <div class="app-card">
            <h5 class="mb-3">Add Exercise</h5>
            <form method="POST" class="row g-3 align-items-end">
                <input type="hidden" name="action" value="add_exercise">
                <div class="col-12 col-md-6 col-xl-3">
                    <label for="exercise_name" class="form-label">Exercise name</label>
                    <input type="text" id="exercise_name" name="name" class="form-control" placeholder="e.g. Shoulder Stretch" required>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label for="default_reps" class="form-label">Default repetitions</label>
                    <input type="number" min="0" id="default_reps" name="default_reps" class="form-control" placeholder="10" required>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label for="default_duration" class="form-label">Default duration (minutes)</label>
                    <input type="number" min="0" id="default_duration" name="default_duration_minutes" class="form-control" placeholder="15" required>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label for="is_active" class="form-label">Status</label>
                    <select id="is_active" name="is_active" class="form-select" required>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 col-xl-1">
                    <button class="btn btn-success w-100">Add</button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Default Repetitions</th>
                        <th scope="col">Default Duration (min)</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exercises as $ex): ?>
                    <tr>
                        <td><?= htmlspecialchars($ex['name']) ?></td>
                        <td><?= htmlspecialchars($ex['default_reps']) ?></td>
                        <td><?= htmlspecialchars($ex['default_duration_minutes']) ?></td>
                        <td>
                            <span class="badge <?= $ex['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= $ex['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <button class="btn btn-sm btn-info" onclick="openEditModal(
                                    <?= (int) $ex['id'] ?>,
                                    <?= json_encode($ex['name']) ?>,
                                    <?= json_encode($ex['default_reps']) ?>,
                                    <?= json_encode($ex['default_duration_minutes']) ?>
                                )">Edit</button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_exercise">
                                    <input type="hidden" name="id" value="<?= (int) $ex['id'] ?>">
                                    <button class="btn btn-sm <?= $ex['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                        <?= $ex['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this exercise?')">
                                    <input type="hidden" name="action" value="delete_exercise">
                                    <input type="hidden" name="id" value="<?= (int) $ex['id'] ?>">
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav aria-label="Exercise pagination" class="d-flex justify-content-end">
            <ul class="pagination mt-3">
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

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editExerciseLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="action" value="edit_exercise">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-header">
        <h5 class="modal-title" id="editExerciseLabel">Edit Exercise</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label for="edit_name" class="form-label">Exercise name</label>
            <input type="text" name="name" id="edit_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="edit_default_reps" class="form-label">Default repetitions</label>
            <input type="number" name="default_reps" id="edit_default_reps" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="edit_default_duration_minutes" class="form-label">Default duration (minutes)</label>
            <input type="number" name="default_duration_minutes" id="edit_default_duration_minutes" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Save Changes</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(id, name, default_reps, default_duration_minutes) {
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_name').value = name;
  document.getElementById('edit_default_reps').value = default_reps;
  document.getElementById('edit_default_duration_minutes').value = default_duration_minutes;
  const modal = new bootstrap.Modal(document.getElementById('editModal'));
  modal.show();
}
</script>

<?php include '../../includes/footer.php'; ?>
