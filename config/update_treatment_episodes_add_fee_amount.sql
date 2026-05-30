-- Store the per-session fee configured when an episode is created.
ALTER TABLE treatment_episodes
  ADD COLUMN fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER initial_complaints;

-- Backfill existing episode-level fees so future sessions can use them.
UPDATE treatment_episodes te
JOIN (
  SELECT episode_id, MAX(amount) AS fee_amount
  FROM patient_payment_ledger
  WHERE transaction_type = 'charge'
    AND session_reference LIKE 'episode:%'
    AND episode_id IS NOT NULL
  GROUP BY episode_id
) existing_fees ON existing_fees.episode_id = te.id
SET te.fee_amount = existing_fees.fee_amount;
