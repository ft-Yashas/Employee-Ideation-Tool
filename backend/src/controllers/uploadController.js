/**
 * Upload controller — HTTP layer over uploadService. Maps to api/upload.php.
 * The multipart file is provided by multer as req.file (memory storage).
 */
import * as uploadService from '../services/uploadService.js';
import { respond } from '../utils/respond.js';
import asyncHandler from '../utils/asyncHandler.js';

export const upload = asyncHandler(async (req, res) => {
  const slug = req.tenant?.slug || 'ifqm';
  const { safeName, filename } = await uploadService.upload(req.db, slug, req.user, {
    ideaId: req.body?.idea_id,
    section: req.body?.section ?? 'situation',
    file: req.file,
  });
  const url = `${req.protocol}://${req.get('host')}/api/uploads/${slug}/${safeName}`;
  return respond(res, { success: true, filename, url });
});

export const remove = asyncHandler(async (req, res) => {
  const slug = req.tenant?.slug || 'ifqm';
  const result = await uploadService.remove(
    req.db, slug, req.user, req.params.id ?? req.body?.attachment_id
  );
  return respond(res, result);
});

export default { upload, remove };
