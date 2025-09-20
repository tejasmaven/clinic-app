DROP TABLE IF EXISTS payments;

CREATE TABLE IF NOT EXISTS patient_payment_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    transaction_type ENUM('charge', 'payment') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'received') NOT NULL DEFAULT 'pending',
    episode_id INT DEFAULT NULL,
    session_reference VARCHAR(100) DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS patient_credit_balances (
    patient_id INT PRIMARY KEY,
    credit_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);
