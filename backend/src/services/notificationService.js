/**
 * Notification service — Node port of the `notifications` and `mark_read`
 * actions in PHP api/users.php.
 *
 *   list()      → most recent 20 notifications for the user, plus unread_count.
 *                 Back-fills a missing idea_id by scanning the message for an
 *                 IDA-YYYY-NNN code and linking (and persisting) the match.
 *   markRead()  → marks all of the user's notifications as read.
 */

const IDEA_CODE_RE = /IDA-\d{4}-\d{3}/;

export async function list(db, user) {
  const [notifs] = await db.execute(
    'SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20',
    [user.id]
  );

  for (const n of notifs) {
    if (!n.idea_id) {
      const m = String(n.message ?? '').match(IDEA_CODE_RE);
      if (m) {
        const [rows] = await db.execute('SELECT id FROM ideas WHERE idea_code = ? LIMIT 1', [m[0]]);
        const found = rows[0]?.id;
        if (found) {
          n.idea_id = Number(found);
          await db.execute('UPDATE notifications SET idea_id=? WHERE id=?', [found, n.id]);
        }
      }
    }
  }

  const unreadCount = notifs.filter((n) => !n.is_read).length;
  return { success: true, notifications: notifs, unread_count: unreadCount };
}

export async function markRead(db, user) {
  await db.execute('UPDATE notifications SET is_read=1 WHERE user_id=?', [user.id]);
  return { success: true };
}

export default { list, markRead };
