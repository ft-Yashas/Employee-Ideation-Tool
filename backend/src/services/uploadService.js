/**
 * Upload service — Node port of PHP api/upload.php (idea attachments).
 *
 *   upload → validate params + ownership + size + extension, write the file to
 *            the per-tenant uploads dir, and record it in idea_attachments.
 *   remove → verify ownership, unlink the file, delete the row.
 *
 * Files live under backend/uploads/<slug>/ and are served at
 * /api/uploads/<slug>/<file> (static middleware in app.js), mirroring PHP's
 * api/uploads/<slug>/ layout.
 */
import path from 'node:path';
import fs from 'node:fs/promises';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import config from '../config/index.js';
import { badRequest, forbidden, ApiError } from '../utils/respond.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const UPLOADS_BASE = path.join(__dirname, '..', '..', 'uploads');

const ALLOWED_EXT = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'xlsx', 'xls', 'csv', 'docx', 'doc'];

/** Per-tenant upload directory (created if missing). Mirrors PHP uploadDir(). */
export async function tenantUploadDir(slug) {
  const dir = path.join(UPLOADS_BASE, slug || 'ifqm');
  await fs.mkdir(dir, { recursive: true });
  return dir;
}

/**
 * @param file { originalname, buffer, size } (from multer memoryStorage)
 * @returns { safeName, filename }
 */
export async function upload(db, slug, user, { ideaId, section, file }) {
  ideaId = Number(ideaId) || 0;
  if (!ideaId || !['situation', 'solution'].includes(section)) {
    throw badRequest('Invalid parameters.');
  }

  const [rows] = await db.execute('SELECT id FROM ideas WHERE id=? AND submitter_id=?', [ideaId, user.id]);
  if (!rows.length) throw forbidden('Unauthorized or idea not found.');

  if (!file) throw badRequest('No file uploaded.');

  const maxBytes = config.maxFileMb * 1024 * 1024;
  if (file.size > maxBytes) throw badRequest(`File exceeds ${config.maxFileMb}MB limit.`);

  const ext = path.extname(file.originalname).slice(1).toLowerCase();
  if (!ALLOWED_EXT.includes(ext)) throw badRequest('File type not allowed.');

  const dir = await tenantUploadDir(slug);
  const safeName = `attach_${Date.now().toString(16)}${crypto.randomBytes(7).toString('hex')}.${ext}`;

  try {
    await fs.writeFile(path.join(dir, safeName), file.buffer);
  } catch {
    throw new ApiError(500, 'Failed to save file.');
  }

  await db.execute(
    'INSERT INTO idea_attachments (idea_id,section,filename,filepath) VALUES (?,?,?,?)',
    [ideaId, section, file.originalname, safeName]
  );

  return { safeName, filename: file.originalname };
}

export async function remove(db, slug, user, attachmentId) {
  attachmentId = Number(attachmentId) || 0;

  const [rows] = await db.execute(
    `SELECT a.* FROM idea_attachments a
     JOIN ideas i ON i.id = a.idea_id
     WHERE a.id=? AND i.submitter_id=?`,
    [attachmentId, user.id]
  );
  const att = rows[0];
  if (!att) throw forbidden('Not found or unauthorized.');

  const dir = await tenantUploadDir(slug);
  await fs.unlink(path.join(dir, att.filepath)).catch(() => {}); // best-effort (PHP @unlink)
  await db.execute('DELETE FROM idea_attachments WHERE id=?', [attachmentId]);

  return { success: true };
}

export default { upload, remove, tenantUploadDir };
