/**
 * Auth routes — /api/auth/*
 * Ported from PHP api/auth.php (action-based dispatch → REST sub-paths).
 */
import { Router } from 'express';
import * as auth from '../controllers/authController.js';
import { optionalAuth } from '../middleware/auth.js';
import { authLimiter } from '../middleware/rateLimiter.js';

const router = Router();

router.get('/me', optionalAuth, auth.me);
router.post('/login', authLimiter, auth.login);
router.post('/logout', auth.logout);
router.post('/forgot-password', authLimiter, auth.forgotPassword);
router.post('/reset-password', authLimiter, auth.resetPassword);
router.get('/check-reset-token', auth.checkResetToken);

export default router;
