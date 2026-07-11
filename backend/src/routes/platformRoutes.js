/**
 * Platform routes — /api/platform/*  (IFQM vendor console)
 * Ported from PHP api/platform.php. Every route requires platform-admin auth.
 */
import { Router } from 'express';
import * as platform from '../controllers/platformController.js';
import { requirePlatformAuth } from '../middleware/auth.js';

const router = Router();

router.use(requirePlatformAuth);

router.get('/tenants', platform.tenants);                       // action=tenants
router.get('/tenants/:id/hierarchy', platform.tenantHierarchy); // action=tenant_hierarchy
router.get('/tenants/:id', platform.tenantDetail);              // action=tenant_detail
router.post('/tenants', platform.createTenant);                 // action=create_tenant

export default router;
