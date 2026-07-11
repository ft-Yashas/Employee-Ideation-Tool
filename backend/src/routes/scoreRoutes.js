/**
 * Score routes — /api/score/*
 * Ported from PHP api/score.php. `score` requires auth; `batch_rescore` is
 * admin-only (PHP requireRole('admin') — not super_admin).
 */
import { Router } from 'express';
import * as score from '../controllers/scoreController.js';
import { requireAuth, requireRole } from '../middleware/auth.js';

const router = Router();

router.get('/', requireAuth, score.score);                        // action=score&id=
router.post('/batch-rescore', requireRole('admin'), score.batchRescore); // action=batch_rescore

export default router;
