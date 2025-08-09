<?php
require_once '../../includes/session.php';
require_once '../../includes/auth.php'; // Ensure correct relative path
requireLogin();
requireRole('Doctor');

include '../../includes/header.php';
?>
<div class="row">
  <div class="col-md-3"><?php include '../../layouts/doctor_sidebar.php'; ?></div>
  <div class="col-md-9">
    <h4>Doctor Dashboard</h4>
    <p>Welcome, Dr. <?= htmlspecialchars($_SESSION['name']) ?>!</p>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
