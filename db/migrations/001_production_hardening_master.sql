-- ─────────────────────────────────────────────────────────────────────────────
--  Migration 001 (master) — apply to ifqm_master ONLY.
--
--    mysql -u root -p ifqm_master < db/migrations/001_production_hardening_master.sql
--
--  login_attempts
--    Brute-force lockout used to live in a process-local JavaScript Map. That
--    meant: it reset to zero on every restart or deploy (so an attacker just had
--    to wait for a bounce), it did not exist across a second worker process, and
--    it grew without bound as a memory leak. It is now persisted, shared, and
--    prunable.
--
--    The key is `<email>|<org-slug>` — the same identifier the in-memory version
--    used — so a single account is locked, not a whole office behind one NAT IP.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS login_attempts (
  login_id      VARCHAR(191) NOT NULL PRIMARY KEY,  -- '<email>|<slug>'
  attempts      INT          NOT NULL DEFAULT 0,
  locked_until  DATETIME     NULL DEFAULT NULL,
  last_attempt  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_login_attempts_last (last_attempt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The tenants table stored db_user/db_pass in plaintext — a list of live
-- database credentials (in practice, root) sitting in the registry. The
-- application now connects with a single least-privilege account from the
-- environment (APP_DB_USER/APP_DB_PASS) and only reads db_host/db_name from
-- here, so these columns are dead weight and are scrubbed.
UPDATE tenants SET db_pass = '' WHERE db_pass <> '';
