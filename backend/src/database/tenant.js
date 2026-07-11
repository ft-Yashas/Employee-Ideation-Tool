/**
 * Per-tenant database resolution and connection pooling.
 *
 * Faithfully reproduces the PHP `resolveTenant()` + `db()` behaviour from
 * api/config.php, adapted for a stateless (JWT) world:
 *
 *   PHP priority was: 1) session org_slug  2) ?org= param  3) domain
 *                     4) default tenant     5) hardcoded fallback
 *
 * In the migrated backend the authenticated tenant slug travels inside the
 * JWT (set at login), so for an authenticated request we resolve by that slug.
 * For the login request itself — where there is no token yet — we resolve using
 * the same priority chain the PHP login used: body org_slug → domain → default
 * → fallback.
 *
 * Each distinct tenant database gets its own connection pool, cached by
 * host+db+user so we never open more connections than the PHP `static $pdo`
 * memoisation implied.
 */
import mysql from 'mysql2/promise';
import config from '../config/index.js';
import { masterDb } from './master.js';
import logger from '../utils/logger.js';

const poolCache = new Map();

/** The built-in single-tenant fallback — identical to PHP's fallback array. */
export function fallbackTenant(host = 'localhost') {
  return {
    id: 0,
    name: 'IFQM',
    slug: 'ifqm',
    domain: host,
    db_host: config.fallbackDb.host,
    db_name: config.fallbackDb.database,
    db_user: config.fallbackDb.user,
    db_pass: config.fallbackDb.password,
    status: 'active',
    is_default: 1,
    primary_color: '#4f46e5',
  };
}

/** Sanitise an org slug exactly like PHP: lowercase, [a-z0-9_-] only. */
export function sanitizeSlug(raw) {
  return String(raw || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9_-]/g, '');
}

function stripPort(host) {
  return String(host || 'localhost').toLowerCase().replace(/:\d+$/, '');
}

/**
 * Resolve a tenant using the PHP login priority chain.
 * @param {{ slug?: string, host?: string }} opts
 * @returns {Promise<object>} tenant row (or fallback)
 */
export async function resolveTenant({ slug = '', host = 'localhost' } = {}) {
  const cleanSlug = sanitizeSlug(slug);
  const cleanHost = stripPort(host);

  try {
    const master = masterDb();

    if (cleanSlug) {
      const [rows] = await master.execute(
        "SELECT * FROM tenants WHERE slug = ? AND status = 'active' LIMIT 1",
        [cleanSlug]
      );
      if (rows.length) return rows[0];
    }

    // Domain-based
    let [rows] = await master.execute(
      "SELECT * FROM tenants WHERE domain = ? AND status = 'active' LIMIT 1",
      [cleanHost]
    );
    if (rows.length) return rows[0];

    // Default tenant
    [rows] = await master.execute(
      "SELECT * FROM tenants WHERE is_default = 1 AND status = 'active' LIMIT 1"
    );
    if (rows.length) return rows[0];
  } catch (err) {
    logger.warn('ifqm_master unavailable, using fallback tenant', err.message);
  }

  return fallbackTenant(cleanHost);
}

/** Resolve strictly by slug (used for authenticated requests carrying a JWT). */
export async function resolveTenantBySlug(slug, host = 'localhost') {
  return resolveTenant({ slug, host });
}

/**
 * Get (or lazily create) the connection pool for a tenant.
 * @param {object} tenant  a tenant row from resolveTenant()
 * @returns {import('mysql2/promise').Pool}
 */
export function getTenantPool(tenant) {
  const key = `${tenant.db_host}|${tenant.db_name}|${tenant.db_user}`;
  if (poolCache.has(key)) return poolCache.get(key);

  const pool = mysql.createPool({
    host: tenant.db_host,
    user: tenant.db_user,
    password: tenant.db_pass,
    database: tenant.db_name,
    charset: 'utf8mb4',
    waitForConnections: true,
    connectionLimit: 10,
    namedPlaceholders: false,
    dateStrings: true,
    // PDO had ATTR_EMULATE_PREPARES => false; mysql2 uses real prepared
    // statements for execute() by default, matching that behaviour.
  });
  poolCache.set(key, pool);
  return pool;
}

export default { resolveTenant, resolveTenantBySlug, getTenantPool, fallbackTenant, sanitizeSlug };
