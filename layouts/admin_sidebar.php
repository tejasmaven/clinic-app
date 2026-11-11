<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');

$adminNavItems = [
    [
        'label' => 'Dashboard',
        'href' => BASE_URL . '/views/admin/index.php',
        'matches' => ['index.php'],
        'permission' => 'dashboard.admin.view',
    ],
    [
        'label' => 'Manage Users',
        'href' => BASE_URL . '/views/admin/manage_users.php',
        'matches' => ['manage_users.php'],
        'permission' => 'users.view',
    ],
    [
        'label' => 'Manage Patients',
        'href' => BASE_URL . '/views/admin/manage_patients.php',
        'matches' => [
            'manage_patients.php',
            'patient_form.php',
            'select_or_create_episode.php',
            'start_treatment.php',
        ],
        'permission' => 'patients.view',
    ],
    [
        'label' => 'Exercises',
        'href' => BASE_URL . '/views/admin/manage_exercises.php',
        'matches' => ['manage_exercises.php'],
        'permission' => 'exercises.view',
    ],
    [
        'label' => 'Machines',
        'href' => BASE_URL . '/views/admin/manage_machines.php',
        'matches' => ['manage_machines.php'],
        'permission' => 'machines.view',
    ],
    [
        'label' => 'Exercise Groups',
        'href' => BASE_URL . '/views/admin/manage_exercise_groups.php',
        'matches' => ['manage_exercise_groups.php'],
        'permission' => 'exercise_groups.view',
    ],
    [
        'label' => 'Assessment Setup',
        'href' => BASE_URL . '/views/shared/assessment_setup.php',
        'matches' => ['assessment_setup.php', 'assessment_questions.php'],
        'permission' => 'assessment_setup.view',
    ],
    [
        'label' => 'Patient Report File Types',
        'href' => BASE_URL . '/views/admin/manage_patient_report_file_types.php',
        'matches' => ['manage_patient_report_file_types.php'],
        'permission' => 'report_file_types.view',
    ],
    [
        'label' => 'Referral Sources',
        'href' => BASE_URL . '/views/admin/manage_referrals.php',
        'matches' => ['manage_referrals.php'],
        'permission' => 'referrals.view',
    ],
    [
        'label' => 'Financial Reports',
        'href' => BASE_URL . '/views/admin/financial_reports.php',
        'matches' => ['financial_reports.php'],
        'permission' => 'financial_reports.view',
    ],
    [
        'label' => 'Progress Tracker',
        'href' => BASE_URL . '/views/admin/progress_tracker.php',
        'matches' => ['progress_tracker.php'],
        'permission' => 'progress_tracker.view',
    ],
    [
        'label' => 'Payment Slip Template',
        'href' => BASE_URL . '/views/admin/payment_slip_template.php',
        'matches' => ['payment_slip_template.php'],
        'permission' => 'payment_template.view',
    ],
    [
        'label' => 'Medical Report',
        'href' => BASE_URL . '/views/admin/medical_report.php',
        'matches' => ['medical_report.php'],
        'permission' => 'medical_reports.view',
    ],
    [
        'label' => 'Configuration',
        'href' => BASE_URL . '/views/admin/configuration.php',
        'matches' => ['configuration.php'],
        'super_admin_only' => true,
    ],
    [
        'label' => 'Modules & Permissions',
        'href' => BASE_URL . '/views/admin/modules_permissions.php',
        'matches' => ['modules_permissions.php'],
        'super_admin_only' => true,
    ],
    [
        'label' => 'View Logs',
        'href' => BASE_URL . '/views/admin/view_logs.php',
        'matches' => ['view_logs.php'],
        'super_admin_only' => true,
    ],
    [
        'label' => 'Logout',
        'href' => BASE_URL . '/views/shared/logout.php',
        'matches' => [],
    ],
];

if (!function_exists('renderAdminNavLinks')) {
    function renderAdminNavLinks(array $items, string $currentScript): void
    {
        foreach ($items as $item) {
            if (!empty($item['super_admin_only']) && ($_SESSION['role'] ?? '') !== 'Super Admin') {
                continue;
            }
            if (!empty($item['permission']) && !hasPermission($item['permission'])) {
                continue;
            }

            $matches = $item['matches'] ?? [];
            $isActive = false;

            foreach ($matches as $match) {
                if ($match === $currentScript) {
                    $isActive = true;
                    break;
                }
            }

            $classes = 'nav-link';
            if ($isActive) {
                $classes .= ' active';
            }

            $href = htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');

            echo '<a class="' . $classes . '" href="' . $href . '">' . $label . '</a>';
        }
    }
}
?>
<div class="admin-sidebar-wrapper">
    <button class="btn btn-outline-primary admin-sidebar-toggle d-lg-none w-100" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar">
        <span class="fs-5" aria-hidden="true">&#9776;</span>
        <span>Admin Menu</span>
    </button>

    <div class="offcanvas offcanvas-start admin-offcanvas d-lg-none" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="adminSidebarLabel">Admin Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="nav flex-column admin-nav gap-1">
                <?php renderAdminNavLinks($adminNavItems, $currentScript); ?>
            </nav>
        </div>
    </div>

    <aside class="admin-sidebar-card d-none d-lg-block">
        <h5 class="mb-3">Admin Panel</h5>
        <nav class="nav flex-column admin-nav gap-1">
            <?php renderAdminNavLinks($adminNavItems, $currentScript); ?>
        </nav>
    </aside>
</div>
