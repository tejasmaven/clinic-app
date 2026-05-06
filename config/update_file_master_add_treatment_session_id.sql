ALTER TABLE file_master
  ADD COLUMN treatment_session_id INT NULL AFTER file_type_id,
  ADD INDEX idx_file_master_treatment_session_id (treatment_session_id),
  ADD CONSTRAINT fk_file_master_treatment_session
    FOREIGN KEY (treatment_session_id) REFERENCES treatment_sessions(id)
    ON DELETE SET NULL;
