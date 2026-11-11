<?php

class ModulePermissionController
{
    private $pdo;
    private $roles = ['Admin', 'Doctor', 'Receptionist', 'Patient'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function isValidRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function getModulesWithPermissions(): array
    {
        $stmt = $this->pdo->query(
            "SELECT m.id AS module_id, m.module_key, m.name AS module_name, m.category,
                    m.description, m.is_protected, m.sort_order AS module_sort,
                    p.id AS permission_id, p.permission_key, p.action_key,
                    p.name AS permission_name, p.sort_order AS permission_sort
             FROM system_modules m
             LEFT JOIN module_permissions p ON p.module_id = m.id AND p.is_active = 1
             WHERE m.is_active = 1
             ORDER BY m.sort_order, m.id, p.sort_order, p.id"
        );

        $modules = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $moduleKey = $row['module_key'];
            if (!isset($modules[$moduleKey])) {
                $modules[$moduleKey] = [
                    'id' => (int) $row['module_id'],
                    'key' => $moduleKey,
                    'label' => $row['module_name'],
                    'category' => $row['category'],
                    'description' => $row['description'] ?? '',
                    'protected' => (bool) $row['is_protected'],
                    'permissions' => [],
                ];
            }

            if (!empty($row['permission_id'])) {
                $modules[$moduleKey]['permissions'][$row['permission_key']] = [
                    'id' => (int) $row['permission_id'],
                    'key' => $row['permission_key'],
                    'action_key' => $row['action_key'],
                    'label' => $row['permission_name'],
                ];
            }
        }

        return $modules;
    }

    public function getRoleConfiguration(string $role): array
    {
        if (!$this->isValidRole($role)) {
            throw new InvalidArgumentException('Invalid role selected.');
        }

        $moduleStmt = $this->pdo->prepare(
            "SELECT m.module_key
             FROM role_module_settings rms
             INNER JOIN system_modules m ON m.id = rms.module_id
             WHERE rms.role_name = ? AND rms.is_enabled = 1"
        );
        $moduleStmt->execute([$role]);

        $permissionStmt = $this->pdo->prepare(
            "SELECT p.permission_key
             FROM role_permission_grants rpg
             INNER JOIN module_permissions p ON p.id = rpg.permission_id
             WHERE rpg.role_name = ? AND rpg.is_allowed = 1"
        );
        $permissionStmt->execute([$role]);

        return [
            'enabled_modules' => array_fill_keys($moduleStmt->fetchAll(PDO::FETCH_COLUMN), true),
            'allowed_permissions' => array_fill_keys($permissionStmt->fetchAll(PDO::FETCH_COLUMN), true),
        ];
    }

    public function saveRoleConfiguration(string $role, array $submittedModules, int $changedBy): void
    {
        if (!$this->isValidRole($role)) {
            throw new InvalidArgumentException('Invalid role selected.');
        }

        $catalog = $this->getModulesWithPermissions();
        $enabledModuleIds = [];
        $allowedPermissionIds = [];

        foreach ($catalog as $moduleKey => $module) {
            if ($module['protected']) {
                continue;
            }

            $submittedModule = isset($submittedModules[$moduleKey]) && is_array($submittedModules[$moduleKey])
                ? $submittedModules[$moduleKey]
                : [];
            $enabled = isset($submittedModule['enabled']) && $submittedModule['enabled'] === '1';

            if (!$enabled) {
                continue;
            }

            $enabledModuleIds[] = $module['id'];
            $submittedPermissions = isset($submittedModule['permissions']) && is_array($submittedModule['permissions'])
                ? $submittedModule['permissions']
                : [];

            foreach ($submittedPermissions as $permissionKey) {
                if (is_string($permissionKey) && isset($module['permissions'][$permissionKey])) {
                    $allowedPermissionIds[$module['permissions'][$permissionKey]['id']] = true;
                }
            }
        }

        $previous = $this->getRoleConfiguration($role);

        try {
            $this->pdo->beginTransaction();

            $moduleStmt = $this->pdo->prepare(
                "INSERT INTO role_module_settings (role_name, module_id, is_enabled, updated_by)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), updated_by = VALUES(updated_by)"
            );

            foreach ($catalog as $module) {
                if ($module['protected']) {
                    continue;
                }
                $moduleStmt->execute([
                    $role,
                    $module['id'],
                    in_array($module['id'], $enabledModuleIds, true) ? 1 : 0,
                    $changedBy > 0 ? $changedBy : null,
                ]);
            }

            $deleteStmt = $this->pdo->prepare(
                "DELETE rpg
                 FROM role_permission_grants rpg
                 INNER JOIN module_permissions p ON p.id = rpg.permission_id
                 INNER JOIN system_modules m ON m.id = p.module_id
                 WHERE rpg.role_name = ? AND m.is_protected = 0"
            );
            $deleteStmt->execute([$role]);

            if (!empty($allowedPermissionIds)) {
                $grantStmt = $this->pdo->prepare(
                    "INSERT INTO role_permission_grants (role_name, permission_id, is_allowed, updated_by)
                     VALUES (?, ?, 1, ?)"
                );
                foreach (array_keys($allowedPermissionIds) as $permissionId) {
                    $grantStmt->execute([$role, $permissionId, $changedBy > 0 ? $changedBy : null]);
                }
            }

            $current = $this->getRoleConfiguration($role);
            $auditStmt = $this->pdo->prepare(
                "INSERT INTO permission_change_log
                    (role_name, changed_by, previous_configuration, new_configuration)
                 VALUES (?, ?, ?, ?)"
            );
            $auditStmt->execute([
                $role,
                $changedBy > 0 ? $changedBy : null,
                json_encode($previous, JSON_UNESCAPED_SLASHES),
                json_encode($current, JSON_UNESCAPED_SLASHES),
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}

