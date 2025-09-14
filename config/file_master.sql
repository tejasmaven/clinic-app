CREATE TABLE file_master (
  file_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  upload_date DATETIME NOT NULL,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

