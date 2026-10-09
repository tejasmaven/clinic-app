# Hiral Physiotherapy Clinic

A PHP and MySQL web application for managing clinic users, patients, treatment
episodes, treatment sessions, exercises, machines, reports, and payments.

## 1. Setup Requirements

Install the following software before setting up the application:

- **PHP 7.4 or newer** with these extensions enabled:
  - `pdo`
  - `pdo_mysql`
  - `json`
- **MySQL 8.0+** or a compatible MariaDB release.
- A web server capable of running PHP. Apache with `mod_rewrite` is recommended
  because the repository includes an `.htaccess` file. PHP's built-in server can
  also be used for local development.
- **Git** for cloning the repository.
- Internet access in the browser to load the Bootstrap and Select2 assets served
  from CDNs.
- Write permission for the web-server user in the project directory. The
  application creates patient-document directories below `uploads/` when files
  are uploaded.

The application does not use Composer or npm, so there are no PHP or JavaScript
packages to install locally.

## 2. Setup Steps

### 2.1 Clone the repository

Clone the project into a directory named `clinic-app`:

```bash
git clone <repository-url> clinic-app
cd clinic-app
```

### 2.2 Create and initialize the database

Create the local database expected by the default configuration:

```sql
CREATE DATABASE physio_clinic
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Import the clinic's **base database schema** first. The base schema must provide
the core tables, including `users`, `patients`, `treatment_episodes`,
`treatment_sessions`, `exercises_master`, and `treatment_exercises`.

> **Important:** This repository currently contains feature schemas and upgrade
> scripts in `config/`, but it does not contain a complete fresh-install base
> schema. Obtain the base schema/database export from the project maintainer
> before running the scripts below.

After importing the base schema, apply the relevant feature schemas and migration
scripts in dependency order. For a database that has not received these features,
the following order can be used:

```bash
mysql -u root -p physio_clinic < config/update_password_hash_fields.sql
mysql -u root -p physio_clinic < config/update_patients_password_hash_nullable.sql
mysql -u root -p physio_clinic < config/update_patients_table.sql
mysql -u root -p physio_clinic < config/machines.sql
mysql -u root -p physio_clinic < config/treatment_machines.sql
mysql -u root -p physio_clinic < config/patient_report_file_types.sql
mysql -u root -p physio_clinic < config/file_master.sql
mysql -u root -p physio_clinic < config/update_file_master_treatment_session_nullable.sql
mysql -u root -p physio_clinic < config/payments.sql
mysql -u root -p physio_clinic < config/update_treatment_episodes_add_fee_amount.sql
mysql -u root -p physio_clinic < config/update_treatment_sessions_add_therapist_fields.sql
mysql -u root -p physio_clinic < config/exercise_groups.sql
mysql -u root -p physio_clinic < config/app_settings.sql
mysql -u root -p physio_clinic < config/app_logs.sql
```

Do not run a migration again if the target columns or constraints already exist.
The two `update_file_master_*.sql` scripts are intended only for installations
that already have an older `file_master` table; do not run them after importing
the current `config/file_master.sql` file.

### 2.3 Configure the database connection and base path

Open `config/config.php` and set the local database values to match your MySQL
installation:

```php
$host = 'localhost';
$db = 'physio_clinic';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
```

The existing local configuration expects the application to be available at
`http://localhost:8081/clinic-app`. If you use a different host, port, or folder,
update the host conditions and `BASE_PATH` in `config/config.php`. For Apache,
also update `RewriteBase` in `.htaccess` when the application is not installed at
`/clinic-app/`.

### 2.4 Prepare the upload directory

Create the upload directory and make it writable by the web-server process:

```bash
mkdir -p uploads/patient_docs
chmod -R u+rwX uploads
```

On a shared or production server, assign the directory to the web-server user and
use the least-permissive ownership and mode suitable for that environment.

### 2.5 Start the application

For a quick local setup with PHP's built-in server, run this command from the
repository root:

```bash
php -S localhost:8081 -t ..
```

The parent directory is used as the document root because the configured local
URL includes the `/clinic-app` folder. Open:

```text
http://localhost:8081/clinic-app/
```

For Apache, place the repository below the configured document root, enable
`mod_rewrite`, allow `.htaccess` overrides, and browse to the corresponding
`/clinic-app/` URL.

### 2.6 Create a login and verify the installation

Ensure the imported `users` table contains an active, non-deleted user. Passwords
must be stored as PHP password hashes; generate one with:

```bash
php -r "echo password_hash('change-this-password', PASSWORD_DEFAULT), PHP_EOL;"
```

Store the generated value in the user's `password_hash` column. Assign one of the
roles used by the application (`Admin`, `Doctor`, or `Receptionist`), then open
the relevant login page:

- Admin: `http://localhost:8081/clinic-app/views/auth/admin_login.php`
- Doctor: `http://localhost:8081/clinic-app/views/login.php`
- Patient: `http://localhost:8081/clinic-app/views/patient/login.php`

When Apache rewriting is enabled, the shorter Admin URL
`http://localhost:8081/clinic-app/admin` is also available.

After signing in, confirm that the dashboard loads, database records can be read,
and a test upload can be saved under `uploads/patient_docs/`.

### 2.7 Create the hidden super admin login

To create a super admin that can sign in from the Admin Login screen, update the
values in `config/setup_super_admin.sql`. Generate the password hash with:

```bash
php -r "echo password_hash('change-this-password', PASSWORD_DEFAULT), PHP_EOL;"
```

Then run:

```bash
mysql -u root -p physio_clinic < config/setup_super_admin.sql
```

The super admin role has admin access throughout the application, but is hidden
from the Manage Users screen and cannot be edited, deleted, restored, or
deactivated through that screen.

After signing in as the super admin, open Admin Panel > Configuration to upload
the global JPG logo and update the site name shown in the browser title, navbar,
landing page, and login pages.

The super admin can also open Admin Panel > View Logs to search user action logs
and highlighted application error logs. Actions performed by the super admin are
not stored in the action log.
