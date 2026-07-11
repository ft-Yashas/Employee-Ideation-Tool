/**
 * Platform service — Node port of PHP api/platform.php (IFQM vendor console).
 * Operates on the master registry (ifqm_master) and reads per-tenant aggregate
 * stats. Guarded by requirePlatformAuth.
 *
 * Privacy contract (preserved): platform admins see aggregate counts, trends,
 * and user directory/hierarchy only — never idea titles, content, or scores.
 * Tenant DB credentials are stripped (safeTenant) before responding.
 */
import path from 'node:path';
import fs from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import mysql from 'mysql2/promise';
import bcrypt from 'bcryptjs';
import config from '../config/index.js';
import { masterDb } from '../database/master.js';
import { getTenantPool } from '../database/tenant.js';
import { badRequest, notFound, ApiError } from '../utils/respond.js';
import logger from '../utils/logger.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
// Consolidated tenant schema (source schema.sql + the idea_comments/challenges/
// email_queue tables that lived only in schema_updates.sql). See the file header
// and MIGRATION.md — the original schema.sql was incomplete for provisioning.
const SCHEMA_PATH = path.resolve(__dirname, '..', '..', 'schema', 'tenant_schema.sql');

/** Strip sensitive DB credentials before sending a tenant to the client. */
function safeTenant(t) {
  const { db_host, db_name, db_user, db_pass, ...rest } = t;
  return rest;
}

/** Order-by-role FIELD() fragment shared across tenant user queries. */
const ROLE_ORDER = "FIELD(u.role,'admin','executive','senior_manager','manager','project_lead','team_lead','employee','trainee')";

// ── GET tenants (aggregate stats only) ─────────────────────────────
export async function tenants() {
  const master = masterDb();
  const [rows] = await master.query('SELECT * FROM tenants ORDER BY created_at ASC');

  const out = [];
  for (const t of rows) {
    const stats = { user_count: 0, idea_count: 0, implemented_count: 0, last_activity: null, trend: [] };
    try {
      const db = getTenantPool(t);
      const [[uc]] = await db.query("SELECT COUNT(*) AS c FROM users WHERE role != 'super_admin'");
      const [[ic]] = await db.query("SELECT COUNT(*) AS c FROM ideas WHERE status != 'Draft'");
      const [[imp]] = await db.query("SELECT COUNT(*) AS c FROM ideas WHERE status = 'Implemented'");
      const [[la]] = await db.query("SELECT MAX(submitted_at) AS last FROM ideas WHERE status != 'Draft'");
      const [trend] = await db.query(
        `SELECT DATE_FORMAT(submitted_at,'%Y-%m') AS month, COUNT(*) AS cnt
         FROM ideas WHERE submitted_at IS NOT NULL AND status != 'Draft'
         GROUP BY month ORDER BY month DESC LIMIT 6`
      );
      stats.user_count = Number(uc.c);
      stats.idea_count = Number(ic.c);
      stats.implemented_count = Number(imp.c);
      stats.last_activity = la.last ?? null;
      stats.trend = trend;
    } catch (e) {
      logger.warn(`tenant DB unavailable for ${t.slug}`, e.message);
      stats.db_error = true;
    }
    out.push(safeTenant({ ...t, ...stats }));
  }

  return { success: true, tenants: out };
}

// ── GET tenant hierarchy (user tree, no idea content) ──────────────
export async function tenantHierarchy(tenantId) {
  tenantId = Number(tenantId) || 0;
  if (!tenantId) throw badRequest('Missing tenant id.');

  const master = masterDb();
  const [rows] = await master.execute("SELECT * FROM tenants WHERE id = ? AND status = 'active' LIMIT 1", [tenantId]);
  const t = rows[0];
  if (!t) throw notFound('Tenant not found.');

  try {
    const db = getTenantPool(t);
    const [users] = await db.query(
      `SELECT u.id, u.employee_id, u.name, u.department, u.business_unit,
              u.location, u.role, u.manager_id,
              m.name AS manager_name,
              (SELECT COUNT(*) FROM ideas WHERE submitter_id = u.id AND status != 'Draft') AS idea_count
       FROM users u
       LEFT JOIN users m ON m.id = u.manager_id
       WHERE u.role != 'super_admin'
       ORDER BY ${ROLE_ORDER}, u.name`
    );
    return {
      success: true,
      tenant: { id: t.id, name: t.name, slug: t.slug, domain: t.domain },
      users,
    };
  } catch (e) {
    throw new ApiError(503, 'Tenant database is unavailable.');
  }
}

