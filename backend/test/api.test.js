/**
 * Backend invariant suite.
 *
 * These are not unit tests — they drive the real Express app over HTTP against
 * scratch databases, because every regression this suite exists to catch was a
 * cross-layer one: a controller trusting a service, a service trusting a
 * driver, a mask string dying in transport. Each test names the incident or
 * property it guards.
 *
 * Run:  npm test   (from backend/)
 */
import test, { before, after } from 'node:test';
import assert from 'node:assert/strict';
import {
  setupSuite, teardownSuite, api, login, sql, signToken,
  tinyPng, fakePng, PASSWORDS,
} from './helpers.js';

let PA;       // platform admin token
let AADMIN;   // org A admin token
let AUSER;    // org A employee token
let BADMIN;   // org B admin token
let tenantAId;
let tenantBId;

before(async () => {
  await setupSuite();
  PA = (await login('platform@ifqm.io', PASSWORDS.platform)).token;
  AADMIN = (await login('admin@orga.test', PASSWORDS.orgaAdmin, 'orga')).token;
  AUSER = (await login('user@orga.test', PASSWORDS.orgaUser, 'orga')).token;
  BADMIN = (await login('admin@orgb.test', PASSWORDS.orgbAdmin, 'orgb')).token;
  assert.ok(PA && AADMIN && AUSER && BADMIN, 'all four seed accounts must be able to sign in');

  const tenants = await sql('ifqm_test_master', 'SELECT id, slug FROM ifqm_test_master.tenants');
  tenantAId = tenants.find((t) => t.slug === 'orga').id;
  tenantBId = tenants.find((t) => t.slug === 'orgb').id;
});

after(async () => { await teardownSuite(); });

// ── Authentication ──────────────────────────────────────────────────────────

test('wrong password is 401 and counts toward lockout', async () => {
  const r = await login('user@orga.test', 'not-the-password', 'orga');
  assert.equal(r.status, 401);
  assert.match(r.error, /attempt\(s\) remaining/);
});

test('nonexistent email gets the same 401 as a wrong password (no enumeration)', async () => {
  const r = await login('nobody@orga.test', 'whatever', 'orga');
  assert.equal(r.status, 401);
  assert.match(r.error, /Invalid email, password, or organization code/);
});

test('5 failures lock the account for 15 minutes', async () => {
  for (let i = 0; i < 5; i++) await login('locked@orga.test', 'bad', 'orga');
  const r = await login('locked@orga.test', 'bad', 'orga');
  assert.equal(r.status, 429);
  assert.match(r.error, /try again in/i);
});

test('a tampered JWT signature is rejected', async () => {
  const good = signToken({ user: { id: 1, role: 'admin' }, org_slug: 'orga', pwd_ts: 0 });
  const bad = good.slice(0, -4) + (good.endsWith('AAAA') ? 'BBBB' : 'AAAA');
  const r = await api('GET', '/api/auth/me', { token: bad });
  // optionalAuth treats an invalid token as "not signed in", never as an error.
  assert.equal(r.status, 200);
  assert.equal(r.data.authenticated, false);
});

test('a token with a stale pwd_ts is rejected (password change revokes sessions)', async () => {
  const rows = await sql('ifqm_test_a', 'SELECT id FROM ifqm_test_a.users WHERE email = ?', ['user@orga.test']);
  const stale = signToken({ user: { id: rows[0].id, role: 'employee' }, org_slug: 'orga', pwd_ts: 12345 });
  const r = await api('GET', '/api/notifications', { token: stale });
  assert.equal(r.status, 401);
});

// ── Cross-tenant isolation ──────────────────────────────────────────────────

test('a ticket raised in org A is invisible to org B — list and direct read', async () => {
  const created = await api('POST', '/api/support/tickets', {
    token: AUSER,
    body: { subject: 'Isolation probe', body: 'raised inside org A' },
  });
  assert.equal(created.data.success, true);
  const id = created.data.ticket_id;

  const bList = await api('GET', '/api/support/tickets', { token: BADMIN });
  assert.equal(bList.data.tickets.length, 0, 'org B must see an empty list');

  const bRead = await api('GET', `/api/support/tickets/${id}`, { token: BADMIN });
  assert.equal(bRead.status, 404, 'direct read across tenants must 404, not 403 (no existence oracle)');
});

test('an employee sees only their own tickets; their org admin sees the org', async () => {
  const asAdmin = await api('GET', '/api/support/tickets', { token: AADMIN });
  assert.ok(asAdmin.data.tickets.length >= 1);
  const asUser = await api('GET', '/api/support/tickets', { token: AUSER });
  assert.ok(asUser.data.tickets.every((t) => t.requester_name === 'Orga Employee'));
});

