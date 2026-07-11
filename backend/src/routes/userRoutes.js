/**
 * User routes — /api/users/*
 * Ported from the user-management actions of PHP api/users.php.
 * Role guards mirror the PHP requireRole(...) calls exactly.
 */
import { Router } from 'express';
import * as users from '../controllers/userController.js';
import { requireAuth, requireRole } from '../middleware/auth.js';

const router = Router();

// Literal paths first, before the /:id param routes.
router.get('/', requireAuth, users.list);                       // action=list
router.get('/admin', requireRole('admin', 'super_admin'), users.adminUsers);      // action=admin_users
router.get('/managers', requireRole('admin', 'super_admin'), users.managers);     // action=managers
router.get('/hierarchy', requireRole('super_admin'), users.hierarchy);            // action=hierarchy
router.post('/profile', requireAuth, users.updateProfile);      // action=profile

router.post('/', requireRole('admin', 'super_admin'), users.createUser);          // action=create_user
router.put('/:id', requireRole('admin', 'super_admin'), users.updateUser);        // action=update_user
router.delete('/:id', requireRole('admin', 'super_admin'), users.deleteUser);     // action=delete_user

export default router;
