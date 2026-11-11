<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();
requireRole('Super Admin');

ensure_app_logs_table();

$search = trim($_GET['search'] ?? '');
$logType = $_GET['log_type'] ?? '';
$severity = $_GET['severity'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(message LIKE ? OR action LIKE ? OR user_name LIKE ? OR user_email LIKE ? OR user_role LIKE ? OR request_uri LIKE ?)";
    $searchTerm = '%' . $search . '%';
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}

if (in_array($logType, ['action', 'error'], true)) {
    $where[] = "log_type = ?";
    $params[] = $logType;
}

if (in_array($severity, ['info', 'notice', 'warning', 'error', 'critical'], true)) {
    $where[] = "severity = ?";
    $params[] = $severity;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM app_logs $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $limit));

$logsStmt = $pdo->prepare(
    "SELECT *
     FROM app_logs
     $whereSql
     ORDER BY created_at DESC, id DESC
     LIMIT $limit OFFSET $offset"
);
$logsStmt->execute($params);
$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

$permissionLogStmt = $pdo->query(
    "SELECT pcl.*, u.name AS actor_name, u.email AS actor_email, u.role AS actor_role
     FROM permission_change_log pcl
     LEFT JOIN users u ON u.id = pcl.changed_by
     ORDER BY pcl.created_at DESC, pcl.id DESC
     LIMIT 50"
);
$permissionLogs = $permissionLogStmt->fetchAll(PDO::FETCH_ASSOC);

function permissionConfigurationKeys(array $configuration, string $key): array {
    $values = $configuration[$key] ?? [];
    return is_array($values) ? array_keys($values) : [];
}

function permissionChangeDetails(array $log): array {
    $previous = json_decode((string) ($log['previous_configuration'] ?? ''), true);
    $current = json_decode((string) ($log['new_configuration'] ?? ''), true);
    $previous = is_array($previous) ? $previous : [];
    $current = is_array($current) ? $current : [];

    $previousModules = permissionConfigurationKeys($previous, 'enabled_modules');
    $currentModules = permissionConfigurationKeys($current, 'enabled_modules');
    $previousPermissions = permissionConfigurationKeys($previous, 'allowed_permissions');
    $currentPermissions = permissionConfigurationKeys($current, 'allowed_permissions');

    return [
        'modules_enabled' => array_values(array_diff($currentModules, $previousModules)),
        'modules_disabled' => array_values(array_diff($previousModules, $currentModules)),
        'permissions_granted' => array_values(array_diff($currentPermissions, $previousPermissions)),
        'permissions_revoked' => array_values(array_diff($previousPermissions, $currentPermissions)),
    ];
}

function formatPermissionChangeItems(array $items): string {
    if (empty($items)) {
        return '';
    }

    return implode(', ', array_map(
        static function ($item) {
            return ucwords(str_replace(['.', '_'], [' / ', ' '], (string) $item));
        },
        $items
    ));
}

$queryBase = [
    'search' => $search,
    'log_type' => $logType,
    'severity' => $severity,
];

