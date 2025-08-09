<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Admin');

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-3">
        <?php include '../../layouts/admin_sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <h4>Admin Dashboard</h4>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border-primary">
                    <div class="card-body">
                        <h5 class="card-title">Manage Users</h5>
                        <p class="card-text">View, add, and manage doctors and receptionists.</p>
                        <a href="/views/admin/manage_users.php" class="btn btn-primary">Go to Users</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-secondary">
                    <div class="card-body">
                        <h5 class="card-title">Exercise Master</h5>
                        <p class="card-text">Define standard physiotherapy exercises.</p>
                        <a href="/views/admin/manage_exercises.php" class="btn btn-secondary">Manage Exercises</a>
                    </div>
                </div>
            </div>
            <!-- Add more dashboard cards as needed -->
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
