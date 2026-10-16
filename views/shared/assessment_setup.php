<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['Admin', 'Super Admin', 'Doctor'], true)) {
    header('Location: ../login.php');
    exit;
}

require_once '../../controllers/AssessmentController.php';
$controller = new AssessmentController($pdo);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $msg = $controller->handleSetupActions($_POST);
    } catch (Throwable $e) {
        $msg = 'Unable to update assessment setup: ' . $e->getMessage();
    }
}

$sections = $controller->getSectionsWithItems(false);
$isAdmin = in_array($_SESSION['role'] ?? '', ['Admin', 'Super Admin'], true);

include '../../includes/header.php';
?>

<div class="<?= $isAdmin ? 'admin-layout' : 'workspace-layout' ?>">
    <?php include $isAdmin ? '../../layouts/admin_sidebar.php' : '../../layouts/doctor_sidebar.php'; ?>
    <div class="<?= $isAdmin ? 'admin-content' : 'workspace-content' ?>">
        <div class="<?= $isAdmin ? 'admin-page-header' : 'workspace-page-header' ?>">
            <div>
                <h1 class="<?= $isAdmin ? 'admin-page-title' : 'workspace-page-title' ?>">Assessment Setup</h1>
                <p class="<?= $isAdmin ? 'admin-page-subtitle' : 'workspace-page-subtitle' ?>">Create clinic assessment sections and manage their questions separately.</p>
            </div>
        </div>

        <?php if ($msg !== ''): ?>
            <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="app-card">
            <h5 class="mb-1">Add Section</h5>
            <div class="text-muted small mb-3">Examples: Neck Pain, Back Pain, Knee Pain, Shoulder Pain.</div>
            <form method="post" class="row g-3 align-items-end">
                <input type="hidden" name="action" value="add_section">
                <div class="col-12 col-lg-5">
                    <label class="form-label" for="section_title">Section name</label>
                    <input class="form-control" id="section_title" name="title" placeholder="Example: Neck Pain" required>
                </div>
                <div class="col-12 col-lg-5">
                    <label class="form-label" for="section_description">Short note</label>
                    <input class="form-control" id="section_description" name="description" placeholder="Optional note shown during patient assessment">
                </div>
                <div class="col-6 col-lg-1">
                    <label class="form-label" for="section_sort">Order</label>
                    <input class="form-control" id="section_sort" type="number" name="sort_order" value="0">
                </div>
                <div class="col-6 col-lg-1">
                    <button class="btn btn-primary w-100">Add</button>
                </div>
            </form>
        </div>

        <div class="app-card">
            <h5 class="mb-3">Assessment Sections</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Section</th>
                            <th>Questions</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sections as $section): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($section['title']) ?></div>
                                    <?php if (!empty($section['description'])): ?>
                                        <div class="text-muted small"><?= htmlspecialchars($section['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= count($section['items'] ?? []) ?></td>
                                <td>
                                    <span class="badge <?= !empty($section['is_active']) ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= !empty($section['is_active']) ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap justify-content-end gap-2">
                                        <a class="btn btn-sm btn-primary" href="assessment_questions.php?section_id=<?= (int) $section['id'] ?>">Manage Questions</a>
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editSection<?= (int) $section['id'] ?>">Edit</button>
                                        <form method="post">
                                            <input type="hidden" name="action" value="toggle_section">
                                            <input type="hidden" name="id" value="<?= (int) $section['id'] ?>">
                                            <button class="btn btn-sm btn-outline-secondary"><?= !empty($section['is_active']) ? 'Deactivate' : 'Activate' ?></button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('Delete this assessment section? Related questions will also be deleted.');">
                                            <input type="hidden" name="action" value="delete_section">
                                            <input type="hidden" name="id" value="<?= (int) $section['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr class="collapse" id="editSection<?= (int) $section['id'] ?>">
                                <td colspan="4">
                                    <form method="post" class="row g-3 align-items-end bg-light border rounded p-3">
                                        <input type="hidden" name="action" value="edit_section">
                                        <input type="hidden" name="id" value="<?= (int) $section['id'] ?>">
                                        <div class="col-12 col-lg-5">
                                            <label class="form-label">Section name</label>
                                            <input class="form-control" name="title" value="<?= htmlspecialchars($section['title']) ?>" required>
                                        </div>
                                        <div class="col-12 col-lg-5">
                                            <label class="form-label">Short note</label>
                                            <input class="form-control" name="description" value="<?= htmlspecialchars($section['description'] ?? '') ?>">
                                        </div>
                                        <div class="col-6 col-lg-1">
                                            <label class="form-label">Order</label>
                                            <input class="form-control" type="number" name="sort_order" value="<?= (int) $section['sort_order'] ?>">
                                        </div>
                                        <div class="col-6 col-lg-1">
                                            <button class="btn btn-primary w-100">Save</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sections)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No assessment sections added yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
