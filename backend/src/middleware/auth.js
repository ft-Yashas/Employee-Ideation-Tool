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
 * Decode the token (if any) and populate req.user/req.db without rejecting.
 * Used by endpoints (like auth/me) that must respond for both states.
 */
export const optionalAuth = asyncHandler(async (req, _res, next) => {
  const token = getBearer(req);
  if (!token) return next();
  try {
    const payload = verifyToken(token);
    req.auth = payload;
    req.user = payload.user;
    if (payload.platform_admin) {
      req.isPlatformAdmin = true;
      req.master = masterDb();
    } else {
      await attachTenantDb(req, payload.org_slug);
    }
  } catch {
    // Invalid/expired token → treated as unauthenticated for optional routes.
  }
  next();
});

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
  req.user = payload.user;
  if (payload.platform_admin) {
    req.isPlatformAdmin = true;
    req.master = masterDb();
  } else {
    await attachTenantDb(req, payload.org_slug);
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
