/**
 * Shared per-tenant helpers — Node ports of the small utility functions in
 * PHP api/config.php (generateIdeaCode, addNotification, addWorkflow, addPoints).
 * Each takes the tenant `db` pool as its first argument.
 */

/** Generate the next idea code: IDA-<year>-<NNN>. Mirrors generateIdeaCode(). */
export async function generateIdeaCode(db) {
  const year = new Date().getFullYear();
  const [rows] = await db.execute(
    'SELECT COUNT(*) AS c FROM ideas WHERE YEAR(created_at) = ?',
    [year]
  );
  const n = Number(rows[0].c) + 1;
  return `IDA-${year}-${String(n).padStart(3, '0')}`;
}

/** Insert a notification. Mirrors addNotification(). */
export async function addNotification(db, userId, title, msg, ideaId = null) {
  await db.execute(
    'INSERT INTO notifications (user_id,title,message,idea_id) VALUES (?,?,?,?)',
    [userId, title, msg, ideaId]
  );
}

/** Insert a workflow/audit entry. Mirrors addWorkflow(). */
export async function addWorkflow(db, ideaId, actorId, action, comment = null) {
  await db.execute(
    'INSERT INTO idea_workflow (idea_id,actor_id,action,comment) VALUES (?,?,?,?)',
    [ideaId, actorId, action, comment]
  );
}

/** Increment a user's points. Mirrors addPoints(). */
export async function addPoints(db, userId, pts) {
  await db.execute('UPDATE users SET points = points + ? WHERE id = ?', [pts, userId]);
}

export default { generateIdeaCode, addNotification, addWorkflow, addPoints };
