-- Migration: ensure password_hash columns exist for credentialed tables
-- Adds password_hash to users and patients for storing secure hashes

-- Add password_hash to users table if it is missing
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NOT NULL AFTER role;

-- Add password_hash to patients table if it is missing
ALTER TABLE patients
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT NULL AFTER email;
