/**
 * Auth controller — thin HTTP layer over authService.
 * Maps to the PHP api/auth.php actions (me, login, logout, forgot_password,
 * reset_password, check_reset_token).
 */
import * as authService from '../services/authService.js';
import { respond } from '../utils/respond.js';
import asyncHandler from '../utils/asyncHandler.js';

const hostOf = (req) => req.headers['x-forwarded-host'] || req.headers.host || 'localhost';

/** GET /api/auth/me — mirrors action=me. Uses optionalAuth upstream. */
export const me = asyncHandler(async (req, res) => {
  if (!req.user) {
    return respond(res, { success: false, authenticated: false });
  }
  // CSRF token intentionally omitted — JWT-in-header removes the need (see
  // authService docblock). Response is otherwise identical to PHP.
  return respond(res, { success: true, authenticated: true, user: req.user });
});

/** POST /api/auth/login */
export const login = asyncHandler(async (req, res) => {
  const { email, password, org_slug } = req.body || {};
  const result = await authService.login({
    email,
    password,
    orgSlug: org_slug,
    host: hostOf(req),
  });
  return respond(res, { success: true, user: result.user, token: result.token });
});

/** POST /api/auth/logout — stateless; client discards the token. */
export const logout = asyncHandler(async (_req, res) => {
  return respond(res, { success: true });
});

/** POST /api/auth/forgot-password */
export const forgotPassword = asyncHandler(async (req, res) => {
  const { email, org_slug } = req.body || {};
  const result = await authService.forgotPassword({ email, orgSlug: org_slug, host: hostOf(req) });
  return respond(res, result);
});

/** POST /api/auth/reset-password */
export const resetPassword = asyncHandler(async (req, res) => {
  const { token, password, org_slug } = req.body || {};
  const result = await authService.resetPassword({
    token,
    password,
    orgSlug: org_slug,
    host: hostOf(req),
  });
  return respond(res, result);
});

/** GET /api/auth/check-reset-token */
export const checkResetToken = asyncHandler(async (req, res) => {
  const result = await authService.checkResetToken({
    token: req.query.token,
    orgSlug: req.query.org,
    host: hostOf(req),
  });
  return respond(res, result);
});

export default { me, login, logout, forgotPassword, resetPassword, checkResetToken };
