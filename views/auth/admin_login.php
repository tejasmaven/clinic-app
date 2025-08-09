<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Handle form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../controllers/AuthController.php';
    $auth = new AuthController($pdo);
    $error = $auth->AdminLogin($_POST['email'], $_POST['password'],'Admin');
    if (!$error) {
        header("Location: ../admin/dashboard/");
        exit();
    }
}
?>

<?php include '../../includes/header.php'; ?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <h3>Admin Login</h3>
        <?php if ($error) echo alert('danger', $error); ?>
        <form method="POST">
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
