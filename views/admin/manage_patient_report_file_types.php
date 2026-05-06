<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Admin');

require_once '../../controllers/AdminController.php';
$admin = new AdminController($pdo);

$msg = $admin->handlePatientReportFileTypeActions();

$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$limit = 10;

$fileTypes = $admin->getPatientReportFileTypes($search, $page, $limit);
$total = $admin->countPatientReportFileTypes($search);
$totalPages = (int) ceil($total / $limit);

include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Manage Patient Report File Types</h1>
                <p class="admin-page-subtitle">Maintain the master list of patient report file types.</p>
            </div>
        </div>

        <div class="app-card">
            <?php if (!empty($msg)): ?>
                <div class="alert alert-info mb-4" role="alert"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="GET" class="row g-3 align-items-end mb-0">
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="search" class="form-label">Search file types</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="File type" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <button class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>

        <div class="app-card">
            <h5 class="mb-3">Add File Type</h5>
            <form method="POST" class="row g-3 align-items-end">
                <input type="hidden" name="action" value="add_patient_report_file_type">
                <div class="col-12 col-md-6 col-xl-4">
                    <label for="file_type_name" class="form-label">File Type</label>
                    <input type="text" id="file_type_name" name="name" class="form-control" placeholder="e.g. MRI" required>
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
                        <th scope="col">ID</th>
                        <th scope="col">File Type</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fileTypes as $fileType): ?>
                    <tr>
                        <td><?= (int) $fileType['id'] ?></td>
                        <td><?= htmlspecialchars($fileType['name']) ?></td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-info"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editFileTypeModal"
                                    data-id="<?= (int) $fileType['id'] ?>"
                                    data-name="<?= htmlspecialchars($fileType['name'], ENT_QUOTES, 'UTF-8') ?>"
                                >Edit</button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this file type?');">
                                    <input type="hidden" name="action" value="delete_patient_report_file_type">
                                    <input type="hidden" name="id" value="<?= (int) $fileType['id'] ?>">
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($fileTypes)): ?>
                    <tr>
                        <td colspan="3" class="text-center text-muted">No file types found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav aria-label="Patient report file type pagination" class="d-flex justify-content-end">
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

<!-- Edit File Type Modal -->
<div class="modal fade" id="editFileTypeModal" tabindex="-1" aria-labelledby="editFileTypeLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="action" value="edit_patient_report_file_type">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-header">
        <h5 class="modal-title" id="editFileTypeLabel">Edit File Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label for="edit_name" class="form-label">File Type</label>
            <input type="text" name="name" id="edit_name" class="form-control" required>
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
document.addEventListener('DOMContentLoaded', function () {
    var editFileTypeModal = document.getElementById('editFileTypeModal');
    if (!editFileTypeModal) {
        return;
    }

    editFileTypeModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        if (!button) {
            return;
        }

        document.getElementById('edit_id').value = button.getAttribute('data-id') || '';
        document.getElementById('edit_name').value = button.getAttribute('data-name') || '';
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
