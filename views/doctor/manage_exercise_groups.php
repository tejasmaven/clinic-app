<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Doctor');
requirePermission('exercise_groups.view');
requireActionPermission([
    'add_group' => 'exercise_groups.create',
    'edit_group' => 'exercise_groups.edit',
    'toggle_group' => 'exercise_groups.status',
    'delete_group' => 'exercise_groups.delete',
]);

require_once '../../controllers/ExerciseGroupController.php';
$groupController = new ExerciseGroupController($pdo);

$msg = $groupController->handleActions($_SESSION['user_id'] ?? null);

$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 10;

$groups = $groupController->getGroups($search, $page, $limit);
$total = $groupController->countGroups($search);
$totalPages = (int) ceil($total / $limit);
$exerciseMaster = $groupController->getExerciseMaster();
$machineMaster = $groupController->getMachineMaster();
$pageUrl = 'manage_exercise_groups.php';

include '../../includes/header.php';
?>

<div class="workspace-layout">
    <?php include '../../layouts/doctor_sidebar.php'; ?>
    <div class="workspace-content">
        <div class="workspace-page-header">
            <div>
                <h1 class="workspace-page-title">Exercise Groups</h1>
                <p class="workspace-page-subtitle">Create reusable exercise and machine bundles for daily treatments.</p>
            </div>
        </div>

        <?php include __DIR__ . '/../shared/manage_exercise_groups_content.php'; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
