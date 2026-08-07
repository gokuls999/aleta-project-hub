-- Part 6 (templates) + Part 7 (journals). Idempotent where possible (MariaDB).
SET NAMES utf8mb4;

ALTER TABLE projects
  ADD COLUMN IF NOT EXISTS is_research TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS created_by  INT NULL;

CREATE TABLE IF NOT EXISTS project_templates (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(190) NOT NULL,
  description VARCHAR(500) NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS template_task_lists (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  template_id INT NOT NULL,
  name        VARCHAR(255) NOT NULL,
  sequence    INT NOT NULL DEFAULT 0,
  FOREIGN KEY (template_id) REFERENCES project_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS journals (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  project_id    INT NOT NULL,
  name          VARCHAR(255) NOT NULL,
  indexing      VARCHAR(120) NULL,       -- Scopus / SCI / Web of Science...
  impact_factor VARCHAR(40) NULL,
  deadline      DATE NULL,
  fee           DECIMAL(10,2) NULL,
  url           VARCHAR(500) NULL,
  notes         TEXT NULL,
  stage         ENUM('not_started','pre_check','submitted','post_audit','done') NOT NULL DEFAULT 'not_started',
  result        ENUM('pending','accepted','rejected','revise') NOT NULL DEFAULT 'pending',
  decision_date DATE NULL,
  created_by    INT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  INDEX idx_journals_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS journal_checklist (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  journal_id INT NOT NULL,
  phase      ENUM('pre','post') NOT NULL,
  item       VARCHAR(300) NOT NULL,
  is_done    TINYINT(1) NOT NULL DEFAULT 0,
  sequence   INT NOT NULL DEFAULT 0,
  FOREIGN KEY (journal_id) REFERENCES journals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS journal_checklist_defaults (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  phase    ENUM('pre','post') NOT NULL,
  item     VARCHAR(300) NOT NULL,
  sequence INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
