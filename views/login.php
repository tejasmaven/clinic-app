<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../controllers/AuthController.php';

$auth = new AuthController($pdo);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $msg = $auth->login($email, $password, ['Doctor', 'Receptionist', 'Admin', 'Super Admin']);
    if (strpos($msg, 'Success') !== false) {
        header('Location: ' . getRoleLandingUrl($_SESSION['role'] ?? ''));
        exit;
    }
}

include '../includes/header.php';
?>

<div class="container mt-5" style="max-width:500px;">
  <h4><?= htmlspecialchars(get_site_name()) ?> Staff Login</h4>
  <?php if (!empty($msg)): ?>
    <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="mb-3">
      <label>Email</label>
      <input type="email" name="email" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button class="btn btn-primary w-100">Login</button>
  </form>
</div>

<?php include '../includes/footer.php'; ?>
