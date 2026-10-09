<?php
// index.php – Landing Page
include 'includes/header.php';
?>

<div class="text-center mt-5">
    <img src="<?= htmlspecialchars(get_site_logo_url()) ?>" alt="Clinic Logo" class="mb-4 img-fluid" style="max-width: 200px;">
    <h1>Welcome to <?= htmlspecialchars(get_site_name()) ?></h1>
    <div class="mt-4">
        <a href="views/patient/login.php" class="btn btn-primary btn-lg mx-2">Patient Login</a>
        <a href="views/login.php" class="btn btn-secondary btn-lg mx-2">Doctor Login</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
