<?php
require_once '../includes/db.php';
require_once '../controllers/AuthController.php';

$auth = new AuthController($pdo);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $msg = $auth->login($email, $password);
    if (strpos($msg, 'Success') !== false) {
        
        $role = $_SESSION['role'];
       
        if ($role === 'Doctor') {
            
            header('Location: ./dashboard/doctor_dashboard.php'); 
            exit;
        } elseif ($role === 'Receptionist') {
            header('Location: ./dashboard/receptionist_dashboard'); 
            exit;
        } else {
            $msg = "Unauthorized role.";
        }
    }
}

include '../includes/header.php';
?>

<div class="container mt-5" style="max-width:500px;">
  <h4>Doctor / Receptionist Login</h4>
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
