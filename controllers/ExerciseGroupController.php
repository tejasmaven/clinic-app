<?php

class ExerciseGroupController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function handleActions($createdByUserId = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return '';

        $action = $_POST['action'] ?? '';
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($action === 'add_group' || ($action === 'edit_group' && $id > 0)) {
            $title = trim($_POST['title'] ?? '');
            $isActive = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
            $exerciseIds = $_POST['exercise_ids'] ?? [];
            $machineIds = $_POST['machine_ids'] ?? [];

            if ($title === '') {
                return 'Exercise group title is required.';
            }

            $exerciseIds = $this->uniquePositiveIds($exerciseIds);
            $machineIds = $this->uniquePositiveIds($machineIds);

            if (empty($exerciseIds) && empty($machineIds)) {
                return 'Please select at least one exercise or machine.';
            }

            $duplicateStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM exercise_groups WHERE title = ? AND id != ?'
            );
            $duplicateStmt->execute([$title, $action === 'edit_group' ? $id : 0]);
            if ($duplicateStmt->fetchColumn() > 0) {
                return 'Another exercise group with this title already exists.';
            }

            try {
                $this->pdo->beginTransaction();

                if ($action === 'add_group') {
                    $stmt = $this->pdo->prepare(
                        'INSERT INTO exercise_groups (title, is_active, created_by_user_id) VALUES (?, ?, ?)'
                    );
                    $stmt->execute([$title, $isActive, $createdByUserId]);
                    $id = (int) $this->pdo->lastInsertId();
                    $message = 'Exercise group added successfully.';
                } else {
                    $stmt = $this->pdo->prepare(
                        'UPDATE exercise_groups SET title = ?, is_active = ? WHERE id = ?'
                    );
                    $stmt->execute([$title, $isActive, $id]);
                    $this->pdo->prepare('DELETE FROM exercise_group_exercises WHERE group_id = ?')->execute([$id]);
                    $this->pdo->prepare('DELETE FROM exercise_group_machines WHERE group_id = ?')->execute([$id]);
                    $message = 'Exercise group updated successfully.';
                }

                $this->saveGroupItems($id, $exerciseIds, $machineIds);
                $this->pdo->commit();

                return $message;
            } catch (Exception $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return 'Error: ' . $e->getMessage();
            }
        }

        if ($action === 'toggle_group' && $id > 0) {
            $stmt = $this->pdo->prepare('UPDATE exercise_groups SET is_active = NOT is_active WHERE id = ?');
            $stmt->execute([$id]);
            return 'Exercise group status updated.';
        }

        if ($action === 'delete_group' && $id > 0) {
            $stmt = $this->pdo->prepare('DELETE FROM exercise_groups WHERE id = ?');
            $stmt->execute([$id]);
            return 'Exercise group deleted.';
        }

        return '';
    }

    public function getGroups($search = '', $page = 1, $limit = 10) {
        $offset = ((int) $page - 1) * (int) $limit;
        $sql = "SELECT eg.*, u.name AS created_by_name,
                    (SELECT COUNT(*) FROM exercise_group_exercises ege WHERE ege.group_id = eg.id) AS exercise_count,
                    (SELECT COUNT(*) FROM exercise_group_machines egm WHERE egm.group_id = eg.id) AS machine_count
                FROM exercise_groups eg
                LEFT JOIN users u ON u.id = eg.created_by_user_id
                WHERE eg.title LIKE ?
                ORDER BY eg.id DESC
                LIMIT $limit OFFSET $offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['%' . $search . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countGroups($search = '') {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM exercise_groups WHERE title LIKE ?');
        $stmt->execute(['%' . $search . '%']);
        return (int) $stmt->fetchColumn();
    }

    public function getGroupDetails($groupId) {
        $stmt = $this->pdo->prepare('SELECT * FROM exercise_groups WHERE id = ?');
        $stmt->execute([(int) $groupId]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$group) {
            return null;
        }

        $exerciseStmt = $this->pdo->prepare(
            'SELECT ege.exercise_id, em.name, em.default_reps, em.default_duration_minutes
             FROM exercise_group_exercises ege
             INNER JOIN exercises_master em ON em.id = ege.exercise_id
             WHERE ege.group_id = ?
             ORDER BY ege.sort_order ASC, em.name ASC'
        );
        $exerciseStmt->execute([(int) $groupId]);

        $machineStmt = $this->pdo->prepare(
            'SELECT egm.machine_id, m.name, m.default_duration_minutes
             FROM exercise_group_machines egm
             INNER JOIN machines m ON m.id = egm.machine_id
             WHERE egm.group_id = ?
             ORDER BY egm.sort_order ASC, m.name ASC'
        );
        $machineStmt->execute([(int) $groupId]);

        $group['exercises'] = $exerciseStmt->fetchAll(PDO::FETCH_ASSOC);
        $group['machines'] = $machineStmt->fetchAll(PDO::FETCH_ASSOC);

        return $group;
    }

    public function getActiveGroupsWithItems() {
        $stmt = $this->pdo->query(
            'SELECT id, title FROM exercise_groups WHERE is_active = 1 ORDER BY title ASC'
        );
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($groups as &$group) {
            $details = $this->getGroupDetails((int) $group['id']);
            $group['exercises'] = $details['exercises'] ?? [];
            $group['machines'] = $details['machines'] ?? [];
        }
        unset($group);

        return $groups;
    }

    public function getExerciseMaster() {
        return $this->pdo->query(
            'SELECT id, name, default_reps, default_duration_minutes FROM exercises_master WHERE is_active = 1 ORDER BY name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMachineMaster() {
        return $this->pdo->query(
            'SELECT id, name, default_duration_minutes FROM machines ORDER BY name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private function uniquePositiveIds($ids) {
        if (!is_array($ids)) {
            return [];
        }

        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }

        return array_values($clean);
    }

    private function saveGroupItems($groupId, array $exerciseIds, array $machineIds) {
        $exerciseStmt = $this->pdo->prepare(
            'INSERT INTO exercise_group_exercises (group_id, exercise_id, sort_order) VALUES (?, ?, ?)'
        );
        foreach ($exerciseIds as $sortOrder => $exerciseId) {
            $exerciseStmt->execute([$groupId, $exerciseId, $sortOrder + 1]);
        }

        $machineStmt = $this->pdo->prepare(
            'INSERT INTO exercise_group_machines (group_id, machine_id, sort_order) VALUES (?, ?, ?)'
        );
        foreach ($machineIds as $sortOrder => $machineId) {
            $machineStmt->execute([$groupId, $machineId, $sortOrder + 1]);
        }
    }
}
