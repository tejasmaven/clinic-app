<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();
requireRole('Super Admin');

require_once '../../controllers/AppSettingsController.php';

$settings = new AppSettingsController($pdo);
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_site_name') {
        $messages[] = $settings->updateSiteName($_POST['site_name'] ?? '');
    } elseif ($action === 'upload_logo') {
        $messages[] = $settings->updateLogo($_FILES['logo'] ?? []);
    }
}

$siteName = $settings->getSiteName();
$logoUrl = get_site_logo_url();

include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Configuration</h1>
                <p class="admin-page-subtitle">Manage global website branding settings.</p>
            </div>
        </div>

        <?php foreach ($messages as $message): ?>
            <div class="alert alert-info mb-4" role="alert"><?= htmlspecialchars($message) ?></div>
        <?php endforeach; ?>

        <div class="app-card">
            <h5 class="mb-3">Logo Upload</h5>
            <div class="row g-4 align-items-center">
                <div class="col-12 col-md-auto">
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Current Logo" class="img-fluid border rounded bg-white p-2" style="max-width: 220px; max-height: 140px;">
                </div>
                <div class="col-12 col-md">
                    <form method="POST" enctype="multipart/form-data" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="upload_logo">
                        <div class="col-12 col-lg-8">
                            <label for="logo" class="form-label">Upload JPG logo</label>
                            <input type="file" id="logo" name="logo" class="form-control" accept=".jpg,image/jpeg" required>
                            <div class="form-text">The uploaded file replaces assets/logo.jpg and is used wherever the site logo appears.</div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <button class="btn btn-primary w-100">Upload Logo</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="app-card">
            <h5 class="mb-3">Site Name</h5>
            <form method="POST" class="row g-3 align-items-end">
                <input type="hidden" name="action" value="update_site_name">
                <div class="col-12 col-lg-8">
                    <label for="site_name" class="form-label">Site name</label>
                    <input type="text" id="site_name" name="site_name" class="form-control" maxlength="120" value="<?= htmlspecialchars($siteName) ?>" required>
                </div>
                <div class="col-12 col-lg-4">
                    <button class="btn btn-primary w-100">Save Site Name</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
