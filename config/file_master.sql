CREATE TABLE file_master (
  file_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_type_id INT NULL,
  treatment_session_id INT NULL,
  upload_date DATETIME NOT NULL,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (file_type_id) REFERENCES patient_report_file_types(id) ON DELETE SET NULL,
  FOREIGN KEY (treatment_session_id) REFERENCES treatment_sessions(id) ON DELETE SET NULL
);

