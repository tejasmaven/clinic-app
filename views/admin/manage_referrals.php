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
$totalPages = ceil($total / $limit);

include '../../includes/header.php';
?>

<div class="row">
  <div class="col-md-3"><?php include '../../layouts/admin_sidebar.php'; ?></div>
  <div class="col-md-9">
    <h4>Manage Referral Sources</h4>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="GET" class="row g-2 mb-3">
      <div class="col-md-6">
        <input type="text" name="search" class="form-control" placeholder="Search referrals..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary">Search</button>
      </div>
    </form>

    <form method="POST" class="row g-2 mb-4">
      <input type="hidden" name="action" value="add_referral">
      <div class="col-md-4">
        <input type="text" name="name" class="form-control" placeholder="Referral Name" required>
      </div>
      <div class="col-md-3">
        <select name="type" class="form-select" required>
          <option value="Doctor">Doctor</option>
          <option value="Hospital">Hospital</option>
          <option value="Person">Person</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-success w-100">Add</button>
      </div>
    </form>

    <table class="table table-bordered table-hover">
      <thead>
        <tr><th>Name</th><th>Type</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($referrals as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td><?= htmlspecialchars($r['type']) ?></td>
          <td>
            <button class="btn btn-sm btn-info" onclick="openEditModal(<?= $r['id'] ?>, '<?= addslashes($r['name']) ?>', '<?= $r['type'] ?>')">Edit</button>
            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this referral?')">
              <input type="hidden" name="action" value="delete_referral">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
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
      <input type="hidden" name="action" value="edit_referral">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-header">
        <h5 class="modal-title">Edit Referral</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="text" name="name" id="edit_name" class="form-control mb-3" required>
        <select name="type" id="edit_type" class="form-select" required>
          <option value="Doctor">Doctor</option>
          <option value="Hospital">Hospital</option>
          <option value="Person">Person</option>
          <option value="Other">Other</option>
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
function openEditModal(id, name, type) {
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_name').value = name;
  document.getElementById('edit_type').value = type;
  new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php include '../../includes/footer.php'; ?>
