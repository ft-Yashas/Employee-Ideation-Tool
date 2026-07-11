/**
 * Notification controller — HTTP layer over notificationService.
 * Maps to the `notifications` / `mark_read` actions of PHP api/users.php.
 */
import * as notificationService from '../services/notificationService.js';
import { respond } from '../utils/respond.js';
import asyncHandler from '../utils/asyncHandler.js';

export const list = asyncHandler(async (req, res) =>
  respond(res, await notificationService.list(req.db, req.user))
);

export const markRead = asyncHandler(async (req, res) =>
  respond(res, await notificationService.markRead(req.db, req.user))
);

export default { list, markRead };
