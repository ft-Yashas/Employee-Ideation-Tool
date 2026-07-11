/**
 * API route aggregator. Mounts each feature module under /api/*.
 * Modules are added here as the migration proceeds (users, ideas, votes, ...).
 */
import { Router } from 'express';
import authRoutes from './authRoutes.js';
import userRoutes from './userRoutes.js';
import ideaRoutes from './ideaRoutes.js';
import votingRoutes from './votingRoutes.js';
import commentRoutes from './commentRoutes.js';
import leaderboardRoutes from './leaderboardRoutes.js';
import scoreRoutes from './scoreRoutes.js';
import notificationRoutes from './notificationRoutes.js';
import settingsRoutes from './settingsRoutes.js';
import challengeRoutes from './challengeRoutes.js';
import exportRoutes from './exportRoutes.js';
import reportRoutes from './reportRoutes.js';
import uploadRoutes from './uploadRoutes.js';
import platformRoutes from './platformRoutes.js';

const router = Router();

router.get('/health', (_req, res) => res.json({ success: true, status: 'ok' }));

router.use('/auth', authRoutes);
router.use('/users', userRoutes);
router.use('/ideas', ideaRoutes);
router.use('/votes', votingRoutes);
router.use('/comments', commentRoutes);
router.use('/leaderboard', leaderboardRoutes);
router.use('/score', scoreRoutes);
router.use('/notifications', notificationRoutes);
router.use('/settings', settingsRoutes);
router.use('/challenges', challengeRoutes);
router.use('/export', exportRoutes);
router.use('/reports', reportRoutes);
router.use('/upload', uploadRoutes);
router.use('/platform', platformRoutes);

export default router;
