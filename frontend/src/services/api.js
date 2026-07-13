import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' },
});

// Attach JWT token from localStorage
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('ifqm_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  const org = localStorage.getItem('ifqm_org');
  if (org && !config.params?.org_slug) {
    config.params = { ...config.params, org_slug: org };
  }
  return config;
});

// Attach org_slug to non-GET requests via the request body
api.interceptors.request.use((config) => {
  if (['post','put','patch','delete'].includes(config.method)) {
    const org = localStorage.getItem('ifqm_org');
    if (org && config.data && typeof config.data === 'object' && !config.data.org_slug) {
      config.data = { ...config.data, org_slug: org };
    }
  }
  return config;
});

// A 401 now means the session is genuinely gone: expired, or revoked server-side
// because the account was deactivated, its role changed, or its password was
// reset. Drop the dead token and bounce to login rather than leaving the UI in a
// half-broken state issuing failing calls.
api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401 && !isAuthEndpoint(err.config?.url)) {
      localStorage.removeItem('ifqm_token');
      localStorage.removeItem('ifqm_org');
      if (!window.location.pathname.startsWith('/login') && window.location.pathname !== '/') {
        window.location.replace('/');
      }
    }
    return Promise.reject(err);
  }
);

// The login call itself returns 401 on bad credentials — that must surface as a
// form error, not a redirect loop.
function isAuthEndpoint(url = '') {
  return url.includes('/auth/login') || url.includes('/auth/reset-password') ||
         url.includes('/auth/forgot-password');
}

export default api;

// ── Auth ──────────────────────────────────────────────────────────
export const authApi = {
  login: (data) => api.post('/auth/login', data),
  logout: () => api.post('/auth/logout'),
  me: () => api.get('/auth/me'),
  forgotPassword: (data) => api.post('/auth/forgot-password', data),
  resetPassword: (data) => api.post('/auth/reset-password', data),
};

// ── Ideas ─────────────────────────────────────────────────────────
export const ideasApi = {
  dashboard: () => api.get('/ideas/dashboard'),
  my: () => api.get('/ideas/my'),
  list: (params) => api.get('/ideas', { params }),
  get: (id) => api.get(`/ideas/${id}`),
  saveDraft: (data) => api.post('/ideas/draft', data),
  submit: (data) => api.post('/ideas/submit', data),
  reviewAction: (data) => api.post('/ideas/review-action', data),
  bulkReview: (data) => api.post('/ideas/bulk-review', data),
  reviewerDecision: (data) => api.post('/ideas/reviewer-decision', data),
  assignReviewers: (data) => api.post('/ideas/assign-reviewers', data),
  checkDuplicate: (params) => api.get('/ideas/check-duplicate', { params }),
  reviewQueue: () => api.get('/ideas/review'),
  updateRoi: (data) => api.post('/ideas/update-roi', data),
  updateImplementation: (data) => api.post('/ideas/update-implementation', data),
};

// ── Votes ─────────────────────────────────────────────────────────
export const votesApi = {
  castVote: (data) => api.post('/votes/rate', data),
  stats: (params) => api.get('/votes/stats', { params }),
  communityStats: (params) => api.get('/votes/community-stats', { params }),
  upvote: (data) => api.post('/votes/upvote', data),
  downvote: (data) => api.post('/votes/downvote', data),
  pollAll: () => api.get('/votes/poll-all'),
  communityVote: (data) => api.post('/votes/community', data),
  board: (params) => api.get('/votes/board', { params }),
};

// ── Leaderboard ───────────────────────────────────────────────────
export const leaderboardApi = {
  get: (params) => api.get('/leaderboard', { params }),
};

// ── Notifications ─────────────────────────────────────────────────
export const notifApi = {
  list: () => api.get('/notifications'),
  markRead: (ids) => api.post('/notifications/mark-read', { ids }),
};

// ── Users ─────────────────────────────────────────────────────────
export const usersApi = {
  list: (params) => api.get('/users', { params }),
  analytics: () => api.get('/reports/analytics'),
  audit: () => api.get('/reports/audit'),
  hierarchy: () => api.get('/users/hierarchy'),
  adminList: (params) => api.get('/users/admin', { params }),
  managers: () => api.get('/users/managers'),
  createUser: (data) => api.post('/users', data),
  updateUser: (data) => api.put(`/users/${data.id}`, data),
  deleteUser: (id) => api.delete(`/users/${id}`),
  profile: () => api.get('/users/profile'),
  resetPasswordAdmin: (data) => api.post('/users/reset-password', data),
};

// ── AI Score ──────────────────────────────────────────────────────
export const scoreApi = {
  batchRescore: () => api.post('/score/batch-rescore'),
};

// ── Settings ──────────────────────────────────────────────────────
export const settingsApi = {
  get: () => api.get('/settings'),
  update: (data) => api.post('/settings', data),
  testEmail: () => api.get('/settings/test-email'),
};

// ── Challenges ────────────────────────────────────────────────────
export const challengesApi = {
  list: () => api.get('/challenges'),
  create: (data) => api.post('/challenges', data),
  update: (data) => api.put(`/challenges/${data.id}`, data),
  delete: (id) => api.delete(`/challenges/${id}`),
};

// ── Export ────────────────────────────────────────────────────────
// These used to build URLs with the JWT in the query string
// (`?token=<jwt>`), which leaks the credential into browser history, proxy and
// server access logs, and the Referer header of any outbound link. They also
// pointed at paths the backend never exposed (/ideas-csv vs /ideas), so they
// could not have worked. Downloads now go through the normal authenticated
// client and are handed to the user as a blob.
async function downloadBlob(path, filename) {
  const res = await api.get(path, { responseType: 'blob' });
  saveBlob(res.data, filename);
}

export const exportApi = {
  ideasCsv: () => downloadBlob('/export/ideas', 'ideas.csv'),
  leaderboardCsv: () => downloadBlob('/export/leaderboard', 'leaderboard.csv'),
  analyticsHtml: () => downloadBlob('/export/analytics', 'analytics.html'),
};

/** Trigger a browser "save as" for an in-memory blob. */
export function saveBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename || 'download';
  document.body.appendChild(a);
  a.click();
  a.remove();
  // Revoke on the next tick so the click has definitely been dispatched.
  setTimeout(() => URL.revokeObjectURL(url), 0);
}

// ── Upload ────────────────────────────────────────────────────────
export const uploadApi = {
  upload: (formData) => api.post('/upload', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
  delete: (id) => api.delete(`/upload/${id}`),

  // Attachments are no longer public files on disk — they are fetched through
  // an authenticated, tenant-scoped endpoint. <img src> and <a href> cannot
  // carry an Authorization header, so we pull the bytes and hand back an
  // object URL rather than putting a credential in the URL.
  fetchBlob: (id) => api.get(`/upload/${id}/download`, { responseType: 'blob' }).then((r) => r.data),
  download: async (id, filename) => {
    const blob = await uploadApi.fetchBlob(id);
    saveBlob(blob, filename);
  },
};

// ── Platform (platform admin only) ───────────────────────────────
export const platformApi = {
  tenants: () => api.get('/platform/tenants'),
  tenantHierarchy: (id) => api.get(`/platform/tenants/${id}/hierarchy`),
  tenantDetail: (id) => api.get(`/platform/tenants/${id}`),
  createTenant: (data) => api.post('/platform/tenants', data),
};
