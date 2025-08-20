<?php
// layouts/doctor_sidebar.php
?>
<div class="sidebar bg-light p-3">
    <h5>Doctor Panel</h5>
    <ul class="list-group">
        <li class="list-group-item"><a href="<?= BASE_URL ?>/views/dashboard/doctor_dashboard.php">Dashboard</a></li>
        <li class="list-group-item"><a href="<?= BASE_URL ?>/views/doctor/manage_patients.php">Manage Patients</a></li>
        <li class="list-group-item"><a href="<?= BASE_URL ?>/views/shared/logout.php">Logout</a></li>
    </ul>

</div>
