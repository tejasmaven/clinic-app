<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(?string $redirect = null) {
    if (empty($_SESSION['user_id'])) {
        $redirectTo = $redirect ?? '../login.php';
        header("Location: $redirectTo"); // Adjust path if needed
        exit;
    }
}

function requireRole($role, ?string $redirect = null) {
    $redirectTo = $redirect ?? '../login.php';
    requireLogin($redirectTo);

    $allowedRoles = is_array($role) ? $role : [$role];
    if (in_array('Admin', $allowedRoles, true) && !in_array('Super Admin', $allowedRoles, true)) {
        $allowedRoles[] = 'Super Admin';
    }

    if (!in_array($_SESSION['role'] ?? '', $allowedRoles, true)) {
        header("Location: $redirectTo");
        exit;
    }
}

function isSuperAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'Super Admin';
}

function hasModuleAccess(string $moduleKey, ?string $role = null): bool {
    $role = $role ?? ($_SESSION['role'] ?? '');
    if ($role === 'Super Admin') {
        return true;
    }
    if ($role === '') {
        return false;
    }

    global $pdo;
    if (!isset($pdo) || !$pdo instanceof PDO) {
        return false;
    }

    static $cache = [];
    $cacheKey = $role . ':' . $moduleKey;
    if (!array_key_exists($cacheKey, $cache)) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM role_module_settings rms
             INNER JOIN system_modules m ON m.id = rms.module_id
             WHERE rms.role_name = ? AND m.module_key = ?
               AND rms.is_enabled = 1 AND m.is_active = 1"
        );
        $stmt->execute([$role, $moduleKey]);
        $cache[$cacheKey] = (int) $stmt->fetchColumn() > 0;
    }

    return $cache[$cacheKey];
}

function hasPermission(string $permissionKey, ?string $role = null): bool {
    $role = $role ?? ($_SESSION['role'] ?? '');
    if ($role === 'Super Admin') {
        return true;
    }
    if ($role === '') {
        return false;
    }

    global $pdo;
    if (!isset($pdo) || !$pdo instanceof PDO) {
        return false;
    }

    static $cache = [];
    $cacheKey = $role . ':' . $permissionKey;
    if (!array_key_exists($cacheKey, $cache)) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM role_permission_grants rpg
             INNER JOIN module_permissions p ON p.id = rpg.permission_id
             INNER JOIN system_modules m ON m.id = p.module_id
             INNER JOIN role_module_settings rms
               ON rms.module_id = m.id AND rms.role_name = rpg.role_name
             WHERE rpg.role_name = ? AND p.permission_key = ?
               AND rpg.is_allowed = 1 AND rms.is_enabled = 1
               AND p.is_active = 1 AND m.is_active = 1"
        );
        $stmt->execute([$role, $permissionKey]);
        $cache[$cacheKey] = (int) $stmt->fetchColumn() > 0;
    }

    return $cache[$cacheKey];
}

function denyAccess(string $message = 'You do not have permission to access this resource.'): void {
    http_response_code(403);
    include __DIR__ . '/header.php';
    echo '<div class="container py-5"><div class="alert alert-danger" role="alert">'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . '</div></div>';
    include __DIR__ . '/footer.php';
    exit;
}

function requireModule(string $moduleKey): void {
    requireLogin();
    if (!hasModuleAccess($moduleKey)) {
        denyAccess('This module is disabled for your role.');
    }
}

function requirePermission(string $permissionKey): void {
    requireLogin();
    if (!hasPermission($permissionKey)) {
        denyAccess();
    }
}

function requireActionPermission(array $actionPermissions, ?string $action = null): void {
    $action = $action ?? (string) ($_POST['action'] ?? $_GET['action'] ?? '');
    if ($action !== '' && isset($actionPermissions[$action])) {
        requirePermission($actionPermissions[$action]);
    }
}

function getRoleLandingUrl(?string $role = null): string {
    $role = $role ?? ($_SESSION['role'] ?? '');

    $candidates = [
        'Super Admin' => [
            ['dashboard.admin.view', '/views/admin/index.php'],
        ],
        'Admin' => [
            ['dashboard.admin.view', '/views/admin/index.php'],
            ['users.view', '/views/admin/manage_users.php'],
            ['patients.view', '/views/admin/manage_patients.php'],
            ['exercises.view', '/views/admin/manage_exercises.php'],
            ['machines.view', '/views/admin/manage_machines.php'],
            ['exercise_groups.view', '/views/admin/manage_exercise_groups.php'],
            ['assessment_setup.view', '/views/shared/assessment_setup.php'],
            ['report_file_types.view', '/views/admin/manage_patient_report_file_types.php'],
            ['referrals.view', '/views/admin/manage_referrals.php'],
            ['financial_reports.view', '/views/admin/financial_reports.php'],
            ['progress_tracker.view', '/views/admin/progress_tracker.php'],
            ['payment_template.view', '/views/admin/payment_slip_template.php'],
            ['medical_reports.view', '/views/admin/medical_report.php'],
        ],
        'Doctor' => [
            ['dashboard.doctor.view', '/views/dashboard/doctor_dashboard.php'],
            ['patients.view', '/views/doctor/manage_patients.php'],
            ['episodes.view', '/views/doctor/active_patients.php'],
            ['exercises.view', '/views/doctor/exercises_list.php'],
            ['machines.view', '/views/doctor/machines_list.php'],
            ['exercise_groups.view', '/views/doctor/manage_exercise_groups.php'],
            ['assessment_setup.view', '/views/shared/assessment_setup.php'],
        ],
        'Receptionist' => [
            ['dashboard.reception.view', '/views/dashboard/receptionist_dashboard.php'],
            ['patients.view', '/views/receptionist/manage_patients.php'],
        ],
        'Patient' => [
            ['patient_portal.dashboard', '/views/patient/dashboard.php'],
            ['patient_portal.history', '/views/patient/history.php'],
            ['patient_portal.payments', '/views/patient/payments.php'],
            ['patient_portal.password', '/views/patient/change_password.php'],
        ],
    ];

    foreach ($candidates[$role] ?? [] as [$permission, $path]) {
        if (hasPermission($permission, $role)) {
            return BASE_URL . $path;
        }
    }

    return BASE_URL . '/index.php';
}
