<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Admin');

require_once '../../controllers/AdminController.php';
$admin = new AdminController($pdo);

$msg = $admin->handleReferralActions();

$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 10;

$referrals = $admin->getReferralSources($search, $page, $limit);
$total = $admin->countReferralSources($search);
$totalPages = (int) ceil($total / $limit);

include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Manage Referral Sources</h1>
                <p class="admin-page-subtitle">Track and update referral partners for the clinic.</p>
            </div>
        </div>

        <div class="app-card">
            <?php if (!empty($msg)): ?>
                <div class="alert alert-info mb-4" role="alert"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="GET" class="row g-3 align-items-end mb-0">
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="search" class="form-label">Search referrals</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Referral name" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <button class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>

        <div class="app-card">
            <h5 class="mb-3">Add Referral Source</h5>
            <form method="POST" class="row g-3 align-items-end">
                <input type="hidden" name="action" value="add_referral">
                <div class="col-12 col-md-6 col-xl-4">
                    <label for="referral_name" class="form-label">Name</label>
                    <input type="text" id="referral_name" name="name" class="form-control" placeholder="Referral name" required>
                </div>
                <div class="col-12 col-md-4 col-xl-3">
                    <label for="referral_type" class="form-label">Type</label>
                    <select id="referral_type" name="type" class="form-select" required>
                        <option value="Doctor">Doctor</option>
                        <option value="Hospital">Hospital</option>
                        <option value="Person">Person</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 col-xl-2">
                    <button class="btn btn-success w-100">Add</button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Type</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($referrals as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td><?= htmlspecialchars($r['type']) ?></td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <button class="btn btn-sm btn-info" onclick="openEditModal(
                                    <?= (int) $r['id'] ?>,
                                    <?= json_encode($r['name']) ?>,
                                    <?= json_encode($r['type']) ?>
                                )">Edit</button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this referral?')">
                                    <input type="hidden" name="action" value="delete_referral">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
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
        <nav aria-label="Referral pagination" class="d-flex justify-content-end">
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
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editReferralLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="action" value="edit_referral">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-header">
        <h5 class="modal-title" id="editReferralLabel">Edit Referral</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label for="edit_name" class="form-label">Name</label>
            <input type="text" name="name" id="edit_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="edit_type" class="form-label">Type</label>
            <select name="type" id="edit_type" class="form-select" required>
                <option value="Doctor">Doctor</option>
                <option value="Hospital">Hospital</option>
                <option value="Person">Person</option>
                <option value="Other">Other</option>
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
function openEditModal(id, name, type) {
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_name').value = name;
  document.getElementById('edit_type').value = type;
  const modal = new bootstrap.Modal(document.getElementById('editModal'));
  modal.show();
}
</script>

<?php include '../../includes/footer.php'; ?>
