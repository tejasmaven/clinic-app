<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Admin');

require_once '../../controllers/AdminController.php';
$admin = new AdminController($pdo);

$msg = $admin->handleUserActions();

$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 10;

$users = $admin->getUsers($search, $page, $limit);
$total = $admin->countUsers($search);
$totalPages = ceil($total / $limit);

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-3"><?php include '../../layouts/admin_sidebar.php'; ?></div>
    <div class="col-md-9">
        <h4>Manage Users</h4>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link <?= !isset($_GET['show_deleted']) ? 'active' : '' ?>" href="?<?= $search ? 'search='.urlencode($search) : '' ?>">Active Users</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= isset($_GET['show_deleted']) ? 'active' : '' ?>" href="?show_deleted=1<?= $search ? '&search='.urlencode($search) : '' ?>">Deleted Users</a>
            </li>
        </ul>

        <!-- Show messages -->
        <?php if ($msg): ?>
            <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="GET" class="row g-2 mb-3">
            <input type="hidden" name="<?= isset($_GET['show_deleted']) ? 'show_deleted' : '' ?>" value="1">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary">Search</button>
            </div>
        </form>

        <?php if (!isset($_GET['show_deleted'])): ?>
        <form method="POST" class="row g-2 mb-4">
            <input type="hidden" name="action" value="add_user">
            <div class="col-md-3"><input type="text" name="name" class="form-control" placeholder="Name" required></div>
            <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
            <div class="col-md-2">
                <select name="role" class="form-control" required>
                    <option value="Doctor">Doctor</option>
                    <option value="Receptionist">Receptionist</option>
                </select>
            </div>
            <div class="col-md-2"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
            <div class="col-md-2"><button class="btn btn-success">Add</button></div>
        </form>
        <?php endif; ?>

        <table class="table table-bordered">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['role']) ?></td>
                    <td><?= $u['is_active'] ? 'Active' : 'Inactive' ?></td>
                    <td>
                        <?php if (!isset($_GET['show_deleted'])): ?>
                        <button class="btn btn-sm btn-info" onclick="openEditModal(
                          <?= $u['id'] ?>, '<?= addslashes($u['name']) ?>', '<?= addslashes($u['email']) ?>', '<?= $u['role'] ?>'
                        )">Edit</button>

                        <form method="POST" style="display:inline-block">
                            <input type="hidden" name="action" value="toggle_user_status">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm <?= $u['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>

                        <form method="POST" onsubmit="return confirm('Are you sure?')" style="display:inline-block">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" style="display:inline-block">
                            <input type="hidden" name="action" value="restore_user">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-secondary">Restore</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <nav><ul class="pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= isset($_GET['show_deleted']) ? 'show_deleted=1&' : '' ?>search=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-header"><h5>Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="text" name="name" id="edit_name" class="form-control mb-2" required>
        <input type="email" name="email" id="edit_email" class="form-control mb-2" required>
        <select name="role" id="edit_role" class="form-control mb-2" required>
          <option value="Doctor">Doctor</option>
          <option value="Receptionist">Receptionist</option>
        </select>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(id, name, email, role) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>

<?php include '../../includes/footer.php'; ?>
