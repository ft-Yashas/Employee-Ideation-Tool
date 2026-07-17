/**
 * JWT helpers — the stateless replacement for PHP `$_SESSION`.
 *
 * The PHP app stored the authenticated user in the session together with the
 * resolved tenant slug (and a `platform_admin` flag for vendor staff). We keep
 * exactly that payload inside a signed JWT so downstream code has the same
 * information the PHP `$_SESSION['user']` / `$_SESSION['org_slug']` carried.
 */
import jwt from 'jsonwebtoken';
import config from '../config/index.js';

/**
 * @param {object} payload  { user, org_slug?, platform_admin? }
 */
export function signToken(payload) {
  return jwt.sign(payload, config.jwt.secret, {
    algorithm: 'HS256',
    expiresIn: config.jwt.expiresIn, // seconds; mirrors SESSION_LIFETIME
  });
}

export function verifyToken(token) {
  // Algorithm pinned on both sides. Without the allow-list, verification
  // accepts whatever `alg` the token header claims (any HS variant here) —
  // the verifier must decide the algorithm, never the attacker-controlled token.
  return jwt.verify(token, config.jwt.secret, { algorithms: ['HS256'] });
}

export default { signToken, verifyToken };
