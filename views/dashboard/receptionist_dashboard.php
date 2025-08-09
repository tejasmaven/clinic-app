<?php
require_once '../../includes/session.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Receptionist');
include '../../includes/header.php';
?>
<div class="row">
  <div class="col-md-3"><?php include '../../layouts/receptionist_sidebar.php'; ?></div>
  <div class="col-md-9">
    <h4>Receptionist Dashboard</h4>
    <p>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!</p>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
