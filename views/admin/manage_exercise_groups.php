<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Admin');

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

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Exercise Groups</h1>
                <p class="admin-page-subtitle">Create reusable exercise and machine bundles for common treatment plans.</p>
            </div>
        </div>

        <?php include __DIR__ . '/../shared/manage_exercise_groups_content.php'; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
