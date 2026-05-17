-- ============================================================
--  Task Tracker for Teams — Database Schema
--  Kelompok 7 | Teknik Komputer Undip 2026
--  Compatible: MySQL 5.7+ / MariaDB 10.3+ (XAMPP)
-- ============================================================

CREATE DATABASE IF NOT EXISTS task_tracker
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE task_tracker;

-- ------------------------------------------------------------
-- 1. USERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  name       VARCHAR(100)    NOT NULL,
  email      VARCHAR(150)    NOT NULL UNIQUE,
  password   VARCHAR(255)    NOT NULL,
  role       ENUM('admin','member') NOT NULL DEFAULT 'member',
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. PROJECTS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name        VARCHAR(150)  NOT NULL,
  description TEXT,
  created_by  INT UNSIGNED  NOT NULL,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_project_creator
    FOREIGN KEY (created_by) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. PROJECT_MEMBERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS project_members (
  id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED  NOT NULL,
  user_id    INT UNSIGNED  NOT NULL,
  joined_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_project_member (project_id, user_id),
  CONSTRAINT fk_pm_project
    FOREIGN KEY (project_id) REFERENCES projects (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_pm_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. TASK_CATEGORIES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS task_categories (
  id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED  NOT NULL,
  name       VARCHAR(100)  NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_cat_project
    FOREIGN KEY (project_id) REFERENCES projects (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. TASKS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
  id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  project_id  INT UNSIGNED   NOT NULL,
  assigned_to INT UNSIGNED,
  created_by  INT UNSIGNED   NOT NULL,
  title       VARCHAR(200)   NOT NULL,
  description TEXT,
  deadline    DATE,
  status      ENUM('todo','in_progress','done') NOT NULL DEFAULT 'todo',
  created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_task_project
    FOREIGN KEY (project_id) REFERENCES projects (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_task_assignee
    FOREIGN KEY (assigned_to) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_task_creator
    FOREIGN KEY (created_by) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. TASK_CATEGORY_MAP
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS task_category_map (
  task_id     INT UNSIGNED  NOT NULL,
  category_id INT UNSIGNED  NOT NULL,
  PRIMARY KEY (task_id, category_id),
  CONSTRAINT fk_tcm_task
    FOREIGN KEY (task_id) REFERENCES tasks (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_tcm_category
    FOREIGN KEY (category_id) REFERENCES task_categories (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tambah kolom role di project_members
ALTER TABLE project_members
  ADD COLUMN role ENUM('admin','member') NOT NULL DEFAULT 'member'
  AFTER user_id;

-- Creator proyek otomatis jadi admin di proyeknya sendiri
UPDATE project_members pm
JOIN projects p ON p.id = pm.project_id
SET pm.role = 'admin'
WHERE pm.user_id = p.created_by;

-- ============================================================
--  Update: Hapus creator dari project_members
--  (creator proyek tidak boleh muncul sebagai member)
-- ============================================================
DELETE pm FROM project_members pm
JOIN projects p ON p.id = pm.project_id
WHERE pm.user_id = p.created_by;

-- Hapus creator dari project_members (data lama)
DELETE pm FROM project_members pm
JOIN projects p ON p.id = pm.project_id
WHERE pm.user_id = p.created_by;