// ── GET tenant detail (users + idea status counts) ─────────────────
export async function tenantDetail(tenantId) {
  tenantId = Number(tenantId) || 0;
  if (!tenantId) throw badRequest('Missing tenant id.');

  const master = masterDb();
  const [rows] = await master.execute('SELECT * FROM tenants WHERE id = ? LIMIT 1', [tenantId]);
  const t = rows[0];
  if (!t) throw notFound('Tenant not found.');

  try {
    const db = getTenantPool(t);
    const [users] = await db.query(
      `SELECT u.id, u.employee_id, u.name, u.email, u.department, u.business_unit,
              u.location, u.role, u.status, u.points, u.manager_id, u.created_at,
              m.name AS manager_name,
              (SELECT COUNT(*) FROM ideas WHERE submitter_id=u.id AND status!='Draft') AS idea_count
       FROM users u LEFT JOIN users m ON m.id=u.manager_id
       ORDER BY ${ROLE_ORDER}, u.name`
    );
    const [ideaStats] = await db.query(
      "SELECT status, COUNT(*) AS cnt FROM ideas WHERE status!='Draft' GROUP BY status"
    );
    return { success: true, tenant: safeTenant(t), users, idea_stats: ideaStats };
  } catch (e) {
    throw new ApiError(503, 'Tenant database is unavailable.');
  }
}

// ── POST create_tenant (provision a new organisation) ──────────────
const APPROVAL_DEFAULTS = [
  ['approval_mode', 'default'],
  ['approval_reviewer_roles', 'team_lead,project_lead,manager,senior_manager'],
  ['approval_final_approver_roles', 'executive,admin,super_admin'],
  ['approval_threshold', '100'],
];

/** Split schema.sql into executable statements (mirrors the PHP explode(';')). */
function splitSqlStatements(sql) {
  return sql.split(';').map((s) => s.trim()).filter((s) => {
    if (!s) return false;
    const code = s.split('\n').filter((l) => !l.trim().startsWith('--')).join('\n').trim();
    return code.length > 0;
  });
}

export async function createTenant(body) {
  const orgName = String(body.org_name ?? '').trim();
  const slug = String(body.slug ?? '').trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
  const adminName = String(body.admin_name ?? '').trim();
  const adminEmail = String(body.admin_email ?? '').trim().toLowerCase();
  const adminPass = body.admin_password ?? '';
  const color = /^#[0-9a-fA-F]{6}$/.test(body.primary_color ?? '') ? body.primary_color : '#4f46e5';

  if (!orgName || !slug || !adminName || !adminEmail || !adminPass) {
    throw badRequest('All fields are required.');
  }
  if (slug.length < 2 || slug.length > 30) throw badRequest('Org code must be 2–30 characters.');
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(adminEmail)) throw badRequest('Invalid admin email address.');
  if (String(adminPass).length < 6) throw badRequest('Admin password must be at least 6 characters.');

  const master = masterDb();
  const [dup] = await master.execute('SELECT id FROM tenants WHERE slug=? LIMIT 1', [slug]);
  if (dup.length) throw new ApiError(409, 'Organization code already in use.');

  const dbName = 'ifqm_' + slug.replace(/[^a-z0-9_]/g, '_');
  const adminEmpId = slug.toUpperCase() + '-ADMIN';

  let conn;
  try {
    await master.query(`CREATE DATABASE IF NOT EXISTS \`${dbName}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`);

    conn = await mysql.createConnection({
      host: config.masterDb.host,
      user: config.masterDb.user,
      password: config.masterDb.password,
      database: dbName,
      charset: 'utf8mb4',
    });

    const schema = await fs.readFile(SCHEMA_PATH, 'utf8');
    for (const stmt of splitSqlStatements(schema)) {
      await conn.query(stmt);
    }

    const initials = adminName.split(' ').filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('') || 'OA';
    const hash = bcrypt.hashSync(adminPass, 10);
    await conn.execute(
      `INSERT INTO users (employee_id, name, email, password_hash, role, avatar_initials, status)
       VALUES (?, ?, ?, ?, 'admin', ?, 'active')`,
      [adminEmpId, adminName, adminEmail, hash, initials]
    );

    for (const [k, v] of APPROVAL_DEFAULTS) {
      await conn.execute(
        'INSERT INTO org_settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=value',
        [k, v]
      );
    }

    const [res] = await master.execute(
      `INSERT INTO tenants (name, slug, domain, db_host, db_name, db_user, db_pass, status, is_default, primary_color)
       VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 0, ?)`,
      [orgName, slug, slug + '.localhost', config.masterDb.host, dbName, config.masterDb.user, config.masterDb.password, color]
    );

    return {
      success: true,
      tenant_id: res.insertId,
      org_name: orgName,
      slug,
      db_name: dbName,
      login_url: '?org=' + slug,
      admin_email: adminEmail,
    };
  } catch (e) {
    try { await master.query(`DROP DATABASE IF EXISTS \`${dbName}\``); } catch { /* ignore */ }
    throw new ApiError(500, 'Failed to create organisation: ' + e.message);
  } finally {
    if (conn) await conn.end();
  }
}

export default { tenants, tenantHierarchy, tenantDetail, createTenant };
