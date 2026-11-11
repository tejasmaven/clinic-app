<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Super Admin');

require_once '../../controllers/ModulePermissionController.php';

$permissionController = new ModulePermissionController($pdo);
$roles = $permissionController->getRoles();
$selectedRole = $_POST['selected_role'] ?? $_GET['role'] ?? 'Admin';
if (!$permissionController->isValidRole($selectedRole)) {
    $selectedRole = 'Admin';
}

if (empty($_SESSION['module_permission_csrf'])) {
    $_SESSION['module_permission_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['module_permission_csrf'], $submittedToken)) {
        $_SESSION['module_permission_flash'] = [
            'type' => 'danger',
            'message' => 'The form session expired. Please try again.',
        ];
    } else {
        try {
            $permissionController->saveRoleConfiguration(
                $selectedRole,
                isset($_POST['modules']) && is_array($_POST['modules']) ? $_POST['modules'] : [],
                (int) ($_SESSION['user_id'] ?? 0)
            );
            $_SESSION['module_permission_flash'] = [
                'type' => 'success',
                'message' => $selectedRole . ' module permissions saved successfully.',
            ];
        } catch (Throwable $e) {
            $_SESSION['module_permission_flash'] = [
                'type' => 'danger',
                'message' => 'Unable to save module permissions: ' . $e->getMessage(),
            ];
        }
    }

    header('Location: modules_permissions.php?role=' . urlencode($selectedRole));
    exit;
}

$modules = $permissionController->getModulesWithPermissions();
$roleConfiguration = $permissionController->getRoleConfiguration($selectedRole);
$enabledModules = $roleConfiguration['enabled_modules'];
$allowedPermissions = $roleConfiguration['allowed_permissions'];
$flash = $_SESSION['module_permission_flash'] ?? null;
unset($_SESSION['module_permission_flash']);

include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h1 class="admin-page-title">Modules &amp; Permissions</h1>
                    <span class="badge text-bg-secondary">Database backed</span>
                </div>
                <p class="admin-page-subtitle">Configure module access for the fixed system roles.</p>
            </div>
            <button class="btn btn-primary" type="submit" form="permissionsForm">Save Changes</button>
        </div>

        <?php if (is_array($flash) && !empty($flash['message'])): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info') ?>" role="alert">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <div class="app-card permission-role-panel">
            <fieldset>
                <legend class="form-label fw-semibold mb-3">Select Role</legend>
                <div class="permission-role-selector" role="radiogroup" aria-label="Select role">
                    <?php foreach ($roles as $role): ?>
                        <?php $roleId = strtolower(str_replace(' ', '_', $role)); ?>
                        <input class="btn-check permission-role-input" type="radio" name="role_preview" id="role_<?= htmlspecialchars($roleId) ?>" value="<?= htmlspecialchars($role) ?>" autocomplete="off" <?= $selectedRole === $role ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary" for="role_<?= htmlspecialchars($roleId) ?>"><?= htmlspecialchars($role) ?></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </div>

        <form method="post" id="permissionsForm" class="app-card p-0 overflow-hidden">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['module_permission_csrf']) ?>">
            <input type="hidden" name="selected_role" value="<?= htmlspecialchars($selectedRole) ?>">
            <div class="permission-table-heading">
                <div>
                    <h5 class="mb-1" id="permissionTableTitle"><?= htmlspecialchars($selectedRole) ?> permissions</h5>
                    <p class="text-muted mb-0">Complete permission catalog</p>
                </div>
                <span class="permission-count" id="permissionCount" aria-live="polite"></span>
            </div>

            <div class="table-responsive permission-table-wrap">
                <table class="table align-middle mb-0 permission-table">
                    <thead>
                        <tr>
                            <th scope="col">Module</th>
                            <th scope="col">Permissions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $currentCategory = null; ?>
                        <?php foreach ($modules as $moduleKey => $module): ?>
                            <?php if ($currentCategory !== $module['category']): ?>
                                <?php $currentCategory = $module['category']; ?>
                                <tr class="permission-category-row">
                                    <th colspan="2" scope="colgroup"><?= htmlspecialchars($currentCategory) ?></th>
                                </tr>
                            <?php endif; ?>
                            <tr data-module="<?= htmlspecialchars($moduleKey) ?>" data-protected="<?= !empty($module['protected']) ? 'true' : 'false' ?>">
                                <th scope="row" class="permission-module-cell">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input permission-module-toggle" type="checkbox" role="switch" id="module_<?= htmlspecialchars($moduleKey) ?>" name="modules[<?= htmlspecialchars($moduleKey) ?>][enabled]" value="1" aria-label="Enable <?= htmlspecialchars($module['label']) ?>" <?= isset($enabledModules[$moduleKey]) ? 'checked' : '' ?> <?= !empty($module['protected']) ? 'disabled' : '' ?>>
                                        </div>
                                        <div>
                                            <label class="fw-semibold d-block" for="module_<?= htmlspecialchars($moduleKey) ?>"><?= htmlspecialchars($module['label']) ?></label>
                                            <span class="text-muted small d-block mt-1"><?= htmlspecialchars($module['description']) ?></span>
                                            <?php if (!empty($module['protected'])): ?>
                                                <span class="badge bg-light text-dark border mt-2">Always Super Admin only</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </th>
                                <td>
                                    <div class="permission-options">
                                        <?php foreach ($module['permissions'] as $permissionKey => $permission): ?>
                                            <?php $permissionId = $moduleKey . '_' . $permissionKey; ?>
                                            <div class="form-check permission-option">
                                                <input class="form-check-input permission-action" type="checkbox" id="permission_<?= htmlspecialchars($permissionId) ?>" name="modules[<?= htmlspecialchars($moduleKey) ?>][permissions][]" value="<?= htmlspecialchars($permissionKey) ?>" data-permission="<?= htmlspecialchars($permissionKey) ?>" <?= isset($allowedPermissions[$permissionKey]) ? 'checked' : '' ?> <?= !isset($enabledModules[$moduleKey]) || !empty($module['protected']) ? 'disabled' : '' ?>>
                                                <label class="form-check-label" for="permission_<?= htmlspecialchars($permissionId) ?>"><?= htmlspecialchars($permission['label']) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <section class="permission-recommendations" aria-labelledby="permissionRecommendationsTitle">
            <div class="mb-3">
                <h2 class="h5 mb-1" id="permissionRecommendationsTitle">Recommended Next Decisions</h2>
                <p class="text-muted mb-0">Items to confirm before permission enforcement begins.</p>
            </div>
            <div class="permission-recommendation-list">
                <div class="permission-recommendation-item">
                    <strong>Keep module access separate from action access</strong>
                    <span>A disabled module should block its menu, page URLs, and all related actions.</span>
                </div>
                <div class="permission-recommendation-item">
                    <strong>Use role defaults first</strong>
                    <span>Start with fixed role permissions and avoid per-user exceptions until a real need appears.</span>
                </div>
                <div class="permission-recommendation-item">
                    <strong>Protect destructive actions</strong>
                    <span>Delete and deactivate permissions should remain separate from create and edit.</span>
                </div>
                <div class="permission-recommendation-item">
                    <strong>Record every permission change</strong>
                    <span>Permission changes are logged with the actor, role, previous value, new value, and time.</span>
                </div>
            </div>
        </section>
    </div>
