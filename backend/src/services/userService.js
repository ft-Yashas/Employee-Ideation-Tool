/**
 * User service — Node port of the user-management actions in PHP api/users.php:
 *   list, admin_users, create_user, update_user, delete_user, managers,
 *   hierarchy, profile.
 *
 * (The leaderboard / notifications / analytics / audit actions that also live
 * in users.php are cross-cutting and are migrated with their own modules.)
 *
 * All role checks, validation order, SQL, and response shapes mirror the PHP
 * exactly. "Departments" are a free-text `users.department` column — there is
 * no departments table in the source app.
 */
import bcrypt from 'bcryptjs';
import { badRequest, forbidden, notFound, ApiError } from '../utils/respond.js';
import { assertPasswordStrength } from './authService.js';

// Role sets used across create/update/managers (mirrors the PHP literals).
const ROLES_ADMIN_CAN_ASSIGN = [
  'trainee', 'employee', 'team_lead', 'project_lead', 'manager', 'senior_manager', 'executive',
];
const ROLES_SUPER_ADMIN_CAN_ASSIGN = [...ROLES_ADMIN_CAN_ASSIGN, 'admin'];

const assignableRoles = (actorRole) =>
  actorRole === 'super_admin' ? ROLES_SUPER_ADMIN_CAN_ASSIGN : ROLES_ADMIN_CAN_ASSIGN;

/**
 * Avatar initials — matches PHP:
 *   implode('', array_map(fn($w)=>strtoupper($w[0]),
 *     array_slice(array_filter(explode(' ', $name)), 0, 2)))
 */
function avatarInitials(name) {
  return String(name || '')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('');
}

const firstCharUpper = (s) => (s ? s.charAt(0).toUpperCase() : '');

/** GET action=list — search users (excludes self), LIMIT 20. */
export async function list(db, actor, q) {
  const like = `%${String(q || '').trim()}%`;
  const [rows] = await db.execute(
    `SELECT id, employee_id, name, department, email, role, avatar_initials
       FROM users WHERE (name LIKE ? OR employee_id LIKE ? OR email LIKE ?)
       AND id != ? LIMIT 20`,
    [like, like, like, actor.id]
  );
  return { success: true, users: rows };
}

/** GET action=admin_users — full user list for admin console. */
export async function adminUsers(db) {
  const [rows] = await db.query(
    `SELECT u.id, u.employee_id, u.name, u.department, u.business_unit, u.location,
            u.email, u.role, u.avatar_initials, u.points, u.status, u.manager_id,
            m.name AS manager_name
       FROM users u LEFT JOIN users m ON m.id=u.manager_id
      ORDER BY FIELD(u.role,'admin','executive','senior_manager','manager','project_lead','team_lead','employee','trainee'), u.name`
  );
  return { success: true, users: rows };
}

/** POST action=create_user. */
export async function createUser(db, actor, body) {
  const name = String(body.name || '').trim();
  const email = String(body.email || '').trim().toLowerCase();
  const password = body.password || '';
  const employeeId = String(body.employee_id || '').trim();
  const department = String(body.department || '').trim();
  const businessUnit = String(body.business_unit || '').trim();
  const location = String(body.location || '').trim();
  const role = body.role || 'employee';
  const managerId = body.manager_id ? parseInt(body.manager_id, 10) : null;

  if (!name || !email || !password || !employeeId) {
    throw badRequest('Name, email, employee ID, and password are required.');
  }
  if (!isValidEmail(email)) throw badRequest('Invalid email address.');
  assertPasswordStrength(password);
  if (!assignableRoles(actor.role).includes(role)) throw forbidden('You cannot assign that role.');

  const [dup] = await db.execute(
    'SELECT id FROM users WHERE email=? OR employee_id=? LIMIT 1',
    [email, employeeId]
  );
  if (dup.length) throw new ApiError(409, 'Email or employee ID already exists.');

  const initials = avatarInitials(name) || firstCharUpper(name);
  const hash = bcrypt.hashSync(password, 12);

  const [result] = await db.execute(
    `INSERT INTO users (employee_id, name, email, password_hash, department, business_unit,
                        location, role, manager_id, avatar_initials, status, password_changed_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,'active',NOW())`,
    [employeeId, name, email, hash, department, businessUnit, location, role, managerId, initials]
  );
  return { success: true, user_id: result.insertId };
}