// ── Ticket privacy: internal notes ──────────────────────────────────────────

test('IFQM internal notes never reach any tenant reader', async () => {
  const created = await api('POST', '/api/support/tickets', {
    token: AUSER, body: { subject: 'Note privacy', body: 'help' },
  });
  const id = created.data.ticket_id;

  await api('POST', `/api/platform/tickets/${id}/messages`, {
    token: PA, body: { body: 'public answer' },
  });
  await api('POST', `/api/platform/tickets/${id}/messages`, {
    token: PA, body: { body: 'INTERNAL-MARKER-9f2a upsell them', is_internal: true },
  });

  for (const [who, token] of [['employee', AUSER], ['tenant admin', AADMIN]]) {
    const thread = await api('GET', `/api/support/tickets/${id}`, { token });
    const text = JSON.stringify(thread.data);
    assert.ok(!text.includes('INTERNAL-MARKER-9f2a'), `internal note leaked to ${who}`);
    assert.ok(text.includes('public answer'), `${who} should still see the public reply`);
  }

  const paThread = await api('GET', `/api/platform/tickets/${id}`, { token: PA });
  assert.ok(JSON.stringify(paThread.data).includes('INTERNAL-MARKER-9f2a'), 'platform must see its own note');
});

test('tenants may close their ticket but never set IFQM triage statuses', async () => {
  const created = await api('POST', '/api/support/tickets', {
    token: AUSER, body: { subject: 'Status rules', body: 'x' },
  });
  const id = created.data.ticket_id;

  const resolve = await api('PATCH', `/api/support/tickets/${id}`, { token: AUSER, body: { status: 'resolved' } });
  assert.equal(resolve.status, 403);

  const close = await api('PATCH', `/api/support/tickets/${id}`, { token: AUSER, body: { status: 'closed' } });
  assert.equal(close.data.success, true);

  const replyClosed = await api('POST', `/api/support/tickets/${id}/messages`, { token: AUSER, body: { body: 'hi' } });
  assert.equal(replyClosed.status, 400, 'replying to a closed ticket must be refused');
});

// ── Branding ────────────────────────────────────────────────────────────────

test('any user reads branding; only admins write it', async () => {
  const read = await api('GET', '/api/branding', { token: AUSER });
  assert.equal(read.data.success, true);

  const write = await api('PUT', '/api/branding', { token: AUSER, body: { org_name: 'Hacked' } });
  assert.equal(write.status, 403);
});

test('logo upload validates the actual bytes, not the filename', async () => {
  const upload = (bytes) => {
    const fd = new FormData();
    fd.append('logo', new Blob([bytes], { type: 'image/png' }), 'logo.png');
    return api('POST', '/api/branding/logo', { token: AADMIN, raw: fd });
  };

  const fake = await upload(fakePng());
  assert.equal(fake.status, 400, 'GIF bytes in a .png must be rejected');

  const real = await upload(tinyPng());
  assert.equal(real.data.success, true);
  assert.match(real.data.logo, /^data:image\/png;base64,/);

  // org B must not see org A's logo
  const bBranding = await api('GET', '/api/branding', { token: BADMIN });
  assert.equal(bBranding.data.branding.logo, null);
});

// ── SMTP password lifecycle (the wipe incidents) ────────────────────────────

test('saving unrelated tenant settings never wipes a stored SMTP password', async () => {
  await sql('ifqm_test_a', `UPDATE ifqm_test_a.org_settings SET value = 'MailSecret1' WHERE key_name = 'smtp_pass'`);

  // The exact request AdminPage sends with an untouched password field.
  const save = await api('POST', '/api/settings', {
    token: AADMIN, body: { smtp_host: 'smtp.test', smtp_pass: '', review_sla_days: '9' },
  });
  assert.equal(save.data.success, true);

  const rows = await sql('ifqm_test_a', `SELECT value FROM ifqm_test_a.org_settings WHERE key_name = 'smtp_pass'`);
  assert.equal(rows[0].value, 'MailSecret1', 'untouched field wiped the stored password');
});

