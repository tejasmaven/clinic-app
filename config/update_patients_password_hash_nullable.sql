-- Allow patients to be created before their portal password is set.
ALTER TABLE patients
  MODIFY password_hash VARCHAR(255) DEFAULT NULL;
