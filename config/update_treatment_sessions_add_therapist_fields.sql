-- Add therapist assignments and additional notes to treatment sessions
ALTER TABLE treatment_sessions
  ADD COLUMN primary_therapist_id INT NULL AFTER doctor_id,
  ADD COLUMN secondary_therapist_id INT NULL AFTER primary_therapist_id,
  ADD COLUMN additional_treatment_notes TEXT NULL AFTER advise;

-- Backfill primary therapist with the doctor who logged the session
UPDATE treatment_sessions
SET primary_therapist_id = doctor_id
WHERE primary_therapist_id IS NULL;

-- Make primary therapist mandatory for all future rows
ALTER TABLE treatment_sessions
  MODIFY COLUMN primary_therapist_id INT NOT NULL;

-- Optional: enforce referential integrity with the users table
ALTER TABLE treatment_sessions
  ADD CONSTRAINT fk_treatment_sessions_primary_therapist FOREIGN KEY (primary_therapist_id) REFERENCES users(id);

ALTER TABLE treatment_sessions
  ADD CONSTRAINT fk_treatment_sessions_secondary_therapist FOREIGN KEY (secondary_therapist_id) REFERENCES users(id);
