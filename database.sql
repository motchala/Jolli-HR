-- JolliHR database
-- Import once: phpMyAdmin > Import > choose this file (or: mysql -u root < database.sql)
-- Safe to re-import: tables use IF NOT EXISTS and seed rows use INSERT IGNORE.
-- NOTE: includes/db.php also runs this file automatically if the tables are missing,
-- so avoid semicolons inside string values below.


CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name    VARCHAR(50)  NOT NULL,
  last_name     VARCHAR(50)  NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS jobs (
  id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  title      VARCHAR(100)     NOT NULL,
  branch     VARCHAR(150)     NOT NULL,
  tags       VARCHAR(255)     NOT NULL DEFAULT '',  -- comma-separated, e.g. 'Student Friendly,Career Growth'
  hours      VARCHAR(100)     NOT NULL,
  distance   VARCHAR(20)      NOT NULL,             -- display text, e.g. '2.5 km'
  match_pct  TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- shown as "92% Match" on the job card
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  job_id     INT UNSIGNED NOT NULL,
  status     ENUM('Applied','Docs Verified','Training','Hired') NOT NULL DEFAULT 'Applied',
  applied_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_applications_user_job (user_id, job_id),
  CONSTRAINT fk_applications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_applications_job  FOREIGN KEY (job_id)  REFERENCES jobs (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE applications
  MODIFY COLUMN status
    ENUM('Applied','Docs Verified','Training','Hired','Rejected')
    NOT NULL DEFAULT 'Applied';

-- Jobs (the 4 listings that used to be hardcoded in jobs.php)
INSERT IGNORE INTO jobs (id, title, branch, tags, hours, distance, match_pct) VALUES
  (1, 'Crew Member',  'Jollibee – Sta. Rosa',  'Student Friendly,No Experience Needed', 'Part-time · Flexible Schedule', '2.5 km', 92),
  (2, 'Service Crew', 'Jollibee – Biñan',      'Student Friendly,Career Growth',        'Part-time · Flexible Schedule', '3.1 km', 88),
  (3, 'Kitchen Crew', 'Jollibee – Southmall',  'Meal Allowance,Training Provided',      'Part-time · Flexible Schedule', '4 km',   85),
  (4, 'Cashier',      'Jollibee – Sta. Rosa',  'Student Friendly,Flexible Hours',       'Part-time · Flexible Schedule', '2.5 km', 79);

-- Demo account (shamea@jollihr.test / shamea123) so it gets a real user_id on login
INSERT IGNORE INTO users (first_name, last_name, email, password_hash) VALUES
  ('Shamea', 'Dela Cruz', 'shamea@jollihr.test', '$2y$10$FLY0wpbRR18TJgaUlnTWnuqnwY.88bSITHcnS5U.ZENQ6O1TxE65.');

-- Demo admin account (admin@jollihr.test / admin123)
INSERT IGNORE INTO users (first_name, last_name, email, password_hash, is_admin) VALUES
  ('Admin', 'HR', 'admin@jollihr.test',
   '$2y$10$cZS4ryhi8HBg3.5hfVjWUePbmJUH812ExaMedtbAJIZQZLTUmZJYS', 1);

-- ───────────────────────────────────────────────────────────────
-- New tables for dynamic Profile, Training, and Schedule pages
-- ───────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS user_profiles (
  user_id            INT UNSIGNED     NOT NULL,
  university         VARCHAR(150)     NOT NULL DEFAULT '',
  programme          VARCHAR(100)     NOT NULL DEFAULT '',
  year_level         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  available_days     VARCHAR(50)      NOT NULL DEFAULT '',  -- comma-separated e.g. 'Mon,Tue,Thu'
  preferred_shift    VARCHAR(50)      NOT NULL DEFAULT '',
  max_hours_per_week TINYINT UNSIGNED NOT NULL DEFAULT 20,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_documents (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  document_name VARCHAR(100) NOT NULL,
  verified      TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  CONSTRAINT fk_ud_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_modules (
  id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  name          VARCHAR(100)     NOT NULL,
  description   VARCHAR(255)     NOT NULL DEFAULT '',
  display_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_training (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  module_id    INT UNSIGNED NOT NULL,
  status       ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
  completed_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ut (user_id, module_id),
  CONSTRAINT fk_ut_user   FOREIGN KEY (user_id)   REFERENCES users (id)            ON DELETE CASCADE,
  CONSTRAINT fk_ut_module FOREIGN KEY (module_id) REFERENCES training_modules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_slots (
  id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED     NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,  -- 0=Mon, 1=Tue, … 6=Sun
  start_time  TIME             NOT NULL,
  end_time    TIME             NOT NULL,
  label       VARCHAR(100)     NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  CONSTRAINT fk_cs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_shifts (
  id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED     NOT NULL,
  shift_name  VARCHAR(100)     NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,  -- 0=Mon, 1=Tue, … 6=Sun
  start_time  TIME             NOT NULL,
  end_time    TIME             NOT NULL,
  branch      VARCHAR(150)     NOT NULL DEFAULT '',
  status      ENUM('available','selected') NOT NULL DEFAULT 'available',
  PRIMARY KEY (id),
  CONSTRAINT fk_ws_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mood_entries (
  id       INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  user_id  INT UNSIGNED     NOT NULL,
  mood     ENUM('Great','Okay','Tired') NOT NULL,
  iso_year SMALLINT UNSIGNED NOT NULL,
  iso_week TINYINT UNSIGNED  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_me (user_id, iso_year, iso_week),
  CONSTRAINT fk_me_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training modules (global seed)
INSERT IGNORE INTO training_modules (id, name, description, display_order) VALUES
  (1, 'Orientation',       'Welcome to the team',           1),
  (2, 'Food Safety',       'Safe food, happy customers',    2),
  (3, 'Customer Service',  'Creating great experiences',    3),
  (4, 'POS System',        'Basic transactions',            4),
  (5, 'Workplace Safety',  'A safer workplace for all',     5);

-- Demo account seed data (resolved via sub-select so user_id is always correct)
INSERT IGNORE INTO user_profiles (user_id, university, programme, year_level, available_days, preferred_shift, max_hours_per_week)
  SELECT id, 'PUP Binan Campus', 'BSBA', 4, 'Mon,Tue,Thu,Fri,Sat', 'Morning', 20
  FROM users WHERE email = 'shamea@jollihr.test';

INSERT IGNORE INTO user_documents (user_id, document_name, verified)
  SELECT id, 'Certificate of Registration (COR)', 1 FROM users WHERE email = 'shamea@jollihr.test';
INSERT IGNORE INTO user_documents (user_id, document_name, verified)
  SELECT id, 'Valid ID', 1 FROM users WHERE email = 'shamea@jollihr.test';
INSERT IGNORE INTO user_documents (user_id, document_name, verified)
  SELECT id, 'NBI Clearance', 0 FROM users WHERE email = 'shamea@jollihr.test';
INSERT IGNORE INTO user_documents (user_id, document_name, verified)
  SELECT id, 'Medical Certificate', 0 FROM users WHERE email = 'shamea@jollihr.test';

INSERT IGNORE INTO user_training (user_id, module_id, status, completed_at)
  SELECT id, 1, 'completed', '2026-10-01 09:00:00' FROM users WHERE email = 'shamea@jollihr.test';
INSERT IGNORE INTO user_training (user_id, module_id, status, completed_at)
  SELECT id, 2, 'completed', '2026-10-05 10:00:00' FROM users WHERE email = 'shamea@jollihr.test';
INSERT IGNORE INTO user_training (user_id, module_id, status, completed_at)
  SELECT id, 3, 'completed', '2026-10-10 11:00:00' FROM users WHERE email = 'shamea@jollihr.test';
INSERT IGNORE INTO user_training (user_id, module_id, status, completed_at)
  SELECT id, 4, 'in_progress', NULL FROM users WHERE email = 'shamea@jollihr.test';
INSERT IGNORE INTO user_training (user_id, module_id, status, completed_at)
  SELECT id, 5, 'not_started', NULL FROM users WHERE email = 'shamea@jollihr.test';

INSERT IGNORE INTO work_shifts (user_id, shift_name, day_of_week, start_time, end_time, branch, status)
  SELECT id, 'Morning Shift',   0, '07:00:00', '11:00:00', 'Jollibee - Sta. Rosa', 'available' FROM users WHERE email = 'shamea@jollihr.test';
INSERT IGNORE INTO work_shifts (user_id, shift_name, day_of_week, start_time, end_time, branch, status)
  SELECT id, 'Afternoon Shift', 1, '14:00:00', '18:00:00', 'Jollibee - Sta. Rosa', 'available' FROM users WHERE email = 'shamea@jollihr.test';
INSERT IGNORE INTO work_shifts (user_id, shift_name, day_of_week, start_time, end_time, branch, status)
  SELECT id, 'Evening Shift',   2, '17:00:00', '21:00:00', 'Jollibee - Binan',     'available' FROM users WHERE email = 'shamea@jollihr.test';

-- ───────────────────────────────────────────────────────────────
-- Notifications
-- ───────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  message    VARCHAR(255) NOT NULL,
  is_read    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset tokens
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  token      CHAR(64)     NOT NULL,
  expires_at DATETIME     NOT NULL,
  used       TINYINT(1)   NOT NULL DEFAULT 0,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pr_token (token),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