</div>

<style>
.permission-role-panel { padding-bottom: 1.25rem; padding-top: 1.25rem; }
.permission-role-selector { display: flex; flex-wrap: wrap; gap: .65rem; }
.permission-role-selector .btn { min-width: 8.5rem; }
.permission-table-heading { align-items: center; border-bottom: 1px solid rgba(15, 23, 42, .08); display: flex; justify-content: space-between; padding: 1.25rem 1.5rem; }
.permission-count { color: #4b5563; font-size: .9rem; font-weight: 600; }
.permission-table-wrap { border: 0 !important; border-radius: 0 !important; }
.permission-table th:first-child { width: 36%; }
.permission-module-cell { min-width: 250px; padding: 1.25rem !important; }
.permission-table td { padding: 1.25rem !important; }
.permission-category-row th { background-color: #e9eef5; color: #334155; font-size: .78rem; letter-spacing: 0; padding: .7rem 1.25rem !important; text-transform: uppercase; }
.permission-table tr[data-protected="true"] { background-color: #f8fafc; }
.permission-options { display: grid; gap: .75rem 1.25rem; grid-template-columns: repeat(3, minmax(150px, 1fr)); }
.permission-option { margin: 0; min-height: 1.5rem; }
.permission-option .form-check-input:disabled + .form-check-label { color: #94a3b8; }
.permission-recommendations { padding: .25rem 0; }
.permission-recommendation-list { border-bottom: 1px solid rgba(15, 23, 42, .1); }
.permission-recommendation-item { align-items: start; border-top: 1px solid rgba(15, 23, 42, .1); display: grid; gap: .5rem 1.5rem; grid-template-columns: minmax(220px, .8fr) minmax(280px, 1.2fr); padding: 1rem 0; }
.permission-recommendation-item span { color: #4b5563; }
@media (max-width: 767.98px) {
    .permission-role-selector { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .permission-role-selector .btn { min-width: 0; width: 100%; }
    .permission-table-heading { align-items: flex-start; flex-direction: column; gap: .5rem; }
    .permission-options { grid-template-columns: 1fr; }
    .permission-recommendation-item { grid-template-columns: 1fr; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleInputs = document.querySelectorAll('.permission-role-input');
    const count = document.getElementById('permissionCount');

    function updateCount() {
        const enabledModules = document.querySelectorAll('.permission-module-toggle:checked').length;
        const selectedPermissions = document.querySelectorAll('.permission-action:checked').length;
        count.textContent = enabledModules + ' modules, ' + selectedPermissions + ' permissions selected';
    }

    roleInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            if (input.checked) {
                window.location.href = 'modules_permissions.php?role=' + encodeURIComponent(input.value);
            }
        });
    });

    document.querySelectorAll('.permission-module-toggle').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            const row = toggle.closest('[data-module]');
            row.querySelectorAll('.permission-action').forEach(function (action) {
                action.disabled = !toggle.checked;
                if (!toggle.checked) action.checked = false;
            });
            updateCount();
        });
    });

    document.querySelectorAll('.permission-action').forEach(function (action) {
        action.addEventListener('change', updateCount);
    });

    updateCount();
});
</script>

<?php include '../../includes/footer.php'; ?>
