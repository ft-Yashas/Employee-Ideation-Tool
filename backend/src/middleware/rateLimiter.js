/**
 * Rate limiters.
 *
 * The PHP app protected the login action with a per-identifier brute-force
 * lock (5 failed attempts → 15-min lockout) — that exact rule is reproduced in
 * authService (see loginAttempts). These IP-based limiters are an additional
 * coarse safety net ("maintain or improve security") applied globally and to
 * the auth routes, and do not change the per-account lockout behaviour.
 */
import rateLimit from 'express-rate-limit';

export const globalLimiter = rateLimit({
  windowMs: 60 * 1000,
  max: 300,
  standardHeaders: true,
  legacyHeaders: false,
  message: { success: false, error: 'Too many requests. Please slow down.' },
});

export const authLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 100,
  standardHeaders: true,
  legacyHeaders: false,
  message: { success: false, error: 'Too many authentication requests. Please try again later.' },
});

export default { globalLimiter, authLimiter };