include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">View Logs</h1>
                <p class="admin-page-subtitle">Review user activity and application errors across the clinic portal.</p>
            </div>
        </div>

        <div class="app-card">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Permission Changes</h2>
                    <p class="text-muted mb-0">Latest role module and permission updates made by Super Admin.</p>
                </div>
                <span class="badge text-bg-secondary"><?= count($permissionLogs) ?> latest changes</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Changed By</th>
                            <th scope="col">Target Role</th>
                            <th scope="col">Update Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($permissionLogs)): ?>
                            <?php foreach ($permissionLogs as $permissionLog): ?>
                                <?php $changes = permissionChangeDetails($permissionLog); ?>
                                <tr>
                                    <td class="text-nowrap"><?= htmlspecialchars(format_display_date($permissionLog['created_at'], 'd-M-Y H:i:s')) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($permissionLog['actor_name'] ?: 'System / Unknown') ?></div>
                                        <div class="small text-muted">
                                            <?= htmlspecialchars($permissionLog['actor_role'] ?: '-') ?>
                                            <?php if (!empty($permissionLog['actor_email'])): ?>
                                                &middot; <?= htmlspecialchars($permissionLog['actor_email']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><span class="badge text-bg-primary"><?= htmlspecialchars($permissionLog['role_name']) ?></span></td>
                                    <td style="min-width: 360px;">
                                        <?php if (!empty($changes['modules_enabled'])): ?>
                                            <div><strong class="text-success">Modules enabled:</strong> <?= htmlspecialchars(formatPermissionChangeItems($changes['modules_enabled'])) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($changes['modules_disabled'])): ?>
                                            <div><strong class="text-danger">Modules disabled:</strong> <?= htmlspecialchars(formatPermissionChangeItems($changes['modules_disabled'])) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($changes['permissions_granted'])): ?>
                                            <div><strong class="text-success">Permissions granted:</strong> <?= htmlspecialchars(formatPermissionChangeItems($changes['permissions_granted'])) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($changes['permissions_revoked'])): ?>
                                            <div><strong class="text-danger">Permissions revoked:</strong> <?= htmlspecialchars(formatPermissionChangeItems($changes['permissions_revoked'])) ?></div>
                                        <?php endif; ?>
                                        <?php if (empty(array_filter($changes))): ?>
                                            <span class="text-muted">Configuration saved with no effective permission changes.</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No permission changes have been recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="app-card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-12 col-lg-4">
                    <label for="search" class="form-label">Search logs</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Message, action, user, URL" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label for="log_type" class="form-label">Type</label>
                    <select id="log_type" name="log_type" class="form-select">
                        <option value="">All</option>
                        <option value="action" <?= $logType === 'action' ? 'selected' : '' ?>>Action</option>
                        <option value="error" <?= $logType === 'error' ? 'selected' : '' ?>>Error</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label for="severity" class="form-label">Severity</label>
                    <select id="severity" name="severity" class="form-select">
                        <option value="">All</option>
                        <?php foreach (['info', 'notice', 'warning', 'error', 'critical'] as $level): ?>
                            <option value="<?= htmlspecialchars($level) ?>" <?= $severity === $level ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($level)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-lg-2">
                    <button class="btn btn-primary w-100">Search</button>
                </div>
                <div class="col-12 col-lg-2">
                    <a class="btn btn-outline-secondary w-100" href="<?= BASE_URL ?>/views/admin/view_logs.php">Reset</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Type</th>
                        <th scope="col">Severity</th>
                        <th scope="col">User</th>
                        <th scope="col">Action</th>
                        <th scope="col">Message</th>
                        <th scope="col">URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                                $isError = ($log['log_type'] ?? '') === 'error';
                                $rowClass = $isError ? 'table-danger' : '';
                                $badgeClass = $isError ? 'text-bg-danger' : 'text-bg-primary';
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td class="text-nowrap"><?= htmlspecialchars(format_display_date($log['created_at'], 'd-M-Y H:i:s')) ?></td>
                                <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($log['log_type'])) ?></span></td>
                                <td><?= htmlspecialchars(ucfirst($log['severity'])) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($log['user_name'] ?: 'Guest/System') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($log['user_role'] ?: '-') ?></div>
                                </td>
                                <td><?= htmlspecialchars($log['action'] ?: '-') ?></td>
                                <td style="min-width: 260px;"><?= htmlspecialchars($log['message']) ?></td>
                                <td class="small" style="min-width: 220px;"><?= htmlspecialchars($log['request_uri'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No logs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav aria-label="Logs pagination" class="d-flex justify-content-end">
                <ul class="pagination mt-3">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php $query = array_merge($queryBase, ['page' => $p]); ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= htmlspecialchars(http_build_query($query)) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
