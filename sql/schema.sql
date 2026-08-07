-- Aleta Work Tracker — database schema (MySQL / MariaDB, XAMPP + GoDaddy cPanel compatible)
-- Import into a database named `aleta_worktracker`.

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- Staff who log into the app (Zoho email + a password we set here).
CREATE TABLE IF NOT EXISTS users (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  zoho_email     VARCHAR(190) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  full_name      VARCHAR(190) NOT NULL,
  role           ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  zoho_zpuid     VARCHAR(40) NULL,           -- maps this staff to their Zoho Projects user id
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  must_reset     TINYINT(1) NOT NULL DEFAULT 1, -- force password change on first login
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mirror of Zoho Projects projects (system of record = this app; Zoho stays in sync).
CREATE TABLE IF NOT EXISTS projects (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  zoho_project_id  VARCHAR(40) NULL UNIQUE,   -- null until first push to Zoho
  name             VARCHAR(255) NOT NULL,
  status           VARCHAR(60) NULL,
  last_synced_at   DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Task lists inside a project.
CREATE TABLE IF NOT EXISTS task_lists (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  zoho_tasklist_id  VARCHAR(40) NULL UNIQUE,
  project_id        INT NOT NULL,
  name              VARCHAR(255) NOT NULL,
  status            VARCHAR(60) NULL,
  sequence          INT NULL,
  last_synced_at    DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tasks (the core unit staff work with).
CREATE TABLE IF NOT EXISTS tasks (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  zoho_task_id       VARCHAR(40) NULL UNIQUE,
  project_id         INT NOT NULL,
  task_list_id       INT NULL,
  parent_task_id     INT NULL,                 -- for subtasks
  name               VARCHAR(500) NOT NULL,
  description        TEXT NULL,
  assignee_user_id   INT NULL,                 -- FK users.id (null if the Zoho owner isn't an app user yet)
  assignee_zpuid     VARCHAR(40) NULL,         -- Zoho owner id (kept for display + mapping)
  assignee_name      VARCHAR(190) NULL,        -- Zoho owner name (display even without an app account)
  status             VARCHAR(60) NOT NULL DEFAULT 'Open',
  priority           ENUM('none','low','medium','high') NOT NULL DEFAULT 'none',
  start_date         DATE NULL,
  due_date           DATE NULL,
  completion         TINYINT NOT NULL DEFAULT 0, -- 0..100
  -- sync bookkeeping
  local_modified_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_synced_at     DATETIME NULL,
  sync_state         ENUM('new','dirty','synced') NOT NULL DEFAULT 'new',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id)       REFERENCES projects(id)  ON DELETE CASCADE,
  FOREIGN KEY (task_list_id)     REFERENCES task_lists(id) ON DELETE SET NULL,
  FOREIGN KEY (assignee_user_id) REFERENCES users(id)      ON DELETE SET NULL,
  INDEX idx_tasks_assignee (assignee_user_id),
  INDEX idx_tasks_syncstate (sync_state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Time logs against tasks (timesheets).
CREATE TABLE IF NOT EXISTS time_logs (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  zoho_log_id    VARCHAR(40) NULL UNIQUE,
  task_id        INT NOT NULL,
  user_id        INT NOT NULL,
  log_date       DATE NOT NULL,
  hours          DECIMAL(6,2) NOT NULL DEFAULT 0,
  notes          VARCHAR(500) NULL,
  billable       TINYINT(1) NOT NULL DEFAULT 0,
  sync_state     ENUM('new','dirty','synced') NOT NULL DEFAULT 'new',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_logs_syncstate (sync_state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit of each sync run (manual or the 12h auto job).
CREATE TABLE IF NOT EXISTS sync_runs (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  trigger_type ENUM('manual','auto') NOT NULL,
  started_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at  DATETIME NULL,
  status       ENUM('running','success','error') NOT NULL DEFAULT 'running',
  pulled       INT NOT NULL DEFAULT 0,
  pushed       INT NOT NULL DEFAULT 0,
  message      TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Key/value app settings (last sync time, cached Zoho tokens, etc.).
CREATE TABLE IF NOT EXISTS settings (
  skey   VARCHAR(100) PRIMARY KEY,
  svalue TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET foreign_key_checks = 1;
