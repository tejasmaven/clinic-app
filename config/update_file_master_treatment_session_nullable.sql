-- Patient intake files are not always linked to a treatment session.
ALTER TABLE file_master
  MODIFY treatment_session_id INT NULL DEFAULT NULL;