/** POST action=update_user. */
export async function updateUser(db, actor, id, body) {
  id = parseInt(id, 10) || 0;
  if (!id) throw badRequest('Missing user ID.');

  const [tgtRows] = await db.execute('SELECT id, role FROM users WHERE id=? LIMIT 1', [id]);
  const target = tgtRows[0];
  if (!target) throw notFound('User not found.');
  if (target.role === 'super_admin') throw forbidden('Cannot edit super admin.');
  if (id === Number(actor.id)) throw forbidden('Cannot edit your own account here.');

  const name = String(body.name || '').trim();
  const department = String(body.department || '').trim();
  const businessUnit = String(body.business_unit || '').trim();
  const location = String(body.location || '').trim();
  const role = body.role || target.role;
  const managerId = body.manager_id ? parseInt(body.manager_id, 10) : null;
  const status = (body.status || 'active') === 'inactive' ? 'inactive' : 'active';

  if (!assignableRoles(actor.role).includes(role)) throw forbidden('You cannot assign that role.');

  const initials = avatarInitials(name) || firstCharUpper(name);

  // Deactivating an employee now ends their session on their very next request
  // (the auth middleware re-reads status from this row), instead of leaving them
  // logged in for the remaining life of their token. Same for a role change:
  // the new role takes effect immediately rather than after the token expires.
  await db.execute(
    `UPDATE users SET name=?, department=?, business_unit=?, location=?, role=?,
                      manager_id=?, avatar_initials=?, status=?,
                      deactivated_at = IF(? = 'inactive', COALESCE(deactivated_at, NOW()), NULL)
      WHERE id=?`,
    [name, department, businessUnit, location, role, managerId, initials, status, status, id]
  );
  return { success: true };
}

/** POST action=delete_user — deactivates instead if the user has real ideas. */
export async function deleteUser(db, actor, id) {
  id = parseInt(id, 10) || 0;
  if (!id) throw badRequest('Missing user ID.');
  if (id === Number(actor.id)) throw forbidden('Cannot delete your own account.');

  const [tgtRows] = await db.execute('SELECT role FROM users WHERE id=? LIMIT 1', [id]);
  const target = tgtRows[0];
  if (!target) throw notFound('User not found.');
  if (target.role === 'super_admin') throw forbidden('Cannot delete super admin.');

  const [cntRows] = await db.execute(
    "SELECT COUNT(*) AS c FROM ideas WHERE submitter_id=? AND status!='Draft'",
    [id]
  );
  if (Number(cntRows[0].c) > 0) {
    // Offboarding: the account is retained (their ideas must keep an author) but
    // deactivated. The live session check ends any open session immediately.
    await db.execute(
      "UPDATE users SET status='inactive', deactivated_at=COALESCE(deactivated_at, NOW()) WHERE id=?",
      [id]
    );
    return {
      success: true,
      deactivated: true,
      message: 'User has submitted ideas — account deactivated instead of deleted.',
    };
  }

  await db.execute('UPDATE users SET manager_id=NULL WHERE manager_id=?', [id]);
  await db.execute('DELETE FROM users WHERE id=?', [id]);
  return { success: true, deleted: true };
}

/** GET action=managers — eligible managers for dropdowns. */
export async function managers(db) {
  const [rows] = await db.query(
    `SELECT id, name, department, role FROM users
      WHERE role IN ('team_lead','project_lead','manager','senior_manager','executive','admin') AND status='active'
      ORDER BY FIELD(role,'admin','executive','senior_manager','manager','project_lead','team_lead'), name`
  );
  return { success: true, managers: rows };
}

/** GET action=hierarchy — org tree data + role stats (super_admin only). */
export async function hierarchy(db) {
  const [users] = await db.query(
    `SELECT u.id, u.employee_id, u.name, u.email, u.department, u.business_unit,
            u.location, u.role, u.manager_id, u.points, u.avatar_initials,
            m.name AS manager_name,
            (SELECT COUNT(*) FROM ideas WHERE submitter_id = u.id AND status != 'Draft') AS idea_count
       FROM users u
       LEFT JOIN users m ON m.id = u.manager_id
      WHERE u.role != 'super_admin'
      ORDER BY FIELD(u.role,'admin','executive','senior_manager','manager','project_lead','team_lead','employee','trainee'), u.name`
  );

  // Stats keyed by role+'s' — only admins/managers/employees/executives are
  // tracked (identical to PHP; other roles increment total only).
  const stats = { total: 0, admins: 0, managers: 0, employees: 0, executives: 0 };
  for (const u of users) {
    stats.total++;
    const key = `${u.role}s`;
    if (Object.prototype.hasOwnProperty.call(stats, key)) stats[key]++;
  }
  return { success: true, users, stats };
}

/** POST action=profile — update own phone. */
export async function updateProfile(db, actor, body) {
  const phone = String(body.phone || '').trim();
  await db.execute('UPDATE users SET phone=? WHERE id=?', [phone, actor.id]);
  // NOTE: PHP also mutated $_SESSION['user']['phone']; with a stateless JWT the
  // client updates its own copy. Response shape is unchanged.
  return { success: true };
}

function isValidEmail(email) {
  // Mirrors PHP filter_var(..., FILTER_VALIDATE_EMAIL) closely enough for parity.
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

export default {
  list, adminUsers, createUser, updateUser, deleteUser, managers, hierarchy, updateProfile,
};