test('platform console never returns smtp_pass, and empty writes preserve it', async () => {
  const get = await api('GET', `/api/platform/tenants/${tenantAId}/settings`, { token: PA });
  assert.equal('smtp_pass' in get.data.settings, false, 'smtp_pass must never be in a response');
  assert.equal(get.data.settings.smtp_pass_set, true);

  await api('PUT', `/api/platform/tenants/${tenantAId}/settings`, {
    token: PA, body: { smtp_pass: '', review_sla_days: '7' },
  });
  let rows = await sql('ifqm_test_a', `SELECT value FROM ifqm_test_a.org_settings WHERE key_name = 'smtp_pass'`);
  assert.equal(rows[0].value, 'MailSecret1');

  await api('PUT', `/api/platform/tenants/${tenantAId}/settings`, {
    token: PA, body: { smtp_pass_clear: true },
  });
  rows = await sql('ifqm_test_a', `SELECT value FROM ifqm_test_a.org_settings WHERE key_name = 'smtp_pass'`);
  assert.equal(rows[0].value, '', 'explicit clear must actually clear');
});

// ── Platform privacy boundary ───────────────────────────────────────────────

test('platform tenant views expose no employee PII — only admin contacts', async () => {
  for (const path of ['/api/platform/tenants', `/api/platform/tenants/${tenantAId}`]) {
    const r = await api('GET', path, { token: PA });
    const text = JSON.stringify(r.data);
    assert.ok(!text.includes('Orga Employee'), `employee name leaked via ${path}`);
    assert.ok(!text.includes('user@orga.test'), `employee email leaked via ${path}`);
    assert.ok(!text.includes('db_pass'), `db credentials leaked via ${path}`);
  }
  const detail = await api('GET', `/api/platform/tenants/${tenantAId}`, { token: PA });
  assert.equal(detail.data.admins.length, 1);
  assert.equal(detail.data.admins[0].email, 'admin@orga.test');
});

test('the old org-chart endpoint stays dead', async () => {
  const r = await api('GET', `/api/platform/tenants/${tenantAId}/hierarchy`, { token: PA });
  assert.equal(r.status, 404);
});

test('tenant admins cannot reach platform endpoints', async () => {
  const r = await api('GET', '/api/platform/tenants', { token: AADMIN });
  assert.equal(r.status, 401);
});

// ── Tenant management ───────────────────────────────────────────────────────

test('suspending a tenant blocks its logins; reactivating restores them', async () => {
  await api('PATCH', `/api/platform/tenants/${tenantBId}`, { token: PA, body: { status: 'suspended' } });
  const blocked = await login('admin@orgb.test', PASSWORDS.orgbAdmin, 'orgb');
  assert.equal(blocked.status, 404);

  await api('PATCH', `/api/platform/tenants/${tenantBId}`, { token: PA, body: { status: 'active' } });
  const restored = await login('admin@orgb.test', PASSWORDS.orgbAdmin, 'orgb');
  assert.ok(restored.token, 'reactivated org must be able to sign in again');
  BADMIN = restored.token;
});

test('the default org cannot be suspended; deletion requires the org code', async () => {
  const suspend = await api('PATCH', `/api/platform/tenants/${tenantAId}`, { token: PA, body: { status: 'suspended' } });
  assert.equal(suspend.status, 400);

  const del = await api('DELETE', `/api/platform/tenants/${tenantBId}`, { token: PA, body: { confirm_slug: 'wrong' } });
  assert.equal(del.status, 400);
});

test('admin password reset is scoped to admins only', async () => {
  const employee = await api('POST', `/api/platform/tenants/${tenantAId}/reset-admin-password`, {
    token: PA, body: { admin_email: 'user@orga.test' },
  });
  assert.equal(employee.status, 404, 'must not be usable to take over an employee account');
});

// ── Platform admin accounts ─────────────────────────────────────────────────

test('platform admin account guards hold', async () => {
  const weak = await api('POST', '/api/platform/admins', {
    token: PA, body: { name: 'X', email: 'x@ifqm.io', password: 'short' },
  });
  assert.equal(weak.status, 400);

  const admins = await api('GET', '/api/platform/admins', { token: PA });
  const meId = admins.data.admins[0].id;

  const self = await api('DELETE', `/api/platform/admins/${meId}`, { token: PA });
  assert.equal(self.status, 400, 'self-delete must be refused');

  const wrongPw = await api('POST', '/api/platform/admins/change-password', {
    token: PA, body: { current_password: 'nope', new_password: 'SomethingLong123' },
  });
  assert.equal(wrongPw.status, 400);
});

// ── Notifications (the platform-admin 500) ──────────────────────────────────

test('notification polling as a platform admin returns empty, not 500', async () => {
  const r = await api('GET', '/api/notifications', { token: PA });
  assert.equal(r.status, 200);
  assert.deepEqual(r.data, { success: true, notifications: [], unread_count: 0 });
});
