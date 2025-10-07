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
    ],
    [
        'label' => 'Manage Users',
        'href' => BASE_URL . '/views/admin/manage_users.php',
        'matches' => ['manage_users.php'],
    ],
    [
        'label' => 'Manage Patients',
        'href' => BASE_URL . '/views/admin/manage_patients.php',
        'matches' => ['manage_patients.php', 'patient_form.php'],
    ],
    [
        'label' => 'Exercises',
        'href' => BASE_URL . '/views/admin/manage_exercises.php',
        'matches' => ['manage_exercises.php'],
    ],
    [
        'label' => 'Machines',
        'href' => BASE_URL . '/views/admin/manage_machines.php',
        'matches' => ['manage_machines.php'],
    ],
    [
        'label' => 'Referral Sources',
        'href' => BASE_URL . '/views/admin/manage_referrals.php',
        'matches' => ['manage_referrals.php'],
    ],
    [
        'label' => 'Financial Reports',
        'href' => BASE_URL . '/views/admin/financial_reports.php',
        'matches' => ['financial_reports.php'],
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
