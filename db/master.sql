-- ============================================================
--  IFQM Master Database – Tenant Registry
-- ============================================================
CREATE DATABASE IF NOT EXISTS ifqm_master CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ifqm_master;

CREATE TABLE IF NOT EXISTS tenants (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  slug          VARCHAR(50)  NOT NULL UNIQUE,
  domain        VARCHAR(255) NOT NULL,
  db_host       VARCHAR(100) NOT NULL DEFAULT 'localhost',
  db_name       VARCHAR(100) NOT NULL,
  db_user       VARCHAR(100) NOT NULL DEFAULT 'root',
  db_pass       VARCHAR(255) NOT NULL DEFAULT '',
  status        ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
  is_default    TINYINT(1) NOT NULL DEFAULT 0,
  logo_url      VARCHAR(500) NULL,
  primary_color VARCHAR(7)   NOT NULL DEFAULT '#4f46e5',
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_domain (domain)
);

-- Default IFQM tenant for local development
INSERT IGNORE INTO tenants (name, slug, domain, db_host, db_name, db_user, db_pass, status, is_default)
VALUES ('IFQM', 'ifqm', 'localhost', 'localhost', 'ifqm_ideation', 'root', '', 'active', 1);

-- ── Platform Admins (IFQM vendor staff — NOT tenant users) ────────────────
-- These are the SaaS platform operators. They live in ifqm_master,
-- not inside any tenant database, and can only see aggregate stats.
CREATE TABLE IF NOT EXISTS platform_admins (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Seed: password = "password"
INSERT IGNORE INTO platform_admins (name, email, password_hash)
VALUES (
  'IFQM Platform Admin',
  'platform@ifqm.io',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
);

-- ── Brute-force lockout state ────────────────────────────────────────────────
-- Persisted rather than held in process memory: an in-memory counter reset on
-- every restart or deploy, did not exist for a second worker process, and grew
-- without bound. Keyed '<email>|<org-slug>' so a single account locks, not an
-- entire office behind one NAT'd IP.
CREATE TABLE IF NOT EXISTS login_attempts (
  login_id      VARCHAR(191) NOT NULL PRIMARY KEY,
  attempts      INT          NOT NULL DEFAULT 0,
  locked_until  DATETIME     NULL DEFAULT NULL,
  last_attempt  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_login_attempts_last (last_attempt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tenant branding (organisation display name + PNG logo) ───────────────────
-- `name` and `logo_url` already exist above. logo_url was declared but never
-- populated; it now holds the *stored filename* of the tenant's uploaded PNG,
-- not a public URL. The bytes live under backend/uploads/<slug>/ next to idea
-- attachments, which is deliberately NOT web-accessible — they are served
-- inline (as a data: URI) from the authenticated GET /api/branding.
-- logo_updated_at is what lets a client tell that an admin replaced the file.
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS logo_updated_at DATETIME NULL;
