<?php
// controllers/AuthController.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
class AuthController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function AdminLogin($email, $password, $expectedRole = null) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? AND is_deleted = 0 AND is_active = 1 AND role = 'Admin'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {

          if ($expectedRole && $user['role'] !== $expectedRole) {
                return "Access denied for this role.";
            }

            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
           session_write_close();
          return null;
           
        } else {
            return "Invalid login credentials.";
        }
    }
    
    public function login($email, $password, $expectedRole = null) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? AND is_deleted = 0 AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($expectedRole && $user['role'] !== $expectedRole) {
                return "Access denied for this role.";
            }

            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
           session_write_close();
           return "Success";
           
        } else {
            return "Invalid login credentials.";
        }
    }

    public function logout() {
    session_start();
    session_destroy();
    header("Location: ../views/login.php");
    exit;
  }
}
/*

<?php
class AuthController {
  private $pdo;

  public function __construct($pdo) {
    $this->pdo = $pdo;
    session_start();
  }

  public function login($email, $password) {
    $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? AND is_deleted = 0 AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

     if ($user && hash_equals($user['password_hash'], hash('sha256', $password))) {
      $_SESSION['user'] = [
        'user_id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'name' => $user['name']
      ];
      return "Success";
    }
    return "Invalid login credentials.";
  }

  public function logout() {
    session_start();
    session_destroy();
    header("Location: ../views/login.php");
    exit;
  }
}
*/