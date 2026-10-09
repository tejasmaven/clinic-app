-- Create or update the hidden super admin account.
-- 1. Generate a PHP password hash:
--    php -r "echo password_hash('change-this-password', PASSWORD_DEFAULT), PHP_EOL;"
-- 2. Replace the values below before running this script.

SET @super_admin_name = 'Super Admin';
SET @super_admin_email = 'superadmin@example.com';
SET @super_admin_password_hash = 'REPLACE_WITH_PASSWORD_HASH';

UPDATE users
SET
  name = @super_admin_name,
  role = 'Super Admin',
  password_hash = @super_admin_password_hash,
  is_active = 1,
  is_deleted = 0
WHERE email = @super_admin_email;

INSERT INTO users (name, email, role, password_hash, is_active, is_deleted)
SELECT
  @super_admin_name,
  @super_admin_email,
  'Super Admin',
  @super_admin_password_hash,
  1,
  0
WHERE NOT EXISTS (
  SELECT 1
  FROM users
  WHERE email = @super_admin_email
);
