<?php

class AdminController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function handleUserActions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return '';

        $action = $_POST['action'] ?? '';
        $id = $_POST['id'] ?? null;

        if ($action === 'add_user') {
            $email = trim($_POST['email']);

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND is_deleted = 0");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                return "User with this email already exists.";
            }

            $stmt = $this->pdo->prepare("INSERT INTO users (name, email, role, password_hash, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([
                trim($_POST['name']),
                $email,
                $_POST['role'],
                password_hash($_POST['password'], PASSWORD_DEFAULT)
            ]);
            return "User added successfully.";

        } elseif ($action === 'edit_user' && $id) {
            $stmt = $this->pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
            $stmt->execute([
                trim($_POST['name']),
                trim($_POST['email']),
                $_POST['role'],
                $id
            ]);
            return "User updated successfully.";

        } elseif ($action === 'delete_user' && $id) {
            $stmt = $this->pdo->prepare("UPDATE users SET is_deleted = 1 WHERE id = ?");
            $stmt->execute([$id]);
            return "User deleted.";

        } elseif ($action === 'restore_user' && $id) {
            $stmt = $this->pdo->prepare("UPDATE users SET is_deleted = 0 WHERE id = ?");
            $stmt->execute([$id]);
            return "User restored.";

        } elseif ($action === 'toggle_user_status' && $id) {
            $stmt = $this->pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);
            return "User status updated.";
        }

        return '';
    }

    public function getUsers($search = '', $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $showDeleted = isset($_GET['show_deleted']) ? 1 : 0;

        $sql = "SELECT * FROM users WHERE is_deleted = ? AND (name LIKE ? OR email LIKE ?) ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$showDeleted, "%$search%", "%$search%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countUsers($search = '') {
        $showDeleted = isset($_GET['show_deleted']) ? 1 : 0;
        $sql = "SELECT COUNT(*) FROM users WHERE is_deleted = ? AND (name LIKE ? OR email LIKE ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$showDeleted, "%$search%", "%$search%"]);
        return $stmt->fetchColumn();
    }
    // -------------------- Referral Sources ------------------------

    public function handleReferralActions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return '';

        $action = $_POST['action'] ?? '';
        $id = $_POST['id'] ?? null;

        if ($action === 'add_referral') {
            $name = trim($_POST['name']);
            $type = $_POST['type'];

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM referral_sources WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetchColumn() > 0) {
                return "Referral name already exists.";
            }

            $stmt = $this->pdo->prepare("INSERT INTO referral_sources (name, type) VALUES (?, ?)");
            $stmt->execute([$name, $type]);
            return "Referral source added.";
        }

        if ($action === 'edit_referral' && $id) {
            $name = trim($_POST['name']);
            $type = $_POST['type'];

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM referral_sources WHERE name = ? AND id != ?");
            $stmt->execute([$name, $id]);
            if ($stmt->fetchColumn() > 0) {
                return "Another referral with this name already exists.";
            }

            $stmt = $this->pdo->prepare("UPDATE referral_sources SET name = ?, type = ? WHERE id = ?");
            $stmt->execute([$name, $type, $id]);
            return "Referral source updated.";
        }

        if ($action === 'delete_referral' && $id) {
            $stmt = $this->pdo->prepare("DELETE FROM referral_sources WHERE id = ?");
            $stmt->execute([$id]);
            return "Referral source deleted.";
        }

       

        return '';
    }

    public function getReferralSources($search = '', $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM referral_sources WHERE name LIKE ? ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(["%$search%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countReferralSources($search = '') {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM referral_sources WHERE name LIKE ?");
        $stmt->execute(["%$search%"]);
        return $stmt->fetchColumn();
    }
// -------------------- Exercises ------------------------

    public function handleExercisesActions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return '';

        $action = $_POST['action'] ?? '';
        $id = $_POST['id'] ?? null;

        if ($action === 'add_exercise') {
            $name = trim($_POST['name']);
            $default_reps = $_POST['default_reps'];
            $default_duration_minutes = $_POST['default_duration_minutes'];
            $is_active = $_POST['is_active'];

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM exercises_master WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetchColumn() > 0) {
                return "Exercises name already exists.";
            }

            $stmt = $this->pdo->prepare("INSERT INTO exercises_master (name, default_reps, default_duration_minutes, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $default_reps, $default_duration_minutes, $is_active]);
            return "Exercises source added.";
        }

        if ($action === 'edit_exercise' && $id) {
            $name = trim($_POST['name']);
            $default_reps = $_POST['default_reps'];
            $default_duration_minutes = $_POST['default_duration_minutes'];
            
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM exercises_master WHERE name = ? AND id != ?");
            $stmt->execute([$name, $id]);
            if ($stmt->fetchColumn() > 0) {
                return "Another Exercises with this name already exists.";
            }

            $stmt = $this->pdo->prepare("UPDATE exercises_master SET name = ?, default_reps = ? , default_duration_minutes = ?  WHERE id = ?");
            $stmt->execute([$name, $default_reps, $default_duration_minutes, $id]);
            return "Exercises source updated.";
        }

        if ($action === 'delete_exercise' && $id) {
            $stmt = $this->pdo->prepare("DELETE FROM exercises_master WHERE id = ?");
            $stmt->execute([$id]);
            return "Exercises source deleted.";
        }
        if ($action === 'toggle_exercise' && $id) {
           
            $stmt = $this->pdo->prepare("UPDATE exercises_master SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);
            return "Exercises status updated.";
        }

        return '';
    }

    public function getExercisesSources($search = '', $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM exercises_master WHERE name LIKE ? ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(["%$search%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countExercisesSources($search = '') {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM exercises_master WHERE name LIKE ?");
        $stmt->execute(["%$search%"]);
        return $stmt->fetchColumn();
    }
    
}
