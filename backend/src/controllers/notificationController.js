/**
 * Notification controller — HTTP layer over notificationService.
 * Maps to the `notifications` / `mark_read` actions of PHP api/users.php.
 */
import * as notificationService from '../services/notificationService.js';
import { respond } from '../utils/respond.js';
import asyncHandler from '../utils/asyncHandler.js';

/*
 * Platform admins have no tenant database (req.db is only attached for tenant
 * tokens), and notifications are a tenant-DB feature. The bell in the shared
 * Topbar polls this endpoint every 60s regardless of who is signed in, so
 * without this guard a signed-in platform admin generated a 500 per minute —
 * `Cannot read properties of undefined (reading 'execute')` — for as long as
 * the console was open. Empty list is the honest answer, not an error.
 */
export const list = asyncHandler(async (req, res) => {
  if (!req.db) return respond(res, { success: true, notifications: [], unread_count: 0 });
  return respond(res, await notificationService.list(req.db, req.user));
});

export const markRead = asyncHandler(async (req, res) => {
  if (!req.db) return respond(res, { success: true });
  return respond(res, await notificationService.markRead(req.db, req.user));
});

export default { list, markRead };
