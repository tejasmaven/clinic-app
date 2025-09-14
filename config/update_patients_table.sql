ALTER TABLE patients
  ADD COLUMN allergy_medicines_in_use TEXT,
  ADD COLUMN family_history TEXT,
  ADD COLUMN history TEXT,
  ADD COLUMN chief_complaints TEXT,
  ADD COLUMN assessment TEXT,
  ADD COLUMN investigation TEXT,
  ADD COLUMN diagnosis TEXT,
  ADD COLUMN goal TEXT;

ALTER TABLE treatment_sessions
  ADD COLUMN advise TEXT;