<?php
// layouts/patient_sidebar.php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');

$patientNavItems = [
    [
        'label' => 'Dashboard',
        'href' => BASE_URL . '/views/patient/dashboard.php',
        'matches' => ['dashboard.php'],
    ],
    [
        'label' => 'My History',
        'href' => BASE_URL . '/views/patient/history.php',
        'matches' => ['history.php'],
    ],
    [
        'label' => 'My Payments',
        'href' => BASE_URL . '/views/patient/payments.php',
        'matches' => ['payments.php'],
    ],
    [
        'label' => 'Change Password',
        'href' => BASE_URL . '/views/patient/change_password.php',
        'matches' => ['change_password.php'],
    ],
    [
        'label' => 'Logout',
        'href' => BASE_URL . '/views/shared/logout.php',
        'matches' => [],
    ],
];

if (!function_exists('renderWorkspaceNavLinks')) {
    function renderWorkspaceNavLinks(array $items, string $currentScript): void
    {
        foreach ($items as $item) {
            $matches = $item['matches'] ?? [];
            $isActive = in_array($currentScript, $matches, true);

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
<div class="workspace-sidebar-wrapper">
    <button class="btn btn-outline-primary workspace-sidebar-toggle d-lg-none w-100" type="button" data-bs-toggle="offcanvas" data-bs-target="#patientSidebar" aria-controls="patientSidebar">
        <span class="fs-5" aria-hidden="true">&#9776;</span>
        <span>Patient Menu</span>
    </button>

    <div class="offcanvas offcanvas-start workspace-offcanvas d-lg-none" tabindex="-1" id="patientSidebar" aria-labelledby="patientSidebarLabel">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="patientSidebarLabel">Patient Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="nav flex-column workspace-nav gap-1">
                <?php renderWorkspaceNavLinks($patientNavItems, $currentScript); ?>
            </nav>
        </div>
    </div>

    <aside class="workspace-sidebar-card d-none d-lg-block">
        <h5 class="mb-3">Patient Panel</h5>
        <nav class="nav flex-column workspace-nav gap-1">
            <?php renderWorkspaceNavLinks($patientNavItems, $currentScript); ?>
        </nav>
    </aside>
</div>
