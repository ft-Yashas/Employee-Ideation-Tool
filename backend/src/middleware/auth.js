/**
 * Authentication & authorization middleware.
 *
 * Replaces the PHP session guards from api/config.php:
 *   requireAuth()          → requireAuth
 *   requireRole(...roles)  → requireRole(...roles)
 *   requirePlatformAuth()  → requirePlatformAuth
 *
 * The JWT payload mirrors what PHP kept in the session:
 *   { user: {...}, org_slug: 'acme', platform_admin?: true }
 *
 * On a valid tenant token we resolve the tenant (by the slug embedded in the
 * token) and attach its connection pool as `req.db` — the exact equivalent of
 * PHP `db()` resolving the tenant from `$_SESSION['org_slug']`.
 */
import { verifyToken } from '../utils/jwt.js';
import { resolveTenantBySlug, getTenantPool } from '../database/tenant.js';
import { masterDb } from '../database/master.js';
import { ApiError, unauthorized, forbidden } from '../utils/respond.js';
import asyncHandler from '../utils/asyncHandler.js';

function getBearer(req) {
  const h = req.headers.authorization || '';
  if (h.startsWith('Bearer ')) return h.slice(7).trim();
  return '';
}

async function attachTenantDb(req, orgSlug) {
  const host = req.headers['x-forwarded-host'] || req.headers.host || 'localhost';
  const tenant = await resolveTenantBySlug(orgSlug, host);
  req.tenant = tenant;
  req.db = getTenantPool(tenant);
}

/**
 * Re-check the session against the database on every request.
 *
 * The JWT embeds a *snapshot* of the user taken at login and stays valid for 8
 * hours. Trusting that snapshot meant:
 *   • deactivating an employee (offboarding) did not end their session — they
 *     kept full access until the token happened to expire;
 *   • demoting someone from manager to employee left their elevated role intact
 *     inside the token, so privileged endpoints kept honouring it;
 *   • resetting a password did not invalidate sessions opened with the old one.
 *
 * So the token now only tells us *who is claiming to be logged in*; the
 * authoritative role and status come from the row, on every request.
 */
async function loadLiveUser(req, payload) {
  const claimed = payload.user || {};
  const [rows] = await req.db.execute(
    // UNIX_TIMESTAMP() is resolved by MySQL in its own timezone. Parsing the raw
    // DATETIME string in Node would silently treat a local timestamp as UTC and
    // shift it by the server's offset — which, for a positive offset, would make
    // the password change look like it happened in the future and log the user
    // straight back out of the session they just reset.
    `SELECT u.id, u.employee_id, u.name, u.email, u.phone, u.department, u.business_unit,
            u.location, u.role, u.manager_id, u.points, u.avatar_initials, u.status,
            UNIX_TIMESTAMP(u.password_changed_at) AS password_changed_ts,
            m.name AS manager_name
       FROM users u
       LEFT JOIN users m ON m.id = u.manager_id
      WHERE u.id = ? LIMIT 1`,
    [claimed.id]
  );
  const row = rows[0];

  if (!row) throw unauthorized('Your account no longer exists.');
  if (row.status !== 'active') throw unauthorized('Your account has been deactivated.');

  // Tokens minted before the last password change are dead. `iat` is in whole
  // seconds, so allow a small skew rather than logging a user out of the very
  // session they just reset the password from.
  const changedAt = Number(row.password_changed_ts);
  if (Number.isFinite(changedAt) && changedAt > 0 && payload.iat && payload.iat + 2 < changedAt) {
    throw new ApiError(401, 'Session expired', { expired: true });
  }

  return {
    id: row.id,
    employee_id: row.employee_id,
    name: row.name,
    email: row.email,
    phone: row.phone,
    department: row.department,
    business_unit: row.business_unit,
    location: row.location,
    role: row.role,            // authoritative — never the role baked into the token
    manager_id: row.manager_id,
    manager_name: row.manager_name,
    points: row.points,
    avatar_initials: row.avatar_initials,
    status: row.status,
    org_name: req.tenant?.name,
    org_slug: req.tenant?.slug,
  };
}

/**
 * Decode the token (if any) and populate req.user/req.db without rejecting.
 * Used by endpoints (like auth/me) that must respond for both states.
 */
export const optionalAuth = asyncHandler(async (req, _res, next) => {
  const token = getBearer(req);
  if (!token) return next();
  try {
    const payload = verifyToken(token);
    req.auth = payload;
    if (payload.platform_admin) {
      req.isPlatformAdmin = true;
      req.master = masterDb();
      req.user = await loadLivePlatformAdmin(req, payload);
    } else {
      await attachTenantDb(req, payload.org_slug);
      req.user = await loadLiveUser(req, payload);
    }
  } catch {
    // Invalid/expired/revoked token → treated as unauthenticated here.
    req.auth = undefined;
    req.user = undefined;
    req.isPlatformAdmin = false;
  }
  next();
});

/**
 * Same live re-check for platform (vendor) admins, whose accounts live in the
 * master registry rather than a tenant DB. Their token id is `pa_<id>`.
 */
async function loadLivePlatformAdmin(req, payload) {
  const claimed = payload.user || {};
  const id = Number(String(claimed.id || '').replace(/^pa_/, ''));
  if (!id) throw unauthorized('Not authenticated');

  const [rows] = await req.master.execute(
    'SELECT id, name, email FROM platform_admins WHERE id = ? LIMIT 1',
    [id]
  );
  const row = rows[0];
  if (!row) throw unauthorized('Your account no longer exists.');

  return {
    id: `pa_${row.id}`,
    name: row.name,
    email: row.email,
    role: 'platform_admin',
    avatar_initials: claimed.avatar_initials || 'PA',
    points: 0,
  };
}

/** Hard auth guard — mirrors PHP requireAuth(). */
export const requireAuth = asyncHandler(async (req, _res, next) => {
  const token = getBearer(req);
  if (!token) throw unauthorized('Not authenticated');

  let payload;
  try {
    payload = verifyToken(token);
  } catch (e) {
    // PHP destroyed the idle session and returned {expired:true}; JWT expiry
    // is the direct analogue.
    if (e.name === 'TokenExpiredError') {
      throw new ApiError(401, 'Session expired', { expired: true });
    }
    throw unauthorized('Not authenticated');
  }

  req.auth = payload;
  if (payload.platform_admin) {
    req.isPlatformAdmin = true;
    req.master = masterDb();
    req.user = await loadLivePlatformAdmin(req, payload);
  } else {
    await attachTenantDb(req, payload.org_slug);
    // Authoritative role/status come from the DB, not the 8-hour-old token.
    req.user = await loadLiveUser(req, payload);
  }
  next();
});

/** Role guard — mirrors PHP requireRole(...$roles). */
export const requireRole = (...roles) => [
  requireAuth,
  (req, _res, next) => {
    if (!roles.includes(req.user?.role)) return next(forbidden('Insufficient permissions'));
    next();
  },
];

/** Platform-admin guard — mirrors PHP requirePlatformAuth(). */
export const requirePlatformAuth = [
  requireAuth,
  (req, _res, next) => {
    if (!req.isPlatformAdmin) {
      return next(unauthorized('Not authenticated as platform admin'));
    }
    next();
  },
];

export default { optionalAuth, requireAuth, requireRole, requirePlatformAuth };
