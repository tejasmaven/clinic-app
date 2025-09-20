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
$totalPages = (int) ceil($total / $limit);

include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Manage Users</h1>
                <p class="admin-page-subtitle">Create, update, and deactivate clinic user accounts.</p>
            </div>
        </div>

        <div class="app-card">
            <ul class="nav nav-pills flex-wrap gap-2 mb-3">
                <li class="nav-item">
                    <a class="nav-link <?= !isset($_GET['show_deleted']) ? 'active' : '' ?>" href="?<?= $search ? 'search=' . urlencode($search) : '' ?>">Active Users</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= isset($_GET['show_deleted']) ? 'active' : '' ?>" href="?show_deleted=1<?= $search ? '&search=' . urlencode($search) : '' ?>">Deleted Users</a>
                </li>
            </ul>

            <?php if ($msg): ?>
                <div class="alert alert-info mb-4" role="alert"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="GET" class="row g-3 align-items-end">
                <?php if (isset($_GET['show_deleted'])): ?>
                    <input type="hidden" name="show_deleted" value="1">
                <?php endif; ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="search" class="form-label">Search users</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Name or email" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <button class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>

        <?php if (!isset($_GET['show_deleted'])): ?>
        <div class="app-card">
            <h5 class="mb-3">Add New User</h5>
            <form method="POST" class="row g-3 align-items-end">
                <input type="hidden" name="action" value="add_user">
                <div class="col-12 col-md-6 col-xl-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" id="name" name="name" class="form-control" placeholder="Full name" required>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="user@example.com" required>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label for="role" class="form-label">Role</label>
                    <select id="role" name="role" class="form-select" required>
                        <option value="Doctor">Doctor</option>
                        <option value="Receptionist">Receptionist</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="col-12 col-md-4 col-xl-2">
                    <button class="btn btn-success w-100">Add User</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['role']) ?></td>
                        <td>
                            <span class="badge <?= $u['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <?php if (!isset($_GET['show_deleted'])): ?>
                                <button class="btn btn-sm btn-info" onclick="openEditModal(
                                    <?= (int) $u['id'] ?>,
                                    <?= json_encode($u['name']) ?>,
                                    <?= json_encode($u['email']) ?>,
                                    <?= json_encode($u['role']) ?>
                                )">Edit</button>

                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_user_status">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button class="btn btn-sm <?= $u['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                        <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>

                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="restore_user">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button class="btn btn-sm btn-secondary">Restore</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav aria-label="User pagination" class="d-flex justify-content-end">
            <ul class="pagination mt-3">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= isset($_GET['show_deleted']) ? 'show_deleted=1&' : '' ?>search=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-header">
        <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="edit_name" class="form-label">Name</label>
          <input type="text" name="name" id="edit_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="edit_email" class="form-label">Email</label>
          <input type="email" name="email" id="edit_email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="edit_role" class="form-label">Role</label>
          <select name="role" id="edit_role" class="form-select" required>
            <option value="Doctor">Doctor</option>
            <option value="Receptionist">Receptionist</option>
          </select>
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
function openEditModal(id, name, email, role) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}
</script>

<?php include '../../includes/footer.php'; ?>
