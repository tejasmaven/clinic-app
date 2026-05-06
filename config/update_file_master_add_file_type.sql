ALTER TABLE file_master
  ADD COLUMN file_type_id INT NULL AFTER file_name,
  ADD INDEX idx_file_master_file_type_id (file_type_id),
  ADD CONSTRAINT fk_file_master_file_type
    FOREIGN KEY (file_type_id) REFERENCES patient_report_file_types(id)
    ON DELETE SET NULL;
