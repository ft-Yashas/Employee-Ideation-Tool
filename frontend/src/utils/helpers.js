export function escHtml(str) {
  if (str == null) return '';
  return String(str)
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;');
}

export function fmtDate(dateStr) {
  if (!dateStr) return '–';
  try {
    return new Date(dateStr).toLocaleDateString('en-IN', {day:'2-digit',month:'short',year:'numeric'});
  } catch { return dateStr; }
}

export function timeAgo(dateStr, t) {
  if (!dateStr) return '';
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 2) return (t && t('time.just_now')) || 'Just now';
  if (mins < 60) return mins + ((t && t('time.min_ago')) || ' min ago');
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return hrs + ((t && t('time.hr_ago')) || 'h ago');
  return Math.floor(hrs / 24) + ((t && t('time.day_ago')) || 'd ago');
}

export function statusBadge(status) {
  const map = {
    'Submitted':'badge-submitted',
    'Under Review':'badge-review',
    'Approved':'badge-approved',
    'Rejected':'badge-rejected',
    'Implemented':'badge-implemented',
    'Draft':'badge-draft',
  };
  return map[status] || 'badge-draft';
}

export function impactBadge(level) {
  const map = { Low:'badge-low', Medium:'badge-medium', High:'badge-high' };
  return map[level] || 'badge-draft';
}

export function scoreBadgeClass(score) {
  const s = parseInt(score) || 0;
  if (s >= 75) return 'score-badge score-high';
  if (s >= 50) return 'score-badge score-med';
  if (s > 0)   return 'score-badge score-low';
  return 'score-badge score-none';
}

export function actionLabel(action) {
  const m = {
    'Submitted':'S','Approved':'A','Rejected':'R','Under Review':'U',
    'Implemented':'I','Escalated':'E','Comment':'C','Draft':'D',
  };
  return m[action] || action?.[0] || '?';
}

export function translateStatus(status, t) {
  if (!t) return status;
  const map = {
    'Submitted': t('status.submitted'),
    'Under Review': t('status.review'),
    'Approved': t('status.approved'),
    'Rejected': t('status.rejected'),
    'Implemented': t('status.implemented'),
    'Draft': t('status.draft'),
  };
  return map[status] || status;
}

export function translateImpact(level, t) {
  if (!t) return level;
  const map = {
    'Low': t('impact.low'),
    'Medium': t('impact.medium'),
    'High': t('impact.high'),
  };
  return map[level] || level;
}

export function formatRole(role) {
  if (!role) return '';
  return role.replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase());
}

export function engagementIndex(ai_score, avg_rating, vote_count) {
  const a = (parseFloat(avg_rating) || 0) * 20;
  const v = Math.min(parseInt(vote_count) || 0, 20) / 20 * 100;
  return Math.round(a * 0.4 + v * 0.3 + (parseInt(ai_score) || 0) * 0.3);
}

export function communityScore(aiScore, upvotes, downvotes) {
  const net = (parseInt(upvotes) || 0) - (parseInt(downvotes) || 0);
  const adj = Math.max(-20, Math.min(20, net * 3));
  return Math.max(0, Math.min(100, (parseInt(aiScore) || 0) + adj));
}

export function animateCounter(el, target, duration = 900) {
  if (!el) return;
  const start = performance.now();
  const from = parseInt(el.textContent) || 0;
  function step(now) {
    const p = Math.min((now - start) / duration, 1);
    const ease = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(from + (target - from) * ease);
    if (p < 1) requestAnimationFrame(step);
    else el.textContent = target;
  }
  requestAnimationFrame(step);
}

export const ROLE_PRIV = ['team_lead','project_lead','manager','senior_manager','executive','admin','super_admin'];
export function isPrivileged(role) { return ROLE_PRIV.includes(role); }
export function isAdmin(role) { return role === 'admin'; }
export function isSuperAdmin(role) { return role === 'super_admin'; }
export function isPlatformAdmin(role) { return role === 'platform_admin'; }
