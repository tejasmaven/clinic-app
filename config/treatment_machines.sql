CREATE TABLE IF NOT EXISTS treatment_machines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    machine_id INT NULL,
    machine_name VARCHAR(255) DEFAULT NULL,
    duration_minutes INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES treatment_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE SET NULL
);
