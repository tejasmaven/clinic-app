<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');

$doctorNavItems = [
    [
        'label' => 'Dashboard',
        'href' => BASE_URL . '/views/dashboard/doctor_dashboard.php',
        'matches' => ['doctor_dashboard.php'],
    ],
    [
        'label' => 'Manage Patients',
        'href' => BASE_URL . '/views/doctor/manage_patients.php',
        'matches' => [
            'manage_patients.php',
            'patient_form.php',
            'select_or_create_episode.php',
            'start_treatment.php',
        ],
    ],
    [
        'label' => 'Active Episodes',
        'href' => BASE_URL . '/views/doctor/active_patients.php',
        'matches' => ['active_patients.php'],
    ],
    [
        'label' => 'Exercises Library',
        'href' => BASE_URL . '/views/doctor/exercises_list.php',
        'matches' => ['exercises_list.php'],
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
    <button class="btn btn-outline-primary workspace-sidebar-toggle d-lg-none w-100" type="button" data-bs-toggle="offcanvas" data-bs-target="#doctorSidebar" aria-controls="doctorSidebar">
        <span class="fs-5" aria-hidden="true">&#9776;</span>
        <span>Doctor Menu</span>
    </button>

    <div class="offcanvas offcanvas-start workspace-offcanvas d-lg-none" tabindex="-1" id="doctorSidebar" aria-labelledby="doctorSidebarLabel">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="doctorSidebarLabel">Doctor Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="nav flex-column workspace-nav gap-1">
                <?php renderWorkspaceNavLinks($doctorNavItems, $currentScript); ?>
            </nav>
        </div>
    </div>

    <aside class="workspace-sidebar-card d-none d-lg-block">
        <h5 class="mb-3">Doctor Panel</h5>
        <nav class="nav flex-column workspace-nav gap-1">
            <?php renderWorkspaceNavLinks($doctorNavItems, $currentScript); ?>
        </nav>
    </aside>
</div>
