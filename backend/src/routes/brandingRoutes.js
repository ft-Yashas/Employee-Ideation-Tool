/**
 * Branding routes — /api/branding/*  (per-tenant organisation name + logo)
 *
 *   GET    /api/branding       — read (any authenticated user)
 *   PUT    /api/branding       — rename the organisation (admin)
 *   POST   /api/branding/logo  — replace the PNG logo (admin), field name "logo"
 *   DELETE /api/branding/logo  — remove the logo (admin)
 */
import { Router } from 'express';
import multer from 'multer';
import * as branding from '../controllers/brandingController.js';
import { requireAuth, requireRole } from '../middleware/auth.js';
import { MAX_LOGO_BYTES } from '../services/brandingService.js';
import { badRequest } from '../utils/respond.js';

// Unlike idea attachments, the app limit and multer's ceiling are the same here:
// a logo is inlined into a JSON response, so there is no reason to buffer one
// that is already over the limit just to reject it a layer later.
const multerUpload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: MAX_LOGO_BYTES },
});

function handleLogo(req, res, next) {
  multerUpload.single('logo')(req, res, (err) => {
    if (err) {
      if (err.code === 'LIMIT_FILE_SIZE') {
        return next(badRequest(`Logo exceeds the ${MAX_LOGO_BYTES / 1024 / 1024}MB limit.`));
      }
      return next(err);
    }
    next();
  });
}

const router = Router();

router.get('/', requireAuth, branding.get);
router.put('/', requireRole('admin', 'super_admin'), branding.updateName);
router.post('/logo', requireRole('admin', 'super_admin'), handleLogo, branding.updateLogo);
router.delete('/logo', requireRole('admin', 'super_admin'), branding.removeLogo);

export default router;
