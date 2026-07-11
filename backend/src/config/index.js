/**
 * Central configuration loader.
 *
 * Mirrors the constants defined in the PHP `api/config.php` so that the
 * migrated backend behaves identically. Anything that was a `define()` in
 * PHP lives here, sourced from environment variables with the same defaults.
 */
import dotenv from 'dotenv';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Load backend/.env regardless of the process cwd.
dotenv.config({ path: path.resolve(__dirname, '../../.env') });

const int = (v, fallback) => {
  const n = parseInt(v, 10);
  return Number.isFinite(n) ? n : fallback;
};

const config = {
  env: process.env.NODE_ENV || 'development',
  port: int(process.env.PORT, 4000),

  // Public base URL of the React frontend — used to build emailed links
  // (e.g. the password-reset URL, which PHP built from getAppBaseUrl()).
  frontendBaseUrl: process.env.FRONTEND_BASE_URL || 'http://localhost:5173',

  // CORS allow-list (Vite dev server, etc.)
  corsOrigins: (process.env.CORS_ORIGIN || 'http://localhost:5173')
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean),

  // ── Master DB (tenant registry) — MASTER_DB_* in config.php ──
  masterDb: {
    host: process.env.MASTER_DB_HOST || 'localhost',
    user: process.env.MASTER_DB_USER || 'root',
    password: process.env.MASTER_DB_PASS || '',
    database: process.env.MASTER_DB_NAME || 'ifqm_master',
  },

  // ── Built-in fallback tenant — FALLBACK_DB_* in config.php ──
  fallbackDb: {
    host: process.env.FALLBACK_DB_HOST || 'localhost',
    user: process.env.FALLBACK_DB_USER || 'root',
    password: process.env.FALLBACK_DB_PASS || '',
    database: process.env.FALLBACK_DB_NAME || 'ifqm_ideation',
  },

  // ── Auth (replaces PHP sessions) ──
  // SESSION_LIFETIME in PHP = 28800 (8h). We reuse it as the JWT lifetime
  // so an idle token expires on the same schedule the PHP idle-session did.
  jwt: {
    secret: process.env.JWT_SECRET || 'change-this-to-a-long-random-secret-string',
    expiresIn: int(process.env.JWT_EXPIRES_IN, 28800),
  },
  sessionLifetime: int(process.env.JWT_EXPIRES_IN, 28800),

  // ── Points — POINTS_* in config.php ──
  points: {
    submit: int(process.env.POINTS_SUBMIT, 10),
    approved: int(process.env.POINTS_APPROVED, 25),
    implemented: int(process.env.POINTS_IMPLEMENTED, 65),
  },

  // ── Uploads — MAX_FILE_MB in config.php ──
  maxFileMb: int(process.env.MAX_FILE_MB, 10),

  // ── AI providers (blank by default → heuristic fallback) ──
  ai: {
    provider: (process.env.AI_PROVIDER || '').trim().toLowerCase(),
    openaiApiKey: process.env.OPENAI_API_KEY || '',
    geminiApiKey: process.env.GEMINI_API_KEY || '',
  },
};

export default config;
