/**
 * Settings routes — /api/settings/*
 * Ported from PHP api/settings.php.
 */
import { Router } from 'express';
import * as settings from '../controllers/settingsController.js';
import { requireAuth, requireRole } from '../middleware/auth.js';

const router = Router();

router.get('/', requireAuth, settings.get);                                   // action=get
router.post('/', requireRole('admin', 'super_admin'), settings.update);       // action=update
router.get('/test-email', requireRole('admin', 'super_admin'), settings.testEmail); // action=send_test_email

export default router;
