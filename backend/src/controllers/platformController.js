/**
 * Platform controller — HTTP layer over platformService. Maps to api/platform.php.
 * All routes are guarded by requirePlatformAuth.
 */
import * as platformService from '../services/platformService.js';
import { respond } from '../utils/respond.js';
import asyncHandler from '../utils/asyncHandler.js';

export const tenants = asyncHandler(async (req, res) =>
  respond(res, await platformService.tenants())
);

export const tenantHierarchy = asyncHandler(async (req, res) =>
  respond(res, await platformService.tenantHierarchy(req.params.id ?? req.query.id))
);

export const tenantDetail = asyncHandler(async (req, res) =>
  respond(res, await platformService.tenantDetail(req.params.id ?? req.query.id))
);

export const createTenant = asyncHandler(async (req, res) =>
  respond(res, await platformService.createTenant(req.body || {}))
);

export default { tenants, tenantHierarchy, tenantDetail, createTenant };
