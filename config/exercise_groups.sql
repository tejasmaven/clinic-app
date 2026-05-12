CREATE TABLE IF NOT EXISTS exercise_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_exercise_groups_title (title),
    KEY idx_exercise_groups_active_title (is_active, title),
    CONSTRAINT fk_exercise_groups_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exercise_group_exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    exercise_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_group_exercise (group_id, exercise_id),
    KEY idx_group_exercises_exercise_id (exercise_id),
    CONSTRAINT fk_group_exercises_group
        FOREIGN KEY (group_id) REFERENCES exercise_groups(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_group_exercises_exercise
        FOREIGN KEY (exercise_id) REFERENCES exercises_master(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exercise_group_machines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    machine_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_group_machine (group_id, machine_id),
    KEY idx_group_machines_machine_id (machine_id),
    CONSTRAINT fk_group_machines_group
        FOREIGN KEY (group_id) REFERENCES exercise_groups(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_group_machines_machine
        FOREIGN KEY (machine_id) REFERENCES machines(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
