CREATE TABLE IF NOT EXISTS system_modules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  module_key VARCHAR(100) NOT NULL,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_protected TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_system_modules_key (module_key)
);

CREATE TABLE IF NOT EXISTS module_permissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  module_id INT UNSIGNED NOT NULL,
  permission_key VARCHAR(150) NOT NULL,
  action_key VARCHAR(80) NOT NULL,
  name VARCHAR(150) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_module_permissions_key (permission_key),
  UNIQUE KEY uq_module_action (module_id, action_key),
  CONSTRAINT fk_module_permissions_module FOREIGN KEY (module_id) REFERENCES system_modules(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS role_module_settings (
  role_name VARCHAR(50) NOT NULL,
  module_id INT UNSIGNED NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 0,
  updated_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (role_name, module_id),
  CONSTRAINT fk_role_module_settings_module FOREIGN KEY (module_id) REFERENCES system_modules(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS role_permission_grants (
  role_name VARCHAR(50) NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  is_allowed TINYINT(1) NOT NULL DEFAULT 1,
  updated_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (role_name, permission_id),
  CONSTRAINT fk_role_permission_grants_permission FOREIGN KEY (permission_id) REFERENCES module_permissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS permission_change_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(50) NOT NULL,
  changed_by INT DEFAULT NULL,
  previous_configuration JSON DEFAULT NULL,
  new_configuration JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_permission_change_role_created (role_name, created_at),
  INDEX idx_permission_change_actor_created (changed_by, created_at)
);

INSERT INTO system_modules
  (id, module_key, name, category, description, sort_order, is_protected, is_active)
VALUES
  (1, 'dashboards', 'Dashboard', 'Access & Administration', 'Role-specific operational dashboard and summary widgets.', 10, 0, 1),
  (2, 'user_management', 'User Management', 'Access & Administration', 'Doctor and receptionist accounts and account status.', 20, 0, 1),
  (3, 'patient_records', 'Patient Records', 'Patients & Care', 'Patient directory, intake details, clinical fields and record lifecycle.', 30, 0, 1),
  (4, 'patient_credentials', 'Patient Credentials', 'Patients & Care', 'Patient portal password status and password reset.', 40, 0, 1),
  (5, 'patient_documents', 'Patient Documents', 'Patients & Care', 'Uploaded patient reports and treatment attachments.', 50, 0, 1),
  (6, 'treatment_episodes', 'Treatment Episodes', 'Patients & Care', 'Treatment episode details, fees and status.', 60, 0, 1),
  (7, 'treatment_sessions', 'Treatment Sessions', 'Patients & Care', 'Treatment history, exercises, machines, notes and files.', 70, 0, 1),
  (8, 'patient_assessments', 'Patient Assessments', 'Patients & Care', 'Patient assessment answers, notes and voice entry.', 80, 0, 1),
  (9, 'payments', 'Payments', 'Finance & Reporting', 'Patient ledger, receipts, payment entries and treatment charges.', 90, 0, 1),
  (10, 'financial_reports', 'Financial Reports', 'Finance & Reporting', 'Clinic financial summaries, balances and transaction history.', 100, 0, 1),
  (11, 'progress_tracker', 'Progress Tracker', 'Finance & Reporting', 'Weekly patient, treatment and payment trends.', 110, 0, 1),
  (12, 'medical_reports', 'Medical Reports', 'Finance & Reporting', 'Patient treatment summaries for review and printing.', 120, 0, 1),
  (13, 'payment_slip_template', 'Payment Slip Template', 'Finance & Reporting', 'Global payment receipt fields, labels and branding.', 130, 0, 1),
  (14, 'referral_sources', 'Referral Sources', 'Libraries & Setup', 'Clinic referral directory used during patient intake.', 140, 0, 1),
  (15, 'patient_report_file_types', 'Patient Report File Types', 'Libraries & Setup', 'Categories available when patient documents are uploaded.', 150, 0, 1),
  (16, 'exercise_library', 'Exercise Library', 'Libraries & Setup', 'Master exercise list used while recording treatment.', 160, 0, 1),
  (17, 'machine_library', 'Machine Library', 'Libraries & Setup', 'Master machine list and default treatment durations.', 170, 0, 1),
  (18, 'exercise_groups', 'Exercise Groups', 'Libraries & Setup', 'Reusable groups of exercises and machines.', 180, 0, 1),
  (19, 'assessment_setup', 'Assessment Setup', 'Libraries & Setup', 'Global assessment sections, questions and availability.', 190, 0, 1),
  (20, 'system_configuration', 'System Configuration', 'Protected Super Admin', 'Global site name and logo settings.', 200, 1, 1),
  (21, 'audit_logs', 'Audit Logs', 'Protected Super Admin', 'Application activity, errors and permission-change history.', 210, 1, 1),
  (22, 'patient_self_service', 'Patient Self-Service', 'Patient Portal', 'Patient access to their own dashboard, history and account.', 220, 0, 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name), category = VALUES(category), description = VALUES(description),
  sort_order = VALUES(sort_order), is_protected = VALUES(is_protected), is_active = VALUES(is_active);

INSERT INTO module_permissions
  (id, module_id, permission_key, action_key, name, sort_order, is_active)
VALUES
  (1, 1, 'dashboard.admin.view', 'admin_view', 'Admin Dashboard', 10, 1),
  (2, 1, 'dashboard.doctor.view', 'doctor_view', 'Doctor Dashboard', 20, 1),
  (3, 1, 'dashboard.reception.view', 'reception_view', 'Reception Dashboard', 30, 1),
  (4, 1, 'dashboard.patient.view', 'patient_view', 'Patient Dashboard', 40, 1),
  (5, 2, 'users.view', 'view', 'View', 10, 1),
  (6, 2, 'users.create', 'create', 'Create', 20, 1),
  (7, 2, 'users.edit', 'edit', 'Edit', 30, 1),
  (8, 2, 'users.status', 'status', 'Activate / Deactivate', 40, 1),
  (9, 2, 'users.delete', 'delete', 'Delete', 50, 1),
  (10, 2, 'users.restore', 'restore', 'Restore', 60, 1),
  (11, 3, 'patients.view', 'view', 'View', 10, 1),
  (12, 3, 'patients.create', 'create', 'Create', 20, 1),
  (13, 3, 'patients.edit', 'edit', 'Edit', 30, 1),
  (14, 3, 'patients.delete', 'delete', 'Delete', 40, 1),
  (15, 3, 'patients.search', 'search', 'Search', 50, 1),
  (16, 4, 'patient_password.view_status', 'view_status', 'View Password Status', 10, 1),
  (17, 4, 'patient_password.reset', 'reset', 'Create / Reset Password', 20, 1),
  (18, 5, 'patient_files.list', 'list', 'View List', 10, 1),
  (19, 5, 'patient_files.upload', 'upload', 'Upload', 20, 1),
  (20, 5, 'patient_files.download', 'download', 'Download', 30, 1),
  (21, 6, 'episodes.view', 'view', 'View', 10, 1),
  (22, 6, 'episodes.create', 'create', 'Create', 20, 1),
  (23, 6, 'episodes.edit', 'edit', 'Edit', 30, 1),
  (24, 6, 'episodes.status', 'status', 'Change Status', 40, 1),
  (25, 7, 'sessions.view', 'view', 'View', 10, 1),
  (26, 7, 'sessions.create', 'create', 'Create', 20, 1),
  (27, 7, 'sessions.edit', 'edit', 'Edit', 30, 1),
  (28, 7, 'sessions.delete', 'delete', 'Delete', 40, 1),
  (29, 7, 'sessions.copy', 'copy', 'Copy Previous', 50, 1),
  (30, 7, 'sessions.files', 'files', 'Manage Files', 60, 1),
  (31, 8, 'patient_assessment.view', 'view', 'View', 10, 1),
  (32, 8, 'patient_assessment.save', 'save', 'Create / Edit', 20, 1),
  (33, 8, 'patient_assessment.voice', 'voice', 'Voice Entry', 30, 1),
  (34, 9, 'payments.view', 'view', 'View Ledger', 10, 1),
  (35, 9, 'payments.receive', 'receive', 'Record Payment', 20, 1),
  (36, 9, 'payments.edit_payment', 'edit_payment', 'Edit Payment', 30, 1),
  (37, 9, 'payments.delete_payment', 'delete_payment', 'Delete Payment', 40, 1),
  (38, 9, 'payments.edit_charge', 'edit_charge', 'Edit Charges', 50, 1),
  (39, 9, 'payments.receipt', 'receipt', 'Print Receipt', 60, 1),
  (40, 10, 'financial_reports.view', 'view', 'View', 10, 1),
  (41, 10, 'financial_reports.export', 'export', 'Export', 20, 1),
  (42, 10, 'financial_reports.history', 'history', 'View History', 30, 1),
  (43, 11, 'progress_tracker.view', 'view', 'View', 10, 1),
  (44, 12, 'medical_reports.view', 'view', 'View', 10, 1),
  (45, 12, 'medical_reports.generate', 'generate', 'Generate', 20, 1),
  (46, 12, 'medical_reports.print', 'print', 'Print', 30, 1),
  (47, 13, 'payment_template.view', 'view', 'View', 10, 1),
  (48, 13, 'payment_template.update', 'update', 'Update', 20, 1),
  (49, 14, 'referrals.view', 'view', 'View', 10, 1),
  (50, 14, 'referrals.create', 'create', 'Create', 20, 1),
  (51, 14, 'referrals.edit', 'edit', 'Edit', 30, 1),
  (52, 14, 'referrals.delete', 'delete', 'Delete', 40, 1),
  (53, 15, 'report_file_types.view', 'view', 'View', 10, 1),
  (54, 15, 'report_file_types.create', 'create', 'Create', 20, 1),
  (55, 15, 'report_file_types.edit', 'edit', 'Edit', 30, 1),
  (56, 15, 'report_file_types.delete', 'delete', 'Delete', 40, 1),
  (57, 16, 'exercises.view', 'view', 'View', 10, 1),
  (58, 16, 'exercises.create', 'create', 'Create', 20, 1),
  (59, 16, 'exercises.edit', 'edit', 'Edit', 30, 1),
  (60, 16, 'exercises.delete', 'delete', 'Delete', 40, 1),
  (61, 16, 'exercises.status', 'status', 'Activate / Deactivate', 50, 1),
  (62, 17, 'machines.view', 'view', 'View', 10, 1),
  (63, 17, 'machines.create', 'create', 'Create', 20, 1),
  (64, 17, 'machines.edit', 'edit', 'Edit', 30, 1),
  (65, 17, 'machines.delete', 'delete', 'Delete', 40, 1),
  (66, 17, 'machines.status', 'status', 'Activate / Deactivate', 50, 1),
  (67, 18, 'exercise_groups.view', 'view', 'View', 10, 1),
  (68, 18, 'exercise_groups.create', 'create', 'Create', 20, 1),
  (69, 18, 'exercise_groups.edit', 'edit', 'Edit', 30, 1),
  (70, 18, 'exercise_groups.delete', 'delete', 'Delete', 40, 1),
  (71, 18, 'exercise_groups.status', 'status', 'Activate / Deactivate', 50, 1),
  (72, 19, 'assessment_setup.view', 'view', 'View', 10, 1),
  (73, 19, 'assessment_setup.create_section', 'create_section', 'Create Section', 20, 1),
  (74, 19, 'assessment_setup.edit_section', 'edit_section', 'Edit Section', 30, 1),
  (75, 19, 'assessment_setup.status', 'status', 'Activate / Deactivate', 40, 1),
  (76, 19, 'assessment_setup.delete', 'delete', 'Delete', 50, 1),
  (77, 19, 'assessment_setup.questions', 'questions', 'Manage Questions', 60, 1),
  (78, 20, 'configuration.view', 'view', 'View', 10, 1),
  (79, 20, 'configuration.site_name', 'site_name', 'Change Site Name', 20, 1),
  (80, 20, 'configuration.logo', 'logo', 'Replace Logo', 30, 1),
  (81, 21, 'audit_logs.view', 'view', 'View', 10, 1),
  (82, 21, 'audit_logs.filter', 'filter', 'Search / Filter', 20, 1),
  (83, 22, 'patient_portal.dashboard', 'dashboard', 'Own Dashboard', 10, 1),
  (84, 22, 'patient_portal.history', 'history', 'Own History', 20, 1),
  (85, 22, 'patient_portal.payments', 'payments', 'Own Payments', 30, 1),
  (86, 22, 'patient_portal.password', 'password', 'Change Password', 40, 1)
ON DUPLICATE KEY UPDATE
  module_id = VALUES(module_id), permission_key = VALUES(permission_key), action_key = VALUES(action_key),
  name = VALUES(name), sort_order = VALUES(sort_order), is_active = VALUES(is_active);

INSERT INTO role_module_settings (role_name, module_id, is_enabled)
SELECT 'Admin', id, 1 FROM system_modules WHERE id BETWEEN 1 AND 19
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);
INSERT INTO role_module_settings (role_name, module_id, is_enabled)
SELECT 'Doctor', id, 1 FROM system_modules WHERE id IN (1,3,5,6,7,8,12,16,17,18,19)
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);
INSERT INTO role_module_settings (role_name, module_id, is_enabled)
SELECT 'Receptionist', id, 1 FROM system_modules WHERE id IN (1,3,5,8,9,14,15)
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);
INSERT INTO role_module_settings (role_name, module_id, is_enabled)
SELECT 'Patient', id, 1 FROM system_modules WHERE id IN (1,22)
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

INSERT INTO role_permission_grants (role_name, permission_id, is_allowed)
SELECT 'Admin', id, 1 FROM module_permissions WHERE id = 1 OR module_id BETWEEN 2 AND 19
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);
INSERT INTO role_permission_grants (role_name, permission_id, is_allowed)
SELECT 'Doctor', id, 1 FROM module_permissions
WHERE id IN (2,11,12,13,15,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,44,45,46,57,62,67,72)
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);
INSERT INTO role_permission_grants (role_name, permission_id, is_allowed)
SELECT 'Receptionist', id, 1 FROM module_permissions
WHERE id IN (3,11,12,13,15,18,19,20,31,34,35,36,37,38,39,49,53)
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);
INSERT INTO role_permission_grants (role_name, permission_id, is_allowed)
SELECT 'Patient', id, 1 FROM module_permissions WHERE id IN (4,83,84,85,86)
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

