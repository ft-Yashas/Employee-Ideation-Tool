<?php
require_once __DIR__ . '/api/config.php';
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_set_cookie_params(SESSION_LIFETIME);
session_start();

// Enforce idle-session timeout on page load too
if (!empty($_SESSION['user_id'])) {
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_destroy();
        session_start();
    } else {
        $_SESSION['last_activity'] = time();
    }
}

$loggedIn = !empty($_SESSION['user_id']);
$user     = $_SESSION['user'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>
  // Apply saved theme before paint to prevent flash
  (function(){var t=localStorage.getItem('ifqm-theme');if(t)document.documentElement.setAttribute('data-theme',t)})();
</script>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>IFQM Ideation Platform</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    /* ── Colors ── */
    --primary:#1f2937;--primary-hover:#111827;--primary-light:#d1d5db;--primary-dim:rgba(31,41,55,.12);
    --primary-glow:rgba(31,41,55,.25);
    --success:#10b981;--success-light:#a7f3d0;--success-dim:rgba(16,185,129,.12);
    --danger:#ef4444;--danger-light:#fee2e2;--danger-dim:rgba(239,68,68,.12);
    --warning:#f59e0b;--warning-light:#fef3c7;--warning-dim:rgba(245,158,11,.12);
    --info:#3b82f6;--info-light:#dbeafe;--info-dim:rgba(59,130,246,.12);
    /* ── Surfaces ── */
    --bg:#d1d5db;--surface:#ffffff;--surface-2:#e5e7eb;--surface-3:#f4f5f7;
    --border:#d1d5db;--border-strong:#bcc2cc;--input-border:#c8ccd1;
    --input-bg:#e5e7eb;--panel-bg:#9ca3af;--tag-bg:#d1d5db;
    /* ── Text ── */
    --text:#0a0d10;--text-muted:#4b5563;--heading:#0a0d10;--subtext:#1f2937;
    --label:#6b7280;--subtle:#4b5563;
    /* ── Layout ── */
    --topbar-bg:rgba(244,245,247,.85);--topbar-border:rgba(0,0,0,.08);
    --table-header:#9ca3af;--table-row-hover:#9ca3af;
    --bar-track:#9ca3af;--progress-bg:#9ca3af;--separator:#d1d5db;
    /* ── Sidebar ── */
    --sidebar-bg:#090b0e;--sidebar-border:rgba(255,255,255,.05);
    --sidebar-text:rgba(255,255,255,.55);--sidebar-active-bg:rgba(31,41,55,.4);
    --sidebar-active-text:#ffffff;--sidebar-hover-bg:rgba(255,255,255,.04);
    --sidebar-section-text:rgba(148,163,184,.5);
    /* ── Shadows ── */
    --shadow-xs:0 1px 2px rgba(0,0,0,.04);--shadow-sm:0 1px 2px rgba(0,0,0,.08),0 1px 1px rgba(0,0,0,.05);
    --shadow:0 2px 4px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
    --shadow-md:0 4px 10px rgba(0,0,0,.09),0 2px 4px rgba(0,0,0,.06);
    --shadow-lg:0 12px 24px rgba(0,0,0,.10),0 3px 8px rgba(0,0,0,.06);
    --shadow-xl:0 20px 40px rgba(0,0,0,.12),0 6px 12px rgba(0,0,0,.08);
    /* ── Radii ── */
    --r-sm:6px;--r:8px;--r-md:10px;--r-lg:14px;--r-xl:18px;--r-2xl:24px;--r-full:9999px;
    /* ── Easing ── */
    --ease:cubic-bezier(.4,0,.2,1);--spring:cubic-bezier(.34,1.56,.64,1);
  }
  [data-theme=dark]{
    --bg:#090b0e;--surface:#141720;--surface-2:#181c2a;--surface-3:#1e2333;
    --border:#252a3d;--border-strong:#2d3348;--input-border:#2d3348;
    --input-bg:#181c2a;--panel-bg:#181c2a;--tag-bg:rgba(31,41,55,.15);
    --text:#e0e3e8;--text-muted:#6b7280;--heading:#e0e3e8;--subtext:#8a8f97;
    --label:#6b7280;--subtle:#4b5563;
    --topbar-bg:rgba(9,11,14,.85);--topbar-border:rgba(255,255,255,.04);
    --table-header:#141720;--table-row-hover:#181c2a;
    --bar-track:#1e2333;--progress-bg:#1e2333;--separator:#1e2333;
    --shadow-xs:0 1px 2px rgba(0,0,0,.3);--shadow-sm:0 1px 2px rgba(0,0,0,.4),0 1px 1px rgba(0,0,0,.3);
    --shadow:0 2px 4px rgba(0,0,0,.45);--shadow-md:0 4px 10px rgba(0,0,0,.5),0 2px 4px rgba(0,0,0,.35);
    --shadow-lg:0 12px 24px rgba(0,0,0,.55),0 3px 8px rgba(0,0,0,.4);--shadow-xl:0 20px 40px rgba(0,0,0,.60),0 6px 12px rgba(0,0,0,.45);
    --sidebar-bg:#090b0e;--sidebar-active-bg:rgba(31,41,55,.25);--sidebar-active-text:#ffffff;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  html{scroll-behavior:smooth}
  body{font-family:'Inter',system-ui,-apple-system,'Segoe UI',Arial,sans-serif;background:var(--bg);color:var(--text);font-size:14px;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;transition:background .3s,color .3s;line-height:1.5}
  .card,.kpi-card,.idea-card,.modal,.topbar,.nav-item,.login-card,.form-control,.btn-outline{transition-property:background,border-color,box-shadow,color,transform;transition-duration:.2s,.2s,.2s,.2s,.15s;transition-timing-function:var(--ease)}
  #app{display:flex;height:100vh;overflow:hidden}

  /* ── SIDEBAR ── */
  #sidebar{width:240px;background:var(--sidebar-bg);color:#fff;display:flex;flex-direction:column;flex-shrink:0;transition:width .22s var(--ease);border-right:1px solid var(--sidebar-border);overflow:hidden}
  #sidebar.collapsed{width:64px}
  #main{flex:1;display:flex;flex-direction:column;overflow:hidden}
  #topbar{background:var(--topbar-bg);padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--topbar-border);flex-shrink:0;backdrop-filter:blur(20px) saturate(180%);-webkit-backdrop-filter:blur(20px) saturate(180%);position:relative;z-index:50}
  #content{flex:1;overflow-y:auto;padding:28px 32px}
  .sidebar-logo{padding:16px 18px;display:flex;align-items:center;gap:12px;white-space:nowrap;overflow:hidden;border-bottom:1px solid var(--sidebar-border)}
  .sidebar-logo span{font-size:15px;font-weight:700;color:#fff;letter-spacing:-.3px;transition:opacity .2s;font-family:system-ui,sans-serif}
  .sidebar-logo .logo-icon{width:34px;height:34px;background:rgba(31,41,55,.4);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid rgba(55,65,81,.45)}
  #sidebar.collapsed .sidebar-logo span{opacity:0;width:0;overflow:hidden}
  .nav-section{padding:20px 0 6px;font-size:10px;text-transform:uppercase;letter-spacing:1.5px;color:var(--sidebar-section-text);padding-left:18px;white-space:nowrap;overflow:hidden;font-weight:600}
  #sidebar.collapsed .nav-section{opacity:0}
  .nav-item{display:flex;align-items:center;gap:11px;padding:9px 12px;margin:2px 10px;cursor:pointer;white-space:nowrap;overflow:hidden;border-radius:var(--r-md);border:1px solid transparent;color:var(--sidebar-text);transition:background .15s var(--ease),color .15s,border-color .15s}
  .nav-item:hover{background:var(--sidebar-hover-bg);color:rgba(255,255,255,.88)}
  .nav-item.active{background:var(--sidebar-active-bg);color:var(--sidebar-active-text);border-color:rgba(55,65,81,.3)}
  .nav-item .icon{flex-shrink:0;width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:8px;transition:background .15s;background:rgba(255,255,255,.06)}
  .nav-item.active .icon{background:rgba(55,65,81,.4)}
  .nav-item .icon svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round}
  .nav-item span.label{font-size:13.5px;font-weight:500;transition:opacity .2s}
  #sidebar.collapsed .nav-item span.label{opacity:0;width:0;overflow:hidden}
  #sidebar.collapsed .nav-item{margin:2px 8px;justify-content:center}
  #sidebar.collapsed .nav-item .icon{margin:0}

  /* ── TOPBAR ── */
  .topbar-left{display:flex;align-items:center;gap:14px}
  .page-title{font-size:16px;font-weight:700;color:var(--heading);letter-spacing:-.3px}
  .topbar-right{display:flex;align-items:center;gap:8px}
  .notif-bell{position:relative;cursor:pointer;font-size:13px;font-weight:600;color:var(--text-muted);padding:7px 10px;border:1px solid var(--border);border-radius:var(--r-md);background:var(--surface);transition:all .15s var(--ease)}
  .notif-bell:hover{border-color:var(--primary);color:var(--primary);background:var(--primary-light)}
  .notif-badge{position:absolute;top:-5px;right:-5px;background:#ef4444;color:#fff;border-radius:50%;width:18px;height:18px;font-size:10px;display:flex;align-items:center;justify-content:center;border:2px solid var(--surface);font-weight:700;animation:badge-pop .3s var(--ease),pulse-ring 2s ease-in-out 1s infinite}
  .user-chip{display:flex;align-items:center;gap:8px;background:var(--surface-2);padding:5px 12px 5px 6px;border-radius:var(--r-full);cursor:pointer;transition:all .15s var(--ease);border:1px solid var(--border)}
  .user-chip:hover{background:var(--surface);border-color:var(--border-strong);box-shadow:var(--shadow-sm)}
  .avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#1f2937,#111827);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;box-shadow:0 0 0 2px rgba(31,41,55,.25)}
  .role-badge{font-size:10px;background:var(--primary-dim);color:var(--primary);padding:2px 9px;border-radius:var(--r-full);font-weight:700;letter-spacing:.3px;border:1px solid rgba(31,41,55,.25)}

  /* ── CARDS ── */
  .card{background:var(--surface);border-radius:var(--r-lg);padding:22px;box-shadow:var(--shadow-sm);margin-bottom:16px;border:1px solid var(--border);transition:box-shadow .2s var(--ease),border-color .2s,transform .2s var(--ease)}
  .card:hover{box-shadow:var(--shadow-md);border-color:var(--border-strong)}
  .card-title{font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;text-transform:uppercase;letter-spacing:.6px}

  /* ── KPI ── */
  .kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:28px}
  .kpi-card{background:var(--surface);border-radius:var(--r-lg);padding:22px 24px;box-shadow:var(--shadow-sm);border:1px solid var(--border);transition:transform .18s var(--ease),box-shadow .18s var(--ease),border-color .18s;display:flex;align-items:center;gap:16px;position:relative;overflow:hidden}
  .kpi-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-md);border-color:var(--border-strong)}
  .kpi-val{font-size:30px;font-weight:800;color:var(--heading);line-height:1.1;letter-spacing:-1px}
  .kpi-label{font-size:13px;color:var(--text-muted);margin-top:4px;font-weight:500;letter-spacing:0}
  .kpi-delta{font-size:12px;color:#10b981;margin-top:5px;font-weight:600}

  /* ── TABLES ── */
  table{width:100%;border-collapse:collapse}
  th{background:var(--table-header);color:var(--label);font-size:11.5px;padding:11px 14px;text-align:left;font-weight:600;border-bottom:2px solid var(--border);text-transform:uppercase;letter-spacing:.5px}
  td{padding:12px 14px;border-bottom:1px solid var(--border);font-size:13.5px;vertical-align:middle;color:var(--text)}
  tr:hover td{background:var(--surface-2)}

  /* ── BADGES ── */
  .badge{display:inline-flex;align-items:center;padding:3px 11px;border-radius:var(--r-full);font-size:11.5px;font-weight:600;letter-spacing:.1px;white-space:nowrap}
  .badge-submitted{background:var(--info-light);color:var(--info)}
  .badge-review{background:var(--warning-light);color:#92400e}
  .badge-approved{background:var(--success-light);color:#065f46}
  .badge-rejected{background:var(--danger-light);color:#991b1b}
  .badge-implemented{background:var(--primary-light);color:var(--primary)}
  .badge-draft{background:var(--surface-2);color:var(--text-muted);border:1px solid var(--border)}
  .badge-low{background:var(--success-light);color:#065f46}
  .badge-medium{background:var(--warning-light);color:#92400e}
  .badge-high{background:var(--danger-light);color:#991b1b}

  /* ── BUTTONS ── */
  .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--r-md);border:none;cursor:pointer;font-size:13.5px;font-weight:600;transition:transform .15s var(--ease),box-shadow .15s var(--ease),background .15s,color .15s;letter-spacing:.05px;font-family:inherit;white-space:nowrap}
  .btn:hover{transform:translateY(-1px)}
  .btn:active{transform:translateY(0) scale(.98)}
  .btn-primary{background:var(--primary);color:#fff;box-shadow:0 1px 3px rgba(55,65,81,.3),0 4px 10px rgba(31,41,55,.18)}
  .btn-primary:hover{box-shadow:0 4px 14px rgba(55,65,81,.4);transform:translateY(-2px)}
  .btn-success{background:var(--success);color:#fff;box-shadow:0 1px 3px rgba(16,185,129,.3),0 4px 10px rgba(16,185,129,.15)}
  .btn-success:hover{box-shadow:0 4px 14px rgba(16,185,129,.4);transform:translateY(-2px)}
  .btn-danger{background:var(--danger);color:#fff;box-shadow:0 1px 3px rgba(239,68,68,.3),0 4px 10px rgba(239,68,68,.15)}
  .btn-danger:hover{box-shadow:0 4px 14px rgba(239,68,68,.4);transform:translateY(-2px)}
  .btn-outline{background:var(--surface);border:1.5px solid var(--border);color:var(--text-muted);box-shadow:var(--shadow-xs)}
  .btn-outline:hover{background:var(--surface-2);border-color:var(--border-strong);color:var(--text);box-shadow:var(--shadow-sm)}
  .btn-ghost{background:transparent;color:var(--text-muted);border:1.5px solid transparent}
  .btn-ghost:hover{background:var(--surface-2);border-color:var(--border);color:var(--text)}
  .btn-sm{padding:6px 14px;font-size:12.5px}
  .btn-warning{background:var(--warning);color:#fff;box-shadow:0 1px 3px rgba(245,158,11,.3),0 4px 10px rgba(245,158,11,.15)}
  .btn-warning:hover{box-shadow:0 4px 14px rgba(245,158,11,.4);transform:translateY(-2px)}

  /* ── FORMS ── */
  .form-group{margin-bottom:16px}
  .form-group label{display:block;font-size:11.5px;font-weight:600;color:var(--label);margin-bottom:5px;letter-spacing:.1px}
  .form-control{width:100%;padding:9px 12px;border:1px solid var(--input-border);border-radius:var(--r);font-size:13px;color:var(--text);outline:none;transition:border-color .15s,box-shadow .15s;background:var(--input-bg);font-family:inherit}
  .form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-dim)}
  select.form-control{background:var(--input-bg);color:var(--text)}
  textarea.form-control{resize:vertical;min-height:80px}
  .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}

  /* ── WIZARD ── */
  .wizard-steps{display:flex;gap:0;margin-bottom:24px}
  .w-step{flex:1;text-align:center;padding:10px 4px;font-size:12px;border-bottom:3px solid var(--input-border);color:var(--subtle);cursor:pointer;font-weight:500;transition:color .15s,border-color .15s}
  .w-step.active{border-bottom-color:var(--primary);color:var(--primary);font-weight:700}
  .w-step.done{border-bottom-color:#10b981;color:#10b981}
  .wizard-body{min-height:280px}
  .wizard-footer{display:flex;justify-content:flex-end;gap:10px;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)}

  /* ── TIMELINE ── */
  .timeline{padding:10px 0}
  .tl-item{display:flex;gap:14px;margin-bottom:18px}
  .tl-dot{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0;margin-top:2px}
  .tl-dot-blue{background:#dbeafe;color:#1d4ed8}
  .tl-dot-green{background:#bbf7d0;color:#065f46}
  .tl-dot-red{background:#fee2e2;color:#991b1b}
  .tl-dot-orange{background:#fef3c7;color:#92400e}
  .tl-dot-purple{background:#c8ccd1;color:#4b5563}
  .tl-title{font-size:13px;font-weight:600;color:var(--text)}
  .tl-meta{font-size:11px;color:var(--subtle);margin-top:3px}
  .tl-comment{font-size:12px;color:var(--subtext);margin-top:6px;background:var(--panel-bg);padding:8px 12px;border-radius:var(--r);border-left:3px solid var(--border)}

  /* ── BAR CHARTS ── */
  .bar-chart{display:flex;flex-direction:column;gap:10px}
  .bar-row{display:flex;align-items:center;gap:10px}
  .bar-label{width:100px;font-size:12px;color:var(--label);text-align:right;flex-shrink:0;font-weight:500}
  .bar-track{flex:1;height:8px;background:var(--bar-track);border-radius:10px;overflow:hidden}
  .bar-fill{height:100%;border-radius:10px;transition:width .6s var(--ease)}
  .bar-val{width:36px;font-size:12px;color:var(--subtext);flex-shrink:0;font-weight:600}

  /* ── LEADERBOARD ── */
  .lb-row{display:flex;align-items:center;gap:12px;padding:11px 6px;border-bottom:1px solid var(--border);transition:background .12s;border-radius:var(--r)}
  .lb-row:hover{background:var(--bg)}
  .lb-rank{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0}
  .rank-1{background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#78350f;box-shadow:0 2px 8px rgba(245,158,11,.35)}
  .rank-2{background:linear-gradient(135deg,#94a3b8,#cbd5e1);color:#374151;box-shadow:0 2px 5px rgba(148,163,184,.3)}
  .rank-3{background:linear-gradient(135deg,#f97316,#fbbf24);color:#fff;box-shadow:0 2px 5px rgba(249,115,22,.25)}
  .rank-n{background:var(--tag-bg);color:var(--primary)}
  .lb-name{flex:1;font-size:13px;font-weight:600}
  .lb-dept{font-size:11px;color:var(--subtle);margin-top:1px}
  .lb-points{font-size:16px;font-weight:800;color:var(--heading)}
  .lb-ideas{font-size:11px;color:var(--subtle)}
  .progress-bar{height:4px;background:var(--progress-bg);border-radius:2px;margin-top:5px}
  .progress-fill{height:100%;background:linear-gradient(90deg,#374151,#6b7280);border-radius:2px}

  /* ── SCORE BADGES ── */
  .score-badge{display:inline-flex;align-items:center;padding:3px 8px;border-radius:8px;font-size:10px;font-weight:700}
  .score-high{background:#bbf7d0;color:#065f46}
  .score-med{background:#fef3c7;color:#92400e}
  .score-low{background:#fee2e2;color:#991b1b}
  .score-none{background:var(--bar-track);color:var(--text-muted)}

  /* ── MODALS ── */
  .modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;z-index:1000;display:none}
  .modal-overlay.open{display:flex;animation:mo-in .2s var(--ease)}
  @keyframes mo-in{from{opacity:0}to{opacity:1}}
  @keyframes slide-up{from{transform:translateY(18px);opacity:0}to{transform:translateY(0);opacity:1}}
  .modal{background:var(--surface);border-radius:var(--r-xl);width:620px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-xl);animation:slide-up .22s var(--ease);border:1px solid var(--border)}
  .modal-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);background:var(--surface)}
  .modal-title{font-size:15px;font-weight:700;color:var(--heading)}
  .modal-close{cursor:pointer;font-size:18px;color:var(--subtle);line-height:1;width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:background .12s,color .12s}
  .modal-close:hover{background:var(--border);color:var(--text)}
  .modal-body{padding:22px}
  .modal-footer{padding:14px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;background:var(--bg);border-radius:0 0 var(--r-xl) var(--r-xl)}

  /* ── PAGES ── */
  .page{display:none}
  .page.active{display:block;animation:mo-in .18s var(--ease)}
  .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
  .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
  .text-muted{color:var(--subtle);font-size:12px;margin-top:2px}
  .text-center{text-align:center}
  .mt-8{margin-top:8px}
  .mb-8{margin-bottom:8px}
  .tag{display:inline-block;padding:2px 9px;background:var(--tag-bg);color:var(--primary);border-radius:10px;font-size:11px;margin:2px;font-weight:500}

  /* ── ALERTS ── */
  .alert{padding:11px 16px;border-radius:var(--r);font-size:13px;margin-bottom:14px;font-weight:500}
  .alert-info{background:#dbeafe;color:#1d4ed8;border-left:4px solid #2563eb}
  .alert-success{background:#a7f3d0;color:#065f46;border-left:4px solid #10b981}
  .alert-warning{background:#fef3c7;color:#92400e;border-left:4px solid #f59e0b}
  .alert-danger{background:#fee2e2;color:#991b1b;border-left:4px solid #ef4444}

  /* ── AI PANEL ── */
  .ai-panel{background:var(--primary-light);border-radius:var(--r-lg);padding:16px;border:1px solid rgba(31,41,55,.25)}
  .ai-panel-title{font-size:11px;font-weight:700;color:var(--primary);margin-bottom:10px;text-transform:uppercase;letter-spacing:.6px}

  /* ── IMPACT CHIPS ── */
  .impact-grid{display:flex;flex-wrap:wrap;gap:8px}
  .impact-chip{padding:5px 13px;border-radius:20px;font-size:12px;cursor:pointer;border:1px solid var(--input-border);background:var(--chip-bg);color:var(--subtext);transition:all .15s var(--ease);font-weight:500}
  .impact-chip:hover{border-color:var(--primary);color:var(--primary)}
  .impact-chip.selected{background:var(--primary);color:#fff;border-color:var(--primary);box-shadow:0 2px 8px rgba(55,65,81,.3)}

  /* ── UPLOAD ── */
  .upload-zone{border:2px dashed var(--border);border-radius:var(--r-lg);padding:28px;text-align:center;color:var(--text-muted);cursor:pointer;transition:all .15s var(--ease)}
  .upload-zone:hover{border-color:var(--primary);background:var(--primary-light);color:var(--primary)}

  /* ── NOTIFICATIONS ── */
  .notification-panel{position:fixed;top:52px;right:20px;width:320px;background:var(--surface);border-radius:var(--r-lg);box-shadow:var(--shadow-xl);z-index:9999;display:none;border:1px solid var(--border);overflow:hidden;max-height:calc(100vh - 70px)}
  .notification-panel.open{display:block;animation:slide-up .2s var(--ease)}
  .notif-item{padding:12px 16px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .12s;color:var(--text)}
  .notif-item:hover{background:var(--bg)}
  .notif-item.unread{border-left:3px solid var(--primary);background:var(--primary-light)}
  .notif-item-title{font-size:13px;font-weight:600}
  .notif-item-meta{font-size:11px;color:var(--text-muted);margin-top:2px}

  /* ── LOGIN ── */
  .separator{height:1px;background:var(--separator);margin:16px 0}

  /* ── SIDEBAR USER ── */
  .sidebar-user{padding:14px 16px;border-top:1px solid var(--sidebar-border);display:flex;align-items:center;gap:11px;margin-top:auto;overflow:hidden;background:rgba(0,0,0,.12)}
  .sidebar-user-info span{font-size:12px;color:var(--sidebar-text);display:block;white-space:nowrap;transition:opacity .2s}
  #sidebar.collapsed .sidebar-user-info span{opacity:0;width:0;overflow:hidden}
  .points-badge{background:rgba(245,158,11,.2);color:#fbbf24;font-size:11px;font-weight:700;padding:2px 8px;border-radius:var(--r-full);border:1px solid rgba(245,158,11,.3)}

  /* ── IDEA CARDS ── */
  .idea-card{background:var(--surface);border-radius:var(--r-lg);border:1px solid var(--border);padding:18px;margin-bottom:12px;cursor:pointer;transition:transform .18s var(--ease),box-shadow .18s var(--ease),border-color .18s}
  .idea-card:hover{box-shadow:var(--shadow-md);transform:translateY(-2px);border-color:var(--border-strong)}
  .idea-card-header{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
  .idea-card-id{font-size:10.5px;color:var(--primary);font-weight:700;text-transform:uppercase;letter-spacing:.5px;opacity:.8}
  .idea-card-title{font-size:14.5px;font-weight:600;color:var(--heading);margin-top:3px;letter-spacing:-.1px}
  .idea-card-meta{font-size:11.5px;color:var(--text-muted);margin-top:6px}
  .idea-card-footer{display:flex;align-items:center;justify-content:space-between;margin-top:12px}

  /* ── FILTER BAR ── */
  .filter-bar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
  .filter-bar input,.filter-bar select{padding:8px 12px;border:1px solid var(--input-border);border-radius:var(--r);font-size:13px;outline:none;transition:border-color .15s,box-shadow .15s;font-family:inherit;background:var(--input-bg);color:var(--text)}
  .filter-bar input:focus,.filter-bar select:focus{border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-dim)}

  /* ── TABS ── */
  .tabs{display:flex;border-bottom:1px solid var(--border);margin-bottom:18px;gap:2px}
  .tab{padding:9px 18px;cursor:pointer;font-size:13px;font-weight:500;color:var(--subtle);border-bottom:2px solid transparent;margin-bottom:-1px;transition:color .15s,border-color .15s;border-radius:var(--r) var(--r) 0 0}
  .tab:hover{color:var(--primary);background:var(--primary-light)}
  .tab.active{color:var(--primary);border-bottom-color:var(--primary);font-weight:600}
  .tab-content{display:none}
  .tab-content.active{display:block}

  /* ── CHIPS ── */
  .chip-filter{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
  .chip{padding:5px 14px;border-radius:20px;font-size:12px;cursor:pointer;border:1px solid var(--input-border);background:var(--chip-bg);color:var(--subtext);transition:all .15s var(--ease);font-weight:500}
  .chip:hover{border-color:var(--primary);color:var(--primary)}
  .chip.active{background:var(--primary);color:#fff;border-color:var(--primary);box-shadow:0 2px 8px rgba(55,65,81,.3)}

  /* ── MINI STATS ── */
  .mini-stat{display:flex;flex-direction:column;align-items:center;padding:16px;background:var(--bg);border-radius:var(--r-lg);flex:1;border:1px solid var(--border)}
  .mini-stat-val{font-size:22px;font-weight:800;color:var(--heading)}
  .mini-stat-label{font-size:11px;color:var(--subtle);margin-top:4px;text-align:center;font-weight:500}

  /* ── SPINNER ── */
  .spinner{display:inline-block;width:20px;height:20px;border:2px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle}
  @keyframes spin{to{transform:rotate(360deg)}}

  /* ── EMPTY STATE ── */
  .empty-state{text-align:center;padding:60px 20px;color:var(--subtle);font-size:13px;font-weight:500}
  .empty-state::before{content:'⊘';display:block;font-size:32px;margin-bottom:10px;opacity:.35}

  /* ── DARK MODE TOGGLE ── */
  .dm-toggle{display:flex;align-items:center;gap:7px;cursor:pointer;padding:6px 11px;border:1px solid var(--input-border);border-radius:var(--r);background:var(--surface);transition:all .15s var(--ease);font-size:13px;font-weight:600;color:var(--text-muted);user-select:none}
  .dm-toggle:hover{border-color:var(--primary);color:var(--primary);background:var(--primary-light)}
  .dm-track{width:32px;height:18px;background:var(--border);border-radius:9px;position:relative;transition:background .25s;flex-shrink:0}
  .dm-track.on{background:var(--primary)}
  .dm-thumb{position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform .25s var(--ease);box-shadow:0 1px 3px rgba(0,0,0,.25)}
  .dm-track.on .dm-thumb{transform:translateX(14px)}

  /* ── USER SEARCH ── */
  .user-search-results{border:1.5px solid var(--input-border);border-radius:var(--r);background:var(--surface);max-height:160px;overflow-y:auto;display:none;position:absolute;z-index:10;width:100%;box-shadow:var(--shadow-md)}
  .user-search-results .uitem{padding:9px 14px;cursor:pointer;font-size:13px;transition:background .12s;color:var(--text)}
  .user-search-results .uitem:hover{background:var(--bg)}
  .pos-rel{position:relative}

  /* ── SUPER ADMIN PANE ── */
  .sa-pane{display:block;animation:fade-in .25s var(--ease)}

  /* ── STAR RATING ── */
  .star-rating{display:inline-flex;gap:1px;align-items:center;line-height:1}
  .star{font-size:16px;color:#ddd;transition:color .08s;cursor:pointer;user-select:none;-webkit-user-select:none}
  .star.active,.star.hover{color:#f59e0b}
  .star-rating.readonly .star{cursor:default;pointer-events:none}
  .star-widget{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
  .eng-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:700;border:1px solid}

  /* ── COMMUNITY VOTE WIDGET ── */
  .vote-widget{display:inline-flex;align-items:center;gap:3px}
  .vote-btn{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;border:1px solid var(--border);background:var(--surface);color:var(--text-muted);transition:all .15s var(--ease);user-select:none;-webkit-user-select:none;line-height:1}
  .vote-btn:hover{transform:translateY(-1px);box-shadow:var(--shadow-sm)}
  .vote-btn.up:hover{background:#bbf7d0;color:#065f46;border-color:#86efac}
  .vote-btn.up-active{background:#bbf7d0;color:#065f46;border-color:#86efac}
  .vote-btn.down:hover{background:#fee2e2;color:#991b1b;border-color:#fca5a5}
  .vote-btn.down-active{background:#fee2e2;color:#991b1b;border-color:#fca5a5}
  .vote-btn.vote-disabled{cursor:not-allowed;opacity:.45;pointer-events:none}
  .vote-net{font-size:11px;font-weight:800;min-width:22px;text-align:center;color:var(--heading);padding:0 2px}
  @keyframes vote-pop{0%{transform:scale(1)}40%{transform:scale(1.35)}100%{transform:scale(1)}}
  .vote-pop{animation:vote-pop .22s var(--ease)}

  /* ── LANGUAGE DROPDOWN ── */
  .lang-wrap{position:relative;display:inline-flex}
  .lang-toggle{background:var(--surface);border:1px solid var(--input-border);border-radius:var(--r);padding:5px 22px 5px 10px;cursor:pointer;font-size:11px;font-weight:700;color:var(--text-muted);transition:all .15s var(--ease);letter-spacing:.5px;line-height:1;font-family:inherit;display:flex;align-items:center;gap:5px;white-space:nowrap}
  .lang-toggle:hover,.lang-wrap.open .lang-toggle{border-color:var(--primary);color:var(--primary);background:var(--primary-light)}
  .lang-toggle::after{content:'▾';position:absolute;right:7px;top:50%;transform:translateY(-50%);font-size:9px;pointer-events:none;transition:transform .15s}
  .lang-wrap.open .lang-toggle::after{transform:translateY(-50%) rotate(180deg)}
  .lang-menu{position:fixed;top:0;right:0;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--shadow-xl);z-index:10000;display:none;overflow:hidden;min-width:140px}
  .lang-wrap.open .lang-menu{display:block;animation:slide-up .15s var(--ease)}
  .lang-opt{padding:8px 14px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:background .12s;color:var(--text)}
  .lang-opt:hover{background:var(--primary-light);color:var(--primary)}
  .lang-opt.active{font-weight:700;color:var(--primary);background:var(--primary-light)}
  .lang-opt-code{font-size:11px;font-weight:700;min-width:22px;color:var(--text-muted)}
  .lang-opt.active .lang-opt-code{color:var(--primary)}

  /* ── TOP IDEAS ── */
  .top-idea-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)}
  .top-idea-rank{width:24px;text-align:center;font-size:11px;font-weight:800;color:#500aaa}

  /* ── LOGIN SPLIT LAYOUT ── */
  .login-wrap{min-height:100vh;display:flex;align-items:stretch;background:none}
  .login-left{flex:0 0 440px;background:var(--sidebar-bg);color:#fff;display:flex;flex-direction:column;justify-content:center;padding:60px 48px;overflow:hidden;position:relative}
  .login-left::before{content:'';position:absolute;top:-120px;right:-60px;width:360px;height:360px;background:radial-gradient(circle,rgba(55,65,81,.3) 0%,transparent 70%);border-radius:50%;pointer-events:none;animation:float 7s ease-in-out infinite}
  .login-left::after{content:'';position:absolute;bottom:-80px;left:-80px;width:300px;height:300px;background:radial-gradient(circle,rgba(107,114,128,.2) 0%,transparent 70%);border-radius:50%;pointer-events:none;animation:float 9s ease-in-out infinite reverse}
  .login-left .bubble{position:absolute;border-radius:50%;background:rgba(255,255,255,.02);pointer-events:none}
  .login-left .bubble-1{width:140px;height:140px;top:25%;left:-50px;animation:float 5s ease-in-out infinite 1s}
  .login-left .bubble-2{width:80px;height:80px;top:65%;right:25px;animation:float 4s ease-in-out infinite .5s}
  .login-feature{display:flex;align-items:flex-start;gap:16px;margin-bottom:28px}
  .login-feature-icon{width:44px;height:44px;background:rgba(31,41,55,.3);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid rgba(31,41,55,.4)}
  .login-feature-title{font-size:14px;font-weight:700;margin-bottom:5px;line-height:1.3}
  .login-feature-sub{font-size:12px;opacity:.55;line-height:1.5}
  .login-right{flex:1;display:flex;align-items:center;justify-content:center;background:var(--bg);padding:40px}
  .login-card{background:var(--surface);border-radius:var(--r-xl);padding:40px;width:100%;max-width:400px;box-shadow:var(--shadow-xl);border:1px solid var(--border)}
  .login-logo{text-align:left;margin-bottom:32px}
  .login-logo h2{font-size:22px;color:var(--heading);font-weight:800;margin-bottom:4px;letter-spacing:-.3px}
  .login-logo p{font-size:13px;color:var(--text-muted)}

  /* ── KPI ICON ── */
  .kpi-card{display:flex;align-items:center;gap:14px;position:relative;overflow:hidden}
  .kpi-card::after{content:'';position:absolute;right:-16px;top:-16px;width:72px;height:72px;border-radius:50%;opacity:.05;background:currentColor}
  .kpi-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:transform .2s var(--ease)}
  .kpi-card:hover .kpi-icon{transform:scale(1.08) rotate(-3deg)}
  .kpi-icon svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .kpi-body{flex:1}

  /* ── WIZARD STEP CIRCLES ── */
  .wizard-steps{display:flex;align-items:flex-start;gap:0;margin-bottom:28px;position:relative}
  .wizard-steps::before{content:'';position:absolute;top:16px;left:10%;right:10%;height:2px;background:var(--border);z-index:0}
  .w-step{flex:1;text-align:center;padding:0 4px 10px;font-size:11px;border-bottom:none;color:var(--subtle);cursor:pointer;font-weight:500;transition:color .15s;position:relative;z-index:1}
  .w-step .w-num{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:var(--bar-track);color:var(--text-muted);font-size:12px;font-weight:700;margin:0 auto 6px;border:2px solid var(--border);transition:all .2s var(--ease)}
  .w-step .w-lbl{font-size:11px;font-weight:500;white-space:nowrap}
  .w-step.active{color:var(--primary)}
  .w-step.active .w-num{background:var(--primary);color:#fff;border-color:var(--primary);box-shadow:0 2px 10px rgba(55,65,81,.4)}
  .w-step.done{color:var(--success)}
  .w-step.done .w-num{background:var(--success);color:#fff;border-color:var(--success)}

  /* ── SECTION HEADER ACCENT ── */
  .section-header .page-title{position:relative;padding-left:14px}
  .section-header .page-title::before{content:'';position:absolute;left:0;top:1px;bottom:1px;width:3px;background:var(--primary);border-radius:2px}

  /* ── SCROLLBAR ── */
  #content::-webkit-scrollbar{width:6px}
  #content::-webkit-scrollbar-track{background:transparent}
  #content::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
  #content::-webkit-scrollbar-thumb:hover{background:var(--text-muted)}

  /* ── GLOBAL ANIMATIONS ── */
  @keyframes fadeInUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
  @keyframes fadeInDown{from{opacity:0;transform:translateY(-16px)}to{opacity:1;transform:translateY(0)}}
  @keyframes fadeInLeft{from{opacity:0;transform:translateX(-16px)}to{opacity:1;transform:translateX(0)}}
  @keyframes fadeInRight{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}
  @keyframes scaleIn{from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}
  @keyframes pulse-ring{0%{box-shadow:0 0 0 0 rgba(244,67,54,.5)}70%{box-shadow:0 0 0 7px rgba(244,67,54,0)}100%{box-shadow:0 0 0 0 rgba(244,67,54,0)}}
  @keyframes shimmer{0%{background-position:-400px 0}100%{background-position:400px 0}}
  @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
  @keyframes count-up{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
  @keyframes glow{0%,100%{box-shadow:0 0 0 rgba(255,255,255,.15)}50%{box-shadow:0 0 12px rgba(255,255,255,.3)}}
  @keyframes bar-grow{from{width:0}to{width:var(--bar-w,100%)}}
  @keyframes slide-in-sidebar{from{opacity:0;transform:translateX(-8px)}to{opacity:1;transform:translateX(0)}}
  @keyframes badge-pop{0%{transform:scale(0)}60%{transform:scale(1.2)}100%{transform:scale(1)}}
  @keyframes ripple{to{transform:scale(4);opacity:0}}
  @keyframes gradient-shift{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
  @keyframes tl-item-in{from{opacity:0;transform:translateX(-12px)}to{opacity:1;transform:translateX(0)}}
  @keyframes kpi-count{from{opacity:0;transform:translateY(6px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}

  /* ── ANIMATED PAGE TRANSITIONS ── */
  .page.active{animation:fadeInUp .28s var(--ease)}

  /* ── ANIMATED KPI CARDS ── */
  .kpi-card{animation:fadeInUp .35s var(--ease) both}
  .kpi-grid .kpi-card:nth-child(1){animation-delay:.04s}
  .kpi-grid .kpi-card:nth-child(2){animation-delay:.09s}
  .kpi-grid .kpi-card:nth-child(3){animation-delay:.14s}
  .kpi-grid .kpi-card:nth-child(4){animation-delay:.19s}
  .kpi-val{animation:kpi-count .4s var(--ease) both;animation-delay:.25s}

  /* ── ANIMATED CARDS ── */
  .card{animation:scaleIn .3s var(--ease) both}

  /* ── IDEA CARD STAGGER ── */
  .idea-card{animation:fadeInUp .28s var(--ease) both}

  /* ── NAV ITEM ANIMATIONS ── */
  .nav-item{animation:slide-in-sidebar .22s var(--ease) both}
  .nav-item:nth-child(1){animation-delay:.03s}
  .nav-item:nth-child(2){animation-delay:.06s}
  .nav-item:nth-child(3){animation-delay:.09s}
  .nav-item:nth-child(4){animation-delay:.12s}
  .nav-item:nth-child(5){animation-delay:.15s}
  .nav-item:nth-child(6){animation-delay:.18s}
  .nav-item:nth-child(7){animation-delay:.21s}
  .nav-item:nth-child(8){animation-delay:.24s}

  /* ── LEADERBOARD ROW STAGGER ── */
  .lb-row{animation:fadeInLeft .25s var(--ease) both}

  /* ── TIMELINE STAGGER ── */
  .tl-item{animation:tl-item-in .3s var(--ease) both}

  /* ── SKELETON LOADER ── */
  .skeleton{background:linear-gradient(90deg,var(--bar-track) 25%,var(--border) 50%,var(--bar-track) 75%);background-size:400px 100%;animation:shimmer 1.4s infinite linear;border-radius:var(--r);display:inline-block}

  /* ── BAR FILL BASE ── */
  .bar-fill{transition:width .7s cubic-bezier(.4,0,.2,1)}

  /* ── BUTTON RIPPLE ── */
  .btn{position:relative;overflow:hidden}
  .btn .ripple-el{position:absolute;border-radius:50%;background:rgba(255,255,255,.35);transform:scale(0);animation:ripple .6s linear;pointer-events:none}

  /* ── NOTIF BADGE POP ── */
  .notif-badge{animation:badge-pop .3s var(--ease)}

  /* ── ACTIVE NAV GLOW ── */
  .nav-item.active .icon{animation:none}

  /* ── ANIMATED LOGIN ── */
  .login-card{animation:scaleIn .38s var(--ease)}
  .login-left{overflow:hidden}
  .login-feature{animation:fadeInLeft .35s var(--ease) both}
  .login-feature:nth-child(4){animation-delay:.12s}
  .login-feature:nth-child(5){animation-delay:.22s}
  .login-feature:nth-child(6){animation-delay:.32s}
  .login-left h1{animation:fadeInDown .4s var(--ease)}
  .login-left p{animation:fadeInDown .4s var(--ease) .08s both}

  /* ── ANIMATED MODAL ── */
  .modal{animation:scaleIn .22s var(--ease)}

  /* ── ANIMATED TABS ── */
  .tab{position:relative}
  .tab::after{content:'';position:absolute;bottom:-1px;left:50%;right:50%;height:2px;background:var(--primary);transition:left .22s var(--ease),right .22s var(--ease)}
  .tab.active::after{left:0;right:0}

  /* ── WIZARD STEP TRANSITION ── */
  .wizard-body{animation:fadeInUp .22s var(--ease)}

  /* ── TOPBAR SLIDE DOWN ── */
  #topbar{animation:fadeInDown .3s var(--ease)}

  /* ── UPLOAD ZONE PULSE ON DRAG ── */
  @keyframes upload-pulse{0%,100%{box-shadow:0 0 0 0 rgba(55,65,81,.3)}50%{box-shadow:0 0 0 8px rgba(55,65,81,0)}}
  .upload-zone.drag-over{border-color:var(--primary);background:var(--primary-light);animation:upload-pulse 1s ease-in-out infinite}

  /* ── ANIMATED SCORE BADGE ── */
  .score-badge{transition:transform .15s var(--ease)}
  .score-badge:hover{transform:scale(1.08)}

  /* ── FLOATING ACTION HINT ── */
  .btn-primary.btn-fab{animation:float 3s ease-in-out infinite}

  /* ── CHIP CLICK ANIMATION ── */
  .chip,.impact-chip{transition:all .18s var(--ease),transform .12s}
  .chip:active,.impact-chip:active{transform:scale(.94)}

  /* ── TABLE ROW FADE ── */
  tbody tr{animation:fadeInUp .22s var(--ease) both}
  tbody tr:nth-child(1){animation-delay:.03s}
  tbody tr:nth-child(2){animation-delay:.06s}
  tbody tr:nth-child(3){animation-delay:.09s}
  tbody tr:nth-child(4){animation-delay:.12s}
  tbody tr:nth-child(5){animation-delay:.15s}
  tbody tr:nth-child(6){animation-delay:.18s}
  tbody tr:nth-child(7){animation-delay:.21s}
  tbody tr:nth-child(8){animation-delay:.24s}

  /* ── PROGRESS FILL ANIMATE ── */
  .progress-fill{transition:width .8s var(--ease)}

  /* ── SIDEBAR LOGO ── */
  .sidebar-logo{background:rgba(255,255,255,.02)}

  /* ── SIDEBAR TOOLTIP (collapsed) ── */
  #sidebar.collapsed .nav-item{position:relative}
  #sidebar.collapsed .nav-item:hover::after{content:attr(data-label);position:absolute;left:58px;top:50%;transform:translateY(-50%);background:#0f172a;color:#fff;font-size:12px;font-weight:600;padding:5px 12px;border-radius:7px;white-space:nowrap;pointer-events:none;box-shadow:0 4px 12px rgba(0,0,0,.35);z-index:100;border:1px solid rgba(255,255,255,.08)}

  /* ── STATUS STRIPE ON IDEA CARDS ── */
  .idea-card[data-status="Approved"]{border-left:3px solid #10b981}
  .idea-card[data-status="Rejected"]{border-left:3px solid #ef4444}
  .idea-card[data-status="Implemented"]{border-left:3px solid #4b5563}
  .idea-card[data-status="Under Review"]{border-left:3px solid #f59e0b}
  .idea-card[data-status="Submitted"]{border-left:3px solid #374151}
  .idea-card[data-status="Draft"]{border-left:3px solid #9ca3af}
</style>
</head>
<body>

<div id="login-page" class="login-wrap" <?= $loggedIn ? 'style="display:none"' : '' ?>>

  <div class="login-left">
    <div class="bubble bubble-1"></div>
    <div class="bubble bubble-2"></div>
    <div style="text-align:center;margin-bottom:36px">
      <img src="assets/ifqm-logo.png" alt="IFQM" style="height:56px;margin-bottom:14px;background:#fff;border-radius:10px;padding:6px 12px;object-fit:contain;box-shadow:0 2px 8px rgba(0,0,0,.12)">
      <h2 style="font-size:21px;color:#ffffff;font-weight:800;margin-top:10px" data-i18n="login.app_title">IFQM Ideation</h2>
      <p style="font-size:13px;color:rgba(255,255,255,.65);margin-top:6px;line-height:1.5" data-i18n="login.tagline">Turn great ideas into real improvements</p>
    </div>

    <div class="login-feature">
      <div class="login-feature-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21h6M12 3a6 6 0 016 6c0 2.2-1.1 3.8-2.5 5L15 16H9l-.5-2C7 12.8 6 11.2 6 9a6 6 0 016-6z"/></svg>
      </div>
      <div><div class="login-feature-title" data-i18n="login.feat1_title">Submit &amp; Track Ideas</div><div class="login-feature-sub" data-i18n="login.feat1_sub">5-step wizard with AI quality scoring</div></div>
    </div>
    <div class="login-feature">
      <div class="login-feature-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      </div>
      <div><div class="login-feature-title" data-i18n="login.feat2_title">Earn Points &amp; Rewards</div><div class="login-feature-sub" data-i18n="login.feat2_sub">+10 submit &nbsp;&middot;&nbsp; +25 approved &nbsp;&middot;&nbsp; +65 implemented</div></div>
    </div>
    <div class="login-feature">
      <div class="login-feature-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      </div>
      <div><div class="login-feature-title" data-i18n="login.feat3_title">Analytics &amp; Leaderboard</div><div class="login-feature-sub" data-i18n="login.feat3_sub">Real-time insights across departments</div></div>
    </div>
  </div>

  <div class="login-right">
    <div class="login-card">
      <div class="login-logo">
        <img src="assets/ifqm-logo.png" alt="IFQM" style="height:40px;margin-bottom:10px;background:#fff;border-radius:8px;padding:4px 10px;object-fit:contain;box-shadow:0 1px 4px rgba(0,0,0,.1)">
        <h2 data-i18n="login.welcome">Welcome back</h2>
        <p data-i18n="login.subtitle">Sign in to your IFQM account to continue</p>
      </div>
      <div id="login-error" class="alert alert-danger" style="display:none"></div>
      <div class="form-group" id="login-org-group">
        <label data-i18n="login.org_code">Organization Code</label>
        <input class="form-control" id="login-org" type="text" placeholder="your-org-code" autocomplete="organization" style="text-transform:lowercase"/>
        <div style="font-size:11px;color:var(--subtle);margin-top:4px" data-i18n="login.org_hint">Leave blank to use IFQM platform admin login</div>
      </div>
      <div class="form-group">
        <label data-i18n="login.email">Email Address</label>
        <input class="form-control" id="login-email" type="email" data-i18n-ph="login.email_ph" placeholder="admin@yourorg.com" autocomplete="email"/>
      </div>
      <div class="form-group">
        <label data-i18n="login.password">Password</label>
        <input class="form-control" id="login-pass" type="password" data-i18n-ph="login.password_ph" placeholder="••••••••" autocomplete="current-password"/>
      </div>
      <button class="btn btn-primary" id="login-btn" style="width:100%;justify-content:center;padding:11px;font-size:14px" data-i18n="login.btn">Sign In</button>
      <div class="separator"></div>
      <p style="font-size:11px;color:#aaa;text-align:center">Powered by IFQM &middot; Multi-Tenant &middot; Role-Based Access Control</p>
      <div style="text-align:center;margin-top:4px">
        <a href="#" onclick="openForgotPassword();return false" style="font-size:11px;color:#888;text-decoration:none" data-i18n="login.forgot">Forgot your password?</a>
      </div>
    </div>
  </div>

</div>

<div id="main-app" style="<?= $loggedIn ? '' : 'display:none;' ?>height:100vh">
<div id="app">

  <div id="sidebar">
    <div class="sidebar-logo">
      <img src="assets/ifqm-logo.png" alt="IFQM" style="height:28px;flex-shrink:0;background:#fff;border-radius:6px;padding:3px 8px;object-fit:contain;box-shadow:0 1px 3px rgba(0,0,0,.15)">
      <span style="font-weight:700;letter-spacing:-.3px" data-i18n="app.name">IdeaFlow</span>
    </div>

    <div class="nav-section" data-i18n="section.main">Main</div>
    <div class="nav-item active" id="nav-dashboard" data-label="Dashboard" onclick="navigate('dashboard',this)"><span class="icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span><span class="label" data-i18n="nav.dashboard">Dashboard</span></div>
    <div class="nav-item" id="nav-my-ideas" data-label="My Ideas" onclick="navigate('my-ideas',this)"><span class="icon"><svg viewBox="0 0 24 24"><path d="M9 21h6M12 3a6 6 0 016 6c0 2.2-1.1 3.8-2.5 5L15 16H9l-.5-2C7 12.8 6 11.2 6 9a6 6 0 016-6z"/></svg></span><span class="label" data-i18n="nav.my_ideas">My Ideas</span></div>
    <div class="nav-item" data-label="Submit Idea" id="nav-submit" onclick="navigate('submit',this)"><span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span><span class="label" data-i18n="nav.submit">Submit Idea</span></div>
    <div class="nav-item" data-label="Challenges" id="nav-challenges" onclick="navigate('challenges',this)"><span class="icon"><svg viewBox="0 0 24 24"><path d="M8 21h8M12 17v4M17 3h3l-1 5a4 4 0 01-4 3M7 3H4l1 5a4 4 0 004 3"/><path d="M7 11a5 5 0 0010 0V3H7v8z"/><line x1="12" y1="11" x2="12" y2="7"/></svg></span><span class="label">Challenges</span></div>

    <div class="nav-section" data-i18n="section.workflow">Workflow</div>
    <div class="nav-item" data-label="Review Queue" id="nav-review" onclick="navigate('review',this)" style="display:none"><span class="icon"><svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/><polyline points="9 14 11 16 15 12"/></svg></span><span class="label" data-i18n="nav.review">Review Queue</span></div>
    <div class="nav-item" data-label="All Ideas" id="nav-all" onclick="navigate('ideas-all',this)"><span class="icon"><svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3.5" cy="6" r="1" fill="currentColor" stroke="none"/><circle cx="3.5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="3.5" cy="18" r="1" fill="currentColor" stroke="none"/></svg></span><span class="label" data-i18n="nav.all_ideas">All Ideas</span></div>
    <div class="nav-item" data-label="Idea Board" id="nav-board" onclick="navigate('board',this)"><span class="icon"><svg viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3H14z"/><path d="M7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3"/></svg></span><span class="label">Idea Board</span></div>
    <div class="nav-item" data-label="Audit Trail" id="nav-audit" onclick="navigate('audit',this)"><span class="icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span><span class="label" data-i18n="nav.audit">Audit Trail</span></div>

    <div class="nav-section" data-i18n="section.insights">Insights</div>
    <div class="nav-item" data-label="Leaderboard" onclick="navigate('leaderboard',this)"><span class="icon"><svg viewBox="0 0 24 24"><path d="M8 21h8M12 17v4M17 3h3l-1 5a4 4 0 01-4 3M7 3H4l1 5a4 4 0 004 3"/><path d="M7 11a5 5 0 0010 0V3H7v8z"/></svg></span><span class="label" data-i18n="nav.leaderboard">Leaderboard</span></div>
    <div class="nav-item" data-label="Analytics" id="nav-analytics" onclick="navigate('analytics',this)"><span class="icon"><svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span><span class="label" data-i18n="nav.analytics">Analytics</span></div>

    <div class="nav-section" id="nav-section-admin" style="display:none" data-i18n="section.admin">Admin</div>
    <div class="nav-item" data-label="Admin Panel" id="nav-admin" onclick="navigate('admin',this)" style="display:none"><span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 115 19.07M12 2v2M12 20v2M2 12h2M20 12h2"/></svg></span><span class="label" data-i18n="nav.admin">Admin Panel</span></div>

    <div class="nav-section" id="nav-section-super-admin" style="display:none" data-i18n="section.super_admin">IFQM Super Admin</div>
    <div class="nav-item" data-label="Org Hierarchy" id="nav-super-admin" onclick="navigate('super-admin',this)" style="display:none"><span class="icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></span><span class="label" data-i18n="nav.super_admin">Org Hierarchy</span></div>

    <div class="nav-section" id="nav-section-platform" style="display:none">Platform</div>
    <div class="nav-item" data-label="Platform Dashboard" id="nav-platform-dash" onclick="navigate('platform-dash',this)" style="display:none"><span class="icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span><span class="label">Platform Dashboard</span></div>
    <div class="nav-item" data-label="Tenants" id="nav-platform-tenants" onclick="navigate('platform-tenants',this)" style="display:none"><span class="icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span><span class="label">Tenants</span></div>

    <div class="nav-item" data-label="My Profile" id="nav-profile" onclick="navigate('profile',this)" style="margin-top:auto"><span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></span><span class="label" data-i18n="nav.profile">My Profile</span></div>

    <div class="sidebar-user">
      <div class="avatar" id="sb-avatar" style="flex-shrink:0">??</div>
      <div class="sidebar-user-info">
        <span id="sb-name" style="font-size:13px;font-weight:600">Loading…</span>
        <span id="sb-role"></span>
        <span><span class="points-badge" id="sb-points">0 pts</span></span>
      </div>
    </div>
  </div>

  <div id="main">
    <div id="topbar" style="position:relative">
      <div class="topbar-left">
        <button style="background:none;border:none;cursor:pointer;font-size:20px;color:var(--text-muted);padding:4px 6px;border-radius:6px;transition:background .15s;line-height:1" onmouseover="this.style.background='var(--bar-track)'" onmouseout="this.style.background='none'" onclick="toggleSidebar()">&#9776;</button>
        <span class="page-title" id="page-title" data-i18n="nav.dashboard">Dashboard</span>
      </div>
      <div class="topbar-right">
        <div class="dm-toggle" onclick="toggleDarkMode()" title="Toggle dark mode" id="dm-btn">
          <div class="dm-track" id="dm-track"><div class="dm-thumb"></div></div>
          <span id="dm-label">Dark</span>
        </div>
        <div class="lang-wrap" id="lang-wrap">
          <button class="lang-toggle" id="lang-btn" onclick="toggleLangMenu(event)">EN</button>
          <div class="lang-menu" id="lang-menu"></div>
        </div>
        <div class="notif-bell" onclick="toggleNotif()" title="Notifications" style="display:flex;align-items:center;gap:6px">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
          <span data-i18n="topbar.notifications">Notifications</span>
          <div class="notif-badge" id="notif-count" style="display:none">0</div>
        </div>
        <div class="user-chip" onclick="navigate('profile',null)">
          <div class="avatar" id="top-avatar">??</div>
          <span id="top-name">Loading…</span>
          <span class="role-badge" id="top-role">-</span>
        </div>
        <button class="btn btn-outline btn-sm" onclick="doLogout()" data-i18n="topbar.logout">Logout</button>
      </div>
      <div class="notification-panel" id="notif-panel">
        <div style="padding:10px 14px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center">
          <strong style="font-size:13px" data-i18n="notif.header">Notifications</strong>
          <button class="btn btn-outline btn-sm" onclick="markAllRead()" data-i18n="topbar.mark_read">Mark all read</button>
        </div>
        <div id="notif-list"><div class="empty-state">Loading…</div></div>
      </div>
    </div>

    <div id="content">

      <div class="page active" id="page-dashboard">
        <div class="kpi-grid" id="dash-kpis">
          <div class="kpi-card"><div class="spinner"></div></div>
          <div class="kpi-card"><div class="spinner"></div></div>
          <div class="kpi-card"><div class="spinner"></div></div>
          <div class="kpi-card"><div class="spinner"></div></div>
        </div>
        <div class="grid-2">
          <div class="card">
            <div class="card-title" data-i18n="dash.status_dist">Status Distribution</div>
            <div id="dash-status-chart"><div class="spinner"></div></div>
          </div>
          <div class="card">
            <div class="card-title" data-i18n="dash.recent">Recent Activity</div>
            <div class="timeline" id="dash-activity"><div class="spinner"></div></div>
          </div>
        </div>
      </div>

      <div class="page" id="page-my-ideas">
        <div class="section-header">
          <div><div class="page-title" data-i18n="nav.my_ideas">My Ideas</div><div class="text-muted" data-i18n="page.my_ideas_sub">Track all ideas you have submitted</div></div>
          <button class="btn btn-primary" onclick="navigate('submit',document.getElementById('nav-submit'))" data-i18n="btn.new_idea">New Idea</button>
        </div>
        <div class="filter-bar">
          <input id="my-search" data-i18n-ph="placeholder.search_ideas" placeholder="Search ideas..." style="flex:1;min-width:180px" oninput="filterMyIdeas()"/>
          <select id="my-status-filter" class="form-control" style="width:140px" onchange="filterMyIdeas()">
            <option value="" data-i18n="filter.all_status">All Status</option><option data-i18n="status.draft">Draft</option><option data-i18n="status.submitted">Submitted</option><option data-i18n="status.review">Under Review</option><option data-i18n="status.approved">Approved</option><option data-i18n="status.rejected">Rejected</option><option data-i18n="status.implemented">Implemented</option>
          </select>
        </div>
        <div id="my-ideas-list"><div class="empty-state"><div class="spinner"></div> Loading…</div></div>
      </div>

      <div class="page" id="page-submit">
        <div class="section-header">
          <div><div class="page-title" data-i18n="form.submit_idea">Submit New Idea</div><div class="text-muted" data-i18n="page.submit_sub">Fill in all steps to submit your improvement idea</div></div>
        </div>
        <div class="card">
          <div class="alert alert-info" id="submit-user-banner">Auto-fetched from HR Database: Loading…</div>

          <div class="wizard-steps">
            <div class="w-step active" onclick="goStep(1)"><span class="w-num">1</span><span class="w-lbl" data-i18n="wizard.step1">Situation</span></div>
            <div class="w-step" onclick="goStep(2)"><span class="w-num">2</span><span class="w-lbl" data-i18n="wizard.step2">Solution</span></div>
            <div class="w-step" onclick="goStep(3)"><span class="w-num">3</span><span class="w-lbl" data-i18n="wizard.step3">Impact</span></div>
            <div class="w-step" onclick="goStep(4)"><span class="w-num">4</span><span class="w-lbl" data-i18n="wizard.step4">Co-Suggesters</span></div>
            <div class="w-step" onclick="goStep(5)"><span class="w-num">5</span><span class="w-lbl" data-i18n="wizard.step5">Review &amp; Submit</span></div>
          </div>

          <div class="wizard-body" id="step-1">
            <h3 style="font-size:14px;color:var(--heading);margin-bottom:12px" data-i18n="step1.heading">Step 1: Describe the Present Situation</h3>
            <div class="form-group">
              <label data-i18n="step1.title_label">Situation Title</label> <span style="color:red">*</span>
              <input class="form-control" id="idea-title" data-i18n-ph="step1.title_ph" placeholder="Brief title for your idea" oninput="checkDuplicateTitle(this.value)"/>
              <div id="duplicate-warning" style="display:none;margin-top:6px;padding:8px 12px;background:#fef3c7;border:1px solid #fde68a;border-radius:var(--r);font-size:12px;color:#92400e"></div>
            </div>
            <div class="form-group">
              <label data-i18n="step1.desc_label">Current Situation Description</label> <span style="color:red">*</span>
              <textarea class="form-control" id="idea-situation" rows="5" data-i18n-ph="step1.desc_ph" placeholder="Describe the current problem or inefficiency in detail (min. 50 chars)…"></textarea>
            </div>
            <div class="form-group">
              <label data-i18n="step1.doc_label">Supporting Document (Optional)</label>
              <input type="file" id="file-situation" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.xlsx,.csv,.docx" style="display:none"/>
              <div class="upload-zone" onclick="document.getElementById('file-situation').click()">
                <span data-i18n="step1.upload">Click to upload or drag &amp; drop</span><br/><span style="font-size:11px;color:#bbb" data-i18n="step1.upload_types">PDF, PNG, JPG, XLSX — Max 10 MB</span>
              </div>
              <div id="file-situation-name" style="font-size:12px;color:#10b981;margin-top:4px"></div>
            </div>
          </div>

          <div class="wizard-body" id="step-2" style="display:none">
            <h3 style="font-size:14px;color:var(--heading);margin-bottom:12px" data-i18n="step2.heading">Step 2: Proposed Idea / Solution</h3>
            <div class="form-group">
              <label data-i18n="step2.label">Proposed Solution</label> <span style="color:red">*</span>
              <textarea class="form-control" id="idea-solution" rows="5" data-i18n-ph="step2.ph" placeholder="Describe your proposed improvement in detail…"></textarea>
            </div>
            <div class="form-group">
              <label data-i18n="step1.doc_label">Supporting Document (Optional)</label>
              <input type="file" id="file-solution" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.xlsx,.csv,.docx" style="display:none"/>
              <div class="upload-zone" onclick="document.getElementById('file-solution').click()">
                <span data-i18n="step1.upload">Click to upload or drag &amp; drop</span><br/><span style="font-size:11px;color:#bbb" data-i18n="step1.upload_types">PDF, PNG, JPG, XLSX — Max 10 MB</span>
              </div>
              <div id="file-solution-name" style="font-size:12px;color:#10b981;margin-top:4px"></div>
            </div>
          </div>

          <div class="wizard-body" id="step-3" style="display:none">
            <h3 style="font-size:14px;color:var(--heading);margin-bottom:12px" data-i18n="step3.heading">Step 3: Impact Areas &amp; Measurable Benefits</h3>
            <div class="form-group">
              <label><span data-i18n="step3.impact_label">Select Impact Areas</span> <span style="color:red">*</span> <span data-i18n="step3.impact_sub">(select all that apply)</span></label>
              <div class="impact-grid" style="margin-top:8px">
                <div class="impact-chip" onclick="toggleImpact(this)" data-val="Production" data-i18n="impact.production">Production</div>
                <div class="impact-chip" onclick="toggleImpact(this)" data-val="Quality" data-i18n="impact.quality">Quality</div>
                <div class="impact-chip" onclick="toggleImpact(this)" data-val="Cost" data-i18n="impact.cost">Cost</div>
                <div class="impact-chip" onclick="toggleImpact(this)" data-val="Delivery" data-i18n="impact.delivery">Delivery</div>
                <div class="impact-chip" onclick="toggleImpact(this)" data-val="Safety" data-i18n="impact.safety">Safety</div>
                <div class="impact-chip" onclick="toggleImpact(this)" data-val="Environment" data-i18n="impact.environment">Environment</div>
                <div class="impact-chip" onclick="toggleImpact(this)" data-val="Morale" data-i18n="impact.morale">Morale</div>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label data-i18n="step3.level">Overall Impact Level</label>
                <select class="form-control" id="idea-impact-level">
                  <option data-i18n="impact.low">Low</option><option selected data-i18n="impact.medium">Medium</option><option data-i18n="impact.high">High</option>
                </select>
              </div>
              <div class="form-group">
                <label data-i18n="step3.tangible_label">Tangible Benefit (Optional)</label>
                <input class="form-control" id="idea-tangible" data-i18n-ph="step3.tangible_ph" placeholder="e.g. Rs. 50,000 savings/year"/>
              </div>
            </div>
            <div class="form-group">
              <label data-i18n="step3.intangible_label">Intangible Benefits (Optional)</label>
              <input class="form-control" id="idea-intangible" data-i18n-ph="step3.intangible_ph" placeholder="e.g. Improved worker confidence, better audit scores"/>
            </div>
          </div>

          <div class="wizard-body" id="step-4" style="display:none">
            <h3 style="font-size:14px;color:var(--heading);margin-bottom:12px" data-i18n="step4.heading">Step 4: Co-Suggesters (Optional, max 2)</h3>
            <div class="form-group">
              <label data-i18n="step4.co1">Co-Suggester 1</label>
              <div class="pos-rel">
                <input class="form-control" id="co1-search" data-i18n-ph="step4.co_ph" placeholder="Search by name or employee ID…" oninput="searchUsers(this,'co1-results','co1-id','co1-name-display')" autocomplete="off"/>
                <div class="user-search-results" id="co1-results"></div>
              </div>
              <div id="co1-name-display" style="font-size:12px;color:#374151;margin-top:4px"></div>
              <input type="hidden" id="co1-id"/>
            </div>
            <div class="form-group">
              <label data-i18n="step4.co2">Co-Suggester 2</label>
              <div class="pos-rel">
                <input class="form-control" id="co2-search" data-i18n-ph="step4.co_ph" placeholder="Search by name or employee ID…" oninput="searchUsers(this,'co2-results','co2-id','co2-name-display')" autocomplete="off"/>
                <div class="user-search-results" id="co2-results"></div>
              </div>
              <div id="co2-name-display" style="font-size:12px;color:#374151;margin-top:4px"></div>
              <input type="hidden" id="co2-id"/>
            </div>
          </div>
          <div style="border-top:1px solid var(--border);margin-top:16px;padding-top:16px">
            <h4 style="font-size:13px;font-weight:600;color:var(--heading);margin-bottom:12px">Submission Options</h4>
            <div class="form-row">
              <div class="form-group">
                <label>Idea Template</label>
                <select class="form-control" id="idea-template">
                  <option value="">— No Template —</option>
                  <option value="cost_reduction">Cost Reduction</option>
                  <option value="process_improvement">Process Improvement</option>
                  <option value="safety_improvement">Safety Improvement</option>
                  <option value="quality_improvement">Quality Improvement</option>
                  <option value="revenue_growth">Revenue Growth</option>
                  <option value="waste_reduction">Waste Reduction</option>
                </select>
              </div>
              <div class="form-group">
                <label>Link to Challenge</label>
                <select class="form-control" id="idea-challenge">
                  <option value="">— No Challenge —</option>
                </select>
              </div>
            </div>
            <div class="form-group" style="margin-top:4px">
              <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:500;margin:0">
                <input type="checkbox" id="idea-anonymous" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary)"/>
                Submit Anonymously
              </label>
              <div style="font-size:11px;color:var(--text-muted);margin-top:4px;margin-left:26px">Your identity will be hidden from other employees. Managers can still see who submitted the idea.</div>
            </div>
          </div>

          <div class="wizard-body" id="step-5" style="display:none">
            <h3 style="font-size:14px;color:var(--heading);margin-bottom:12px" data-i18n="step5.heading">Step 5: Review &amp; Submit</h3>
            <div id="review-preview"></div>
            <div class="alert alert-info mt-8" data-i18n="step5.note">By submitting, you confirm this idea is original. You will earn +10 points on submission. An AI quality score will be automatically computed.</div>
          </div>

          <div class="wizard-footer" id="wizard-nav">
            <button class="btn btn-outline" id="btn-back" onclick="prevStep()" style="visibility:hidden" data-i18n="form.back">← Back</button>
            <button class="btn btn-outline" onclick="saveDraft()" data-i18n="form.save_draft">Save Draft</button>
            <button class="btn btn-primary" id="btn-next" onclick="nextStep()" data-i18n="form.next">Next →</button>
          </div>
          <div class="wizard-footer" id="wizard-submit-row" style="display:none">
            <button class="btn btn-outline" id="btn-back-final" onclick="goStep(4)" data-i18n="form.back">← Back</button>
            <button class="btn btn-outline" onclick="saveDraft()" data-i18n="form.save_draft">Save Draft</button>
            <button class="btn btn-success" onclick="submitIdea()" data-i18n="form.submit_idea">Submit Idea</button>
          </div>
        </div>
      </div>

      <div class="page" id="page-review">
        <div class="section-header" style="display:flex;justify-content:space-between;align-items:flex-start">
          <div><div class="page-title" data-i18n="nav.review">Review Queue</div><div class="text-muted">Ideas pending your review — sorted by due date, then AI score</div></div>
          <div style="display:flex;gap:8px;align-items:center">
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-muted);cursor:pointer">
              <input type="checkbox" id="bulk-select-all" style="accent-color:var(--primary)" onchange="toggleBulkAll(this.checked)"/> Select All
            </label>
          </div>
        </div>
        <div id="review-list"><div class="empty-state"><div class="spinner"></div> Loading…</div></div>
        <div id="bulk-action-bar" style="display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:12px 20px;box-shadow:var(--shadow-xl);display:flex;gap:12px;align-items:center;z-index:200">
          <span id="bulk-count-label" style="font-size:13px;font-weight:600;color:var(--heading)">0 selected</span>
          <button class="btn btn-success btn-sm" onclick="submitBulkReview('Approved')" data-i18n="bulk.approve_all">Approve All</button>
          <button class="btn btn-danger btn-sm" onclick="submitBulkReview('Rejected')" data-i18n="bulk.reject_all">Reject All</button>
          <button class="btn btn-outline btn-sm" onclick="clearBulkSelection()" data-i18n="bulk.clear">Clear</button>
        </div>
      </div>

      <div class="page" id="page-ideas-all">
        <div class="section-header" style="display:flex;justify-content:space-between;align-items:flex-start">
          <div><div class="page-title" data-i18n="nav.all_ideas">All Ideas</div></div>
          <div style="display:flex;gap:8px">
            <button class="btn btn-outline btn-sm" onclick="window.open('api/export.php?action=ideas','_blank')">⬇ Export CSV</button>
            <button class="btn btn-outline btn-sm" onclick="window.open('api/export.php?action=analytics','_blank')">⬇ Print Report</button>
          </div>
        </div>
        <div class="filter-bar">
          <input id="all-search" data-i18n-ph="placeholder.search" placeholder="Search…" style="flex:1" oninput="loadAllIdeas()"/>
          <select id="all-status" class="form-control" style="width:140px" onchange="loadAllIdeas()">
            <option value="" data-i18n="filter.all_status">All Status</option><option data-i18n="status.submitted">Submitted</option><option data-i18n="status.review">Under Review</option><option data-i18n="status.approved">Approved</option><option data-i18n="status.rejected">Rejected</option><option data-i18n="status.implemented">Implemented</option>
          </select>
          <select id="all-impact" class="form-control" style="width:130px" onchange="loadAllIdeas()">
            <option value="" data-i18n="filter.all_impact">All Impact</option><option data-i18n="impact.low">Low</option><option data-i18n="impact.medium">Medium</option><option data-i18n="impact.high">High</option>
          </select>
        </div>
        <div class="card" style="padding:0;overflow:hidden">
          <table>
            <thead><tr><th data-i18n="table.idea_id">Idea ID</th><th data-i18n="table.title">Title</th><th data-i18n="table.submitted_by">Submitted By</th><th data-i18n="table.dept">Department</th><th data-i18n="table.impact">Impact</th><th data-i18n="table.ai_score">AI Score</th><th data-i18n="table.engagement">Engagement</th><th data-i18n="table.status">Status</th><th data-i18n="table.date">Date</th><th data-i18n="table.action">Action</th></tr></thead>
            <tbody id="all-ideas-tbody"><tr><td colspan="10" class="text-center"><div class="spinner"></div></td></tr></tbody>
          </table>
        </div>
      </div>

      <div class="page" id="page-challenges">
        <div class="section-header" style="display:flex;justify-content:space-between;align-items:flex-start">
          <div><div class="page-title">Innovation Challenges</div><div class="text-muted">Active campaigns — submit ideas aligned to company goals</div></div>
          <button class="btn btn-primary btn-sm" id="btn-new-challenge" style="display:none" onclick="openChallengeModal()">+ New Challenge</button>
        </div>
        <div id="challenges-list"><div class="empty-state"><div class="spinner"></div> Loading…</div></div>
      </div>

      <div class="page" id="page-board">
        <div class="section-header" style="display:flex;justify-content:space-between;align-items:flex-start">
          <div><div class="page-title">Idea Board</div><div class="text-muted">Community voting board — upvote the ideas you support</div></div>
          <select id="board-sort" class="form-control" style="width:150px" onchange="loadBoard()">
            <option value="votes">Most Voted</option>
            <option value="recent">Most Recent</option>
            <option value="score">Highest AI Score</option>
          </select>
        </div>
        <div id="board-list"><div class="empty-state"><div class="spinner"></div> Loading…</div></div>
      </div>

      <div class="page" id="page-audit">
        <div class="section-header">
          <div class="page-title" data-i18n="page.audit_title">System Audit Trail</div>
          <div class="text-muted" data-i18n="page.audit_sub">Immutable append-only log of all workflow actions</div>
        </div>
        <div class="card" style="padding:0;overflow:hidden">
          <div style="font-size:11px;color:#991b1b;background:#fee2e2;padding:8px 14px;border-left:4px solid #ef4444;font-weight:600;letter-spacing:.2px" data-i18n="audit.proof">This log is append-only and tamper-proof. No record can be edited or deleted.</div>
          <table>
            <thead><tr><th data-i18n="table.timestamp">Timestamp</th><th data-i18n="table.idea_id">Idea</th><th data-i18n="table.action">Action</th><th data-i18n="table.actor">Actor</th><th data-i18n="table.comment_col">Comment</th></tr></thead>
            <tbody id="audit-tbody"><tr><td colspan="5" class="text-center"><div class="spinner"></div></td></tr></tbody>
          </table>
        </div>
      </div>

      <div class="page" id="page-leaderboard">
        <div class="section-header"><div class="page-title" data-i18n="page.leaderboard_title">Leaderboard &amp; Gamification</div></div>
        <div class="filter-bar">
          <div class="chip-filter">
            <div class="chip active" onclick="activateChip(this,'lb-period');" data-val="all" data-i18n="lb.all_time">All Time</div>
            <div class="chip" onclick="activateChip(this,'lb-period');" data-val="monthly" data-i18n="lb.monthly">Monthly</div>
            <div class="chip" onclick="activateChip(this,'lb-period');" data-val="quarterly" data-i18n="lb.quarterly">Quarterly</div>
            <div class="chip" onclick="activateChip(this,'lb-period');" data-val="yearly" data-i18n="lb.yearly">Yearly</div>
          </div>
        </div>
        <div class="grid-2">
          <div class="card">
            <div class="card-title" data-i18n="lb.individual">Individual Rankings</div>
            <div id="lb-individuals"><div class="spinner"></div></div>
          </div>
          <div class="card">
            <div class="card-title" data-i18n="lb.dept">Department Rankings</div>
            <div id="lb-departments"><div class="spinner"></div></div>
          </div>
        </div>
        <div class="card">
          <div class="card-title" data-i18n="lb.top_ideas">Top Ideas by AI Quality Score</div>
          <div id="lb-top-ideas"><div class="spinner"></div></div>
        </div>
      </div>

      <div class="page" id="page-analytics">
        <div class="section-header">
          <div class="page-title" data-i18n="page.analytics_title">Analytics Dashboard</div>
        </div>
        <div class="kpi-grid" id="analytics-kpis">
          <div class="kpi-card"><div class="spinner"></div></div>
          <div class="kpi-card"><div class="spinner"></div></div>
          <div class="kpi-card"><div class="spinner"></div></div>
          <div class="kpi-card"><div class="spinner"></div></div>
        </div>
        <div class="grid-2">
          <div class="card">
            <div class="card-title" data-i18n="analytics.impact_dist">Impact Area Distribution</div>
            <div class="bar-chart" id="analytics-impact"></div>
          </div>
          <div class="card">
            <div class="card-title" data-i18n="analytics.status_summary">Status Summary</div>
            <div id="analytics-status"></div>
          </div>
        </div>
        <div class="grid-2">
          <div class="card">
            <div class="card-title" data-i18n="analytics.monthly_trend">Monthly Submission Trend</div>
            <div id="analytics-trend" class="bar-chart"></div>
          </div>
          <div class="card">
            <div class="card-title" data-i18n="analytics.score_dist">AI Quality Score Distribution</div>
            <div id="analytics-score-dist"></div>
          </div>
        </div>
      </div>

      <div class="page" id="page-admin">
        <div class="section-header"><div class="page-title" data-i18n="nav.admin">Admin Panel</div></div>
        <div class="tabs">
          <div class="tab active" onclick="switchTab(this,'atab1')" data-i18n="admin.tab_users">User Management</div>
          <div class="tab" onclick="switchTab(this,'atab2')" data-i18n="admin.points_config">Points Config</div>
          <div class="tab" onclick="switchTab(this,'atab3')" data-i18n="admin.db_status">DB Status</div>
          <div class="tab" onclick="switchTab(this,'atab4')" data-i18n="btn.rescore_all">Rescore Ideas</div>
          <div class="tab" onclick="switchTab(this,'atab5');loadOrgSettings()">Org Settings</div>
        </div>

        <!-- User Management Tab -->
        <div class="tab-content active" id="atab1">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
            <input class="form-control" id="admin-user-search" type="text" placeholder="Search by name, email or ID…" style="max-width:280px" oninput="filterAdminUsers()"/>
            <button class="btn btn-primary btn-sm" onclick="openCreateUserModal()" data-i18n="admin.add_user">+ Add User</button>
          </div>
          <div style="overflow-x:auto">
            <table>
              <thead><tr>
                <th data-i18n="table.employee">Employee</th>
                <th data-i18n="table.role">Role</th>
                <th data-i18n="table.dept">Dept</th>
                <th>Reports To</th>
                <th data-i18n="table.points_col">Points</th>
                <th>Status</th>
                <th>Actions</th>
              </tr></thead>
              <tbody id="admin-users-tbody"><tr><td colspan="7" class="text-center"><div class="spinner"></div></td></tr></tbody>
            </table>
          </div>
        </div>

        <!-- Points Config Tab -->
        <div class="tab-content" id="atab2">
          <div class="alert alert-info" data-i18n="admin.points_info">Points config (stored in config.php). Restart needed to apply changes.</div>
          <table>
            <thead><tr><th data-i18n="table.event">Event</th><th data-i18n="table.pts_awarded">Current Points</th></tr></thead>
            <tbody>
              <tr><td data-i18n="admin.event_sub">Idea Submitted</td><td><span class="badge badge-submitted">+10 pts</span></td></tr>
              <tr><td data-i18n="admin.event_app">Idea Approved</td><td><span class="badge badge-approved">+25 pts</span></td></tr>
              <tr><td data-i18n="admin.event_impl">Idea Implemented</td><td><span class="badge badge-implemented">+65 pts</span></td></tr>
            </tbody>
          </table>
        </div>

        <!-- DB Status Tab -->
        <div class="tab-content" id="atab3">
          <div class="card" style="max-width:480px">
            <div class="card-title" data-i18n="admin.db_status">Database Status</div>
            <div style="font-size:13px;line-height:2">
              <div><strong>Status:</strong> Connected to MySQL</div>
              <div><strong>Engine:</strong> MySQL 8.x</div>
              <div id="admin-db-name"><strong>Database:</strong> <span class="spinner" style="width:12px;height:12px;display:inline-block"></span></div>
            </div>
          </div>
        </div>

        <!-- Rescore Tab -->
        <div class="tab-content" id="atab4">
          <div class="alert alert-info" data-i18n="admin.rescore_info">Recompute AI quality scores for all existing ideas using the current scoring model.</div>
          <button class="btn btn-warning" onclick="batchRescore()" data-i18n="btn.rescore_all">Rescore All Ideas</button>
          <div id="rescore-result" style="margin-top:12px;font-size:13px"></div>
        </div>

        <!-- Org Settings Tab -->
        <div class="tab-content" id="atab5">
          <div id="org-settings-form"><div class="spinner"></div></div>
        </div>
      </div>

      <div class="page" id="page-super-admin">

        <!-- ── Banner ── -->
        <div style="background:linear-gradient(135deg,#312e81 0%,#3730a3 55%,#4338ca 100%);border-radius:var(--r-xl);padding:26px 30px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;box-shadow:var(--shadow-lg)">
          <div>
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:2px;color:rgba(255,255,255,.55);font-weight:700;margin-bottom:6px">IFQM &middot; Super Admin Console</div>
            <div style="font-size:22px;font-weight:800;color:#fff;letter-spacing:-.5px;line-height:1.15" data-i18n="sa.console">Command Center</div>
            <div style="font-size:12px;color:rgba(255,255,255,.6);margin-top:5px" data-i18n="sa.subtitle">Complete organizational control &amp; oversight across all levels</div>
          </div>
          <div style="text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:8px">
            <div style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:var(--r);padding:8px 18px">
              <div style="font-size:10px;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.8px;margin-bottom:2px" data-i18n="sa.signed_in">Signed in as</div>
              <div style="font-size:13px;font-weight:700;color:#fff" id="sa-session-user">—</div>
            </div>
            <div style="font-size:11px;color:rgba(255,255,255,.45)" id="sa-last-updated"></div>
          </div>
        </div>

        <!-- ── KPI Strip ── -->
        <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:22px" id="sa-kpi-strip">
          <div class="kpi-card" style="border-left-color:#1f2937"><div class="spinner"></div></div>
          <div class="kpi-card" style="border-left-color:#ef4444"><div class="spinner"></div></div>
          <div class="kpi-card" style="border-left-color:#f59e0b"><div class="spinner"></div></div>
          <div class="kpi-card" style="border-left-color:#10b981"><div class="spinner"></div></div>
          <div class="kpi-card" style="border-left-color:#374151"><div class="spinner"></div></div>
          <div class="kpi-card" style="border-left-color:#2563eb"><div class="spinner"></div></div>
        </div>

        <!-- ── Tabs ── -->
        <div class="tabs" style="margin-bottom:0">
          <div class="tab active" onclick="switchSaTab(this,'sa-overview')" data-i18n="sa.tab_overview">Overview</div>
          <div class="tab" onclick="switchSaTab(this,'sa-hierarchy')" data-i18n="sa.tab_hierarchy">Org Hierarchy</div>
          <div class="tab" onclick="switchSaTab(this,'sa-users')" data-i18n="sa.tab_users">User Management</div>
          <div class="tab" onclick="switchSaTab(this,'sa-system')" data-i18n="sa.tab_system">System</div>
        </div>

        <!-- ── Tab: Overview ── -->
        <div id="sa-overview" class="sa-pane" style="padding-top:20px">
          <div class="grid-2">
            <div class="card">
              <div class="card-title" data-i18n="sa.status_dist">Idea Status Distribution</div>
              <div id="sa-status-dist"><div class="spinner"></div></div>
            </div>
            <div class="card">
              <div class="card-title" data-i18n="sa.recent">Recent Activity</div>
              <div class="timeline" id="sa-recent-activity" style="max-height:360px;overflow-y:auto"><div class="spinner"></div></div>
            </div>
          </div>
        </div>

        <!-- ── Tab: Org Hierarchy ── -->
        <div id="sa-hierarchy" class="sa-pane" style="display:none;padding-top:20px">
          <div class="card">
            <div class="card-title" data-i18n="sa.org_tree">Organization Tree &mdash; Admin &rarr; Manager &rarr; Employee</div>
            <div id="hierarchy-tree" style="padding:4px 0"><div class="spinner" style="margin:20px auto"></div></div>
          </div>
        </div>

        <!-- ── Tab: User Management ── -->
        <div id="sa-users" class="sa-pane" style="display:none;padding-top:20px">
          <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
              <div class="card-title" style="margin-bottom:0;padding-bottom:0;border-bottom:none" data-i18n="sa.all_employees">All Employees</div>
              <input id="sa-user-search" class="form-control" data-i18n-ph="placeholder.search_user" placeholder="Search by name, email or ID…" style="width:260px" oninput="filterSaUsers()"/>
            </div>
            <table id="sa-users-table">
              <thead><tr><th data-i18n="table.employee">Employee</th><th data-i18n="table.emp_id">ID</th><th data-i18n="table.role">Role</th><th data-i18n="table.dept">Department</th><th data-i18n="table.bu">Business Unit</th><th data-i18n="table.email_col">Email</th><th data-i18n="table.reports_to">Reports To</th><th data-i18n="table.points_col">Points</th><th data-i18n="table.ideas_col">Ideas</th></tr></thead>
              <tbody id="hierarchy-users-tbody"><tr><td colspan="9" class="text-center"><div class="spinner"></div></td></tr></tbody>
            </table>
          </div>
        </div>

        <!-- ── Tab: System ── -->
        <div id="sa-system" class="sa-pane" style="display:none;padding-top:20px">
          <div class="grid-2" style="margin-bottom:16px">
            <div class="card">
              <div class="card-title" data-i18n="sa.points_config">Points Configuration</div>
              <table style="font-size:13px">
                <thead><tr><th data-i18n="table.event">Event</th><th data-i18n="table.pts_awarded">Points Awarded</th></tr></thead>
                <tbody>
                  <tr><td data-i18n="admin.event_sub">Idea Submitted</td><td><span class="badge badge-submitted">+10 pts</span></td></tr>
                  <tr><td data-i18n="admin.event_app">Idea Approved</td><td><span class="badge badge-approved">+25 pts</span></td></tr>
                  <tr><td data-i18n="admin.event_impl">Idea Implemented</td><td><span class="badge badge-implemented">+65 pts</span></td></tr>
                </tbody>
              </table>
              <div style="font-size:11px;color:var(--subtle);margin-top:12px">Configured in <code>api/config.php</code>. Server restart required to apply changes.</div>
            </div>
            <div class="card">
              <div class="card-title" data-i18n="sa.db_sync">Database &amp; HR Sync</div>
              <div style="font-size:13px;line-height:2.4">
                <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border)"><span style="color:var(--subtle)">Status</span><span style="color:#10b981;font-weight:700">&#9679; Connected</span></div>
                <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border)"><span style="color:var(--subtle)">Engine</span><span>MySQL 8.x</span></div>
                <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border)"><span style="color:var(--subtle)">Database</span><span>ifqm_ideation</span></div>
                <div style="display:flex;justify-content:space-between"><span style="color:var(--subtle)">Charset</span><span>utf8mb4_unicode_ci</span></div>
              </div>
              <div style="font-size:11px;color:var(--subtle);margin-top:12px">Employee data is sourced from the <code>users</code> table. Run the SQL seed script to re-populate.</div>
            </div>
          </div>
          <div class="card">
            <div class="card-title" data-i18n="sa.ai_engine">AI Scoring Engine</div>
            <div style="font-size:13px;color:var(--subtext);margin-bottom:16px" data-i18n="sa.ai_desc">Recompute AI quality scores for all existing ideas using the current scoring model. Use this after importing legacy data or after updating the scoring algorithm.</div>
            <div style="display:flex;align-items:center;gap:14px">
              <button class="btn btn-warning" id="sa-rescore-btn" onclick="batchRescoreSa()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
                <span data-i18n="btn.rescore_all">Rescore All Ideas</span>
              </button>
              <div id="sa-rescore-result" style="font-size:13px"></div>
            </div>
          </div>
        </div>

      </div>

      <!-- ═══ PLATFORM DASHBOARD ═══════════════════════════════════ -->
      <div class="page" id="page-platform-dash">
        <div style="background:linear-gradient(145deg,#1e1b4b 0%,#312e81 50%,#4338ca 100%);border-radius:var(--r-xl);padding:26px 30px;margin-bottom:22px;display:flex;justify-content:space-between;align-items:center;box-shadow:var(--shadow-lg)">
          <div>
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:2px;color:rgba(255,255,255,.5);font-weight:700;margin-bottom:6px">IFQM &middot; Platform Admin Console</div>
            <div style="font-size:22px;font-weight:800;color:#fff;letter-spacing:-.5px;line-height:1.15" data-i18n="pa.overview">Platform Overview</div>
            <div style="font-size:12px;color:rgba(255,255,255,.55);margin-top:5px" data-i18n="pa.private">Aggregate metrics only &mdash; tenant content is private</div>
          </div>
          <div style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:var(--r);padding:8px 18px;text-align:right">
            <div style="font-size:10px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.8px;margin-bottom:2px" data-i18n="pa.signed_in">Signed in as</div>
            <div style="font-size:13px;font-weight:700;color:#fff" id="pa-name">—</div>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:22px" id="pa-kpi-strip">
          <div class="kpi-card" style="border-left-color:#1f2937"><div class="spinner"></div></div>
          <div class="kpi-card" style="border-left-color:#10b981"><div class="spinner"></div></div>
          <div class="kpi-card" style="border-left-color:#f59e0b"><div class="spinner"></div></div>
        </div>
        <div class="card">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <div class="card-title" style="margin:0" data-i18n="pa.all_tenants">All Organisations</div>
            <button class="btn btn-primary btn-sm" onclick="openCreateOrgModal()">+ New Organisation</button>
          </div>
          <div id="pa-tenant-list"><div class="spinner"></div></div>
        </div>
      </div>

      <!-- ═══ PLATFORM TENANT HIERARCHY ════════════════════════════ -->
      <div class="page" id="page-platform-tenants">
        <div class="section-header">
          <div>
            <div class="page-title" id="pt-tenant-name">Tenant Hierarchy</div>
            <div class="text-muted" data-i18n="pa.hierarchy_sub">Org structure — names, roles, departments only. No idea content.</div>
          </div>
          <button class="btn btn-outline btn-sm" onclick="navigate('platform-dash',document.getElementById('nav-platform-dash'))" data-i18n="btn.back_tenants">← All Tenants</button>
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px" id="pt-stats-strip"></div>
        <div class="card">
          <div class="card-title" data-i18n="pa.user_hierarchy">User Hierarchy</div>
          <div id="pt-hierarchy-body"><div class="spinner"></div></div>
        </div>
      </div>

      <div class="page" id="page-profile">
        <div class="grid-2">
          <div class="card">
            <div class="card-title" data-i18n="profile.title">Employee Profile</div>
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
              <div class="avatar" id="profile-avatar" style="width:60px;height:60px;font-size:22px;border-radius:50%">??</div>
              <div>
                <div style="font-size:18px;font-weight:700;color:var(--heading)" id="profile-name">Loading…</div>
                <div style="font-size:12px;color:var(--label)" id="profile-empid"></div>
                <span class="badge badge-submitted" id="profile-role-badge"></span>
              </div>
            </div>
            <table style="font-size:13px" id="profile-table"></table>
            <div class="separator"></div>
            <div style="font-size:11px;color:var(--subtle)" data-i18n="profile.hr_note">Auto-fetched from HR Database. Contact Admin to update.</div>
          </div>
          <div>
            <div class="card">
              <div class="card-title" data-i18n="profile.stats">My Stats</div>
              <div style="display:flex;gap:10px" id="profile-stats"></div>
            </div>
          </div>
        </div>
      </div>

    </div>  </div></div></div>
<div class="modal-overlay" id="modal-idea-detail">
  <div class="modal">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="modal-idea-code">Idea Detail</div>
        <div style="font-size:12px;color:var(--subtle)" id="modal-idea-title-sub"></div>
      </div>
      <span class="modal-close" onclick="closeModal('modal-idea-detail')">&#10005;</span>
    </div>
    <div class="modal-body">
      <div class="tabs">
        <div class="tab active" onclick="switchTab(this,'dtab1')" data-i18n="modal.details">Details</div>
        <div class="tab" onclick="switchTab(this,'dtab2')" data-i18n="modal.timeline">Timeline</div>
        <div class="tab" onclick="switchTab(this,'dtab3')" data-i18n="modal.attachments">Attachments</div>
      </div>
      <div class="tab-content active" id="dtab1">
        <div id="modal-detail-body"><div class="spinner"></div></div>
      </div>
      <div class="tab-content" id="dtab2">
        <div class="timeline" id="modal-timeline"></div>
      </div>
      <div class="tab-content" id="dtab3">
        <div id="modal-attachments"></div>
      </div>
    </div>
    <div class="modal-footer" id="idea-detail-footer">
      <button class="btn btn-outline" onclick="closeModal('modal-idea-detail')" data-i18n="btn.close">Close</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-review">
  <div class="modal" style="width:480px">
    <div class="modal-header">
      <div class="modal-title">Review Decision &mdash; <span id="review-id"></span></div>
      <span class="modal-close" onclick="closeModal('modal-review')">&#10005;</span>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label data-i18n="review.decision">Decision</label> <span style="color:red">*</span>
        <select class="form-control" id="review-decision">
          <option value="Under Review" data-i18n="review.to_review">Move to Under Review</option>
          <option value="Approved" data-i18n="review.approve">Approve</option>
          <option value="Rejected" data-i18n="review.reject">Reject</option>
          <option value="Implemented" data-i18n="review.implement">Mark as Implemented</option>
        </select>
      </div>
      <div class="form-group">
        <label data-i18n="review.comment_label">Comment / Feedback</label>
        <textarea class="form-control" id="review-comment" rows="3" data-i18n-ph="review.comment_ph" placeholder="Optional comments for the submitter…"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('modal-review')" data-i18n="btn.cancel">Cancel</button>
      <button class="btn btn-primary" onclick="submitReview()" data-i18n="btn.submit_decision">Submit Decision</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-success">
  <div class="modal" style="width:400px;text-align:center">
    <div class="modal-body" style="padding:36px 32px">
      <div style="width:72px;height:72px;background:linear-gradient(135deg,#10b981,#34d399);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;animation:badge-pop .4s cubic-bezier(.4,0,.2,1),glow 2s ease-in-out .5s infinite;box-shadow:0 4px 20px rgba(16,185,129,.35)" id="success-icon">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div style="font-size:18px;font-weight:700;color:var(--heading);margin:12px 0 6px;animation:fadeInUp .3s ease .15s both" id="success-title"></div>
      <div style="font-size:13px;color:var(--subtext);animation:fadeInUp .3s ease .25s both" id="success-msg"></div>
      <button class="btn btn-primary mt-8" style="margin-top:20px;animation:fadeInUp .3s ease .35s both" onclick="closeModal('modal-success');navigate('my-ideas',null)" data-i18n="btn.view_my_ideas">View My Ideas</button>
    </div>
  </div>
</div>

<script>
let currentUser   = <?= $loggedIn ? json_encode($user) : 'null' ?>;
let csrfToken      = null;
let currentStep   = 1;
const totalSteps  = 5;
let draftIdeaId   = null;
let pendingIdeaId = null;
let allMyIdeas    = [];
let _activePage   = 'dashboard';

/* ── RIPPLE EFFECT ── */
document.addEventListener('click', function(e) {
  const btn = e.target.closest('.btn');
  if (!btn) return;
  const r = document.createElement('span');
  r.className = 'ripple-el';
  const rect = btn.getBoundingClientRect();
  const size = Math.max(rect.width, rect.height);
  r.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px`;
  btn.appendChild(r);
  r.addEventListener('animationend', () => r.remove());
});

/* ── DRAG & DROP UPLOAD ── */
document.querySelectorAll('.upload-zone').forEach(zone => {
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
  zone.addEventListener('drop', e => { e.preventDefault(); zone.classList.remove('drag-over'); });
});

/* ── TOAST NOTIFICATIONS ── */
function showToast(msg, type='info', duration=3200) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.style.cssText = 'position:fixed;bottom:22px;right:22px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none';
    document.body.appendChild(container);
  }
  const colors = {success:'#10b981',danger:'#ef4444',warning:'#f59e0b',info:'#3b82f6'};
  const icons = {success:'✓',danger:'✕',warning:'⚠',info:'ℹ'};
  const t = document.createElement('div');
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const bgColor = isDark ? '#1e293b' : '#fff';
  const txColor = isDark ? '#e2e8f0' : '#0f172a';
  t.style.cssText = `background:${bgColor};border-left:4px solid ${colors[type]||colors.info};color:${txColor};padding:12px 18px 12px 14px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.14),0 2px 8px rgba(0,0,0,.08);font-size:13px;font-weight:500;display:flex;align-items:center;gap:10px;pointer-events:auto;max-width:340px;animation:fadeInRight .28s cubic-bezier(.4,0,.2,1);font-family:Inter,system-ui,sans-serif`;
  t.innerHTML = `<span style="font-size:16px;color:${colors[type]||colors.info}">${icons[type]||icons.info}</span><span>${msg}</span>`;
  container.appendChild(t);
  setTimeout(() => {
    t.style.animation = 'fadeInRight .28s cubic-bezier(.4,0,.2,1) reverse';
    t.addEventListener('animationend', () => t.remove());
  }, duration);
}

/* ── ANIMATED COUNTER ── */
function animateCounter(el, target, duration=900) {
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

/* ── STAGGER ANIMATE LIST ── */
function staggerAnimate(els, baseDelay=40) {
  els.forEach((el, i) => {
    el.style.animation = 'none';
    el.style.opacity = '0';
    setTimeout(() => {
      el.style.animation = `fadeInUp .28s cubic-bezier(.4,0,.2,1) both`;
      el.style.opacity = '';
    }, i * baseDelay);
  });
}

// Pre-fill org code from URL ?org= parameter
(function() {
  const params = new URLSearchParams(window.location.search);
  const orgParam = params.get('org');
  if (orgParam) {
    const el = document.getElementById('login-org');
    if (el) el.value = orgParam.toLowerCase();
  }
})();

// Wire Sign In button via event listener (safer than onclick attribute)
function attachLoginBtn() {
  const btn = document.getElementById('login-btn');
  if (btn) {
    btn.removeEventListener('click', doLogin);
    btn.addEventListener('click', doLogin);
  }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', attachLoginBtn);
} else {
  attachLoginBtn();
}

// ── CSRF token helper: inject X-CSRF-Token header into mutating requests ──
function csrfHeaders() {
  return csrfToken ? {'X-CSRF-Token': csrfToken} : {};
}

async function doLogin() {
  const email   = document.getElementById('login-email').value.trim();
  const pass    = document.getElementById('login-pass').value.trim();
  const orgSlug = (document.getElementById('login-org')?.value || '').trim().toLowerCase();
  const btn     = document.getElementById('login-btn');
  const err     = document.getElementById('login-error');
  err.style.display = 'none';
  btn.disabled = true; btn.textContent = 'Logging in…';
  try {
    const r = await fetch('api/auth.php?action=login', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify({email, password: pass, org_slug: orgSlug})
    });
    const d = await r.json();
    if (d.success) {
      currentUser = d.user;
      csrfToken = d.csrf_token || null;
      const lp = document.getElementById('login-page');
      lp.style.transition = 'opacity .35s ease';
      lp.style.opacity = '0';
      setTimeout(() => {
        lp.style.display = 'none';
        lp.style.opacity = '';
        document.getElementById('main-app').style.display = '';
        initApp();
      }, 350);
    } else {
      err.textContent = d.error || 'Login failed.';
      err.style.display = 'block';
      err.style.animation = 'none';
      requestAnimationFrame(() => { err.style.animation = 'fadeInDown .25s ease'; });
    }
  } catch(e) {
    console.error('Login error:', e);
    err.textContent = 'Server error. Please try again.';
    err.style.display = 'block';
  }
  btn.disabled = false; btn.textContent = 'Sign In';
}

// ── FORGOT PASSWORD ──────────────────────────────────────────────
function openForgotPassword() {
  const email = prompt('Enter your registered email address:');
  if (!email?.trim()) return;
  const orgSlug = (document.getElementById('login-org')?.value || '').trim().toLowerCase();
  fetch('api/auth.php?action=forgot_password', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    credentials: 'same-origin',
    body: JSON.stringify({email: email.trim(), org_slug: orgSlug})
  }).then(r => r.json()).then(d => {
    if (d.success) {
      showToast('If an account with that email exists, a reset link has been sent.', 'success');
    } else {
      showToast(d.error || 'Request failed.', 'danger');
    }
  }).catch(() => showToast('Network error. Please try again.', 'danger'));
}

// ── PASSWORD RESET PAGE ───────────────────────────────────────────
function openResetPassword() {
  const token = new URLSearchParams(window.location.search).get('reset_token');
  if (!token) return;
  const pw1 = prompt('Enter your new password (min. 8 characters):');
  if (!pw1 || pw1.length < 8) { showToast('Password must be at least 8 characters.', 'warning'); return; }
  const pw2 = prompt('Confirm your new password:');
  if (pw1 !== pw2) { showToast('Passwords do not match.', 'warning'); return; }
  const orgSlug = new URLSearchParams(window.location.search).get('org') || '';
  fetch('api/auth.php?action=reset_password', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    credentials: 'same-origin',
    body: JSON.stringify({token, password: pw1, org_slug: orgSlug})
  }).then(r => r.json()).then(d => {
    if (d.success) {
      showToast('Password updated. Please sign in.', 'success');
      const url = new URL(window.location.href);
      url.searchParams.delete('reset_token');
      url.searchParams.delete('org');
      history.replaceState({}, '', url.pathname);
    } else {
      showToast(d.error || 'Reset failed. The link may have expired.', 'danger');
    }
  }).catch(() => showToast('Network error. Please try again.', 'danger'));
}

// ═══════════════════════════════════════════════════════════════
// MULTILINGUAL SYSTEM — EN / HI / KN / TE / TA / ML
// ═══════════════════════════════════════════════════════════════
const SUPPORTED_LANGS = ['en','hi','mr','kn','te','ta','ml'];
const LANG_LABELS = {en:'EN',hi:'हि',mr:'म',kn:'ಕ',te:'తె',ta:'த',ml:'മ'};
let lang = localStorage.getItem('ifqm-lang') || 'en';
if (!SUPPORTED_LANGS.includes(lang)) lang = 'en';
const TRANSLATIONS = {
  en: {
    'app.name':'IdeaTool',
    'nav.dashboard':'Dashboard','nav.my_ideas':'My Ideas','nav.submit':'Submit Idea',
    'nav.review':'Review Queue','nav.all_ideas':'All Ideas','nav.audit':'Audit Trail',
    'nav.leaderboard':'Leaderboard','nav.analytics':'Analytics','nav.admin':'Admin Panel',
    'nav.super_admin':'Org Hierarchy','nav.profile':'My Profile',
    'section.main':'Main','section.workflow':'Workflow','section.insights':'Insights',
    'section.admin':'Admin','section.super_admin':'IFQM Super Admin',
    'login.app_title':'Employee Ideation Tool','login.tagline':'Turn great ideas into real improvements.',
    'login.welcome':'Welcome back','login.subtitle':'Sign in to your IFQM account to continue',
    'login.org_code':'Organization Code','login.org_hint':'Leave blank for IFQM platform admin login',
    'login.forgot':'Forgot your password?','login.email':'Email Address','login.password':'Password','login.btn':'Sign In',
    'login.email':'Email Address','login.password':'Password','login.btn':'Sign In',
    'login.email_ph':'admin@yourorg.com','login.password_ph':'Enter your password',
    'admin.add_user':'+ Add User',
    'login.feat1_title':'Submit & Track Ideas','login.feat1_sub':'5-step wizard with AI quality scoring',
    'login.feat2_title':'Earn Points & Rewards',
    'login.feat3_title':'Analytics & Leaderboard','login.feat3_sub':'Real-time insights across departments',
    'topbar.dark':'Dark','topbar.light':'Light','topbar.notifications':'Notifications',
    'topbar.logout':'Logout','topbar.mark_read':'Mark all read',
    'dash.total':'Total Ideas','dash.approved':'Approved','dash.implemented':'Implemented',
    'dash.pending_review':'Pending Review','dash.overdue_reviews':'Overdue Reviews',
    'dash.status_dist':'Status Distribution','dash.recent':'Recent Activity',
    'status.submitted':'Submitted','status.review':'Under Review','status.approved':'Approved',
    'status.rejected':'Rejected','status.implemented':'Implemented','status.draft':'Draft',
    'idea.view':'View','idea.review':'Review','idea.votes':'votes',
    'vote.title':'Community Rating','vote.your_rating':'Your Rating',
    'vote.avg':'Avg','vote.engagement_idx':'Engagement Index',
    'vote.no_self':'You cannot rate your own idea','vote.submit':'Submit Rating',
    'vote.rated':'Rated','vote.stars':'stars',
    'lb.individual':'Individual Rankings','lb.dept':'Department Rankings',
    'lb.top_ideas':'Top Scored Ideas','lb.points':'pts','lb.ideas':'ideas',
    'lb.avg_score':'Avg Score','lb.engagement':'Engagement',
    'lb.all_time':'All Time','lb.monthly':'Monthly','lb.quarterly':'Quarterly','lb.yearly':'Yearly','lb.you':'(You)',
    'form.save_draft':'Save Draft','form.next':'Next','form.back':'Back','form.submit_idea':'Submit New Idea',
    'detail.submitted_by':'Submitted by','detail.situation':'Present Situation',
    'detail.solution':'Proposed Solution','detail.impact_areas':'Impact Areas',
    'detail.impact_level':'Impact Level','detail.tangible':'Tangible Benefit',
    'detail.intangible':'Intangible Benefit','detail.co_suggesters':'Co-Suggesters',
    'detail.ai_eval':'AI Evaluation','detail.score':'Score','detail.close':'Close',
    'profile.title':'Employee Profile','profile.stats':'My Stats',
    'profile.dept':'Department','profile.email_lbl':'Email','profile.phone':'Phone',
    'profile.reports_to':'Reporting To','profile.bu':'Business Unit','profile.loc':'Location',
    'profile.hr_note':'Auto-fetched from HR Database. Contact Admin to update.',
    'profile.total_pts':'Total Points',
    'btn.new_idea':'New Idea','btn.close':'Close','btn.cancel':'Cancel',
    'btn.submit_decision':'Submit Decision','btn.view_my_ideas':'View My Ideas',
    'btn.rescore_all':'Rescore All Ideas','btn.back_tenants':'← All Tenants',
    'page.my_ideas_sub':'Track all ideas you have submitted',
    'placeholder.search_ideas':'Search ideas...','filter.all_status':'All Status',
    'page.submit_sub':'Fill in all steps to submit your improvement idea',
    'wizard.step1':'Situation','wizard.step2':'Solution','wizard.step3':'Impact',
    'wizard.step4':'Co-Suggesters','wizard.step5':'Review & Submit',
    'step1.heading':'Step 1: Describe the Present Situation',
    'step1.title_label':'Situation Title','step1.title_ph':'Brief title for your idea',
    'step1.desc_label':'Current Situation Description',
    'step1.desc_ph':'Describe the current problem or inefficiency in detail (min. 50 chars)…',
    'step1.doc_label':'Supporting Document (Optional)',
    'step1.upload':'Click to upload or drag & drop','step1.upload_types':'PDF, PNG, JPG, XLSX — Max 10 MB',
    'step2.heading':'Step 2: Proposed Idea / Solution',
    'step2.label':'Proposed Solution','step2.ph':'Describe your proposed improvement in detail…',
    'step3.heading':'Step 3: Impact Areas & Measurable Benefits',
    'step3.impact_label':'Select Impact Areas','step3.impact_sub':'(select all that apply)',
    'step3.level':'Overall Impact Level',
    'step3.tangible_label':'Tangible Benefit (Optional)','step3.tangible_ph':'e.g. Rs. 50,000 savings/year',
    'step3.intangible_label':'Intangible Benefits (Optional)',
    'step3.intangible_ph':'e.g. Improved worker confidence, better audit scores',
    'step4.heading':'Step 4: Co-Suggesters (Optional, max 2)',
    'step4.co1':'Co-Suggester 1','step4.co2':'Co-Suggester 2','step4.co_ph':'Search by name or employee ID…',
    'step5.heading':'Step 5: Review & Submit',
    'step5.note':'By submitting, you confirm this idea is original. You will earn +10 points on submission. An AI quality score will be automatically computed.',
    'impact.production':'Production','impact.quality':'Quality','impact.cost':'Cost',
    'impact.delivery':'Delivery','impact.safety':'Safety','impact.environment':'Environment',
    'impact.morale':'Morale','impact.low':'Low','impact.medium':'Medium','impact.high':'High',
    'page.review_sub':'Ideas pending your review — sorted by AI quality score (highest first)',
    'placeholder.search':'Search…','filter.all_impact':'All Impact',
    'table.idea_id':'Idea ID','table.title':'Title','table.submitted_by':'Submitted By',
    'table.dept':'Department','table.impact':'Impact','table.ai_score':'AI Score',
    'table.engagement':'Engagement','table.status':'Status','table.date':'Date','table.action':'Action',
    'table.timestamp':'Timestamp','table.actor':'Actor','table.comment_col':'Comment',
    'table.event':'Event','table.pts_awarded':'Points Awarded',
    'table.employee':'Employee','table.emp_id':'ID','table.role':'Role',
    'table.bu':'Business Unit','table.email_col':'Email',
    'table.reports_to':'Reports To','table.points_col':'Points','table.ideas_col':'Ideas',
    'page.audit_title':'System Audit Trail',
    'page.audit_sub':'Immutable append-only log of all workflow actions',
    'audit.proof':'This log is append-only and tamper-proof. No record can be edited or deleted.',
    'modal.details':'Details','modal.timeline':'Timeline','modal.attachments':'Attachments',
    'review.decision':'Decision','review.comment_label':'Comment / Feedback',
    'review.comment_ph':'Optional comments for the submitter…',
    'review.to_review':'Move to Under Review','review.approve':'Approve',
    'review.reject':'Reject','review.implement':'Mark as Implemented',
    'bulk.approve_all':'Approve All','bulk.reject_all':'Reject All','bulk.clear':'Clear',
    'admin.tab_overview':'Overview','admin.tab_ideas':'Idea Management',
    'admin.tab_users':'User List','admin.tab_system':'System',
    'admin.points_config':'Points Configuration',
    'admin.event_sub':'Idea Submitted','admin.event_app':'Idea Approved','admin.event_impl':'Idea Implemented',
    'admin.db_status':'HR Database Sync Status',
    'admin.rescore_desc':'Recompute AI quality scores for all existing ideas using the current scoring model. Use this after importing legacy data.',
    'sa.console':'Command Center','sa.signed_in':'Signed in as',
    'sa.tab_overview':'Overview','sa.tab_hierarchy':'Org Hierarchy',
    'sa.tab_users':'User Management','sa.tab_system':'System',
    'sa.status_dist':'Idea Status Distribution','sa.recent':'Recent Activity',
    'sa.org_tree':'Organization Tree — Admin → Manager → Employee',
    'sa.all_employees':'All Employees','sa.points_config':'Points Configuration',
    'sa.ai_engine':'AI Scoring Engine',
    'sa.ai_desc':'Recompute AI quality scores for all existing ideas using the current scoring model. Use this after importing legacy data or after updating the scoring algorithm.',
    'sa.db_sync':'Database & HR Sync',
    'placeholder.search_user':'Search by name, email or ID…',
    'pa.overview':'Platform Overview','pa.private':'Aggregate metrics only — tenant content is private',
    'pa.signed_in':'Signed in as','pa.all_tenants':'All Tenants — Aggregate Stats',
    'pa.tenant_hierarchy':'Tenant Hierarchy',
    'pa.hierarchy_sub':'Org structure — names, roles, departments only. No idea content.',
    'pa.user_hierarchy':'User Hierarchy',
    'msg.loading':'Loading…','msg.no_ideas':'No ideas found. Submit your first idea!',
    'msg.no_review':'No ideas pending review.','msg.no_audit':'No audit records.',
    'msg.no_leaderboard':'No scored ideas yet. Submit ideas to see rankings.',
    'msg.no_notif':'No notifications','msg.draft_prefix':'Draft saved! Idea code: ',
    'msg.fill_situation':'Please fill in the title and situation description (min 20 chars).',
    'msg.fill_solution':'Please fill in the proposed solution.',
    'msg.server_error':'Server error. Please try again.',
    'msg.fail_dashboard':'Failed to load dashboard. Is the server running?',
    'msg.fail_ideas':'Failed to load ideas. Check server.',
    'msg.fail_queue':'Failed to load review queue. Check server connection.',
    'msg.fail_audit':'Failed to load. Check server connection.',
    'msg.fail_leaderboard':'Failed to load leaderboard.',
    'msg.fail_analytics':'Failed to load analytics. Check server connection.',
    'msg.audit_restricted':'Audit Trail is only available to Managers, Admins and Executives.',
    'msg.analytics_restricted':'Analytics is only available to Managers, Admins and Executives.',
    'msg.decision_ok':'Decision Submitted','msg.idea_ok':'Idea Submitted Successfully',
    'pa.active_tenants':'Active Tenants','pa.total_users':'Total Users','pa.ideas_submitted':'Ideas Submitted',
    'msg.rescoring':'Rescoring…',
    'analytics.approval_rate':'Approval Rate','analytics.impl_rate':'Implementation Rate',
    'analytics.avg_score':'Avg AI Quality Score',
    'page.leaderboard_title':'Leaderboard & Gamification','page.analytics_title':'Analytics Dashboard',
    'analytics.impact_dist':'Impact Area Distribution','analytics.status_summary':'Status Summary',
    'analytics.monthly_trend':'Monthly Submission Trend','analytics.score_dist':'AI Quality Score Distribution',
    'analytics.high':'High (75+)','analytics.med':'Med (50-74)','analytics.low_score':'Low (<50)',
    'analytics.avg_note':'Overall average AI score:',
    'detail.not_scored':'Not scored','detail.no_ai':'No AI evaluation available.',
    'community.title':'Community Vote & Score','community.upvotes':'Upvotes',
    'community.downvotes':'Downvotes','community.net':'Net Score','community.score':'Community Score',
    'community.your_votes':'Community votes on your idea:','community.vote_on':'Vote on this idea:',
    'community.vote_hint':'Click ▲ or ▼ · Click again to remove your vote',
    'review.own_idea':'Your own idea','review.vote_needed':'Your vote needed',
    'review.committee_badge':'Committee','review.route_committee':'Route to Committee',
    'review.submit_mine':'Submit My Review','review.decide':'Review / Decide',
    'review.cannot_own':'You cannot review your own idea',
    'preview.title_label':'Title','preview.situation':'Situation','preview.solution':'Solution',
    'preview.impact_areas':'Impact Areas','preview.impact_level':'Impact Level',
    'preview.co_suggesters':'Co-Suggesters','preview.none_selected':'None selected',
    'platform.users':'Users','platform.ideas':'Ideas','platform.implemented':'Implemented',
    'platform.last_activity':'Last activity','platform.active':'Active',
    'platform.db_error':'DB unreachable','platform.view_org':'View Org',
    'platform.admins':'Admins','platform.executives':'Executives',
    'platform.managers':'Managers','platform.employees':'Employees',
    'platform.reports_to':'Reports to:',
    'sa.subtitle':'Complete organizational control & oversight across all levels',
    'sa.last_refreshed':'Last refreshed:','sa.executives':'Executives',
    'admin.points_info':'Points config (stored in config.php). Restart needed to apply changes.',
    'admin.hr_info':'Employee data is loaded from the users table. Run the SQL seed script to populate.',
    'admin.rescore_info':'Recompute AI quality scores for all existing ideas using the current scoring model. Use this after importing legacy data.',
    'notif.header':'Notifications','login.feat2_sub':'+10 submit · +25 approved · +65 implemented',
    'idea.impact_suffix':'Impact',
    'time.just_now':'Just now','time.min_ago':' min ago','time.hr_ago':'h ago','time.day_ago':'d ago',
    'ar.title':'Route to Committee','ar.search_add':'Search & Add Reviewers',
    'ar.no_reviewers':'No reviewers added yet.','ar.threshold':'Approval Threshold',
    'ar.unanimous':'All reviewers must approve (unanimous)',
    'ar.supermajority':'Supermajority — at least 2/3 must approve',
    'ar.simple_majority':'Simple majority — more than half must approve',
    'ar.info':'Idea moves to Under Review. All assigned reviewers will be notified immediately.',
    'rd.title':'My Review','rd.decision':'Your Decision',
    'rd.approve_btn':'✓ Approve','rd.reject_btn':'✗ Reject',
    'rd.feedback':'Feedback / Comment','rd.feedback_ph':'Share your reasoning with the submitter…',
    'init.hr_banner':'Auto-fetched from HR Database:','init.reporting_to':'Reporting to:',
    'attach.prefix':'Attached: ',
    'committee.approved_count':'approved','committee.rejected_count':'rejected',
    'committee.pending_count':'pending','committee.approval_req':'% approval required',
    'review.my_review':'My Review','review.view_details':'View Details','review.review_btn':'Review',
    'rescore.ok':'Rescored {n} ideas successfully.',
  },
  hi: {
    'app.name':'आइडियाटूल',
    'nav.dashboard':'डैशबोर्ड','nav.my_ideas':'मेरे विचार','nav.submit':'विचार प्रस्तुत करें',
    'nav.review':'समीक्षा सूची','nav.all_ideas':'सभी विचार','nav.audit':'ऑडिट ट्रेल',
    'nav.leaderboard':'लीडरबोर्ड','nav.analytics':'विश्लेषण','nav.admin':'एडमिन पैनल',
    'nav.super_admin':'संगठन संरचना','nav.profile':'मेरी प्रोफ़ाइल',
    'section.main':'मुख्य','section.workflow':'वर्कफ़्लो','section.insights':'अंतर्दृष्टि',
    'section.admin':'एडमिन','section.super_admin':'IFQM सुपर एडमिन',
    'login.app_title':'कर्मचारी विचार मंच','login.tagline':'महान विचारों को वास्तविक सुधारों में बदलें।',
    'login.welcome':'वापस स्वागत है','login.subtitle':'जारी रखने के लिए अपने IFQM खाते में साइन इन करें',
    'login.org_code':'संगठन कोड','login.org_hint':'IFQM प्लेटफ़ॉर्म एडमिन के लिए खाली छोड़ें',
    'login.forgot':'अपना पासवर्ड भूल गए?','login.email':'ईमेल पता','login.password':'पासवर्ड','login.btn':'साइन इन करें',
    'login.forgot':'अपना पासवर्ड भूल गए?','login.email':'ईमेल पता','login.password':'पासवर्ड','login.btn':'साइन इन करें',
    'login.email':'ईमेल पता','login.password':'पासवर्ड','login.btn':'साइन इन',
    'admin.add_user':'+ उपयोगकर्ता जोड़ें',
    'login.email_ph':'आपका.नाम@jain.com','login.password_ph':'अपना पासवर्ड दर्ज करें',
    'login.feat1_title':'विचार सबमिट और ट्रैक करें','login.feat1_sub':'एआई स्कोरिंग सहित 5-चरण विज़ार्ड',
    'login.feat2_title':'अंक और पुरस्कार अर्जित करें',
    'login.feat3_title':'विश्लेषण और लीडरबोर्ड','login.feat3_sub':'विभागों में रीयल-टाइम जानकारी',
    'topbar.dark':'डार्क','topbar.light':'लाइट','topbar.notifications':'सूचनाएं',
    'topbar.logout':'लॉगआउट','topbar.mark_read':'सभी पढ़ा हुआ चिह्नित करें',
    'dash.total':'कुल विचार','dash.approved':'स्वीकृत','dash.implemented':'लागू किए गए',
    'dash.pending_review':'लंबित समीक्षा','dash.overdue_reviews':'अतिदेय समीक्षाएं',
    'dash.status_dist':'स्थिति वितरण','dash.recent':'हालिया गतिविधि',
    'status.submitted':'सबमिट किया गया','status.review':'समीक्षाधीन','status.approved':'स्वीकृत',
    'status.rejected':'अस्वीकृत','status.implemented':'लागू किया गया','status.draft':'मसौदा',
    'idea.view':'देखें','idea.review':'समीक्षा','idea.votes':'वोट',
    'vote.title':'सामुदायिक रेटिंग','vote.your_rating':'आपकी रेटिंग',
    'vote.avg':'औसत','vote.engagement_idx':'सहभागिता सूचकांक',
    'vote.no_self':'आप अपने विचार को रेट नहीं कर सकते','vote.submit':'रेटिंग सबमिट करें',
    'vote.rated':'रेट किया','vote.stars':'सितारे',
    'lb.individual':'व्यक्तिगत रैंकिंग','lb.dept':'विभाग रैंकिंग',
    'lb.top_ideas':'शीर्ष स्कोर विचार','lb.points':'अंक','lb.ideas':'विचार',
    'lb.avg_score':'औसत स्कोर','lb.engagement':'सहभागिता',
    'lb.all_time':'सर्वकालिक','lb.monthly':'मासिक','lb.quarterly':'त्रैमासिक','lb.yearly':'वार्षिक','lb.you':'(आप)',
    'form.save_draft':'मसौदा सहेजें','form.next':'अगला','form.back':'वापस','form.submit_idea':'नया विचार सबमिट करें',
    'detail.submitted_by':'द्वारा सबमिट','detail.situation':'वर्तमान स्थिति',
    'detail.solution':'प्रस्तावित समाधान','detail.impact_areas':'प्रभाव क्षेत्र',
    'detail.impact_level':'प्रभाव स्तर','detail.tangible':'मूर्त लाभ',
    'detail.intangible':'अमूर्त लाभ','detail.co_suggesters':'सह-सुझावकर्ता',
    'detail.ai_eval':'एआई मूल्यांकन','detail.score':'स्कोर','detail.close':'बंद करें',
    'profile.title':'कर्मचारी प्रोफ़ाइल','profile.stats':'मेरे आँकड़े',
    'profile.dept':'विभाग','profile.email_lbl':'ईमेल','profile.phone':'फ़ोन',
    'profile.reports_to':'रिपोर्टिंग प्रबंधक','profile.bu':'व्यवसाय इकाई','profile.loc':'स्थान',
    'profile.hr_note':'एचआर डेटाबेस से स्वतः प्राप्त। अपडेट के लिए एडमिन से संपर्क करें।',
    'profile.total_pts':'कुल अंक',
    'btn.new_idea':'नया विचार','btn.close':'बंद करें','btn.cancel':'रद्द करें',
    'btn.submit_decision':'निर्णय सबमिट करें','btn.view_my_ideas':'मेरे विचार देखें',
    'btn.rescore_all':'सभी विचारों को री-स्कोर करें','btn.back_tenants':'← सभी टेनेंट',
    'page.my_ideas_sub':'आपके द्वारा सबमिट किए गए सभी विचारों को ट्रैक करें',
    'placeholder.search_ideas':'विचार खोजें...','filter.all_status':'सभी स्थिति',
    'page.submit_sub':'अपना सुधार विचार सबमिट करने के लिए सभी चरण भरें',
    'wizard.step1':'परिस्थिति','wizard.step2':'समाधान','wizard.step3':'प्रभाव',
    'wizard.step4':'सह-सुझावकर्ता','wizard.step5':'समीक्षा और सबमिट',
    'step1.heading':'चरण 1: वर्तमान परिस्थिति का वर्णन करें',
    'step1.title_label':'परिस्थिति शीर्षक','step1.title_ph':'अपने विचार के लिए संक्षिप्त शीर्षक',
    'step1.desc_label':'वर्तमान परिस्थिति विवरण',
    'step1.desc_ph':'वर्तमान समस्या या अकुशलता का विस्तार से वर्णन करें (न्यूनतम 50 अक्षर)…',
    'step1.doc_label':'सहायक दस्तावेज़ (वैकल्पिक)',
    'step1.upload':'अपलोड करने के लिए क्लिक करें या खींचें और छोड़ें',
    'step1.upload_types':'PDF, PNG, JPG, XLSX — अधिकतम 10 MB',
    'step2.heading':'चरण 2: प्रस्तावित विचार / समाधान',
    'step2.label':'प्रस्तावित समाधान','step2.ph':'अपने प्रस्तावित सुधार का विस्तार से वर्णन करें…',
    'step3.heading':'चरण 3: प्रभाव क्षेत्र और मापने योग्य लाभ',
    'step3.impact_label':'प्रभाव क्षेत्र चुनें','step3.impact_sub':'(सभी लागू विकल्प चुनें)',
    'step3.level':'समग्र प्रभाव स्तर',
    'step3.tangible_label':'मूर्त लाभ (वैकल्पिक)','step3.tangible_ph':'जैसे. ₹50,000 बचत/वर्ष',
    'step3.intangible_label':'अमूर्त लाभ (वैकल्पिक)',
    'step3.intangible_ph':'जैसे. कर्मचारी आत्मविश्वास में सुधार, बेहतर ऑडिट स्कोर',
    'step4.heading':'चरण 4: सह-सुझावकर्ता (वैकल्पिक, अधिकतम 2)',
    'step4.co1':'सह-सुझावकर्ता 1','step4.co2':'सह-सुझावकर्ता 2',
    'step4.co_ph':'नाम या कर्मचारी आईडी से खोजें…',
    'step5.heading':'चरण 5: समीक्षा और सबमिट',
    'step5.note':'सबमिट करके आप पुष्टि करते हैं कि यह विचार मौलिक है। सबमिट करने पर आपको +10 अंक मिलेंगे।',
    'impact.production':'उत्पादन','impact.quality':'गुणवत्ता','impact.cost':'लागत',
    'impact.delivery':'डिलीवरी','impact.safety':'सुरक्षा','impact.environment':'पर्यावरण',
    'impact.morale':'मनोबल','impact.low':'कम','impact.medium':'मध्यम','impact.high':'उच्च',
    'page.review_sub':'समीक्षा के लिए लंबित विचार — AI गुणवत्ता स्कोर के अनुसार क्रमबद्ध',
    'placeholder.search':'खोजें…','filter.all_impact':'सभी प्रभाव',
    'table.idea_id':'विचार आईडी','table.title':'शीर्षक','table.submitted_by':'सबमिटर',
    'table.dept':'विभाग','table.impact':'प्रभाव','table.ai_score':'AI स्कोर',
    'table.engagement':'सहभागिता','table.status':'स्थिति','table.date':'दिनांक','table.action':'कार्रवाई',
    'table.timestamp':'समय','table.actor':'कर्ता','table.comment_col':'टिप्पणी',
    'table.event':'घटना','table.pts_awarded':'अंक प्रदान',
    'table.employee':'कर्मचारी','table.emp_id':'आईडी','table.role':'भूमिका',
    'table.bu':'व्यवसाय इकाई','table.email_col':'ईमेल',
    'table.reports_to':'रिपोर्ट करता है','table.points_col':'अंक','table.ideas_col':'विचार',
    'page.audit_title':'सिस्टम ऑडिट ट्रेल',
    'page.audit_sub':'सभी वर्कफ़्लो क्रियाओं का अपरिवर्तनीय लॉग',
    'audit.proof':'यह लॉग केवल जोड़ने योग्य और छेड़छाड़-रोधी है। कोई भी रिकॉर्ड संपादित या हटाया नहीं जा सकता।',
    'modal.details':'विवरण','modal.timeline':'टाइमलाइन','modal.attachments':'संलग्नक',
    'review.decision':'निर्णय','review.comment_label':'टिप्पणी / प्रतिक्रिया',
    'review.comment_ph':'सबमिटर के लिए वैकल्पिक टिप्पणी…',
    'review.to_review':'समीक्षाधीन में ले जाएं','review.approve':'स्वीकृत करें',
    'review.reject':'अस्वीकार करें','review.implement':'कार्यान्वित के रूप में चिह्नित करें',
    'bulk.approve_all':'सभी स्वीकृत करें','bulk.reject_all':'सभी अस्वीकृत करें','bulk.clear':'साफ़ करें',
    'admin.tab_overview':'अवलोकन','admin.tab_ideas':'विचार प्रबंधन',
    'admin.tab_users':'उपयोगकर्ता सूची','admin.tab_system':'सिस्टम',
    'admin.points_config':'अंक कॉन्फ़िगरेशन',
    'admin.event_sub':'विचार सबमिट किया','admin.event_app':'विचार स्वीकृत हुआ','admin.event_impl':'विचार लागू हुआ',
    'admin.db_status':'एचआर डेटाबेस सिंक स्थिति',
    'admin.rescore_desc':'मौजूदा सभी विचारों के AI स्कोर पुनः गणना करें।',
    'sa.console':'कमांड सेंटर','sa.signed_in':'के रूप में साइन इन',
    'sa.tab_overview':'अवलोकन','sa.tab_hierarchy':'संगठन संरचना',
    'sa.tab_users':'उपयोगकर्ता प्रबंधन','sa.tab_system':'सिस्टम',
    'sa.status_dist':'विचार स्थिति वितरण','sa.recent':'हालिया गतिविधि',
    'sa.org_tree':'संगठन वृक्ष — एडमिन → प्रबंधक → कर्मचारी',
    'sa.all_employees':'सभी कर्मचारी','sa.ai_engine':'AI स्कोरिंग इंजन',
    'sa.ai_desc':'मौजूदा सभी विचारों के AI गुणवत्ता स्कोर पुनः गणना करें।',
    'sa.db_sync':'डेटाबेस और एचआर सिंक',
    'placeholder.search_user':'नाम, ईमेल या आईडी से खोजें…',
    'pa.overview':'प्लेटफ़ॉर्म अवलोकन','pa.private':'केवल कुल मेट्रिक्स — टेनेंट सामग्री निजी है',
    'pa.signed_in':'के रूप में साइन इन','pa.all_tenants':'सभी टेनेंट — कुल आँकड़े',
    'pa.tenant_hierarchy':'टेनेंट संरचना',
    'pa.hierarchy_sub':'संगठन संरचना — केवल नाम, भूमिकाएँ, विभाग। कोई विचार सामग्री नहीं।',
    'pa.user_hierarchy':'उपयोगकर्ता संरचना',
    'msg.loading':'लोड हो रहा है…','msg.no_ideas':'कोई विचार नहीं मिला। अपना पहला विचार सबमिट करें!',
    'msg.no_review':'समीक्षा के लिए कोई विचार लंबित नहीं है।','msg.no_audit':'कोई ऑडिट रिकॉर्ड नहीं।',
    'msg.no_leaderboard':'अभी तक कोई स्कोर नहीं। रैंकिंग देखने के लिए विचार सबमिट करें।',
    'msg.no_notif':'कोई सूचना नहीं','msg.draft_prefix':'मसौदा सहेजा! विचार कोड: ',
    'msg.fill_situation':'कृपया शीर्षक और परिस्थिति विवरण भरें (न्यूनतम 20 अक्षर)।',
    'msg.fill_solution':'कृपया प्रस्तावित समाधान भरें।',
    'msg.server_error':'सर्वर त्रुटि। कृपया पुनः प्रयास करें।',
    'msg.fail_dashboard':'डैशबोर्ड लोड नहीं हुआ। क्या सर्वर चल रहा है?',
    'msg.fail_ideas':'विचार लोड नहीं हुए। सर्वर जाँचें।',
    'msg.fail_queue':'समीक्षा सूची लोड नहीं हुई।','msg.fail_audit':'लोड नहीं हुआ। सर्वर जाँचें।',
    'msg.fail_leaderboard':'लीडरबोर्ड लोड नहीं हुआ।',
    'msg.fail_analytics':'विश्लेषण लोड नहीं हुआ।',
    'msg.audit_restricted':'ऑडिट ट्रेल केवल प्रबंधकों, एडमिन और कार्यकारियों के लिए उपलब्ध है।',
    'msg.analytics_restricted':'विश्लेषण केवल प्रबंधकों, एडमिन और कार्यकारियों के लिए उपलब्ध है।',
    'msg.decision_ok':'निर्णय सबमिट किया','msg.idea_ok':'विचार सफलतापूर्वक सबमिट किया गया',
    'pa.active_tenants':'सक्रिय टेनेंट','pa.total_users':'कुल उपयोगकर्ता','pa.ideas_submitted':'विचार सबमिट','msg.rescoring':'री-स्कोरिंग…',
    'analytics.approval_rate':'स्वीकृति दर','analytics.impl_rate':'कार्यान्वयन दर','analytics.avg_score':'औसत AI गुणवत्ता स्कोर',
    'page.leaderboard_title':'लीडरबोर्ड और गेमिफिकेशन','page.analytics_title':'विश्लेषण डैशबोर्ड',
    'analytics.impact_dist':'प्रभाव क्षेत्र वितरण','analytics.status_summary':'स्थिति सारांश',
    'analytics.monthly_trend':'मासिक सबमिशन ट्रेंड','analytics.score_dist':'AI गुणवत्ता स्कोर वितरण',
    'analytics.high':'उच्च (75+)','analytics.med':'मध्यम (50-74)','analytics.low_score':'कम (<50)',
    'analytics.avg_note':'समग्र औसत AI स्कोर:',
    'detail.not_scored':'स्कोर नहीं','detail.no_ai':'कोई AI मूल्यांकन उपलब्ध नहीं।',
    'community.title':'सामुदायिक वोट और स्कोर','community.upvotes':'अपवोट',
    'community.downvotes':'डाउनवोट','community.net':'नेट स्कोर','community.score':'सामुदायिक स्कोर',
    'community.your_votes':'आपके विचार पर सामुदायिक वोट:','community.vote_on':'इस विचार पर वोट करें:',
    'community.vote_hint':'▲ या ▼ क्लिक करें · वोट हटाने के लिए फिर क्लिक करें',
    'review.own_idea':'आपका अपना विचार','review.vote_needed':'आपके वोट की जरूरत',
    'review.committee_badge':'समिति','review.route_committee':'समिति को भेजें',
    'review.submit_mine':'मेरी समीक्षा सबमिट करें','review.decide':'समीक्षा / निर्णय',
    'review.cannot_own':'आप अपने विचार की समीक्षा नहीं कर सकते',
    'preview.title_label':'शीर्षक','preview.situation':'परिस्थिति','preview.solution':'समाधान',
    'preview.impact_areas':'प्रभाव क्षेत्र','preview.impact_level':'प्रभाव स्तर',
    'preview.co_suggesters':'सह-सुझावकर्ता','preview.none_selected':'कोई नहीं चुना',
    'platform.users':'उपयोगकर्ता','platform.ideas':'विचार','platform.implemented':'लागू किए',
    'platform.last_activity':'अंतिम गतिविधि','platform.active':'सक्रिय',
    'platform.db_error':'DB अनुपलब्ध','platform.view_org':'संगठन देखें',
    'platform.admins':'एडमिन','platform.executives':'कार्यकारी',
    'platform.managers':'प्रबंधक','platform.employees':'कर्मचारी','platform.reports_to':'रिपोर्ट करता है:',
    'sa.subtitle':'सभी स्तरों पर पूर्ण संगठनात्मक नियंत्रण','sa.last_refreshed':'अंतिम ताज़ा किया:','sa.executives':'कार्यकारी',
    'admin.points_info':'अंक कॉन्फ़िग (config.php में संग्रहीत)। बदलाव लागू करने के लिए पुनरारंभ करें।',
    'admin.hr_info':'कर्मचारी डेटा users तालिका से लोड किया गया है।',
    'admin.rescore_info':'मौजूदा सभी विचारों के AI स्कोर पुनः गणना करें।',
    'notif.header':'सूचनाएं','login.feat2_sub':'+10 सबमिट · +25 स्वीकृत · +65 लागू','idea.impact_suffix':'प्रभाव',
    'time.just_now':'अभी','time.min_ago':' मिनट पहले','time.hr_ago':'घंटे पहले','time.day_ago':'दिन पहले',
    'ar.title':'समिति को भेजें','ar.search_add':'समीक्षकों को खोजें और जोड़ें',
    'ar.no_reviewers':'अभी तक कोई समीक्षक नहीं जोड़ा।','ar.threshold':'स्वीकृति सीमा',
    'ar.unanimous':'सभी समीक्षकों को मंजूरी देनी होगी (सर्वसम्मति)',
    'ar.supermajority':'सुपरमेजोरिटी — कम से कम 2/3 को मंजूरी देनी होगी',
    'ar.simple_majority':'साधारण बहुमत — आधे से अधिक को मंजूरी देनी होगी',
    'ar.info':'विचार समीक्षाधीन में जाएगा। सभी असाइन किए गए समीक्षकों को तुरंत सूचित किया जाएगा।',
    'rd.title':'मेरी समीक्षा','rd.decision':'आपका निर्णय',
    'rd.approve_btn':'✓ स्वीकृत करें','rd.reject_btn':'✗ अस्वीकार करें',
    'rd.feedback':'प्रतिक्रिया / टिप्पणी','rd.feedback_ph':'सबमिटर के साथ अपना तर्क साझा करें…',
    'init.hr_banner':'एचआर डेटाबेस से स्वतः प्राप्त:','init.reporting_to':'रिपोर्टिंग प्रबंधक:',
    'attach.prefix':'संलग्न: ',
    'committee.approved_count':'स्वीकृत','committee.rejected_count':'अस्वीकृत',
    'committee.pending_count':'लंबित','committee.approval_req':'% अनुमोदन आवश्यक',
    'review.my_review':'मेरी समीक्षा','review.view_details':'विवरण देखें','review.review_btn':'समीक्षा',
    'rescore.ok':'{n} विचार सफलतापूर्वक पुनर्स्कोर किए।',
  },
  mr: {
    'app.name':'आयडिया टूल',
    'nav.dashboard':'डॅशबोर्ड','nav.my_ideas':'माझ्या कल्पना','nav.submit':'कल्पना सादर करा',
    'nav.review':'पुनरावलोकन रांग','nav.all_ideas':'सर्व कल्पना','nav.audit':'ऑडिट ट्रेल',
    'nav.leaderboard':'लीडरबोर्ड','nav.analytics':'विश्लेषण','nav.admin':'प्रशासक पॅनेल',
    'nav.super_admin':'संस्था श्रेणी','nav.profile':'माझी प्रोफाइल',
    'section.main':'मुख्य','section.workflow':'वर्कफ्लो','section.insights':'अंतर्दृष्टी',
    'section.admin':'प्रशासक','section.super_admin':'IFQM सुपर प्रशासक',
    'login.app_title':'कर्मचारी कल्पना साधन','login.tagline':'उत्तम कल्पनांना खऱ्या सुधारणांमध्ये बदला.',
    'login.welcome':'पुन्हा स्वागत आहे','login.subtitle':'सुरू ठेवण्यासाठी तुमच्या IFQM खात्यात साइन इन करा',
    'login.org_code':'संस्था कोड','login.org_hint':'IFQM प्लॅटफॉर्म अॅडमिनसाठी रिकामे ठेवा',
    'login.forgot':'पासवर्ड विसरलात?','login.email':'ईमेल पत्ता','login.password':'पासवर्ड','login.btn':'साइन इन करा',
    'login.email':'ईमेल पत्ता','login.password':'पासवर्ड','login.btn':'साइन इन',
    'admin.add_user':'+ वापरकर्ता जोडा',
    'login.email_ph':'तुमचे.नाव@jain.com','login.password_ph':'तुमचा पासवर्ड प्रविष्ट करा',
    'login.feat1_title':'कल्पना सादर करा आणि ट्रॅक करा','login.feat1_sub':'AI स्कोरिंगसह 5-टप्पा विझार्ड',
    'login.feat2_title':'गुण आणि पुरस्कार मिळवा',
    'login.feat3_title':'विश्लेषण आणि लीडरबोर्ड','login.feat3_sub':'विभागांमध्ये रिअल-टाइम माहिती',
    'topbar.dark':'डार्क','topbar.light':'लाइट','topbar.notifications':'सूचना',
    'topbar.logout':'लॉग आउट','topbar.mark_read':'सर्व वाचले म्हणून चिन्हांकित करा',
    'dash.total':'एकूण कल्पना','dash.approved':'मंजूर','dash.implemented':'अंमलात आणले',
    'dash.pending_review':'प्रलंबित समीक्षा','dash.overdue_reviews':'ड्यू समीक्षा',
    'dash.status_dist':'स्थिती वितरण','dash.recent':'अलीकडील क्रियाकलाप',
    'status.submitted':'सादर केले','status.review':'पुनरावलोकनात','status.approved':'मंजूर',
    'status.rejected':'नाकारले','status.implemented':'अंमलात आणले','status.draft':'मसुदा',
    'idea.view':'पहा','idea.review':'पुनरावलोकन','idea.votes':'मते',
    'vote.title':'समुदाय रेटिंग','vote.your_rating':'तुमचे रेटिंग','vote.avg':'सरासरी',
    'vote.engagement_idx':'सहभाग निर्देशांक','vote.submit':'रेटिंग सादर करा',
    'vote.no_self':'तुम्ही स्वतःच्या कल्पनेला रेट करू शकत नाही','vote.rated':'रेट केले','vote.stars':'तारे',
    'lb.individual':'वैयक्तिक क्रमवारी','lb.dept':'विभाग क्रमवारी','lb.top_ideas':'शीर्ष गुण कल्पना',
    'lb.points':'गुण','lb.ideas':'कल्पना','lb.avg_score':'सरासरी गुण','lb.engagement':'सहभाग',
    'lb.all_time':'सर्वकाळ','lb.monthly':'मासिक','lb.quarterly':'तिमाही','lb.yearly':'वार्षिक','lb.you':'(तुम्ही)',
    'form.save_draft':'मसुदा जतन करा','form.next':'पुढे','form.back':'मागे','form.submit_idea':'नवीन कल्पना सादर करा',
    'detail.submitted_by':'सादर केले','detail.situation':'सध्याची परिस्थिती','detail.solution':'प्रस्तावित उपाय',
    'detail.impact_areas':'प्रभाव क्षेत्रे','detail.impact_level':'प्रभाव पातळी',
    'detail.tangible':'मूर्त फायदा','detail.intangible':'अमूर्त फायदा','detail.co_suggesters':'सह-सूचक',
    'detail.ai_eval':'AI मूल्यांकन','detail.score':'गुण','detail.close':'बंद करा',
    'profile.title':'कर्मचारी प्रोफाइल','profile.stats':'माझी आकडेवारी',
    'profile.dept':'विभाग','profile.email_lbl':'ईमेल','profile.phone':'फोन',
    'profile.reports_to':'अहवाल देतो','profile.bu':'व्यवसाय युनिट','profile.loc':'स्थान',
    'profile.hr_note':'एचआर डेटाबेसमधून स्वतः आणले. अपडेटसाठी प्रशासकाशी संपर्क करा.',
    'profile.total_pts':'एकूण गुण',
    'btn.new_idea':'नवीन कल्पना','btn.close':'बंद करा','btn.cancel':'रद्द करा',
    'btn.submit_decision':'निर्णय सादर करा','btn.view_my_ideas':'माझ्या कल्पना पहा',
    'btn.rescore_all':'सर्व कल्पना री-स्कोर करा','btn.back_tenants':'← सर्व टेनंट',
    'page.my_ideas_sub':'तुम्ही सादर केलेल्या सर्व कल्पना ट्रॅक करा',
    'placeholder.search_ideas':'कल्पना शोधा...','filter.all_status':'सर्व स्थिती',
    'page.submit_sub':'तुमची सुधारणा कल्पना सादर करण्यासाठी सर्व टप्पे भरा',
    'wizard.step1':'परिस्थिती','wizard.step2':'उपाय','wizard.step3':'प्रभाव',
    'wizard.step4':'सह-सूचक','wizard.step5':'समीक्षा आणि सादर',
    'step1.heading':'टप्पा 1: सद्यस्थितीचे वर्णन करा',
    'step1.title_label':'परिस्थिती शीर्षक','step1.title_ph':'तुमच्या कल्पनेसाठी संक्षिप्त शीर्षक',
    'step1.desc_label':'सद्यस्थिती वर्णन',
    'step1.desc_ph':'सध्याची समस्या किंवा अकार्यक्षमतेचे तपशीलवार वर्णन करा (किमान 50 अक्षरे)…',
    'step1.doc_label':'सहाय्यक दस्तऐवज (पर्यायी)',
    'step1.upload':'अपलोड करण्यासाठी क्लिक करा किंवा ड्रॅग करा',
    'step1.upload_types':'PDF, PNG, JPG, XLSX — कमाल 10 MB',
    'step2.heading':'टप्पा 2: प्रस्तावित कल्पना / उपाय',
    'step2.label':'प्रस्तावित उपाय','step2.ph':'तुमच्या प्रस्तावित सुधारणेचे तपशीलवार वर्णन करा…',
    'step3.heading':'टप्पा 3: प्रभाव क्षेत्रे आणि मोजता येण्याजोगे फायदे',
    'step3.impact_label':'प्रभाव क्षेत्रे निवडा','step3.impact_sub':'(सर्व लागू पर्याय निवडा)',
    'step3.level':'एकूण प्रभाव पातळी',
    'step3.tangible_label':'मूर्त फायदा (पर्यायी)','step3.tangible_ph':'उदा. ₹50,000 बचत/वर्ष',
    'step3.intangible_label':'अमूर्त फायदे (पर्यायी)',
    'step3.intangible_ph':'उदा. कर्मचाऱ्यांचा आत्मविश्वास सुधारणे',
    'step4.heading':'टप्पा 4: सह-सूचक (पर्यायी, कमाल 2)',
    'step4.co1':'सह-सूचक 1','step4.co2':'सह-सूचक 2','step4.co_ph':'नाव किंवा कर्मचारी आयडीने शोधा…',
    'step5.heading':'टप्पा 5: समीक्षा आणि सादर',
    'step5.note':'सादर करून तुम्ही पुष्टी करता की ही कल्पना मौलिक आहे. सादर केल्यावर तुम्हाला +10 गुण मिळतील.',
    'impact.production':'उत्पादन','impact.quality':'गुणवत्ता','impact.cost':'खर्च',
    'impact.delivery':'वितरण','impact.safety':'सुरक्षा','impact.environment':'पर्यावरण',
    'impact.morale':'मनोबल','impact.low':'कमी','impact.medium':'मध्यम','impact.high':'उच्च',
    'page.review_sub':'तुमच्या पुनरावलोकनासाठी प्रलंबित कल्पना — AI गुणवत्ता स्कोरनुसार क्रमबद्ध',
    'placeholder.search':'शोधा…','filter.all_impact':'सर्व प्रभाव',
    'table.idea_id':'कल्पना आयडी','table.title':'शीर्षक','table.submitted_by':'सादर केले',
    'table.dept':'विभाग','table.impact':'प्रभाव','table.ai_score':'AI स्कोर',
    'table.engagement':'सहभाग','table.status':'स्थिती','table.date':'दिनांक','table.action':'कृती',
    'table.timestamp':'वेळ','table.actor':'कर्ता','table.comment_col':'टिप्पणी',
    'table.event':'घटना','table.pts_awarded':'गुण प्रदान',
    'table.employee':'कर्मचारी','table.emp_id':'आयडी','table.role':'भूमिका',
    'table.bu':'व्यवसाय युनिट','table.email_col':'ईमेल',
    'table.reports_to':'अहवाल देतो','table.points_col':'गुण','table.ideas_col':'कल्पना',
    'page.audit_title':'सिस्टम ऑडिट ट्रेल',
    'page.audit_sub':'सर्व वर्कफ्लो क्रियांचा अपरिवर्तनीय लॉग',
    'audit.proof':'हा लॉग केवळ जोडण्यायोग्य आणि छेडछाड-रोधक आहे. कोणताही रेकॉर्ड संपादित किंवा हटविला जाऊ शकत नाही.',
    'modal.details':'तपशील','modal.timeline':'टाइमलाइन','modal.attachments':'संलग्नक',
    'review.decision':'निर्णय','review.comment_label':'टिप्पणी / अभिप्राय',
    'review.comment_ph':'सादरकर्त्यासाठी पर्यायी टिप्पणी…',
    'review.to_review':'पुनरावलोकनात हलवा','review.approve':'मंजूर करा',
    'review.reject':'नाकारा','review.implement':'अंमलात आणले म्हणून चिन्हांकित करा',
    'bulk.approve_all':'सर्व मंजूर करा','bulk.reject_all':'सर्व नाकारा','bulk.clear':'साफ करा',
    'admin.tab_overview':'आढावा','admin.tab_ideas':'कल्पना व्यवस्थापन',
    'admin.tab_users':'वापरकर्ता यादी','admin.tab_system':'सिस्टम',
    'admin.points_config':'गुण कॉन्फिगरेशन',
    'admin.event_sub':'कल्पना सादर केली','admin.event_app':'कल्पना मंजूर झाली','admin.event_impl':'कल्पना अंमलात आली',
    'admin.db_status':'एचआर डेटाबेस सिंक स्थिती',
    'admin.rescore_desc':'सध्याच्या स्कोरिंग मॉडेलचा वापर करून सर्व विद्यमान कल्पनांचे AI स्कोर पुन्हा गणना करा.',
    'sa.console':'कमांड सेंटर','sa.signed_in':'म्हणून साइन इन',
    'sa.tab_overview':'आढावा','sa.tab_hierarchy':'संस्था संरचना',
    'sa.tab_users':'वापरकर्ता व्यवस्थापन','sa.tab_system':'सिस्टम',
    'sa.status_dist':'कल्पना स्थिती वितरण','sa.recent':'अलीकडील क्रियाकलाप',
    'sa.org_tree':'संस्था वृक्ष — प्रशासक → व्यवस्थापक → कर्मचारी',
    'sa.all_employees':'सर्व कर्मचारी','sa.ai_engine':'AI स्कोरिंग इंजिन',
    'sa.ai_desc':'सर्व विद्यमान कल्पनांचे AI गुणवत्ता स्कोर पुन्हा गणना करा.',
    'sa.db_sync':'डेटाबेस आणि एचआर सिंक',
    'placeholder.search_user':'नाव, ईमेल किंवा आयडीने शोधा…',
    'pa.overview':'प्लॅटफॉर्म आढावा','pa.private':'केवळ एकत्रित मेट्रिक्स — टेनंट सामग्री खाजगी आहे',
    'pa.signed_in':'म्हणून साइन इन','pa.all_tenants':'सर्व टेनंट — एकत्रित आकडेवारी',
    'pa.tenant_hierarchy':'टेनंट संरचना',
    'pa.hierarchy_sub':'संस्था संरचना — केवळ नावे, भूमिका, विभाग. कोणतीही कल्पना सामग्री नाही.',
    'pa.user_hierarchy':'वापरकर्ता संरचना',
    'msg.loading':'लोड होत आहे…','msg.no_ideas':'कोणतीही कल्पना सापडली नाही. तुमची पहिली कल्पना सादर करा!',
    'msg.no_review':'पुनरावलोकनासाठी कोणतीही कल्पना प्रलंबित नाही.','msg.no_audit':'कोणताही ऑडिट रेकॉर्ड नाही.',
    'msg.no_leaderboard':'अद्याप कोणते स्कोर नाहीत. रँकिंग पाहण्यासाठी कल्पना सादर करा.',
    'msg.no_notif':'कोणत्याही सूचना नाहीत','msg.draft_prefix':'मसुदा जतन केला! कल्पना कोड: ',
    'msg.fill_situation':'कृपया शीर्षक आणि परिस्थिती वर्णन भरा (किमान 20 अक्षरे).',
    'msg.fill_solution':'कृपया प्रस्तावित उपाय भरा.',
    'msg.server_error':'सर्व्हर त्रुटी. कृपया पुन्हा प्रयत्न करा.',
    'msg.fail_dashboard':'डॅशबोर्ड लोड झाला नाही. सर्व्हर चालू आहे का?',
    'msg.fail_ideas':'कल्पना लोड झाल्या नाहीत.','msg.fail_queue':'पुनरावलोकन रांग लोड झाली नाही.',
    'msg.fail_audit':'लोड झाले नाही.','msg.fail_leaderboard':'लीडरबोर्ड लोड झाला नाही.',
    'msg.fail_analytics':'विश्लेषण लोड झाले नाही.',
    'msg.audit_restricted':'ऑडिट ट्रेल केवळ व्यवस्थापक, प्रशासक आणि कार्यकारी यांच्यासाठी उपलब्ध आहे.',
    'msg.analytics_restricted':'विश्लेषण केवळ व्यवस्थापक, प्रशासक आणि कार्यकारी यांच्यासाठी उपलब्ध आहे.',
    'msg.decision_ok':'निर्णय सादर केला','msg.idea_ok':'कल्पना यशस्वीरित्या सादर केली गेली',
    'pa.active_tenants':'सक्रिय भाडेकरू','pa.total_users':'एकूण वापरकर्ते','pa.ideas_submitted':'सादर केलेल्या कल्पना','msg.rescoring':'पुन्हा स्कोरिंग…',
    'analytics.approval_rate':'मंजुरी दर','analytics.impl_rate':'अंमलबजावणी दर','analytics.avg_score':'सरासरी AI गुणवत्ता गुण',
    'page.leaderboard_title':'लीडरबोर्ड आणि गेमिफिकेशन','page.analytics_title':'विश्लेषण डॅशबोर्ड',
    'analytics.impact_dist':'प्रभाव क्षेत्र वितरण','analytics.status_summary':'स्थिती सारांश',
    'analytics.monthly_trend':'मासिक सबमिशन ट्रेंड','analytics.score_dist':'AI गुणवत्ता गुण वितरण',
    'analytics.high':'उच्च (75+)','analytics.med':'मध्यम (50-74)','analytics.low_score':'कमी (<50)',
    'analytics.avg_note':'एकूण सरासरी AI गुण:',
    'detail.not_scored':'गुण दिले नाहीत','detail.no_ai':'कोणतेही AI मूल्यांकन उपलब्ध नाही.',
    'community.title':'समुदाय मत आणि गुण','community.upvotes':'अपवोट',
    'community.downvotes':'डाउनवोट','community.net':'निव्वळ गुण','community.score':'समुदाय गुण',
    'community.your_votes':'तुमच्या कल्पनेवर समुदाय मते:','community.vote_on':'या कल्पनेवर मत द्या:',
    'community.vote_hint':'▲ किंवा ▼ क्लिक करा · मत काढण्यासाठी पुन्हा क्लिक करा',
    'review.own_idea':'तुमची स्वतःची कल्पना','review.vote_needed':'तुमच्या मताची गरज',
    'review.committee_badge':'समिती','review.route_committee':'समितीकडे पाठवा',
    'review.submit_mine':'माझे पुनरावलोकन सादर करा','review.decide':'पुनरावलोकन / निर्णय',
    'review.cannot_own':'तुम्ही स्वतःच्या कल्पनेचे पुनरावलोकन करू शकत नाही',
    'preview.title_label':'शीर्षक','preview.situation':'परिस्थिती','preview.solution':'उपाय',
    'preview.impact_areas':'प्रभाव क्षेत्रे','preview.impact_level':'प्रभाव पातळी',
    'preview.co_suggesters':'सह-सूचक','preview.none_selected':'कोणतेही निवडले नाही',
    'platform.users':'वापरकर्ते','platform.ideas':'कल्पना','platform.implemented':'अंमलात आणले',
    'platform.last_activity':'शेवटची क्रिया','platform.active':'सक्रिय',
    'platform.db_error':'DB अनुपलब्ध','platform.view_org':'संस्था पहा',
    'platform.admins':'प्रशासक','platform.executives':'कार्यकारी',
    'platform.managers':'व्यवस्थापक','platform.employees':'कर्मचारी','platform.reports_to':'अहवाल देतो:',
    'sa.subtitle':'सर्व स्तरांवर संपूर्ण संस्थात्मक नियंत्रण','sa.last_refreshed':'शेवटचे ताजे केले:','sa.executives':'कार्यकारी',
    'admin.points_info':'गुण कॉन्फिगरेशन (config.php मध्ये संग्रहित). बदल लागू करण्यासाठी पुनरारंभ करा.',
    'admin.hr_info':'कर्मचारी डेटा users तक्त्यातून लोड केला जातो.',
    'admin.rescore_info':'सर्व विद्यमान कल्पनांचे AI गुण पुन्हा गणना करा.',
    'notif.header':'सूचना','login.feat2_sub':'+10 सादर · +25 मंजूर · +65 अंमलात','idea.impact_suffix':'प्रभाव',
    'time.just_now':'आत्ताच','time.min_ago':' मिनिटांपूर्वी','time.hr_ago':'तासापूर्वी','time.day_ago':'दिवसापूर्वी',
    'ar.title':'समितीकडे पाठवा','ar.search_add':'समीक्षक शोधा आणि जोडा',
    'ar.no_reviewers':'अद्याप कोणतेही समीक्षक जोडले नाहीत.','ar.threshold':'मंजुरी मर्यादा',
    'ar.unanimous':'सर्व समीक्षकांनी मंजुरी द्यावी (सर्वसंमत)',
    'ar.supermajority':'सुपरमेजोरिटी — किमान 2/3 ने मंजुरी द्यावी',
    'ar.simple_majority':'साधे बहुमत — निम्म्यापेक्षा जास्त ने मंजुरी द्यावी',
    'ar.info':'कल्पना पुनरावलोकनात जाईल. सर्व नियुक्त समीक्षकांना तत्काळ सूचित केले जाईल.',
    'rd.title':'माझे पुनरावलोकन','rd.decision':'तुमचा निर्णय',
    'rd.approve_btn':'✓ मंजूर करा','rd.reject_btn':'✗ नाकारा',
    'rd.feedback':'अभिप्राय / टिप्पणी','rd.feedback_ph':'सादरकर्त्याशी तुमचे तर्क सामायिक करा…',
    'init.hr_banner':'एचआर डेटाबेसमधून स्वतः आणले:','init.reporting_to':'अहवाल देतो:',
    'attach.prefix':'जोडलेले: ',
    'committee.approved_count':'मंजूर','committee.rejected_count':'नाकारले',
    'committee.pending_count':'प्रलंबित','committee.approval_req':'% मंजुरी आवश्यक',
    'review.my_review':'माझी समीक्षा','review.view_details':'तपशील पहा','review.review_btn':'समीक्षा',
    'rescore.ok':'{n} कल्पना यशस्वीपणे पुन्हा स्कोर केल्या।',
  },
  kn: {
    'app.name':'ಐಡಿಯಾ ಟೂಲ್',
    'nav.dashboard':'ಡ್ಯಾಶ್‌ಬೋರ್ಡ್','nav.my_ideas':'ನನ್ನ ಆಲೋಚನೆಗಳು','nav.submit':'ಆಲೋಚನೆ ಸಲ್ಲಿಸಿ',
    'nav.review':'ವಿಮರ್ಶೆ ಸರದಿ','nav.all_ideas':'ಎಲ್ಲಾ ಆಲೋಚನೆಗಳು','nav.audit':'ಆಡಿಟ್',
    'nav.leaderboard':'ಲೀಡರ್‌ಬೋರ್ಡ್','nav.analytics':'ವಿಶ್ಲೇಷಣೆ','nav.admin':'ನಿರ್ವಾಹಕ ಫಲಕ',
    'nav.super_admin':'ಸಂಸ್ಥೆ ಶ್ರೇಣಿ','nav.profile':'ನನ್ನ ಪ್ರೊಫೈಲ್',
    'section.main':'ಮುಖ್ಯ','section.workflow':'ವರ್ಕ್‌ಫ್ಲೋ','section.insights':'ಒಳನೋಟಗಳು',
    'section.admin':'ನಿರ್ವಾಹಕ','section.super_admin':'IFQM ಸೂಪರ್ ನಿರ್ವಾಹಕ',
    'login.app_title':'ಉದ್ಯೋಗಿ ಆಲೋಚನೆ ಸಾಧನ','login.tagline':'ಉತ್ತಮ ಆಲೋಚನೆಗಳನ್ನು ನಿಜವಾದ ಸುಧಾರಣೆಗಳನ್ನಾಗಿ ಮಾಡಿ.',
    'login.welcome':'ಮರಳಿ ಸ್ವಾಗತ','login.subtitle':'ಮುಂದುವರಿಯಲು ನಿಮ್ಮ IFQM ಖಾತೆಗೆ ಸೈನ್ ಇನ್ ಮಾಡಿ',
    'login.org_code':'ಸಂಸ್ಥೆ ಕೋಡ್','login.org_hint':'IFQM ಪ್ಲಾಟ್‌ಫಾರ್ಮ್ ಅಡ್ಮಿನ್‌ಗಾಗಿ ಖಾಲಿ ಬಿಡಿ',
    'login.forgot':'ಪಾಸ್‌ವರ್ಡ್ ಮರೆತಿರಾ?','login.email':'ಇಮೇಲ್ ವಿಳಾಸ','login.password':'ಪಾಸ್‌ವರ್ಡ್','login.btn':'ಸೈನ್ ಇನ್',
    'admin.add_user':'+ ಬಳಕೆದಾರ ಸೇರಿಸಿ',
    'topbar.dark':'ಡಾರ್ಕ್','topbar.light':'ಲೈಟ್','topbar.notifications':'ಅಧಿಸೂಚನೆಗಳು',
    'topbar.logout':'ಲಾಗ್ ಔಟ್','topbar.mark_read':'ಎಲ್ಲಾ ಓದಿದಂತೆ ಗುರುತಿಸಿ',
    'dash.total':'ಒಟ್ಟು ಆಲೋಚನೆಗಳು','dash.approved':'ಅನುಮೋದಿಸಲಾಗಿದೆ','dash.implemented':'ಅನುಷ್ಠಾನಗೊಳಿಸಲಾಗಿದೆ',
    'dash.pending_review':'ಬಾಕಿ ಇರುವ ವಿಮರ್ಶೆ','dash.overdue_reviews':'ವಿಳಂಬದ ವಿಮರ್ಶೆಗಳು',
    'dash.status_dist':'ಸ್ಥಿತಿ ವಿತರಣೆ','dash.recent':'ಇತ್ತೀಚಿನ ಚಟುವಟಿಕೆ',
    'status.submitted':'ಸಲ್ಲಿಸಲಾಗಿದೆ','status.review':'ವಿಮರ್ಶೆಯಲ್ಲಿದೆ','status.approved':'ಅನುಮೋದಿಸಲಾಗಿದೆ',
    'status.rejected':'ತಿರಸ್ಕರಿಸಲಾಗಿದೆ','status.implemented':'ಅನುಷ್ಠಾನಗೊಳಿಸಲಾಗಿದೆ','status.draft':'ಕರಡು',
    'idea.view':'ನೋಡಿ','idea.review':'ವಿಮರ್ಶೆ','idea.votes':'ಮತಗಳು',
    'vote.title':'ಸಮುದಾಯ ರೇಟಿಂಗ್','vote.your_rating':'ನಿಮ್ಮ ರೇಟಿಂಗ್','vote.avg':'ಸರಾಸರಿ',
    'vote.engagement_idx':'ತೊಡಗಿಸಿಕೊಳ್ಳುವಿಕೆ ಸೂಚ್ಯಂಕ','vote.submit':'ರೇಟಿಂಗ್ ಸಲ್ಲಿಸಿ',
    'form.save_draft':'ಕರಡು ಉಳಿಸಿ','form.next':'ಮುಂದೆ','form.back':'ಹಿಂದೆ','form.submit_idea':'ಹೊಸ ಆಲೋಚನೆ ಸಲ್ಲಿಸಿ',
    'detail.submitted_by':'ಸಲ್ಲಿಸಿದವರು','detail.situation':'ಪ್ರಸ್ತುತ ಪರಿಸ್ಥಿತಿ','detail.solution':'ಪ್ರಸ್ತಾವಿತ ಪರಿಹಾರ',
    'detail.ai_eval':'AI ಮೌಲ್ಯಮಾಪನ','detail.score':'ಸ್ಕೋರ್','detail.close':'ಮುಚ್ಚಿ',
    'lb.individual':'ವೈಯಕ್ತಿಕ ಶ್ರೇಣಿ','lb.dept':'ವಿಭಾಗ ಶ್ರೇಣಿ','lb.top_ideas':'ಅಗ್ರ ಸ್ಕೋರ್ ಆಲೋಚನೆಗಳು',
    'lb.points':'ಅಂಕಗಳು','lb.ideas':'ಆಲೋಚನೆಗಳು','lb.avg_score':'ಸರಾಸರಿ ಸ್ಕೋರ್','lb.engagement':'ತೊಡಗಿಸಿಕೊಳ್ಳುವಿಕೆ',
    'lb.all_time':'ಸರ್ವಕಾಲಿಕ','lb.monthly':'ಮಾಸಿಕ','lb.quarterly':'ತ್ರೈಮಾಸಿಕ','lb.yearly':'ವಾರ್ಷಿಕ','lb.you':'(ನೀವು)',
    'profile.title':'ಉದ್ಯೋಗಿ ಪ್ರೊಫೈಲ್','profile.stats':'ನನ್ನ ಅಂಕಿ-ಅಂಶಗಳು',
    'profile.dept':'ವಿಭಾಗ','profile.email_lbl':'ಇಮೇಲ್','profile.phone':'ಫೋನ್',
    'profile.reports_to':'ವರದಿ ಮಾಡುತ್ತಾರೆ','profile.bu':'ವ್ಯವಹಾರ ಘಟಕ','profile.loc':'ಸ್ಥಳ',
    'profile.hr_note':'HR ಡೇಟಾಬೇಸ್‌ನಿಂದ ಸ್ವಯಂಚಾಲಿತವಾಗಿ ಪಡೆಯಲಾಗಿದೆ. ಅಪ್‌ಡೇಟ್ ಗಾಗಿ ಅಡ್ಮಿನ್ ಅನ್ನು ಸಂಪರ್ಕಿಸಿ.',
    'profile.total_pts':'ಒಟ್ಟು ಅಂಕಗಳು',
    'btn.new_idea':'ಹೊಸ ಆಲೋಚನೆ','btn.close':'ಮುಚ್ಚಿ','btn.cancel':'ರದ್ದುಮಾಡಿ',
    'btn.submit_decision':'ನಿರ್ಧಾರ ಸಲ್ಲಿಸಿ','btn.view_my_ideas':'ನನ್ನ ಆಲೋಚನೆಗಳನ್ನು ನೋಡಿ',
    'btn.rescore_all':'ಎಲ್ಲಾ ಆಲೋಚನೆಗಳನ್ನು ಮರು-ಸ್ಕೋರ್ ಮಾಡಿ','btn.back_tenants':'← ಎಲ್ಲಾ ಟೆನೆಂಟ್‌ಗಳು',
    'page.my_ideas_sub':'ನೀವು ಸಲ್ಲಿಸಿದ ಎಲ್ಲಾ ಆಲೋಚನೆಗಳನ್ನು ಟ್ರ್ಯಾಕ್ ಮಾಡಿ',
    'placeholder.search_ideas':'ಆಲೋಚನೆಗಳನ್ನು ಹುಡುಕಿ...','filter.all_status':'ಎಲ್ಲಾ ಸ್ಥಿತಿ',
    'page.submit_sub':'ನಿಮ್ಮ ಸುಧಾರಣಾ ಆಲೋಚನೆ ಸಲ್ಲಿಸಲು ಎಲ್ಲಾ ಹಂತಗಳನ್ನು ಭರ್ತಿ ಮಾಡಿ',
    'wizard.step1':'ಪರಿಸ್ಥಿತಿ','wizard.step2':'ಪರಿಹಾರ','wizard.step3':'ಪ್ರಭಾವ',
    'wizard.step4':'ಸಹ-ಸೂಚಕರು','wizard.step5':'ಪರಿಶೀಲನೆ ಮತ್ತು ಸಲ್ಲಿಕೆ',
    'step1.heading':'ಹಂತ 1: ಪ್ರಸ್ತುತ ಪರಿಸ್ಥಿತಿಯನ್ನು ವಿವರಿಸಿ',
    'step1.title_label':'ಪರಿಸ್ಥಿತಿ ಶೀರ್ಷಿಕೆ','step1.title_ph':'ನಿಮ್ಮ ಆಲೋಚನೆಗೆ ಸಂಕ್ಷಿಪ್ತ ಶೀರ್ಷಿಕೆ',
    'step1.desc_label':'ಪ್ರಸ್ತುತ ಪರಿಸ್ಥಿತಿ ವಿವರಣೆ',
    'step1.desc_ph':'ಪ್ರಸ್ತುತ ಸಮಸ್ಯೆ ಅಥವಾ ಅಕಾರ್ಯಕ್ಷಮತೆಯನ್ನು ವಿವರವಾಗಿ ವಿವರಿಸಿ (ಕನಿಷ್ಠ 50 ಅಕ್ಷರಗಳು)…',
    'step1.doc_label':'ಸಹಾಯಕ ದಾಖಲೆ (ಐಚ್ಛಿಕ)',
    'step1.upload':'ಅಪ್‌ಲೋಡ್ ಮಾಡಲು ಕ್ಲಿಕ್ ಮಾಡಿ ಅಥವಾ ಎಳೆದು ಬಿಡಿ',
    'step1.upload_types':'PDF, PNG, JPG, XLSX — ಗರಿಷ್ಠ 10 MB',
    'step2.heading':'ಹಂತ 2: ಪ್ರಸ್ತಾವಿತ ಆಲೋಚನೆ / ಪರಿಹಾರ',
    'step2.label':'ಪ್ರಸ್ತಾವಿತ ಪರಿಹಾರ','step2.ph':'ನಿಮ್ಮ ಪ್ರಸ್ತಾವಿತ ಸುಧಾರಣೆಯನ್ನು ವಿವರವಾಗಿ ವಿವರಿಸಿ…',
    'step3.heading':'ಹಂತ 3: ಪ್ರಭಾವ ಕ್ಷೇತ್ರಗಳು ಮತ್ತು ಅಳೆಯಬಹುದಾದ ಪ್ರಯೋಜನಗಳು',
    'step3.impact_label':'ಪ್ರಭಾವ ಕ್ಷೇತ್ರಗಳನ್ನು ಆಯ್ಕೆಮಾಡಿ','step3.impact_sub':'(ಎಲ್ಲಾ ಅನ್ವಯಿಸುವ ಆಯ್ಕೆಗಳನ್ನು ಆಯ್ಕೆಮಾಡಿ)',
    'step3.level':'ಒಟ್ಟಾರೆ ಪ್ರಭಾವ ಮಟ್ಟ',
    'step3.tangible_label':'ಮೂರ್ತ ಪ್ರಯೋಜನ (ಐಚ್ಛಿಕ)','step3.tangible_ph':'ಉದಾ. ₹50,000 ಉಳಿತಾಯ/ವರ್ಷ',
    'step3.intangible_label':'ಅಮೂರ್ತ ಪ್ರಯೋಜನಗಳು (ಐಚ್ಛಿಕ)',
    'step3.intangible_ph':'ಉದಾ. ಕೆಲಸಗಾರರ ಆತ್ಮವಿಶ್ವಾಸ ಸುಧಾರಿಸುವುದು',
    'step4.heading':'ಹಂತ 4: ಸಹ-ಸೂಚಕರು (ಐಚ್ಛಿಕ, ಗರಿಷ್ಠ 2)',
    'step4.co1':'ಸಹ-ಸೂಚಕ 1','step4.co2':'ಸಹ-ಸೂಚಕ 2','step4.co_ph':'ಹೆಸರು ಅಥವಾ ಉದ್ಯೋಗಿ ID ಯಿಂದ ಹುಡುಕಿ…',
    'step5.heading':'ಹಂತ 5: ಪರಿಶೀಲನೆ ಮತ್ತು ಸಲ್ಲಿಕೆ',
    'step5.note':'ಸಲ್ಲಿಸುವ ಮೂಲಕ ನೀವು ಈ ಆಲೋಚನೆ ಮೂಲವಾದುದು ಎಂದು ದೃಢಪಡಿಸುತ್ತೀರಿ. ಸಲ್ಲಿಸಿದ ನಂತರ +10 ಅಂಕಗಳು ಸಿಗುತ್ತವೆ.',
    'impact.production':'ಉತ್ಪಾದನೆ','impact.quality':'ಗುಣಮಟ್ಟ','impact.cost':'ವೆಚ್ಚ',
    'impact.delivery':'ವಿತರಣೆ','impact.safety':'ಸುರಕ್ಷತೆ','impact.environment':'ಪರಿಸರ',
    'impact.morale':'ಮನೋಬಲ','impact.low':'ಕಡಿಮೆ','impact.medium':'ಮಧ್ಯಮ','impact.high':'ಹೆಚ್ಚು',
    'page.review_sub':'ನಿಮ್ಮ ಪರಿಶೀಲನೆಗಾಗಿ ಬಾಕಿ ಇರುವ ಆಲೋಚನೆಗಳು — AI ಗುಣಮಟ್ಟ ಸ್ಕೋರ್ ಪ್ರಕಾರ ವಿಂಗಡಿಸಲಾಗಿದೆ',
    'placeholder.search':'ಹುಡುಕಿ…','filter.all_impact':'ಎಲ್ಲಾ ಪ್ರಭಾವ',
    'table.idea_id':'ಆಲೋಚನೆ ID','table.title':'ಶೀರ್ಷಿಕೆ','table.submitted_by':'ಸಲ್ಲಿಸಿದವರು',
    'table.dept':'ವಿಭಾಗ','table.impact':'ಪ್ರಭಾವ','table.ai_score':'AI ಸ್ಕೋರ್',
    'table.engagement':'ತೊಡಗಿಸಿಕೊಳ್ಳುವಿಕೆ','table.status':'ಸ್ಥಿತಿ','table.date':'ದಿನಾಂಕ','table.action':'ಕ್ರಿಯೆ',
    'table.timestamp':'ಸಮಯ','table.actor':'ಕರ್ತೃ','table.comment_col':'ಟಿಪ್ಪಣಿ',
    'table.event':'ಘಟನೆ','table.pts_awarded':'ಅಂಕಗಳು ನೀಡಲಾಯಿತು',
    'table.employee':'ಉದ್ಯೋಗಿ','table.emp_id':'ID','table.role':'ಪಾತ್ರ',
    'table.bu':'ವ್ಯವಹಾರ ಘಟಕ','table.email_col':'ಇಮೇಲ್',
    'table.reports_to':'ವರದಿ ಮಾಡುತ್ತಾರೆ','table.points_col':'ಅಂಕಗಳು','table.ideas_col':'ಆಲೋಚನೆಗಳು',
    'page.audit_title':'ಸಿಸ್ಟಂ ಆಡಿಟ್ ಟ್ರೇಲ್',
    'page.audit_sub':'ಎಲ್ಲಾ ವರ್ಕ್‌ಫ್ಲೋ ಕ್ರಿಯೆಗಳ ಬದಲಾಯಿಸಲಾಗದ ಲಾಗ್',
    'audit.proof':'ಈ ಲಾಗ್ ಕೇವಲ-ಸೇರ್ಪಡೆ ಮತ್ತು ತಿರಸ್ಕರಣ-ನಿರೋಧಕ. ಯಾವುದೇ ದಾಖಲೆ ಸಂಪಾದಿಸಲು ಅಥವಾ ಅಳಿಸಲು ಸಾಧ್ಯವಿಲ್ಲ.',
    'modal.details':'ವಿವರಗಳು','modal.timeline':'ಟೈಮ್‌ಲೈನ್','modal.attachments':'ಲಗತ್ತುಗಳು',
    'review.decision':'ನಿರ್ಧಾರ','review.comment_label':'ಟಿಪ್ಪಣಿ / ಪ್ರತಿಕ್ರಿಯೆ',
    'review.comment_ph':'ಸಲ್ಲಿಸಿದವರಿಗೆ ಐಚ್ಛಿಕ ಟಿಪ್ಪಣಿಗಳು…',
    'review.to_review':'ಪರಿಶೀಲನೆಯಲ್ಲಿ ಸರಿಸಿ','review.approve':'ಅನುಮೋದಿಸಿ',
    'review.reject':'ತಿರಸ್ಕರಿಸಿ','review.implement':'ಅನುಷ್ಠಾನಗೊಂಡಿದೆ ಎಂದು ಗುರುತಿಸಿ',
    'bulk.approve_all':'ಎಲ್ಲಾ ಅನುಮೋದಿಸಿ','bulk.reject_all':'ಎಲ್ಲಾ ತಿರಸ್ಕರಿಸಿ','bulk.clear':'ಅಳಿಸಿ',
    'admin.tab_overview':'ಅವಲೋಕನ','admin.tab_ideas':'ಆಲೋಚನೆ ನಿರ್ವಹಣೆ',
    'admin.tab_users':'ಬಳಕೆದಾರ ಪಟ್ಟಿ','admin.tab_system':'ಸಿಸ್ಟಂ',
    'admin.points_config':'ಅಂಕ ಸಂರಚನೆ',
    'admin.event_sub':'ಆಲೋಚನೆ ಸಲ್ಲಿಸಲಾಯಿತು','admin.event_app':'ಆಲೋಚನೆ ಅನುಮೋದಿಸಲಾಯಿತು','admin.event_impl':'ಆಲೋಚನೆ ಅನುಷ್ಠಾನಗೊಂಡಿತು',
    'admin.db_status':'HR ಡೇಟಾಬೇಸ್ ಸಿಂಕ್ ಸ್ಥಿತಿ',
    'admin.rescore_desc':'ಪ್ರಸ್ತುತ ಸ್ಕೋರಿಂಗ್ ಮಾದರಿ ಬಳಸಿ ಎಲ್ಲಾ ಅಸ್ತಿತ್ವದಲ್ಲಿರುವ ಆಲೋಚನೆಗಳ AI ಸ್ಕೋರ್ ಮರು-ಲೆಕ್ಕಾಚಾರ ಮಾಡಿ.',
    'sa.console':'ಕಮಾಂಡ್ ಸೆಂಟರ್','sa.signed_in':'ಎಂದು ಸೈನ್ ಇನ್',
    'sa.tab_overview':'ಅವಲೋಕನ','sa.tab_hierarchy':'ಸಂಸ್ಥೆ ಶ್ರೇಣಿ',
    'sa.tab_users':'ಬಳಕೆದಾರ ನಿರ್ವಹಣೆ','sa.tab_system':'ಸಿಸ್ಟಂ',
    'sa.status_dist':'ಆಲೋಚನೆ ಸ್ಥಿತಿ ವಿತರಣೆ','sa.recent':'ಇತ್ತೀಚಿನ ಚಟುವಟಿಕೆ',
    'sa.org_tree':'ಸಂಘಟನೆ ವೃಕ್ಷ — ಅಡ್ಮಿನ್ → ಮ್ಯಾನೇಜರ್ → ಉದ್ಯೋಗಿ',
    'sa.all_employees':'ಎಲ್ಲಾ ಉದ್ಯೋಗಿಗಳು','sa.ai_engine':'AI ಸ್ಕೋರಿಂಗ್ ಎಂಜಿನ್',
    'sa.ai_desc':'ಎಲ್ಲಾ ಅಸ್ತಿತ್ವದಲ್ಲಿರುವ ಆಲೋಚನೆಗಳ AI ಗುಣಮಟ್ಟ ಸ್ಕೋರ್ ಮರು-ಲೆಕ್ಕಾಚಾರ ಮಾಡಿ.',
    'sa.db_sync':'ಡೇಟಾಬೇಸ್ ಮತ್ತು HR ಸಿಂಕ್',
    'placeholder.search_user':'ಹೆಸರು, ಇಮೇಲ್ ಅಥವಾ ID ಯಿಂದ ಹುಡುಕಿ…',
    'pa.overview':'ಪ್ಲಾಟ್‌ಫಾರ್ಮ್ ಅವಲೋಕನ','pa.private':'ಕೇವಲ ಒಟ್ಟು ಮೆಟ್ರಿಕ್‌ಗಳು — ಟೆನೆಂಟ್ ವಿಷಯ ಖಾಸಗಿ',
    'pa.signed_in':'ಎಂದು ಸೈನ್ ಇನ್','pa.all_tenants':'ಎಲ್ಲಾ ಟೆನೆಂಟ್‌ಗಳು — ಒಟ್ಟು ಅಂಕಿಅಂಶಗಳು',
    'pa.tenant_hierarchy':'ಟೆನೆಂಟ್ ಶ್ರೇಣಿ',
    'pa.hierarchy_sub':'ಸಂಸ್ಥೆ ರಚನೆ — ಹೆಸರುಗಳು, ಪಾತ್ರಗಳು, ವಿಭಾಗಗಳು ಮಾತ್ರ. ಆಲೋಚನೆ ವಿಷಯ ಇಲ್ಲ.',
    'pa.user_hierarchy':'ಬಳಕೆದಾರ ಶ್ರೇಣಿ',
    'msg.loading':'ಲೋಡ್ ಆಗುತ್ತಿದೆ…','msg.no_ideas':'ಯಾವುದೇ ಆಲೋಚನೆ ಸಿಗಲಿಲ್ಲ. ನಿಮ್ಮ ಮೊದಲ ಆಲೋಚನೆ ಸಲ್ಲಿಸಿ!',
    'msg.no_review':'ಪರಿಶೀಲನೆಗಾಗಿ ಯಾವುದೇ ಆಲೋಚನೆ ಬಾಕಿ ಇಲ್ಲ.','msg.no_audit':'ಯಾವುದೇ ಆಡಿಟ್ ದಾಖಲೆ ಇಲ್ಲ.',
    'msg.no_leaderboard':'ಇನ್ನೂ ಯಾವುದೇ ಸ್ಕೋರ್ ಇಲ್ಲ. ರ್ಯಾಂಕಿಂಗ್ ನೋಡಲು ಆಲೋಚನೆಗಳನ್ನು ಸಲ್ಲಿಸಿ.',
    'msg.no_notif':'ಯಾವುದೇ ಅಧಿಸೂಚನೆ ಇಲ್ಲ','msg.draft_prefix':'ಡ್ರಾಫ್ಟ್ ಉಳಿಸಲಾಗಿದೆ! ಆಲೋಚನೆ ಕೋಡ್: ',
    'msg.fill_situation':'ಶೀರ್ಷಿಕೆ ಮತ್ತು ಪರಿಸ್ಥಿತಿ ವಿವರಣೆ ಭರ್ತಿ ಮಾಡಿ (ಕನಿಷ್ಠ 20 ಅಕ್ಷರ).',
    'msg.fill_solution':'ಪ್ರಸ್ತಾವಿತ ಪರಿಹಾರ ಭರ್ತಿ ಮಾಡಿ.',
    'msg.server_error':'ಸರ್ವರ್ ದೋಷ. ದಯವಿಟ್ಟು ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ.',
    'msg.fail_dashboard':'ಡ್ಯಾಶ್‌ಬೋರ್ಡ್ ಲೋಡ್ ಆಗಲಿಲ್ಲ. ಸರ್ವರ್ ಚಾಲನೆಯಲ್ಲಿದೆಯೇ?',
    'msg.fail_ideas':'ಆಲೋಚನೆಗಳು ಲೋಡ್ ಆಗಲಿಲ್ಲ.','msg.fail_queue':'ಪರಿಶೀಲನೆ ಸಾಲು ಲೋಡ್ ಆಗಲಿಲ್ಲ.',
    'msg.fail_audit':'ಲೋಡ್ ಆಗಲಿಲ್ಲ.','msg.fail_leaderboard':'ಲೀಡರ್‌ಬೋರ್ಡ್ ಲೋಡ್ ಆಗಲಿಲ್ಲ.',
    'msg.fail_analytics':'ವಿಶ್ಲೇಷಣೆ ಲೋಡ್ ಆಗಲಿಲ್ಲ.',
    'msg.audit_restricted':'ಆಡಿಟ್ ಟ್ರೇಲ್ ಕೇವಲ ಮ್ಯಾನೇಜರ್‌ಗಳು, ಅಡ್ಮಿನ್ ಮತ್ತು ಕಾರ್ಯನಿರ್ವಾಹಕರಿಗೆ ಲಭ್ಯ.',
    'msg.analytics_restricted':'ವಿಶ್ಲೇಷಣೆ ಕೇವಲ ಮ್ಯಾನೇಜರ್‌ಗಳು, ಅಡ್ಮಿನ್ ಮತ್ತು ಕಾರ್ಯನಿರ್ವಾಹಕರಿಗೆ ಲಭ್ಯ.',
    'msg.decision_ok':'ನಿರ್ಧಾರ ಸಲ್ಲಿಸಲಾಗಿದೆ','msg.idea_ok':'ಆಲೋಚನೆ ಯಶಸ್ವಿಯಾಗಿ ಸಲ್ಲಿಸಲಾಗಿದೆ',
    'pa.active_tenants':'ಸಕ್ರಿಯ ಟೆನೆಂಟ್‌ಗಳು','pa.total_users':'ಒಟ್ಟು ಬಳಕೆದಾರರು','pa.ideas_submitted':'ಸಲ್ಲಿಸಿದ ಆಲೋಚನೆಗಳು','msg.rescoring':'ಮರು-ಸ್ಕೋರಿಂಗ್…',
    'analytics.approval_rate':'ಅನುಮೋದನೆ ದರ','analytics.impl_rate':'ಅನುಷ್ಠಾನ ದರ','analytics.avg_score':'ಸರಾಸರಿ AI ಗುಣಮಟ್ಟ ಸ್ಕೋರ್',
    'page.leaderboard_title':'ಲೀಡರ್‌ಬೋರ್ಡ್ ಮತ್ತು ಗೇಮಿಫಿಕೇಶನ್','page.analytics_title':'ವಿಶ್ಲೇಷಣೆ ಡ್ಯಾಶ್‌ಬೋರ್ಡ್',
    'analytics.impact_dist':'ಪ್ರಭಾವ ಕ್ಷೇತ್ರ ವಿತರಣೆ','analytics.status_summary':'ಸ್ಥಿತಿ ಸಾರಾಂಶ',
    'analytics.monthly_trend':'ಮಾಸಿಕ ಸಲ್ಲಿಕೆ ಪ್ರವೃತ್ತಿ','analytics.score_dist':'AI ಗುಣಮಟ್ಟ ಸ್ಕೋರ್ ವಿತರಣೆ',
    'analytics.high':'ಹೆಚ್ಚು (75+)','analytics.med':'ಮಧ್ಯಮ (50-74)','analytics.low_score':'ಕಡಿಮೆ (<50)',
    'analytics.avg_note':'ಒಟ್ಟಾರೆ ಸರಾಸರಿ AI ಸ್ಕೋರ್:',
    'detail.not_scored':'ಸ್ಕೋರ್ ಮಾಡಿಲ್ಲ','detail.no_ai':'AI ಮೌಲ್ಯಮಾಪನ ಲಭ್ಯವಿಲ್ಲ.',
    'community.title':'ಸಮುದಾಯ ವೋಟ್ & ಸ್ಕೋರ್','community.upvotes':'ಅಪ್‌ವೋಟ್',
    'community.downvotes':'ಡೌನ್‌ವೋಟ್','community.net':'ನೆಟ್ ಸ್ಕೋರ್','community.score':'ಸಮುದಾಯ ಸ್ಕೋರ್',
    'community.your_votes':'ನಿಮ್ಮ ಆಲೋಚನೆಯ ಸಮುದಾಯ ವೋಟ್‌ಗಳು:','community.vote_on':'ಈ ಆಲೋಚನೆಗೆ ವೋಟ್ ಮಾಡಿ:',
    'community.vote_hint':'▲ ಅಥವಾ ▼ ಕ್ಲಿಕ್ ಮಾಡಿ · ವೋಟ್ ತೆಗೆಯಲು ಮತ್ತೆ ಕ್ಲಿಕ್ ಮಾಡಿ',
    'review.own_idea':'ನಿಮ್ಮ ಸ್ವಂತ ಆಲೋಚನೆ','review.vote_needed':'ನಿಮ್ಮ ವೋಟ್ ಬೇಕು',
    'review.committee_badge':'ಸಮಿತಿ','review.route_committee':'ಸಮಿತಿಗೆ ಕಳುಹಿಸಿ',
    'review.submit_mine':'ನನ್ನ ವಿಮರ್ಶೆ ಸಲ್ಲಿಸಿ','review.decide':'ವಿಮರ್ಶೆ / ನಿರ್ಧಾರ',
    'review.cannot_own':'ನೀವು ನಿಮ್ಮ ಸ್ವಂತ ಆಲೋಚನೆಯನ್ನು ವಿಮರ್ಶಿಸಲು ಸಾಧ್ಯವಿಲ್ಲ',
    'preview.title_label':'ಶೀರ್ಷಿಕೆ','preview.situation':'ಪರಿಸ್ಥಿತಿ','preview.solution':'ಪರಿಹಾರ',
    'preview.impact_areas':'ಪ್ರಭಾವ ಕ್ಷೇತ್ರಗಳು','preview.impact_level':'ಪ್ರಭಾವ ಮಟ್ಟ',
    'preview.co_suggesters':'ಸಹ-ಸೂಚಕರು','preview.none_selected':'ಯಾವುದೂ ಆಯ್ಕೆ ಮಾಡಿಲ್ಲ',
    'platform.users':'ಬಳಕೆದಾರರು','platform.ideas':'ಆಲೋಚನೆಗಳು','platform.implemented':'ಅನುಷ್ಠಾನಗೊಂಡಿವೆ',
    'platform.last_activity':'ಕೊನೆಯ ಚಟುವಟಿಕೆ','platform.active':'ಸಕ್ರಿಯ',
    'platform.db_error':'DB ಅಲಭ್ಯ','platform.view_org':'ಸಂಸ್ಥೆ ನೋಡಿ',
    'platform.admins':'ಅಡ್ಮಿನ್‌ಗಳು','platform.executives':'ಕಾರ್ಯನಿರ್ವಾಹಕರು',
    'platform.managers':'ಮ್ಯಾನೇಜರ್‌ಗಳು','platform.employees':'ಉದ್ಯೋಗಿಗಳು','platform.reports_to':'ವರದಿ ಮಾಡುತ್ತಾರೆ:',
    'sa.subtitle':'ಎಲ್ಲಾ ಮಟ್ಟಗಳಲ್ಲಿ ಸಂಪೂರ್ಣ ಸಂಸ್ಥಾ ನಿಯಂತ್ರಣ','sa.last_refreshed':'ಕೊನೆಯ ರಿಫ್ರೆಶ್:','sa.executives':'ಕಾರ್ಯನಿರ್ವಾಹಕರು',
    'admin.points_info':'ಅಂಕ ಕಾನ್ಫಿಗ್ (config.php ನಲ್ಲಿ ಸಂಗ್ರಹಿಸಲಾಗಿದೆ). ಬದಲಾವಣೆಗಳನ್ನು ಅನ್ವಯಿಸಲು ಮರುಪ್ರಾರಂಭಿಸಿ.',
    'admin.hr_info':'ಉದ್ಯೋಗಿ ಡೇಟಾ users ಟೇಬಲ್‌ನಿಂದ ಲೋಡ್ ಆಗುತ್ತದೆ.',
    'admin.rescore_info':'ಎಲ್ಲಾ ಆಲೋಚನೆಗಳ AI ಸ್ಕೋರ್ ಮರು-ಲೆಕ್ಕಾಚಾರ ಮಾಡಿ.',
    'notif.header':'ಅಧಿಸೂಚನೆಗಳು','login.feat2_sub':'+10 ಸಲ್ಲಿಸಿ · +25 ಅನುಮೋದಿಸಿ · +65 ಅನುಷ್ಠಾನ','idea.impact_suffix':'ಪ್ರಭಾವ',
    'time.just_now':'ಈಗಷ್ಟೇ','time.min_ago':' ನಿಮಿಷ ಹಿಂದೆ','time.hr_ago':'ಗಂ ಹಿಂದೆ','time.day_ago':'ದಿ ಹಿಂದೆ',
    'ar.title':'ಸಮಿತಿಗೆ ಕಳುಹಿಸಿ','ar.search_add':'ಸಮೀಕ್ಷಕರನ್ನು ಹುಡುಕಿ ಮತ್ತು ಸೇರಿಸಿ',
    'ar.no_reviewers':'ಇನ್ನೂ ಯಾವುದೇ ಸಮೀಕ್ಷಕರು ಸೇರಿಸಿಲ್ಲ.','ar.threshold':'ಅನುಮೋದನೆ ಮಿತಿ',
    'ar.unanimous':'ಎಲ್ಲಾ ಸಮೀಕ್ಷಕರು ಅನುಮೋದಿಸಬೇಕು (ಸರ್ವಾನುಮತ)',
    'ar.supermajority':'ಸೂಪರ್‌ಮೇಜಾರಿಟಿ — ಕನಿಷ್ಠ 2/3 ಅನುಮೋದಿಸಬೇಕು',
    'ar.simple_majority':'ಸಾಧಾರಣ ಬಹುಮತ — ಅರ್ಧಕ್ಕಿಂತ ಹೆಚ್ಚು ಅನುಮೋದಿಸಬೇಕು',
    'ar.info':'ಆಲೋಚನೆ ಪರಿಶೀಲನೆಗೆ ಹೋಗುತ್ತದೆ. ನಿಯೋಜಿತ ಸಮೀಕ್ಷಕರಿಗೆ ತಕ್ಷಣ ಸೂಚಿಸಲಾಗುತ್ತದೆ.',
    'rd.title':'ನನ್ನ ವಿಮರ್ಶೆ','rd.decision':'ನಿಮ್ಮ ನಿರ್ಧಾರ',
    'rd.approve_btn':'✓ ಅನುಮೋದಿಸಿ','rd.reject_btn':'✗ ತಿರಸ್ಕರಿಸಿ',
    'rd.feedback':'ಪ್ರತಿಕ್ರಿಯೆ / ಟಿಪ್ಪಣಿ','rd.feedback_ph':'ಸಲ್ಲಿಸಿದವರೊಂದಿಗೆ ನಿಮ್ಮ ತರ್ಕ ಹಂಚಿಕೊಳ್ಳಿ…',
    'init.hr_banner':'HR ಡೇಟಾಬೇಸ್‌ನಿಂದ ಸ್ವಯಂ ಪಡೆದಿದೆ:','init.reporting_to':'ವರದಿ ಮಾಡುತ್ತಾರೆ:',
    'attach.prefix':'ಲಗತ್ತಿಸಲಾಗಿದೆ: ',
    'committee.approved_count':'ಅನುಮೋದಿಸಲಾಗಿದೆ','committee.rejected_count':'ತಿರಸ್ಕರಿಸಲಾಗಿದೆ',
    'committee.pending_count':'ಬಾಕಿ','committee.approval_req':'% ಅನುಮೋದನೆ ಅಗತ್ಯ',
    'review.my_review':'ನನ್ನ ವಿಮರ್ಶೆ','review.view_details':'ವಿವರಗಳನ್ನು ನೋಡಿ','review.review_btn':'ವಿಮರ್ಶೆ',
    'rescore.ok':'{n} ಆಲೋಚನೆಗಳನ್ನು ಯಶಸ್ವಿಯಾಗಿ ಮರುಸ್ಕೋರ್ ಮಾಡಲಾಗಿದೆ।',
  },
  te: {
    'app.name':'ఐడియా టూల్',
    'nav.dashboard':'డాష్‌బోర్డ్','nav.my_ideas':'నా ఆలోచనలు','nav.submit':'ఆలోచన సమర్పించు',
    'nav.review':'సమీక్ష క్యూ','nav.all_ideas':'అన్ని ఆలోచనలు','nav.audit':'ఆడిట్',
    'nav.leaderboard':'లీడర్‌బోర్డ్','nav.analytics':'విశ్లేషణ','nav.admin':'నిర్వాహకుడు',
    'nav.super_admin':'సంస్థ క్రమానుగతం','nav.profile':'నా ప్రొఫైల్',
    'section.main':'ప్రధాన','section.workflow':'వర్క్‌ఫ్లో','section.insights':'అంతర్దృష్టులు',
    'section.admin':'నిర్వాహకుడు','section.super_admin':'IFQM సూపర్ అడ్మిన్',
    'login.app_title':'ఉద్యోగి ఆలోచన సాధనం','login.tagline':'గొప్ప ఆలోచనలను నిజమైన మెరుగుదలలుగా మార్చండి.',
    'login.welcome':'మళ్ళీ స్వాగతం','login.subtitle':'కొనసాగించడానికి మీ IFQM ఖాతాకు సైన్ ఇన్ చేయండి',
    'login.org_code':'సంస్థ కోడ్','login.org_hint':'IFQM ప్లాట్‌ఫారమ్ అడ్మిన్ కోసం ఖాళీగా వదలండి',
    'login.forgot':'పాస్‌వర్డ్ మర్చితారా?','login.email':'ఇమెయిల్ చిరునామా','login.password':'పాస్‌వర్డ్','login.btn':'సైన్ ఇన్',
    'admin.add_user':'+ వినియోగదారుని జోడించు',
    'topbar.dark':'డార్క్','topbar.light':'లైట్','topbar.notifications':'నోటిఫికేషన్లు',
    'topbar.logout':'లాగ్ అవుట్','topbar.mark_read':'అన్నీ చదివినట్లు గుర్తించు',
    'dash.total':'మొత్తం ఆలోచనలు','dash.approved':'అనుమతించబడింది','dash.implemented':'అమలు చేయబడింది',
    'dash.pending_review':'అనుమోదించవలసిన సమీక్ష','dash.overdue_reviews':'అతివ్యాధి సమీక్షలు',
    'dash.status_dist':'స్థితి పంపిణీ','dash.recent':'ఇటీవలి కార్యకలాపాలు',
    'status.submitted':'సమర్పించబడింది','status.review':'సమీక్షలో','status.approved':'అనుమతించబడింది',
    'status.rejected':'తిరస్కరించబడింది','status.implemented':'అమలు చేయబడింది','status.draft':'ముసాయిదా',
    'idea.view':'చూడండి','idea.review':'సమీక్ష','idea.votes':'ఓట్లు',
    'vote.title':'సమాజ రేటింగ్','vote.your_rating':'మీ రేటింగ్','vote.avg':'సగటు',
    'vote.engagement_idx':'నిమగ్నత సూచిక','vote.submit':'రేటింగ్ సమర్పించు',
    'form.save_draft':'ముసాయిదా సేవ్ చేయి','form.next':'తదుపరి','form.back':'వెనక్కి','form.submit_idea':'కొత్త ఆలోచన సమర్పించు',
    'detail.submitted_by':'సమర్పించినవారు','detail.situation':'ప్రస్తుత పరిస్థితి','detail.solution':'ప్రతిపాదిత పరిష్కారం',
    'detail.ai_eval':'AI మూల్యాంకనం','detail.score':'స్కోరు','detail.close':'మూసివేయి',
    'lb.individual':'వ్యక్తిగత ర్యాంకింగ్','lb.dept':'విభాగ ర్యాంకింగ్','lb.top_ideas':'అగ్ర స్కోరు ఆలోచనలు',
    'lb.points':'పాయింట్లు','lb.ideas':'ఆలోచనలు','lb.avg_score':'సగటు స్కోరు','lb.engagement':'నిమగ్నత',
    'lb.all_time':'సర్వకాలిక','lb.monthly':'నెలవారీ','lb.quarterly':'త్రైమాసిక','lb.yearly':'వార్షిక','lb.you':'(మీరు)',
    'profile.title':'ఉద్యోగి ప్రొఫైల్','profile.stats':'నా గణాంకాలు',
    'profile.dept':'విభాగం','profile.email_lbl':'ఇమెయిల్','profile.phone':'ఫోన్',
    'profile.reports_to':'రిపోర్టింగ్ మేనేజర్','profile.bu':'వ్యాపార విభాగం','profile.loc':'స్థానం',
    'profile.hr_note':'HR డేటాబేస్ నుండి స్వయంచాలకంగా తీసుకోబడింది. అప్‌డేట్ కోసం అడ్మిన్‌ని సంప్రదించండి.',
    'profile.total_pts':'మొత్తం పాయింట్లు',
    'btn.new_idea':'కొత్త ఆలోచన','btn.close':'మూసివేయి','btn.cancel':'రద్దు చేయి',
    'btn.submit_decision':'నిర్ణయం సమర్పించు','btn.view_my_ideas':'నా ఆలోచనలు చూడండి',
    'btn.rescore_all':'అన్ని ఆలోచనలను మళ్ళీ స్కోర్ చేయి','btn.back_tenants':'← అన్ని టెనెంట్లు',
    'page.my_ideas_sub':'మీరు సమర్పించిన అన్ని ఆలోచనలను ట్రాక్ చేయండి',
    'placeholder.search_ideas':'ఆలోచనలు వెతకండి...','filter.all_status':'అన్ని స్థితులు',
    'page.submit_sub':'మీ మెరుగుదల ఆలోచనను సమర్పించడానికి అన్ని దశలు పూర్తి చేయండి',
    'wizard.step1':'పరిస్థితి','wizard.step2':'పరిష్కారం','wizard.step3':'ప్రభావం',
    'wizard.step4':'సహ-సూచికులు','wizard.step5':'సమీక్ష & సమర్పణ',
    'step1.heading':'దశ 1: ప్రస్తుత పరిస్థితిని వివరించండి',
    'step1.title_label':'పరిస్థితి శీర్షిక','step1.title_ph':'మీ ఆలోచనకు సంక్షిప్త శీర్షిక',
    'step1.desc_label':'ప్రస్తుత పరిస్థితి వివరణ',
    'step1.desc_ph':'ప్రస్తుత సమస్య లేదా అసమర్థతను వివరంగా వివరించండి (కనీసం 50 అక్షరాలు)…',
    'step1.doc_label':'మద్దతు పత్రం (ఐచ్ఛికం)',
    'step1.upload':'అప్‌లోడ్ చేయడానికి క్లిక్ చేయండి లేదా డ్రాగ్ చేయండి',
    'step1.upload_types':'PDF, PNG, JPG, XLSX — గరిష్టం 10 MB',
    'step2.heading':'దశ 2: ప్రతిపాదిత ఆలోచన / పరిష్కారం',
    'step2.label':'ప్రతిపాదిత పరిష్కారం','step2.ph':'మీ ప్రతిపాదిత మెరుగుదలను వివరంగా వివరించండి…',
    'step3.heading':'దశ 3: ప్రభావ ప్రాంతాలు & కొలవగల ప్రయోజనాలు',
    'step3.impact_label':'ప్రభావ ప్రాంతాలను ఎంచుకోండి','step3.impact_sub':'(వర్తించే అన్నింటినీ ఎంచుకోండి)',
    'step3.level':'మొత్తం ప్రభావ స్థాయి',
    'step3.tangible_label':'స్పష్టమైన ప్రయోజనం (ఐచ్ఛికం)','step3.tangible_ph':'ఉదా. ₹50,000 ఆదా/సంవత్సరం',
    'step3.intangible_label':'అస్పష్టమైన ప్రయోజనాలు (ఐచ్ఛికం)',
    'step3.intangible_ph':'ఉదా. కార్మికుల ఆత్మవిశ్వాసం మెరుగుపడటం',
    'step4.heading':'దశ 4: సహ-సూచికులు (ఐచ్ఛికం, గరిష్టం 2)',
    'step4.co1':'సహ-సూచికుడు 1','step4.co2':'సహ-సూచికుడు 2','step4.co_ph':'పేరు లేదా ఉద్యోగి ID తో వెతకండి…',
    'step5.heading':'దశ 5: సమీక్ష & సమర్పణ',
    'step5.note':'సమర్పించడం ద్వారా ఈ ఆలోచన మౌలికమైనదని మీరు నిర్ధారిస్తున్నారు. సమర్పించిన తర్వాత +10 పాయింట్లు లభిస్తాయి.',
    'impact.production':'ఉత్పత్తి','impact.quality':'నాణ్యత','impact.cost':'వ్యయం',
    'impact.delivery':'డెలివరీ','impact.safety':'భద్రత','impact.environment':'పర్యావరణం',
    'impact.morale':'మనోబలం','impact.low':'తక్కువ','impact.medium':'మధ్యమం','impact.high':'అధికం',
    'page.review_sub':'మీ సమీక్షకు పెండింగ్‌లో ఉన్న ఆలోచనలు — AI నాణ్యత స్కోరు ప్రకారం క్రమీకరించబడింది',
    'placeholder.search':'వెతకండి…','filter.all_impact':'అన్ని ప్రభావాలు',
    'table.idea_id':'ఆలోచన ID','table.title':'శీర్షిక','table.submitted_by':'సమర్పించినవారు',
    'table.dept':'విభాగం','table.impact':'ప్రభావం','table.ai_score':'AI స్కోరు',
    'table.engagement':'నిమగ్నత','table.status':'స్థితి','table.date':'తేదీ','table.action':'చర్య',
    'table.timestamp':'సమయం','table.actor':'నటుడు','table.comment_col':'వ్యాఖ్య',
    'table.event':'సంఘటన','table.pts_awarded':'పాయింట్లు మంజూరు',
    'table.employee':'ఉద్యోగి','table.emp_id':'ID','table.role':'పాత్ర',
    'table.bu':'వ్యాపార విభాగం','table.email_col':'ఇమెయిల్',
    'table.reports_to':'రిపోర్ట్ చేస్తారు','table.points_col':'పాయింట్లు','table.ideas_col':'ఆలోచనలు',
    'page.audit_title':'సిస్టమ్ ఆడిట్ ట్రెయిల్',
    'page.audit_sub':'అన్ని వర్క్‌ఫ్లో చర్యల మార్చలేని లాగ్',
    'audit.proof':'ఈ లాగ్ కేవలం జోడింపు మాత్రమే మరియు తారుమారు-నిరోధక. ఏ రికార్డూ సవరించడం లేదా తొలగించడం సాధ్యం కాదు.',
    'modal.details':'వివరాలు','modal.timeline':'టైమ్‌లైన్','modal.attachments':'జోడింపులు',
    'review.decision':'నిర్ణయం','review.comment_label':'వ్యాఖ్య / అభిప్రాయం',
    'review.comment_ph':'సమర్పించినవారికి ఐచ్ఛిక వ్యాఖ్యలు…',
    'review.to_review':'సమీక్షలో ఉంచు','review.approve':'అనుమతించు',
    'review.reject':'తిరస్కరించు','review.implement':'అమలు చేయబడిందిగా గుర్తించు',
    'bulk.approve_all':'అన్నీ ఆమోదించు','bulk.reject_all':'అన్నీ తిరస్కరించు','bulk.clear':'తొలగించు',
    'admin.tab_overview':'అవలోకనం','admin.tab_ideas':'ఆలోచన నిర్వహణ',
    'admin.tab_users':'వినియోగదారు జాబితా','admin.tab_system':'సిస్టమ్',
    'admin.points_config':'పాయింట్ కాన్ఫిగరేషన్',
    'admin.event_sub':'ఆలోచన సమర్పించబడింది','admin.event_app':'ఆలోచన అనుమతించబడింది','admin.event_impl':'ఆలోచన అమలు చేయబడింది',
    'admin.db_status':'HR డేటాబేస్ సమకాలీకరణ స్థితి',
    'admin.rescore_desc':'ప్రస్తుత స్కోరింగ్ మోడల్ ఉపయోగించి అన్ని ఉన్న ఆలోచనల AI స్కోర్‌లను మళ్ళీ గణించండి.',
    'sa.console':'కమాండ్ సెంటర్','sa.signed_in':'గా సైన్ ఇన్',
    'sa.tab_overview':'అవలోకనం','sa.tab_hierarchy':'సంస్థ నిర్మాణం',
    'sa.tab_users':'వినియోగదారు నిర్వహణ','sa.tab_system':'సిస్టమ్',
    'sa.status_dist':'ఆలోచన స్థితి వితరణ','sa.recent':'ఇటీవలి కార్యకలాపాలు',
    'sa.org_tree':'సంస్థ వృక్షం — అడ్మిన్ → మేనేజర్ → ఉద్యోగి',
    'sa.all_employees':'అందరు ఉద్యోగులు','sa.ai_engine':'AI స్కోరింగ్ ఇంజన్',
    'sa.ai_desc':'అన్ని ఉన్న ఆలోచనల AI నాణ్యత స్కోర్‌లను మళ్ళీ గణించండి.',
    'sa.db_sync':'డేటాబేస్ & HR సమకాలీకరణ',
    'placeholder.search_user':'పేరు, ఇమెయిల్ లేదా ID తో వెతకండి…',
    'pa.overview':'ప్లాట్‌ఫారమ్ అవలోకనం','pa.private':'కేవలం మొత్తం మెట్రిక్‌లు — టెనెంట్ కంటెంట్ ప్రైవేట్',
    'pa.signed_in':'గా సైన్ ఇన్','pa.all_tenants':'అన్ని టెనెంట్లు — మొత్తం గణాంకాలు',
    'pa.tenant_hierarchy':'టెనెంట్ నిర్మాణం',
    'pa.hierarchy_sub':'సంస్థ నిర్మాణం — పేర్లు, పాత్రలు, విభాగాలు మాత్రమే. ఆలోచన కంటెంట్ లేదు.',
    'pa.user_hierarchy':'వినియోగదారు నిర్మాణం',
    'msg.loading':'లోడ్ అవుతోంది…','msg.no_ideas':'ఆలోచనలు కనుగొనబడలేదు. మీ మొదటి ఆలోచనను సమర్పించండి!',
    'msg.no_review':'సమీక్షకు పెండింగ్‌లో ఆలోచనలు లేవు.','msg.no_audit':'ఆడిట్ రికార్డులు లేవు.',
    'msg.no_leaderboard':'ఇంకా స్కోర్‌లు లేవు. ర్యాంకింగ్‌లు చూడటానికి ఆలోచనలు సమర్పించండి.',
    'msg.no_notif':'నోటిఫికేషన్లు లేవు','msg.draft_prefix':'డ్రాఫ్ట్ సేవ్ చేయబడింది! ఆలోచన కోడ్: ',
    'msg.fill_situation':'శీర్షిక మరియు పరిస్థితి వివరణ నింపండి (కనీసం 20 అక్షరాలు).',
    'msg.fill_solution':'ప్రతిపాదిత పరిష్కారాన్ని నింపండి.',
    'msg.server_error':'సర్వర్ లోపం. దయచేసి మళ్ళీ ప్రయత్నించండి.',
    'msg.fail_dashboard':'డాష్‌బోర్డ్ లోడ్ కాలేదు. సర్వర్ నడుస్తుందా?',
    'msg.fail_ideas':'ఆలోచనలు లోడ్ కాలేదు.','msg.fail_queue':'సమీక్ష క్యూ లోడ్ కాలేదు.',
    'msg.fail_audit':'లోడ్ కాలేదు.','msg.fail_leaderboard':'లీడర్‌బోర్డ్ లోడ్ కాలేదు.',
    'msg.fail_analytics':'విశ్లేషణ లోడ్ కాలేదు.',
    'msg.audit_restricted':'ఆడిట్ ట్రెయిల్ కేవలం మేనేజర్లు, అడ్మిన్లు మరియు ఎగ్జిక్యూటివ్‌లకు మాత్రమే అందుబాటులో ఉంటుంది.',
    'msg.analytics_restricted':'విశ్లేషణ కేవలం మేనేజర్లు, అడ్మిన్లు మరియు ఎగ్జిక్యూటివ్‌లకు మాత్రమే అందుబాటులో ఉంటుంది.',
    'msg.decision_ok':'నిర్ణయం సమర్పించబడింది','msg.idea_ok':'ఆలోచన విజయవంతంగా సమర్పించబడింది',
    'pa.active_tenants':'యాక్టివ్ టెనెంట్‌లు','pa.total_users':'మొత్తం వినియోగదారులు','pa.ideas_submitted':'సమర్పించిన ఆలోచనలు','msg.rescoring':'మళ్ళీ స్కోరింగ్…',
    'analytics.approval_rate':'అనుమతి రేటు','analytics.impl_rate':'అమలు రేటు','analytics.avg_score':'సగటు AI నాణ్యత స్కోరు',
    'page.leaderboard_title':'లీడర్‌బోర్డ్ & గేమిఫికేషన్','page.analytics_title':'విశ్లేషణ డాష్‌బోర్డ్',
    'analytics.impact_dist':'ప్రభావ ప్రాంత పంపిణీ','analytics.status_summary':'స్థితి సారాంశం',
    'analytics.monthly_trend':'నెలవారీ సమర్పణ ధోరణి','analytics.score_dist':'AI నాణ్యత స్కోరు పంపిణీ',
    'analytics.high':'అధికం (75+)','analytics.med':'మధ్యమం (50-74)','analytics.low_score':'తక్కువ (<50)',
    'analytics.avg_note':'మొత్తం సగటు AI స్కోరు:',
    'detail.not_scored':'స్కోరు చేయబడలేదు','detail.no_ai':'AI మూల్యాంకనం అందుబాటులో లేదు.',
    'community.title':'కమ్యూనిటీ ఓటు & స్కోరు','community.upvotes':'అప్‌వోట్లు',
    'community.downvotes':'డౌన్‌వోట్లు','community.net':'నికర స్కోరు','community.score':'కమ్యూనిటీ స్కోరు',
    'community.your_votes':'మీ ఆలోచనపై కమ్యూనిటీ ఓట్లు:','community.vote_on':'ఈ ఆలోచనకు ఓటు వేయండి:',
    'community.vote_hint':'▲ లేదా ▼ క్లిక్ చేయండి · ఓటు తీసివేయడానికి మళ్ళీ క్లిక్ చేయండి',
    'review.own_idea':'మీ సొంత ఆలోచన','review.vote_needed':'మీ ఓటు అవసరం',
    'review.committee_badge':'కమిటీ','review.route_committee':'కమిటీకి రూట్ చేయండి',
    'review.submit_mine':'నా సమీక్షను సమర్పించండి','review.decide':'సమీక్ష / నిర్ణయం',
    'review.cannot_own':'మీరు మీ స్వంత ఆలోచనను సమీక్షించలేరు',
    'preview.title_label':'శీర్షిక','preview.situation':'పరిస్థితి','preview.solution':'పరిష్కారం',
    'preview.impact_areas':'ప్రభావ ప్రాంతాలు','preview.impact_level':'ప్రభావ స్థాయి',
    'preview.co_suggesters':'సహ-సూచికులు','preview.none_selected':'ఏదీ ఎంచుకోలేదు',
    'platform.users':'వినియోగదారులు','platform.ideas':'ఆలోచనలు','platform.implemented':'అమలు చేయబడినవి',
    'platform.last_activity':'చివరి కార్యకలాపం','platform.active':'యాక్టివ్',
    'platform.db_error':'DB అందుబాటులో లేదు','platform.view_org':'సంస్థ చూడండి',
    'platform.admins':'అడ్మిన్లు','platform.executives':'ఎగ్జిక్యూటివ్‌లు',
    'platform.managers':'మేనేజర్లు','platform.employees':'ఉద్యోగులు','platform.reports_to':'రిపోర్ట్ చేస్తారు:',
    'sa.subtitle':'అన్ని స్థాయిలలో సంపూర్ణ సంస్థాగత నియంత్రణ','sa.last_refreshed':'చివరి రిఫ్రెష్:','sa.executives':'ఎగ్జిక్యూటివ్‌లు',
    'admin.points_info':'పాయింట్ కాన్ఫిగ్ (config.php లో నిల్వ చేయబడింది). మార్పులు వర్తింపజేయడానికి పునఃప్రారంభించండి.',
    'admin.hr_info':'ఉద్యోగి డేటా users పట్టిక నుండి లోడ్ చేయబడింది.',
    'admin.rescore_info':'అన్ని ఆలోచనల AI స్కోర్‌లను మళ్ళీ లెక్కించండి.',
    'notif.header':'నోటిఫికేషన్లు','login.feat2_sub':'+10 సమర్పణ · +25 అనుమతి · +65 అమలు','idea.impact_suffix':'ప్రభావం',
    'time.just_now':'ఇప్పుడే','time.min_ago':' నిమిషాల క్రితం','time.hr_ago':'గం క్రితం','time.day_ago':'రో క్రితం',
    'ar.title':'కమిటీకి రూట్ చేయండి','ar.search_add':'సమీక్షకులను వెతకండి & జోడించండి',
    'ar.no_reviewers':'ఇంకా సమీక్షకులు జోడించలేదు.','ar.threshold':'అనుమతి సీమ',
    'ar.unanimous':'అందరు సమీక్షకులు అనుమతించాలి (ఏకగ్రీవంగా)',
    'ar.supermajority':'సూపర్‌మేజారిటీ — కనీసం 2/3 అనుమతించాలి',
    'ar.simple_majority':'సాధారణ మెజారిటీ — సగానికంటే ఎక్కువ అనుమతించాలి',
    'ar.info':'ఆలోచన సమీక్షలోకి వెళ్ళుతుంది. నియుక్త సమీక్షకులందరికీ వెంటనే తెలియజేయబడుతుంది.',
    'rd.title':'నా సమీక్ష','rd.decision':'మీ నిర్ణయం',
    'rd.approve_btn':'✓ అనుమతించండి','rd.reject_btn':'✗ తిరస్కరించండి',
    'rd.feedback':'అభిప్రాయం / వ్యాఖ్య','rd.feedback_ph':'సమర్పించినవారితో మీ తర్కాన్ని పంచుకోండి…',
    'init.hr_banner':'HR డేటాబేస్ నుండి స్వయంచాలకంగా తీసుకోబడింది:','init.reporting_to':'రిపోర్టింగ్ మేనేజర్:',
    'attach.prefix':'జతపర్చబడింది: ',
    'committee.approved_count':'అనుమతించబడింది','committee.rejected_count':'తిరస్కరించబడింది',
    'committee.pending_count':'పెండింగ్','committee.approval_req':'% అనుమతి అవసరం',
    'review.my_review':'నా సమీక్ష','review.view_details':'వివరాలు చూడండి','review.review_btn':'సమీక్ష',
    'rescore.ok':'{n} ఆలోచనలు విజయవంతంగా రీస్కోర్ చేయబడ్డాయి।',
  },
  ta: {
    'app.name':'ஐடியா டூல்',
    'nav.dashboard':'டாஷ்போர்டு','nav.my_ideas':'என் யோசனைகள்','nav.submit':'யோசனை சமர்ப்பி',
    'nav.review':'மறுஆய்வு வரிசை','nav.all_ideas':'அனைத்து யோசனைகள்','nav.audit':'தணிக்கை',
    'nav.leaderboard':'தலைமை பலகை','nav.analytics':'பகுப்பாய்வு','nav.admin':'நிர்வாகி பலகை',
    'nav.super_admin':'நிறுவன படிநிலை','nav.profile':'என் சுயவிவரம்',
    'section.main':'முக்கிய','section.workflow':'பணிப்பாய்வு','section.insights':'நுண்ணறிவுகள்',
    'section.admin':'நிர்வாகி','section.super_admin':'IFQM சூப்பர் அட்மின்',
    'login.app_title':'ஊழியர் யோசனை கருவி','login.tagline':'சிறந்த யோசனைகளை உண்மையான முன்னேற்றங்களாக மாற்றுங்கள்.',
    'login.welcome':'மீண்டும் வரவேற்கிறோம்','login.subtitle':'தொடர உங்கள் IFQM கணக்கில் உள்நுழைக',
    'login.org_code':'நிறுவன குறியீடு','login.org_hint':'IFQM தளம் நிர்வாகிக்கு காலியாக விடுங்கள்',
    'login.forgot':'கடவுச்சொல் மறந்துவிட்டதா?','login.email':'மின்னஞ்சல் முகவரி','login.password':'கடவுச்சொல்','login.btn':'உள்நுழை',
    'admin.add_user':'+ பயனரை சேர்க்கவும்',
    'topbar.dark':'இருள்','topbar.light':'ஒளி','topbar.notifications':'அறிவிப்புகள்',
    'topbar.logout':'வெளியேறு','topbar.mark_read':'அனைத்தையும் படித்ததாக குறி',
    'dash.total':'மொத்த யோசனைகள்','dash.approved':'அங்கீகரிக்கப்பட்டவை','dash.implemented':'செயல்படுத்தப்பட்டவை',
    'dash.pending_review':'நிலுவையில் உள்ள மதிப்பீடு','dash.overdue_reviews':'காலாவதியான மதிப்பீடுகள்',
    'dash.status_dist':'நிலை விநியோகம்','dash.recent':'சமீபத்திய செயல்பாடு',
    'status.submitted':'சமர்ப்பிக்கப்பட்டது','status.review':'மறுஆய்வில்','status.approved':'அங்கீகரிக்கப்பட்டது',
    'status.rejected':'நிராகரிக்கப்பட்டது','status.implemented':'செயல்படுத்தப்பட்டது','status.draft':'வரைவு',
    'idea.view':'பார்க்க','idea.review':'மறுஆய்வு','idea.votes':'வாக்குகள்',
    'vote.title':'சமூக மதிப்பீடு','vote.your_rating':'உங்கள் மதிப்பீடு','vote.avg':'சராசரி',
    'vote.engagement_idx':'ஈடுபாட்டு குறியீடு','vote.submit':'மதிப்பீடு சமர்ப்பி',
    'form.save_draft':'வரைவு சேமி','form.next':'அடுத்து','form.back':'பின்','form.submit_idea':'புதிய யோசனை சமர்ப்பி',
    'detail.submitted_by':'சமர்ப்பித்தவர்','detail.situation':'தற்போதைய நிலை','detail.solution':'முன்மொழியப்பட்ட தீர்வு',
    'detail.ai_eval':'AI மதிப்பீடு','detail.score':'மதிப்பெண்','detail.close':'மூடு',
    'lb.individual':'தனிப்பட்ட தரவரிசை','lb.dept':'துறை தரவரிசை','lb.top_ideas':'சிறந்த மதிப்பெண் யோசனைகள்',
    'lb.points':'புள்ளிகள்','lb.ideas':'யோசனைகள்','lb.avg_score':'சராசரி மதிப்பெண்','lb.engagement':'ஈடுபாடு',
    'lb.all_time':'எல்லா காலமும்','lb.monthly':'மாதாந்திர','lb.quarterly':'காலாண்டு','lb.yearly':'வருடாந்திர','lb.you':'(நீங்கள்)',
    'profile.title':'ஊழியர் சுயவிவரம்','profile.stats':'என் புள்ளிவிவரங்கள்',
    'profile.dept':'துறை','profile.email_lbl':'மின்னஞ்சல்','profile.phone':'தொலைபேசி',
    'profile.reports_to':'அறிக்கை மேலாளர்','profile.bu':'வணிக பிரிவு','profile.loc':'இடம்',
    'profile.hr_note':'HR தரவுத்தளத்திலிருந்து தானாக பெறப்பட்டது. புதுப்பிக்க நிர்வாகியை தொடர்பு கொள்ளுங்கள்.',
    'profile.total_pts':'மொத்த புள்ளிகள்',
    'btn.new_idea':'புதிய யோசனை','btn.close':'மூடு','btn.cancel':'ரத்து செய்',
    'btn.submit_decision':'முடிவு சமர்ப்பி','btn.view_my_ideas':'என் யோசனைகளை பார்',
    'btn.rescore_all':'அனைத்து யோசனைகளையும் மீண்டும் மதிப்பிடு','btn.back_tenants':'← அனைத்து குத்தகைதாரர்கள்',
    'page.my_ideas_sub':'நீங்கள் சமர்ப்பித்த அனைத்து யோசனைகளையும் கண்காணிக்கவும்',
    'placeholder.search_ideas':'யோசனைகளை தேடு...','filter.all_status':'அனைத்து நிலைகள்',
    'page.submit_sub':'உங்கள் மேம்பாட்டு யோசனையை சமர்ப்பிக்க அனைத்து படிகளையும் நிரப்பவும்',
    'wizard.step1':'நிலைமை','wizard.step2':'தீர்வு','wizard.step3':'தாக்கம்',
    'wizard.step4':'சக-பரிந்துரைப்பாளர்கள்','wizard.step5':'மதிப்பாய்வு & சமர்ப்பணம்',
    'step1.heading':'படி 1: தற்போதைய நிலைமையை விவரிக்கவும்',
    'step1.title_label':'நிலைமை தலைப்பு','step1.title_ph':'உங்கள் யோசனைக்கு சுருக்கமான தலைப்பு',
    'step1.desc_label':'தற்போதைய நிலைமை விவரிப்பு',
    'step1.desc_ph':'தற்போதைய பிரச்சனை அல்லது திறமையின்மையை விரிவாக விவரிக்கவும் (குறைந்தது 50 எழுத்துக்கள்)…',
    'step1.doc_label':'ஆதார ஆவணம் (விருப்பம்)',
    'step1.upload':'பதிவேற்ற கிளிக் செய்யவும் அல்லது இழுத்து விடவும்',
    'step1.upload_types':'PDF, PNG, JPG, XLSX — அதிகபட்சம் 10 MB',
    'step2.heading':'படி 2: முன்மொழியப்பட்ட யோசனை / தீர்வு',
    'step2.label':'முன்மொழியப்பட்ட தீர்வு','step2.ph':'உங்கள் முன்மொழியப்பட்ட மேம்பாட்டை விரிவாக விவரிக்கவும்…',
    'step3.heading':'படி 3: தாக்கப் பகுதிகள் & அளவிடக்கூடிய நன்மைகள்',
    'step3.impact_label':'தாக்கப் பகுதிகளை தேர்ந்தெடுக்கவும்','step3.impact_sub':'(பொருந்தும் அனைத்தையும் தேர்ந்தெடுக்கவும்)',
    'step3.level':'ஒட்டுமொத்த தாக்க நிலை',
    'step3.tangible_label':'உறுதியான நன்மை (விருப்பம்)','step3.tangible_ph':'எ.கா. ₹50,000 சேமிப்பு/ஆண்டு',
    'step3.intangible_label':'அருவமான நன்மைகள் (விருப்பம்)',
    'step3.intangible_ph':'எ.கா. தொழிலாளர் நம்பிக்கையை மேம்படுத்துவது',
    'step4.heading':'படி 4: சக-பரிந்துரைப்பாளர்கள் (விருப்பம், அதிகபட்சம் 2)',
    'step4.co1':'சக-பரிந்துரைப்பாளர் 1','step4.co2':'சக-பரிந்துரைப்பாளர் 2',
    'step4.co_ph':'பெயர் அல்லது ஊழியர் ID மூலம் தேடு…',
    'step5.heading':'படி 5: மதிப்பாய்வு & சமர்ப்பணம்',
    'step5.note':'சமர்ப்பிப்பதன் மூலம் இந்த யோசனை அசல் என்று உறுதிப்படுத்துகிறீர்கள். சமர்ப்பித்தால் +10 புள்ளிகள் கிடைக்கும்.',
    'impact.production':'உற்பத்தி','impact.quality':'தரம்','impact.cost':'செலவு',
    'impact.delivery':'டெலிவரி','impact.safety':'பாதுகாப்பு','impact.environment':'சுற்றுச்சூழல்',
    'impact.morale':'மன உறுதி','impact.low':'குறைவு','impact.medium':'நடுத்தரம்','impact.high':'அதிகம்',
    'page.review_sub':'உங்கள் மதிப்பாய்வுக்காக நிலுவையில் உள்ள யோசனைகள் — AI தர மதிப்பெண் வரிசையில்',
    'placeholder.search':'தேடு…','filter.all_impact':'அனைத்து தாக்கங்கள்',
    'table.idea_id':'யோசனை ID','table.title':'தலைப்பு','table.submitted_by':'சமர்ப்பித்தவர்',
    'table.dept':'துறை','table.impact':'தாக்கம்','table.ai_score':'AI மதிப்பெண்',
    'table.engagement':'ஈடுபாடு','table.status':'நிலை','table.date':'தேதி','table.action':'செயல்',
    'table.timestamp':'நேரம்','table.actor':'நடிகர்','table.comment_col':'கருத்து',
    'table.event':'நிகழ்வு','table.pts_awarded':'புள்ளிகள் வழங்கப்பட்டது',
    'table.employee':'ஊழியர்','table.emp_id':'ID','table.role':'பதவி',
    'table.bu':'வணிக பிரிவு','table.email_col':'மின்னஞ்சல்',
    'table.reports_to':'அறிக்கை செய்கிறார்','table.points_col':'புள்ளிகள்','table.ideas_col':'யோசனைகள்',
    'page.audit_title':'கணினி தணிக்கை பாதை',
    'page.audit_sub':'அனைத்து பணிப்பாய்வு செயல்களின் மாற்ற முடியாத பதிவு',
    'audit.proof':'இந்த பதிவு சேர்க்கை மட்டுமே மற்றும் சேதப்படுத்த முடியாதது. எந்த பதிவையும் திருத்தவோ நீக்கவோ முடியாது.',
    'modal.details':'விவரங்கள்','modal.timeline':'காலவரிசை','modal.attachments':'இணைப்புகள்',
    'review.decision':'முடிவு','review.comment_label':'கருத்து / பின்னூட்டம்',
    'review.comment_ph':'சமர்ப்பிப்பாளருக்கு விருப்பமான கருத்துகள்…',
    'review.to_review':'மதிப்பாய்வில் நகர்த்து','review.approve':'அங்கீகரிக்கவும்',
    'review.reject':'நிராகரிக்கவும்','review.implement':'செயல்படுத்தப்பட்டதாக குறி',
    'bulk.approve_all':'அனைத்தையும் ஏற்கவும்','bulk.reject_all':'அனைத்தையும் நிராகரிக்கவும்','bulk.clear':'அழி',
    'admin.tab_overview':'கண்ணோட்டம்','admin.tab_ideas':'யோசனை மேலாண்மை',
    'admin.tab_users':'பயனர் பட்டியல்','admin.tab_system':'கணினி',
    'admin.points_config':'புள்ளிகள் கட்டமைப்பு',
    'admin.event_sub':'யோசனை சமர்ப்பிக்கப்பட்டது','admin.event_app':'யோசனை அங்கீகரிக்கப்பட்டது','admin.event_impl':'யோசனை செயல்படுத்தப்பட்டது',
    'admin.db_status':'HR தரவுத்தள ஒத்திசைவு நிலை',
    'admin.rescore_desc':'தற்போதைய மதிப்பீட்டு மாதிரியைப் பயன்படுத்தி அனைத்து யோசனைகளின் AI மதிப்பெண்களை மீண்டும் கணக்கிடவும்.',
    'sa.console':'கட்டளை மையம்','sa.signed_in':'என உள்நுழைந்தீர்கள்',
    'sa.tab_overview':'கண்ணோட்டம்','sa.tab_hierarchy':'நிறுவன படிநிலை',
    'sa.tab_users':'பயனர் மேலாண்மை','sa.tab_system':'கணினி',
    'sa.status_dist':'யோசனை நிலை விநியோகம்','sa.recent':'சமீபத்திய செயல்பாடு',
    'sa.org_tree':'நிறுவன மரம் — நிர்வாகி → மேலாளர் → ஊழியர்',
    'sa.all_employees':'அனைத்து ஊழியர்கள்','sa.ai_engine':'AI மதிப்பீட்டு இயந்திரம்',
    'sa.ai_desc':'அனைத்து யோசனைகளின் AI தர மதிப்பெண்களை மீண்டும் கணக்கிடவும்.',
    'sa.db_sync':'தரவுத்தளம் & HR ஒத்திசைவு',
    'placeholder.search_user':'பெயர், மின்னஞ்சல் அல்லது ID மூலம் தேடு…',
    'pa.overview':'தளம் கண்ணோட்டம்','pa.private':'மொத்த அளவீடுகள் மட்டும் — குத்தகை உள்ளடக்கம் தனியார்',
    'pa.signed_in':'என உள்நுழைந்தீர்கள்','pa.all_tenants':'அனைத்து குத்தகைதாரர்கள் — மொத்த புள்ளிவிவரங்கள்',
    'pa.tenant_hierarchy':'குத்தகைதாரர் படிநிலை',
    'pa.hierarchy_sub':'நிறுவன அமைப்பு — பெயர்கள், பதவிகள், துறைகள் மட்டும். யோசனை உள்ளடக்கம் இல்லை.',
    'pa.user_hierarchy':'பயனர் படிநிலை',
    'msg.loading':'ஏற்றுகிறது…','msg.no_ideas':'யோசனைகள் கிடைக்கவில்லை. உங்கள் முதல் யோசனையை சமர்ப்பிக்கவும்!',
    'msg.no_review':'மதிப்பாய்வுக்கு நிலுவையில் யோசனைகள் இல்லை.','msg.no_audit':'தணிக்கை பதிவுகள் இல்லை.',
    'msg.no_leaderboard':'இன்னும் மதிப்பெண்கள் இல்லை. தரவரிசைகளை காண யோசனைகளை சமர்ப்பிக்கவும்.',
    'msg.no_notif':'அறிவிப்புகள் இல்லை','msg.draft_prefix':'வரைவு சேமிக்கப்பட்டது! யோசனை குறியீடு: ',
    'msg.fill_situation':'தலைப்பு மற்றும் நிலைமை விவரிப்பை நிரப்பவும் (குறைந்தது 20 எழுத்துக்கள்).',
    'msg.fill_solution':'முன்மொழியப்பட்ட தீர்வை நிரப்பவும்.',
    'msg.server_error':'சேவையக பிழை. மீண்டும் முயற்சிக்கவும்.',
    'msg.fail_dashboard':'டாஷ்போர்டு ஏற்றப்படவில்லை. சேவையகம் இயங்குகிறதா?',
    'msg.fail_ideas':'யோசனைகள் ஏற்றப்படவில்லை.','msg.fail_queue':'மதிப்பாய்வு வரிசை ஏற்றப்படவில்லை.',
    'msg.fail_audit':'ஏற்றப்படவில்லை.','msg.fail_leaderboard':'தலைமை பலகை ஏற்றப்படவில்லை.',
    'msg.fail_analytics':'பகுப்பாய்வு ஏற்றப்படவில்லை.',
    'msg.audit_restricted':'தணிக்கை பாதை மேலாளர்கள், நிர்வாகிகள் மற்றும் நிர்வாகிகளுக்கு மட்டுமே கிடைக்கும்.',
    'msg.analytics_restricted':'பகுப்பாய்வு மேலாளர்கள், நிர்வாகிகள் மற்றும் நிர்வாகிகளுக்கு மட்டுமே கிடைக்கும்.',
    'msg.decision_ok':'முடிவு சமர்ப்பிக்கப்பட்டது','msg.idea_ok':'யோசனை வெற்றிகரமாக சமர்ப்பிக்கப்பட்டது',
    'pa.active_tenants':'செயலில் உள்ள வாடகையாளர்கள்','pa.total_users':'மொத்த பயனர்கள்','pa.ideas_submitted':'சமர்ப்பிக்கப்பட்ட யோசனைகள்','msg.rescoring':'மீண்டும் மதிப்பெண் இடுகிறது…',
    'analytics.approval_rate':'அங்கீகார விகிதம்','analytics.impl_rate':'செயலாக்க விகிதம்','analytics.avg_score':'சராசரி AI தர மதிப்பெண்',
    'page.leaderboard_title':'தலைமை பலகை & விளையாட்டு','page.analytics_title':'பகுப்பாய்வு டாஷ்போர்டு',
    'analytics.impact_dist':'தாக்கப் பகுதி விநியோகம்','analytics.status_summary':'நிலை சுருக்கம்',
    'analytics.monthly_trend':'மாதாந்திர சமர்ப்பணம் போக்கு','analytics.score_dist':'AI தர மதிப்பெண் விநியோகம்',
    'analytics.high':'அதிகம் (75+)','analytics.med':'நடுத்தரம் (50-74)','analytics.low_score':'குறைவு (<50)',
    'analytics.avg_note':'மொத்த சராசரி AI மதிப்பெண்:',
    'detail.not_scored':'மதிப்பெண் இல்லை','detail.no_ai':'AI மதிப்பீடு கிடைக்கவில்லை.',
    'community.title':'சமூக வாக்கு & மதிப்பெண்','community.upvotes':'அப்வோட்கள்',
    'community.downvotes':'டவுன்வோட்கள்','community.net':'நிகர மதிப்பெண்','community.score':'சமூக மதிப்பெண்',
    'community.your_votes':'உங்கள் யோசனையில் சமூக வாக்குகள்:','community.vote_on':'இந்த யோசனைக்கு வாக்களிக்கவும்:',
    'community.vote_hint':'▲ அல்லது ▼ கிளிக் செய்யுங்கள் · வாக்கை நீக்க மீண்டும் கிளிக் செய்யுங்கள்',
    'review.own_idea':'உங்கள் சொந்த யோசனை','review.vote_needed':'உங்கள் வாக்கு தேவை',
    'review.committee_badge':'குழு','review.route_committee':'குழுவிற்கு அனுப்பு',
    'review.submit_mine':'என் மதிப்பாய்வை சமர்ப்பி','review.decide':'மதிப்பாய்வு / முடிவு',
    'review.cannot_own':'நீங்கள் சொந்த யோசனையை மதிப்பாய்வு செய்ய முடியாது',
    'preview.title_label':'தலைப்பு','preview.situation':'நிலைமை','preview.solution':'தீர்வு',
    'preview.impact_areas':'தாக்கப் பகுதிகள்','preview.impact_level':'தாக்க நிலை',
    'preview.co_suggesters':'சக-பரிந்துரைப்பாளர்கள்','preview.none_selected':'எதுவும் தேர்ந்தெடுக்கவில்லை',
    'platform.users':'பயனர்கள்','platform.ideas':'யோசனைகள்','platform.implemented':'செயல்படுத்தப்பட்டவை',
    'platform.last_activity':'கடைசி செயல்பாடு','platform.active':'செயலில்',
    'platform.db_error':'DB அணுகல் இல்லை','platform.view_org':'நிறுவனம் பார்',
    'platform.admins':'நிர்வாகிகள்','platform.executives':'நிர்வாக அதிகாரிகள்',
    'platform.managers':'மேலாளர்கள்','platform.employees':'ஊழியர்கள்','platform.reports_to':'அறிக்கை செய்கிறார்:',
    'sa.subtitle':'அனைத்து நிலைகளிலும் முழு நிறுவன கட்டுப்பாடு','sa.last_refreshed':'கடைசி புதுப்பிப்பு:','sa.executives':'நிர்வாக அதிகாரிகள்',
    'admin.points_info':'புள்ளி கான்ஃபிக் (config.php இல் சேமிக்கப்பட்டுள்ளது). மாற்றங்களை பயன்படுத்த மறுதொடக்கம் தேவை.',
    'admin.hr_info':'ஊழியர் தரவு users அட்டவணையிலிருந்து ஏற்றப்படுகிறது.',
    'admin.rescore_info':'அனைத்து யோசனைகளின் AI மதிப்பெண்களை மீண்டும் கணக்கிடவும்.',
    'notif.header':'அறிவிப்புகள்','login.feat2_sub':'+10 சமர்ப்பிக்க · +25 அங்கீகரிக்க · +65 செயல்படுத்த','idea.impact_suffix':'தாக்கம்',
    'time.just_now':'இப்போதே','time.min_ago':' நிமிடங்களுக்கு முன்','time.hr_ago':'மணி முன்','time.day_ago':'நாள் முன்',
    'ar.title':'குழுவிற்கு அனுப்பு','ar.search_add':'மதிப்பாளர்களை தேடு & சேர்',
    'ar.no_reviewers':'இன்னும் மதிப்பாளர்கள் சேர்க்கவில்லை.','ar.threshold':'அங்கீகார வரம்பு',
    'ar.unanimous':'அனைத்து மதிப்பாளர்களும் அங்கீகரிக்க வேண்டும் (ஒருமித்த)',
    'ar.supermajority':'சூப்பர்மேஜாரிட்டி — குறைந்தது 2/3 அங்கீகரிக்க வேண்டும்',
    'ar.simple_majority':'எளிய பெரும்பான்மை — பாதியிலும் அதிகம் அங்கீகரிக்க வேண்டும்',
    'ar.info':'யோசனை மதிப்பாய்வுக்கு செல்லும். நியமிக்கப்பட்ட மதிப்பாளர்களுக்கு உடனடியாக தெரிவிக்கப்படும்.',
    'rd.title':'என் மதிப்பாய்வு','rd.decision':'உங்கள் முடிவு',
    'rd.approve_btn':'✓ அங்கீகரிக்கவும்','rd.reject_btn':'✗ நிராகரிக்கவும்',
    'rd.feedback':'கருத்து / பின்னூட்டம்','rd.feedback_ph':'சமர்ப்பிப்பாளருடன் உங்கள் காரணத்தை பகிர்ந்துகொள்ளுங்கள்…',
    'init.hr_banner':'HR தரவுத்தளத்திலிருந்து தானாக பெறப்பட்டது:','init.reporting_to':'அறிக்கை மேலாளர்:',
    'attach.prefix':'இணைக்கப்பட்டது: ',
    'committee.approved_count':'அங்கீகரிக்கப்பட்டது','committee.rejected_count':'நிராகரிக்கப்பட்டது',
    'committee.pending_count':'நிலுவை','committee.approval_req':'% அங்கீகாரம் தேவை',
    'review.my_review':'என் மதிப்பீடு','review.view_details':'விவரங்கள் காண்க','review.review_btn':'மதிப்பீடு',
    'rescore.ok':'{n} யோசனைகள் வெற்றிகரமாக மறுமதிப்பீடு செய்யப்பட்டன।',
  },
  ml: {
    'app.name':'ഐഡിയ ടൂൾ',
    'nav.dashboard':'ഡാഷ്‌ബോർഡ്','nav.my_ideas':'എന്റെ ആശയങ്ങൾ','nav.submit':'ആശയം സമർപ്പിക്കുക',
    'nav.review':'അവലോകന ക്യൂ','nav.all_ideas':'എല്ലാ ആശയങ്ങൾ','nav.audit':'ഓഡിറ്റ്',
    'nav.leaderboard':'ലീഡർബോർഡ്','nav.analytics':'വിശകലനം','nav.admin':'അഡ്മിൻ പാനൽ',
    'nav.super_admin':'സ്ഥാപന ഘടന','nav.profile':'എന്റെ പ്രൊഫൈൽ',
    'section.main':'പ്രധാന','section.workflow':'വർക്ക്ഫ്ലോ','section.insights':'ഉൾക്കാഴ്ചകൾ',
    'section.admin':'അഡ്മിൻ','section.super_admin':'IFQM സൂപ്പർ അഡ്മിൻ',
    'login.app_title':'ജീവനക്കാരുടെ ആശയ ഉപകരണം','login.tagline':'മികച്ച ആശയങ്ങളെ യഥാർത്ഥ മെച്ചപ്പെടുത്തലുകളാക്കി മാറ്റുക.',
    'login.welcome':'തിരിച്ചു സ്വാഗതം','login.subtitle':'തുടരാൻ നിങ്ങളുടെ IFQM അക്കൗണ്ടിൽ സൈൻ ഇൻ ചെയ്യുക',
    'login.org_code':'സ്ഥാപന കോഡ്','login.org_hint':'IFQM പ്ലാറ്റ്‌ഫോം അഡ്‌മിനു വേണ്ടി ശൂന്യമായി വിടുക',
    'login.forgot':'പാസ്‌വേഡ് മറന്നോ?','login.email':'ഇ-മെയിൽ വിലാസം','login.password':'പാസ്‌വേഡ്','login.btn':'സൈൻ ഇൻ',
    'admin.add_user':'+ ഉപയോക്താവിനെ ചേർക്കുക',
    'topbar.dark':'ഡാർക്ക്','topbar.light':'ലൈറ്റ്','topbar.notifications':'അറിയിപ്പുകൾ',
    'topbar.logout':'ലോഗ് ഔട്ട്','topbar.mark_read':'എല്ലാം വായിച്ചതായി അടയാളപ്പെടുത്തുക',
    'dash.total':'മൊത്തം ആശയങ്ങൾ','dash.approved':'അംഗീകരിച്ചവ','dash.implemented':'നടപ്പാക്കിയവ',
    'dash.pending_review':'സമഗ്ര നിരീക്ഷണം','dash.overdue_reviews':'കാലാവധി കഴിഞ്ഞ നിരീക്ഷണങ്ങൾ',
    'dash.status_dist':'സ്ഥിതി വിതരണം','dash.recent':'സമീപകാല പ്രവർത്തനം',
    'status.submitted':'സമർപ്പിച്ചു','status.review':'അവലോകനത്തിൽ','status.approved':'അംഗീകരിച്ചു',
    'status.rejected':'നിരസിച്ചു','status.implemented':'നടപ്പാക്കി','status.draft':'ഡ്രാഫ്റ്റ്',
    'idea.view':'കാണുക','idea.review':'അവലോകനം','idea.votes':'വോട്ടുകൾ',
    'vote.title':'കമ്മ്യൂണിറ്റി റേറ്റിംഗ്','vote.your_rating':'നിങ്ങളുടെ റേറ്റിംഗ്','vote.avg':'ശരാശരി',
    'vote.engagement_idx':'ഇടപഴകൽ സൂചിക','vote.submit':'റേറ്റിംഗ് സമർപ്പിക്കുക',
    'form.save_draft':'ഡ്രാഫ്റ്റ് സേവ് ചെയ്യുക','form.next':'അടുത്തത്','form.back':'തിരിച്ച്','form.submit_idea':'പുതിയ ആശയം സമർപ്പിക്കുക',
    'detail.submitted_by':'സമർപ്പിച്ചത്','detail.situation':'നിലവിലെ സ്ഥിതി','detail.solution':'നിർദ്ദേശിച്ച പരിഹാരം',
    'detail.ai_eval':'AI മൂല്യനിർണ്ണയം','detail.score':'സ്കോർ','detail.close':'അടയ്ക്കുക',
    'lb.individual':'വ്യക്തിഗത റാങ്കിംഗ്','lb.dept':'വകുപ്പ് റാങ്കിംഗ്','lb.top_ideas':'ടോപ്പ് സ്കോർ ആശയങ്ങൾ',
    'lb.points':'പോയിന്റുകൾ','lb.ideas':'ആശയങ്ങൾ','lb.avg_score':'ശരാശരി സ്കോർ','lb.engagement':'ഇടപഴകൽ',
    'lb.all_time':'എല്ലാ കാലവും','lb.monthly':'മാസികം','lb.quarterly':'ത്രൈമാസികം','lb.yearly':'വാർഷികം','lb.you':'(നിങ്ങൾ)',
    'profile.title':'ജീവനക്കാരുടെ പ്രൊഫൈൽ','profile.stats':'എന്റെ സ്ഥിതിവിവരക്കണക്കുകൾ',
    'profile.dept':'വകുപ്പ്','profile.email_lbl':'ഇ-മെയിൽ','profile.phone':'ഫോൺ',
    'profile.reports_to':'റിപ്പോർട്ടിംഗ് മേലധികാരി','profile.bu':'ബിസിനസ് യൂണിറ്റ്','profile.loc':'സ്ഥലം',
    'profile.hr_note':'HR ഡേറ്റാബേസിൽ നിന്ന് സ്വയം ലഭിച്ചത്. അപ്‌ഡേറ്റ് ചെയ്യാൻ അഡ്മിനെ ബന്ധപ്പെടുക.',
    'profile.total_pts':'ആകെ പോയിന്റുകൾ',
    'btn.new_idea':'പുതിയ ആശയം','btn.close':'അടയ്ക്കുക','btn.cancel':'റദ്ദ് ചെയ്യുക',
    'btn.submit_decision':'തീരുമാനം സമർപ്പിക്കുക','btn.view_my_ideas':'എന്റെ ആശയങ്ങൾ കാണുക',
    'btn.rescore_all':'എല്ലാ ആശയങ്ങളും വീണ്ടും സ്കോർ ചെയ്യുക','btn.back_tenants':'← എല്ലാ ടെനന്റുകളും',
    'page.my_ideas_sub':'നിങ്ങൾ സമർപ്പിച്ച എല്ലാ ആശയങ്ങളും ട്രാക്ക് ചെയ്യുക',
    'placeholder.search_ideas':'ആശയങ്ങൾ തിരയുക...','filter.all_status':'എല്ലാ സ്ഥിതികളും',
    'page.submit_sub':'നിങ്ങളുടെ മെച്ചപ്പെടുത്തൽ ആശയം സമർപ്പിക്കാൻ എല്ലാ ഘട്ടങ്ങളും പൂരിപ്പിക്കുക',
    'wizard.step1':'സ്ഥിതി','wizard.step2':'പരിഹാരം','wizard.step3':'ആഘാതം',
    'wizard.step4':'സഹ-നിർദ്ദേശകർ','wizard.step5':'അവലോകനം & സമർപ്പണം',
    'step1.heading':'ഘട്ടം 1: നിലവിലെ സ്ഥിതി വിവരിക്കുക',
    'step1.title_label':'സ്ഥിതി തലക്കെട്ട്','step1.title_ph':'നിങ്ങളുടെ ആശയത്തിന് ഒരു ചുരുക്കം തലക്കെട്ട്',
    'step1.desc_label':'നിലവിലെ സ്ഥിതി വിവരണം',
    'step1.desc_ph':'നിലവിലെ പ്രശ്നം അല്ലെങ്കിൽ അക്ഷമത വിശദമായി വിവരിക്കുക (കുറഞ്ഞത് 50 അക്ഷരങ്ങൾ)…',
    'step1.doc_label':'പിന്തുണ ഡോക്യുമെന്റ് (ഐച്ഛികം)',
    'step1.upload':'അപ്‌ലോഡ് ചെയ്യാൻ ക്ലിക്ക് ചെയ്യുക അല്ലെങ്കിൽ വലിച്ചിടുക',
    'step1.upload_types':'PDF, PNG, JPG, XLSX — പരമാവധി 10 MB',
    'step2.heading':'ഘട്ടം 2: നിർദ്ദേശിച്ച ആശയം / പരിഹാരം',
    'step2.label':'നിർദ്ദേശിച്ച പരിഹാരം','step2.ph':'നിങ്ങളുടെ നിർദ്ദേശിച്ച മെച്ചപ്പെടുത്തൽ വിശദമായി വിവരിക്കുക…',
    'step3.heading':'ഘട്ടം 3: ആഘാത മേഖലകളും അളക്കാവുന്ന നേട്ടങ്ങളും',
    'step3.impact_label':'ആഘാത മേഖലകൾ തിരഞ്ഞെടുക്കുക','step3.impact_sub':'(ബാധകമായ എല്ലാം തിരഞ്ഞെടുക്കുക)',
    'step3.level':'മൊത്തത്തിലുള്ള ആഘാത നില',
    'step3.tangible_label':'ഖരമായ നേട്ടം (ഐച്ഛികം)','step3.tangible_ph':'ഉദാ. ₹50,000 ലാഭം/വർഷം',
    'step3.intangible_label':'അഖര നേട്ടങ്ങൾ (ഐച്ഛികം)',
    'step3.intangible_ph':'ഉദാ. തൊഴിലാളികളുടെ ആത്മവിശ്വാസം മെച്ചപ്പെടൽ',
    'step4.heading':'ഘട്ടം 4: സഹ-നിർദ്ദേശകർ (ഐച്ഛികം, പരമാവധി 2)',
    'step4.co1':'സഹ-നിർദ്ദേശകൻ 1','step4.co2':'സഹ-നിർദ്ദേശകൻ 2',
    'step4.co_ph':'പേര് അല്ലെങ്കിൽ ജീവനക്കാരൻ ID കൊണ്ട് തിരയുക…',
    'step5.heading':'ഘട്ടം 5: അവലോകനം & സമർപ്പണം',
    'step5.note':'സമർപ്പിക്കുന്നതിലൂടെ ഈ ആശയം യഥാർത്ഥം ആണെന്ന് നിങ്ങൾ ഉറപ്പിക്കുന്നു. സമർപ്പിച്ചാൽ +10 പോയിന്റുകൾ ലഭിക്കും.',
    'impact.production':'ഉൽപ്പാദനം','impact.quality':'ഗുണനിലവാരം','impact.cost':'ചെലവ്',
    'impact.delivery':'ഡെലിവറി','impact.safety':'സുരക്ഷ','impact.environment':'പരിസ്ഥിതി',
    'impact.morale':'മനോബലം','impact.low':'കുറഞ്ഞ','impact.medium':'ഇടത്തരം','impact.high':'ഉയർന്ന',
    'page.review_sub':'നിങ്ങളുടെ അവലോകനത്തിനായി തീർച്ചകൂടാത്ത ആശയങ്ങൾ — AI ഗുണ സ്കോർ അടിസ്ഥാനത്തിൽ',
    'placeholder.search':'തിരയുക…','filter.all_impact':'എല്ലാ ആഘാതങ്ങളും',
    'table.idea_id':'ആശയ ID','table.title':'തലക്കെട്ട്','table.submitted_by':'സമർപ്പിച്ചത്',
    'table.dept':'വകുപ്പ്','table.impact':'ആഘാതം','table.ai_score':'AI സ്കോർ',
    'table.engagement':'ഇടപഴകൽ','table.status':'സ്ഥിതി','table.date':'തീയതി','table.action':'നടപടി',
    'table.timestamp':'സമയം','table.actor':'അഭിനേതാവ്','table.comment_col':'അഭിപ്രായം',
    'table.event':'സംഭവം','table.pts_awarded':'പോയിന്റുകൾ നൽകി',
    'table.employee':'ജീവനക്കാരൻ','table.emp_id':'ID','table.role':'പദവി',
    'table.bu':'ബിസിനസ് യൂണിറ്റ്','table.email_col':'ഇ-മെയിൽ',
    'table.reports_to':'റിപ്പോർട്ട് ചെയ്യുന്നു','table.points_col':'പോയിന്റുകൾ','table.ideas_col':'ആശയങ്ങൾ',
    'page.audit_title':'സിസ്റ്റം ഓഡിറ്റ് ട്രെയിൽ',
    'page.audit_sub':'എല്ലാ വർക്ക്ഫ്ലോ പ്രവർത്തനങ്ങളുടെ മാറ്റാനാകാത്ത ലോഗ്',
    'audit.proof':'ഈ ലോഗ് ചേർക്കൽ-മാത്രം ആണ്. ഒരു രേഖയും തിരുത്തുകയോ ഇല്ലാതാക്കുകയോ ചെയ്യാൻ കഴിയില്ല.',
    'modal.details':'വിശദാംശങ്ങൾ','modal.timeline':'ടൈംലൈൻ','modal.attachments':'അനുബന്ധങ്ങൾ',
    'review.decision':'തീരുമാനം','review.comment_label':'അഭിപ്രായം / ഫീഡ്‌ബാക്ക്',
    'review.comment_ph':'സമർപ്പിച്ചവർക്ക് ഐച്ഛിക അഭിപ്രായങ്ങൾ…',
    'review.to_review':'അവലോകനത്തിലേക്ക് നീക്കുക','review.approve':'അംഗീകരിക്കുക',
    'review.reject':'നിരസിക്കുക','review.implement':'നടപ്പാക്കിയതായി അടയാളപ്പെടുത്തുക',
    'bulk.approve_all':'എല്ലാം അംഗീകരിക്കുക','bulk.reject_all':'എല്ലാം നിരസിക്കുക','bulk.clear':'മായ്ക്കുക',
    'admin.tab_overview':'അവലോകനം','admin.tab_ideas':'ആശയ മാനേജ്മെന്റ്',
    'admin.tab_users':'ഉപയോക്തൃ പട്ടിക','admin.tab_system':'സിസ്റ്റം',
    'admin.points_config':'പോയിന്റ് കോൺഫിഗറേഷൻ',
    'admin.event_sub':'ആശയം സമർപ്പിച്ചു','admin.event_app':'ആശയം അംഗീകരിച്ചു','admin.event_impl':'ആശയം നടപ്പാക്കി',
    'admin.db_status':'HR ഡേറ്റാബേസ് സമന്വയ സ്ഥിതി',
    'admin.rescore_desc':'നിലവിലെ സ്കോറിംഗ് മോഡൽ ഉപയോഗിച്ച് എല്ലാ ആശയങ്ങളുടെ AI സ്കോർ വീണ്ടും കണക്കാക്കുക.',
    'sa.console':'കമാൻഡ് സെന്റർ','sa.signed_in':'ആയി സൈൻ ഇൻ ചെയ്തു',
    'sa.tab_overview':'അവലോകനം','sa.tab_hierarchy':'സ്ഥാപന ഘടന',
    'sa.tab_users':'ഉപയോക്തൃ മാനേജ്മെന്റ്','sa.tab_system':'സിസ്റ്റം',
    'sa.status_dist':'ആശയ സ്ഥിതി വിതരണം','sa.recent':'സമീപകാല പ്രവർത്തനം',
    'sa.org_tree':'സ്ഥാപന വൃക്ഷം — അഡ്മിൻ → മാനേജർ → ജീവനക്കാരൻ',
    'sa.all_employees':'എല്ലാ ജീവനക്കാരും','sa.ai_engine':'AI സ്കോറിംഗ് എഞ്ചിൻ',
    'sa.ai_desc':'എല്ലാ ആശയങ്ങളുടെ AI ഗുണ സ്കോർ വീണ്ടും കണക്കാക്കുക.',
    'sa.db_sync':'ഡേറ്റാബേസ് & HR സമന്വയം',
    'placeholder.search_user':'പേര്, ഇ-മെയിൽ അല്ലെങ്കിൽ ID കൊണ്ട് തിരയുക…',
    'pa.overview':'പ്ലാറ്റ്ഫോം അവലോകനം','pa.private':'മൊത്ത അളവുകൾ മാത്രം — ടെനന്റ് ഉള്ളടക്കം സ്വകാര്യം',
    'pa.signed_in':'ആയി സൈൻ ഇൻ ചെയ്തു','pa.all_tenants':'എല്ലാ ടെനന്റുകളും — മൊത്ത കണക്കുകൾ',
    'pa.tenant_hierarchy':'ടെനന്റ് ഘടന',
    'pa.hierarchy_sub':'സ്ഥാപന ഘടന — പേരുകൾ, പദവികൾ, വകുപ്പുകൾ മാത്രം. ആശയ ഉള്ളടക്കം ഇല്ല.',
    'pa.user_hierarchy':'ഉപയോക്തൃ ഘടന',
    'msg.loading':'ലോഡ് ചെയ്യുന്നു…','msg.no_ideas':'ആശയങ്ങൾ കണ്ടെത്തിയില്ല. നിങ്ങളുടെ ആദ്യ ആശയം സമർപ്പിക്കുക!',
    'msg.no_review':'അവലോകനത്തിനായി ആശയങ്ങൾ ഇല്ല.','msg.no_audit':'ഓഡിറ്റ് രേഖകൾ ഇല്ല.',
    'msg.no_leaderboard':'ഇതുവരെ സ്കോർ ഇല്ല. റാങ്കിംഗ് കാണാൻ ആശയങ്ങൾ സമർപ്പിക്കുക.',
    'msg.no_notif':'അറിയിപ്പുകൾ ഇല്ല','msg.draft_prefix':'ഡ്രാഫ്റ്റ് സേവ് ചെയ്തു! ആശയ കോഡ്: ',
    'msg.fill_situation':'തലക്കെട്ടും സ്ഥിതി വിവരണവും പൂരിപ്പിക്കുക (കുറഞ്ഞത് 20 അക്ഷരങ്ങൾ).',
    'msg.fill_solution':'നിർദ്ദേശിച്ച പരിഹാരം പൂരിപ്പിക്കുക.',
    'msg.server_error':'സർവർ പിശക്. ദയവായി വീണ്ടും ശ്രമിക്കുക.',
    'msg.fail_dashboard':'ഡാഷ്‌ബോർഡ് ലോഡ് ആയില്ല. സർവർ പ്രവർത്തിക്കുന്നുണ്ടോ?',
    'msg.fail_ideas':'ആശയങ്ങൾ ലോഡ് ആയില്ല.','msg.fail_queue':'അവലോകന ക്യൂ ലോഡ് ആയില്ല.',
    'msg.fail_audit':'ലോഡ് ആയില്ല.','msg.fail_leaderboard':'ലീഡർബോർഡ് ലോഡ് ആയില്ല.',
    'msg.fail_analytics':'വിശകലനം ലോഡ് ആയില്ല.',
    'msg.audit_restricted':'ഓഡിറ്റ് ട്രെയിൽ മാനേജർമാർ, അഡ്മിൻ, എക്സിക്യൂട്ടിവ്‌മാർക്ക് മാത്രം ലഭ്യമാണ്.',
    'msg.analytics_restricted':'വിശകലനം മാനേജർമാർ, അഡ്മിൻ, എക്സിക്യൂട്ടിവ്‌മാർക്ക് മാത്രം ലഭ്യമാണ്.',
    'msg.decision_ok':'തീരുമാനം സമർപ്പിച്ചു','msg.idea_ok':'ആശയം വിജയകരമായി സമർപ്പിച്ചു',
    'pa.active_tenants':'സജീവ ടെനൻ്റുകൾ','pa.total_users':'മൊത്തം ഉപയോക്താക്കൾ','pa.ideas_submitted':'സമർപ്പിച്ച ആശയങ്ങൾ','msg.rescoring':'വീണ്ടും സ്കോർ ചെയ്യുന്നു…',
    'analytics.approval_rate':'അനുമതി നിരക്ക്','analytics.impl_rate':'നടപ്പാക്കൽ നിരക്ക്','analytics.avg_score':'ശരാശരി AI ഗുണ സ്കോർ',
    'page.leaderboard_title':'ലീഡർബോർഡ് & ഗേമിഫിക്കേഷൻ','page.analytics_title':'വിശകലന ഡാഷ്‌ബോർഡ്',
    'analytics.impact_dist':'ആഘാത മേഖല വിതരണം','analytics.status_summary':'സ്ഥിതി സംഗ്രഹം',
    'analytics.monthly_trend':'മാസിക സമർപ്പണ ട്രെൻഡ്','analytics.score_dist':'AI ഗുണ സ്കോർ വിതരണം',
    'analytics.high':'ഉയർന്ന (75+)','analytics.med':'ഇടത്തരം (50-74)','analytics.low_score':'കുറഞ്ഞ (<50)',
    'analytics.avg_note':'മൊത്തത്തിലുള്ള ശരാശരി AI സ്കോർ:',
    'detail.not_scored':'സ്കോർ ചെയ്തിട്ടില്ല','detail.no_ai':'AI മൂല്യനിർണ്ണയം ലഭ്യമല്ല.',
    'community.title':'കമ്മ്യൂണിറ്റി വോട്ടും സ്കോറും','community.upvotes':'അപ്‌വോട്ടുകൾ',
    'community.downvotes':'ഡൗൺവോട്ടുകൾ','community.net':'നെറ്റ് സ്കോർ','community.score':'കമ്മ്യൂണിറ്റി സ്കോർ',
    'community.your_votes':'നിങ്ങളുടെ ആശയത്തിലെ കമ്മ്യൂണിറ്റി വോട്ടുകൾ:','community.vote_on':'ഈ ആശയത്തിന് വോട്ട് ചെയ്യുക:',
    'community.vote_hint':'▲ അല്ലെങ്കിൽ ▼ ക്ലിക്ക് ചെയ്യുക · വോട്ട് നീക്കാൻ വീണ്ടും ക്ലിക്ക് ചെയ്യുക',
    'review.own_idea':'നിങ്ങളുടെ സ്വന്തം ആശയം','review.vote_needed':'നിങ്ങളുടെ വോട്ട് ആവശ്യം',
    'review.committee_badge':'കമ്മിറ്റി','review.route_committee':'കമ്മിറ്റിക്ക് അയക്കുക',
    'review.submit_mine':'എന്റെ അവലോകനം സമർപ്പിക്കുക','review.decide':'അവലോകനം / തീരുമാനം',
    'review.cannot_own':'നിങ്ങൾക്ക് സ്വന്തം ആശയം അവലോകനം ചെയ്യാൻ കഴിയില്ല',
    'preview.title_label':'തലക്കെട്ട്','preview.situation':'സ്ഥിതി','preview.solution':'പരിഹാരം',
    'preview.impact_areas':'ആഘാത മേഖലകൾ','preview.impact_level':'ആഘാത നില',
    'preview.co_suggesters':'സഹ-നിർദ്ദേശകർ','preview.none_selected':'ഒന്നും തിരഞ്ഞെടുത്തിട്ടില്ല',
    'platform.users':'ഉപയോക്താക്കൾ','platform.ideas':'ആശയങ്ങൾ','platform.implemented':'നടപ്പാക്കിയവ',
    'platform.last_activity':'അവസാന പ്രവർത്തനം','platform.active':'സജീവം',
    'platform.db_error':'DB ലഭ്യമല്ല','platform.view_org':'സ്ഥാപനം കാണുക',
    'platform.admins':'അഡ്മിൻമാർ','platform.executives':'എക്സിക്യൂട്ടിവ്‌മാർ',
    'platform.managers':'മാനേജർമാർ','platform.employees':'ജീവനക്കാർ','platform.reports_to':'റിപ്പോർട്ട് ചെയ്യുന്നു:',
    'sa.subtitle':'എല്ലാ തലത്തിലും സമ്പൂർണ്ണ സ്ഥാപന നിയന്ത്രണം','sa.last_refreshed':'അവസാനം പുതുക്കി:','sa.executives':'എക്സിക്യൂട്ടിവ്‌മാർ',
    'admin.points_info':'പോയിന്റ് കോൺഫിഗ് (config.php ൽ സൂക്ഷിച്ചിരിക്കുന്നു). മാറ്റങ്ങൾ ബാധകമാക്കാൻ പുനരാരംഭിക്കുക.',
    'admin.hr_info':'ജീവനക്കാർ ഡേറ്റ users ടേബിളിൽ നിന്ന് ലോഡ് ചെയ്യുന്നു.',
    'admin.rescore_info':'എല്ലാ ആശയങ്ങളുടെ AI സ്കോർ വീണ്ടും കണക്കാക്കുക.',
    'notif.header':'അറിയിപ്പുകൾ','login.feat2_sub':'+10 സമർപ്പിക്കൽ · +25 അംഗീകരിക്കൽ · +65 നടപ്പാക്കൽ','idea.impact_suffix':'ആഘാതം',
    'time.just_now':'ഇപ്പോൾ','time.min_ago':' മിനിറ്റ് മുൻപ്','time.hr_ago':'മണി മുൻപ്','time.day_ago':'ദി മുൻപ്',
    'ar.title':'കമ്മിറ്റിക്ക് അയക്കുക','ar.search_add':'അവലോകനക്കാരെ തിരയൂ & ചേർക്കൂ',
    'ar.no_reviewers':'ഇതുവരെ അവലോകനക്കാരെ ചേർത്തിട്ടില്ല.','ar.threshold':'അനുമതി പരിധി',
    'ar.unanimous':'എല്ലാ അവലോകനക്കാരും അനുമതി നൽകണം (ഏകോപനം)',
    'ar.supermajority':'സൂപ്പർ‌മേജോരിറ്റി — കുറഞ്ഞത് 2/3 അനുമതി നൽകണം',
    'ar.simple_majority':'ലളിത ഭൂരിപക്ഷം — പകുതിയിലേറെ അനുമതി നൽകണം',
    'ar.info':'ആശയം അവലോകനത്തിലേക്ക് പോകും. നിയോഗിക്കപ്പെട്ട അവലോകനക്കാർക്ക് ഉടൻ അറിയിക്കും.',
    'rd.title':'എന്റെ അവലോകനം','rd.decision':'നിങ്ങളുടെ തീരുമാനം',
    'rd.approve_btn':'✓ അംഗീകരിക്കുക','rd.reject_btn':'✗ നിരസിക്കുക',
    'rd.feedback':'ഫീഡ്‌ബാക്ക് / അഭിപ്രായം','rd.feedback_ph':'സമർപ്പിച്ചവരുമായി നിങ്ങളുടെ ചിന്ത പങ്കുവെക്കുക…',
    'init.hr_banner':'HR ഡേറ്റാബേസിൽ നിന്ന് സ്വയം ലഭിച്ചത്:','init.reporting_to':'റിപ്പോർട്ടിംഗ് മേലധികാരി:',
    'attach.prefix':'അനുബന്ധം: ',
    'committee.approved_count':'അംഗീകരിച്ചു','committee.rejected_count':'നിരസിച്ചു',
    'committee.pending_count':'തീർപ്പുകൂടാത്ത','committee.approval_req':'% അനുമതി ആവശ്യം',
    'review.my_review':'എന്റെ അവലോകനം','review.view_details':'വിശദാംശങ്ങൾ കാണുക','review.review_btn':'അവലോകനം',
    'rescore.ok':'{n} ആശയങ്ങൾ വിജയകരമായി റീസ്കോർ ചെയ്തു।',
  }
};

function resetApprovalWorkflow() {
  if (!confirm('Reset approval workflow to platform defaults?')) return;
  document.querySelector('[name=approval_mode][value=default]').checked = true;
  document.getElementById('custom-hierarchy-panel').style.display = 'none';
  const defaults = ['team_lead','project_lead','manager','senior_manager'];
  const finals   = ['executive','admin','super_admin'];
  defaults.forEach(r => {
    const cb = document.querySelector('[name=approval_reviewer_roles][value="'+r+'"]');
    if (cb) cb.checked = true;
  });
  finals.forEach(r => {
    const cb = document.querySelector('[name=approval_final_approver_roles][value="'+r+'"]');
    if (cb) cb.checked = true;
  });
  const t = document.querySelector('[name=approval_threshold]');
  if (t) t.value = '100';
}

// ═══════════════════════════════════════════════════════════════
// MULTILINGUAL SYSTEM — EN / HI / KN / TE / TA / ML
// ═══════════════════════════════════════════════════════════════
function t(key) {
  return (TRANSLATIONS[lang] && TRANSLATIONS[lang][key]) || TRANSLATIONS.en[key] || key;
}
const LANG_NAMES = {en:'English',hi:'हिन्दी',mr:'मराठी',kn:'ಕನ್ನಡ',te:'తెలుగు',ta:'தமிழ்',ml:'മലയാളം'};

function applyTranslations() {
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const k = el.dataset.i18n;
    const v = t(k);
    if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') el.value = v;
    else el.textContent = v;
  });
  document.querySelectorAll('[data-i18n-ph]').forEach(el => { el.placeholder = t(el.dataset.i18nPh); });
  document.querySelectorAll('[data-i18n-html]').forEach(el => { el.innerHTML = t(el.dataset.i18nHtml); });
  document.getElementById('lang-btn').textContent = LANG_LABELS[lang] || lang.toUpperCase();
  document.getElementById('dm-label').textContent = document.getElementById('dm-track').classList.contains('on') ? t('topbar.light') : t('topbar.dark');
  document.querySelectorAll('.lang-opt').forEach(o => o.classList.toggle('active', o.dataset.lang === lang));
}

function buildLangMenu() {
  const menu = document.getElementById('lang-menu');
  menu.innerHTML = SUPPORTED_LANGS.map(l => `
    <div class="lang-opt${l === lang ? ' active' : ''}" data-lang="${l}" onclick="selectLang('${l}')">
      <span class="lang-opt-code">${LANG_LABELS[l]}</span>
      <span>${LANG_NAMES[l]}</span>
    </div>`).join('');
}

function toggleLangMenu(e) {
  e.stopPropagation();
  const wrap = document.getElementById('lang-wrap');
  const isOpen = wrap.classList.contains('open');
  if (!isOpen) {
    buildLangMenu();
    wrap.classList.add('open');
    // position menu below button
    const btn = document.getElementById('lang-btn');
    const r = btn.getBoundingClientRect();
    const menu = document.getElementById('lang-menu');
    menu.style.top = (r.bottom + 6) + 'px';
    menu.style.left = 'auto';
    menu.style.right = (window.innerWidth - r.right) + 'px';
  } else {
    wrap.classList.remove('open');
  }
}

function selectLang(l) {
  lang = l;
  localStorage.setItem('ifqm-lang', lang);
  document.getElementById('lang-wrap').classList.remove('open');
  applyTranslations();
  if (!currentUser) return;
  const reloaders = {
    dashboard:   () => loadDashboard(),
    'my-ideas':  () => loadMyIdeas(),
    review:      () => loadReviewQueue(),
    'ideas-all': () => loadAllIdeas(),
    analytics:   () => loadAnalytics(),
    leaderboard: () => loadLeaderboard(),
    audit:       () => loadAudit(),
    'super-admin': () => loadHierarchy(),
    'platform-dash': () => loadPlatformDashboard(),
    admin:       () => loadAdminUsers(),
    profile:     () => renderProfile(),
    challenges:  () => loadChallenges(),
    board:       () => loadBoard(),
  };
  const fn = reloaders[_activePage];
  if (fn) fn();
}
function getPageTitle(page) {
  const m = {
    dashboard: t('nav.dashboard'), 'my-ideas': t('nav.my_ideas'),
    submit: t('form.submit_idea'), review: t('nav.review'),
    'ideas-all': t('nav.all_ideas'), audit: t('nav.audit'),
    leaderboard: t('nav.leaderboard'), analytics: t('nav.analytics'),
    admin: t('nav.admin'), 'super-admin': t('nav.super_admin'), profile: t('nav.profile'),
  };
  return m[page] || page;
}

// ═══════════════════════════════════════════════════════════════
// ENGAGEMENT INDEX & VOTING
// ═══════════════════════════════════════════════════════════════
function engagementIndex(ai_score, avg_rating, vote_count) {
  const a = (parseFloat(avg_rating) || 0) * 20;
  const v = Math.min(parseInt(vote_count) || 0, 20) / 20 * 100;
  return Math.round(a * 0.4 + v * 0.3 + (parseInt(ai_score) || 0) * 0.3);
}
function engBadge(ai, avg, vc) {
  const idx = engagementIndex(ai, avg, vc);
  if (!idx) return '';
  const col = idx >= 70 ? '#10b981' : idx >= 40 ? '#f59e0b' : '#94a3b8';
  return `<span class="eng-badge" style="background:${col}18;color:${col};border-color:${col}44" title="${t('vote.engagement_idx')}">&#9889; ${idx}</span>`;
}
function engMiniStats(avg_rating, vote_count) {
  if (!parseInt(vote_count)) return '';
  const stars = parseFloat(avg_rating) ? `<span style="color:#f59e0b;font-weight:700;font-size:12px">${avg_rating}&#9733;</span>` : '';
  return `${stars}<span style="font-size:11px;color:var(--subtle)">${vote_count} ${t('idea.votes')}</span>`;
}
function starWidget(ideaId, isSelf, userRating) {
  const stars = [1,2,3,4,5].map(n => {
    const active = n <= (userRating || 0) ? ' active' : '';
    const handlers = isSelf ? '' :
      ` onclick="event.stopPropagation();castVote(${ideaId},${n})" onmouseenter="hoverStar(this)" onmouseleave="unhoverStar(this)"`;
    return `<span class="star${active}" data-n="${n}"${handlers}>&#9733;</span>`;
  }).join('');
  return `<div class="star-rating${isSelf ? ' readonly' : ''}" id="stars-${ideaId}">${stars}</div>`;
}
async function castVote(ideaId, rating) {
  const r = await fetch('api/votes.php?action=vote', {
    method:'POST', headers:{'Content-Type':'application/json', ...csrfHeaders()},
    credentials:'same-origin', body: JSON.stringify({idea_id:ideaId, rating})
  });
  const d = await r.json();
  if (d.success) {
    updateStarWidget(ideaId, d.user_rating);
    const voteInfoEl = document.getElementById('vote-info-' + ideaId);
    if (voteInfoEl) voteInfoEl.innerHTML = engMiniStats(d.avg_rating, d.vote_count);
    const engEl = document.getElementById('eng-' + ideaId);
    if (engEl) engEl.outerHTML = engBadge(engEl.dataset.ai || 0, d.avg_rating, d.vote_count);
    showToast(`${t('vote.rated')} ${rating} ${t('vote.stars')} — thank you!`, 'success');
  } else {
    showToast(d.error || 'Vote failed.', 'danger');
  }
}
function updateStarWidget(ideaId, userRating) {
  const container = document.getElementById('stars-' + ideaId);
  if (!container) return;
  container.querySelectorAll('.star').forEach(s =>
    s.classList.toggle('active', parseInt(s.dataset.n) <= (userRating || 0))
  );
}
function hoverStar(el) {
  const n = parseInt(el.dataset.n);
  el.closest('.star-rating').querySelectorAll('.star').forEach(s =>
    s.classList.toggle('hover', parseInt(s.dataset.n) <= n)
  );
}
function unhoverStar(el) {
  el.closest('.star-rating').querySelectorAll('.star').forEach(s => s.classList.remove('hover'));
}

// ═══════════════════════════════════════════════════════════════
// COMMUNITY UPVOTE / DOWNVOTE
// ═══════════════════════════════════════════════════════════════

// Compute community-adjusted score: ai_score ± (net_votes × 3), capped at ±20, range 0-100
function communityScore(aiScore, upvotes, downvotes) {
  const net = (parseInt(upvotes) || 0) - (parseInt(downvotes) || 0);
  const adj = Math.max(-20, Math.min(20, net * 3));
  return Math.max(0, Math.min(100, (parseInt(aiScore) || 0) + adj));
}

// Render community vote widget (▲ upvote | net | ▼ downvote)
function voteWidget(ideaId, isSelf, upvotes, downvotes, userVote) {
  const up  = parseInt(upvotes)   || 0;
  const dn  = parseInt(downvotes) || 0;
  const net = up - dn;
  const netColor = net > 0 ? '#10b981' : net < 0 ? '#ef4444' : 'var(--text-muted)';
  const netStr   = (net > 0 ? '+' : '') + net;

  if (isSelf) {
    return `<div class="vote-widget" title="Cannot vote on your own idea">
      <span class="vote-btn up vote-disabled">&#9650; ${up}</span>
      <span class="vote-net" style="color:${netColor}">${netStr}</span>
      <span class="vote-btn down vote-disabled">&#9660; ${dn}</span>
    </div>`;
  }

  const upCls = userVote === 'up'   ? ' up-active'   : '';
  const dnCls = userVote === 'down' ? ' down-active' : '';
  return `<div class="vote-widget" id="vw-${ideaId}">
    <span class="vote-btn up${upCls}" id="vbup-${ideaId}"
          onclick="event.stopPropagation();castCommunityVote(${ideaId},'up')"
          title="Upvote this idea">&#9650; <span id="upc-${ideaId}">${up}</span></span>
    <span class="vote-net" id="vnet-${ideaId}" style="color:${netColor}">${netStr}</span>
    <span class="vote-btn down${dnCls}" id="vbdn-${ideaId}"
          onclick="event.stopPropagation();castCommunityVote(${ideaId},'down')"
          title="Downvote this idea">&#9660; <span id="dnc-${ideaId}">${dn}</span></span>
  </div>`;
}

// Cast a community upvote or downvote
async function castCommunityVote(ideaId, type) {
  const btn = document.getElementById(type === 'up' ? 'vbup-' + ideaId : 'vbdn-' + ideaId);
  if (btn) { btn.style.pointerEvents = 'none'; btn.style.opacity = '.6'; }
  try {
    const r = await fetch('api/votes.php?action=' + type + 'vote', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', ...csrfHeaders()},
      credentials: 'same-origin',
      body: JSON.stringify({idea_id: ideaId})
    });
    const d = await r.json();
    if (d.success) {
      updateVoteWidget(ideaId, d.upvotes, d.downvotes, d.user_vote, d.community_score);
      const label = d.user_vote === 'up' ? 'Upvoted!' : (d.user_vote === 'down' ? 'Downvoted!' : 'Vote removed');
      const toastType = d.user_vote === 'up' ? 'success' : (d.user_vote === 'down' ? 'warning' : 'info');
      showToast(label, toastType);
    } else {
      showToast(d.error || 'Vote failed.', 'danger');
    }
  } catch(e) {
    showToast('Vote failed. Check connection.', 'danger');
  } finally {
    if (btn) { btn.style.pointerEvents = ''; btn.style.opacity = ''; }
  }
}

// Update vote widget DOM elements after a vote
function updateVoteWidget(ideaId, upvotes, downvotes, userVote, newScore) {
  const up  = parseInt(upvotes)   || 0;
  const dn  = parseInt(downvotes) || 0;
  const net = up - dn;
  const netColor = net > 0 ? '#10b981' : net < 0 ? '#ef4444' : 'var(--text-muted)';

  const upc  = document.getElementById('upc-'  + ideaId);
  const dnc  = document.getElementById('dnc-'  + ideaId);
  const vnet = document.getElementById('vnet-' + ideaId);
  const vbup = document.getElementById('vbup-' + ideaId);
  const vbdn = document.getElementById('vbdn-' + ideaId);

  if (upc)  { upc.textContent  = up; }
  if (dnc)  { dnc.textContent  = dn; }
  if (vnet) { vnet.textContent = (net > 0 ? '+' : '') + net; vnet.style.color = netColor; }
  if (vbup) {
    vbup.classList.toggle('up-active', userVote === 'up');
    vbup.classList.add('vote-pop');
    vbup.addEventListener('animationend', () => vbup.classList.remove('vote-pop'), {once:true});
  }
  if (vbdn) {
    vbdn.classList.toggle('down-active', userVote === 'down');
    if (userVote === 'down') {
      vbdn.classList.add('vote-pop');
      vbdn.addEventListener('animationend', () => vbdn.classList.remove('vote-pop'), {once:true});
    }
  }

  // Update displayed community score in the All Ideas table
  if (newScore !== undefined) {
    const scoreEl = document.getElementById('cscore-' + ideaId);
    if (scoreEl) {
      scoreEl.textContent = newScore + '/100';
      scoreEl.className   = scoreBadgeClass(newScore);
      scoreEl.title       = 'Community Score (AI + votes)';
    }
  }

  // Update community score in the detail modal if it's open for this idea
  const modalScoreEl = document.getElementById('modal-community-score');
  if (modalScoreEl && pendingIdeaId === ideaId && newScore !== undefined) {
    modalScoreEl.textContent = newScore + '/100';
    modalScoreEl.className   = scoreBadgeClass(newScore);
  }
}

// ── Real-time polling (updates All Ideas table every 10 s) ────
let _votePollTimer = null;
function startVotePoll() {
  stopVotePoll();
  _votePollTimer = setInterval(async () => {
    try {
      const r = await fetch('api/votes.php?action=poll_all', {credentials:'same-origin'});
      const d = await r.json();
      if (!d.success || !d.votes) return;
      Object.entries(d.votes).forEach(([ideaId, v]) => {
        const id  = parseInt(ideaId);
        const up  = v.upvotes   || 0;
        const dn  = v.downvotes || 0;
        const net = up - dn;
        const netColor = net > 0 ? '#10b981' : net < 0 ? '#ef4444' : 'var(--text-muted)';
        const upc  = document.getElementById('upc-'  + id);
        const dnc  = document.getElementById('dnc-'  + id);
        const vnet = document.getElementById('vnet-' + id);
        if (upc)  upc.textContent  = up;
        if (dnc)  dnc.textContent  = dn;
        if (vnet) { vnet.textContent = (net > 0 ? '+' : '') + net; vnet.style.color = netColor; }
        // Update community score badge
        const scoreEl = document.getElementById('cscore-' + id);
        if (scoreEl && v.community_score !== undefined) {
          scoreEl.textContent = v.community_score + '/100';
          scoreEl.className   = scoreBadgeClass(v.community_score);
        }
      });
    } catch(e) { /* silent — non-critical */ }
  }, 10000);
}
function stopVotePoll() {
  if (_votePollTimer) { clearInterval(_votePollTimer); _votePollTimer = null; }
}

// ── COMMITTEE / MULTI-REVIEWER WORKFLOW ────────────────────────────
let pendingAssignIdeaId = null;
let assignedReviewers   = {};

function openAssignReviewersModal(ideaId, ideaCode) {
  pendingAssignIdeaId = ideaId;
  assignedReviewers   = {};
  document.getElementById('ar-idea-code').textContent = '#' + ideaCode;
  document.getElementById('ar-search').value = '';
  document.getElementById('ar-results').style.display = 'none';
  document.getElementById('ar-selected-list').innerHTML = '<div style="font-size:12px;color:var(--subtle);padding:6px 0">No reviewers added yet.</div>';
  document.getElementById('ar-threshold').value = '100';
  const btn = document.getElementById('ar-submit-btn');
  btn.disabled = false; btn.textContent = 'Route to Committee';
  document.getElementById('modal-assign-reviewers').classList.add('open');
}

let _arTimer = null;
function searchReviewersForAssign(q) {
  clearTimeout(_arTimer);
  const res = document.getElementById('ar-results');
  if (q.length < 2) { res.style.display = 'none'; return; }
  _arTimer = setTimeout(async () => {
    const r = await fetch('api/users.php?action=list&q=' + encodeURIComponent(q), {credentials:'same-origin'});
    const d = await r.json();
    if (!d.users?.length) { res.style.display = 'none'; return; }
    res.innerHTML = (d.users||[]).map(u =>
      `<div class="uitem" onclick='addReviewerToAssign(${u.id},${JSON.stringify(u.name)},${JSON.stringify(u.department||"")},${JSON.stringify(u.avatar_initials||u.name[0])},${JSON.stringify(u.role)})'>
        <strong>${escHtml(u.name)}</strong>
        <span style="color:var(--subtle);font-size:11px"> · ${escHtml(u.department||'')} · ${formatRole(u.role)}</span>
      </div>`
    ).join('');
    res.style.display = 'block';
  }, 280);
}

function addReviewerToAssign(id, name, dept, initials, role) {
  if (assignedReviewers[id]) return;
  assignedReviewers[id] = {id, name, dept, initials, role};
  document.getElementById('ar-results').style.display = 'none';
  document.getElementById('ar-search').value = '';
  renderAssignedReviewers();
}

function removeReviewerFromAssign(id) {
  delete assignedReviewers[id];
  renderAssignedReviewers();
}

function renderAssignedReviewers() {
  const el = document.getElementById('ar-selected-list');
  const list = Object.values(assignedReviewers);
  if (!list.length) {
    el.innerHTML = '<div style="font-size:12px;color:var(--subtle);padding:6px 0">No reviewers added yet.</div>';
    return;
  }
  el.innerHTML = `
    <div style="font-size:11px;font-weight:700;color:var(--label);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">${list.length} Reviewer${list.length>1?'s':''} Selected</div>
    ${list.map(u=>`
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
        <div class="avatar" style="width:28px;height:28px;font-size:10px;flex-shrink:0">${escHtml(u.initials)}</div>
        <div style="flex:1">
          <div style="font-size:13px;font-weight:600">${escHtml(u.name)}</div>
          <div style="font-size:11px;color:var(--subtle)">${escHtml(u.dept)} · ${formatRole(u.role)}</div>
        </div>
        <button class="btn btn-outline btn-sm" style="padding:3px 9px;line-height:1" onclick="removeReviewerFromAssign(${u.id})">&#10005;</button>
      </div>`).join('')}`;
}

async function submitAssignReviewers() {
  const reviewerIds = Object.keys(assignedReviewers).map(Number);
  if (!reviewerIds.length) { showToast('Add at least one reviewer.', 'warning'); return; }
  const threshold = parseInt(document.getElementById('ar-threshold').value);
  const btn = document.getElementById('ar-submit-btn');
  btn.disabled = true; btn.textContent = 'Routing…';
  try {
    const r = await fetch('api/ideas.php?action=assign_reviewers', {
      method:'POST', headers:{'Content-Type':'application/json', ...csrfHeaders()},
      credentials:'same-origin',
      body: JSON.stringify({idea_id: pendingAssignIdeaId, reviewer_ids: reviewerIds, threshold})
    });
    const d = await r.json();
    if (d.success) {
      closeModal('modal-assign-reviewers');
      showToast(`Routed to committee of ${d.reviewer_count} reviewer${d.reviewer_count>1?'s':''}. They have been notified.`, 'success');
      loadReviewQueue();
    } else {
      showToast(d.error || 'Failed to assign reviewers.', 'danger');
      btn.disabled = false; btn.textContent = 'Route to Committee';
    }
  } catch(e) {
    showToast('Network error.', 'danger');
    btn.disabled = false; btn.textContent = 'Route to Committee';
  }
}

function openReviewerDecisionModal(ideaId, ideaCode) {
  pendingIdeaId = ideaId;
  document.getElementById('rd-idea-code').textContent = '#' + ideaCode;
  document.getElementById('rd-decision').value = '';
  document.getElementById('rd-comment').value = '';
  document.getElementById('rd-approve-btn').className = 'btn btn-outline';
  document.getElementById('rd-approve-btn').style.flex = '1';
  document.getElementById('rd-approve-btn').style.padding = '10px';
  document.getElementById('rd-approve-btn').style.fontSize = '14px';
  document.getElementById('rd-reject-btn').className = 'btn btn-outline';
  document.getElementById('rd-reject-btn').style.flex = '1';
  document.getElementById('rd-reject-btn').style.padding = '10px';
  document.getElementById('rd-reject-btn').style.fontSize = '14px';
  document.getElementById('rd-submit-btn').disabled = true;
  document.getElementById('modal-reviewer-decision').classList.add('open');
}

function selectReviewerDecision(dec) {
  document.getElementById('rd-decision').value = dec;
  const ab = document.getElementById('rd-approve-btn');
  const rb = document.getElementById('rd-reject-btn');
  ab.className = dec==='approved' ? 'btn btn-success' : 'btn btn-outline';
  rb.className = dec==='rejected' ? 'btn btn-danger'  : 'btn btn-outline';
  ab.style.flex='1'; ab.style.padding='10px'; ab.style.fontSize='14px';
  rb.style.flex='1'; rb.style.padding='10px'; rb.style.fontSize='14px';
  document.getElementById('rd-submit-btn').disabled = false;
}

async function submitReviewerDecision() {
  const decision = document.getElementById('rd-decision').value;
  const comment  = document.getElementById('rd-comment').value;
  if (!decision) { showToast('Please select Approve or Reject.', 'warning'); return; }
  const label = decision === 'approved' ? t('review.approve') : t('review.reject');
  if (!confirm(`Confirm: ${label} this idea? Your decision is final and will be recorded in the audit trail.`)) return;
  const btn = document.getElementById('rd-submit-btn');
  btn.disabled = true; btn.textContent = t('msg.loading');
  try {
    const r = await fetch('api/ideas.php?action=reviewer_decision', {
      method:'POST', headers:{'Content-Type':'application/json', ...csrfHeaders()},
      credentials:'same-origin',
      body: JSON.stringify({idea_id: pendingIdeaId, decision, comment})
    });
    const d = await r.json();
    if (d.success) {
      closeModal('modal-reviewer-decision');
      const statusPart = d.new_status
        ? ` — idea is now <strong>${d.new_status}</strong> (${d.approved}/${d.total} approved)`
        : ` — ${d.pending} reviewer${d.pending!==1?'s':''} still pending`;
      showToast(t('msg.decision_ok') + statusPart, 'success');
      loadReviewQueue();
      loadNotifications();
    } else {
      showToast(d.error || t('msg.fail_queue'), 'danger');
      btn.disabled = false; btn.textContent = t('btn.submit_decision');
    }
  } catch(e) {
    showToast(t('msg.server_error'), 'danger');
    btn.disabled = false; btn.textContent = t('btn.submit_decision');
  }
}

// ═══════════════════════════════════════════════════════════════
// PLATFORM ADMIN — aggregate-only, no tenant idea content
// ═══════════════════════════════════════════════════════════════
let platformTenants = [];

async function loadPlatformDashboard() {
  document.getElementById('pa-tenant-list').innerHTML = '<div class="spinner"></div>';
  document.getElementById('pa-kpi-strip').innerHTML = `
    <div class="kpi-card" style="border-left-color:#1f2937"><div class="spinner"></div></div>
    <div class="kpi-card" style="border-left-color:#10b981"><div class="spinner"></div></div>
    <div class="kpi-card" style="border-left-color:#f59e0b"><div class="spinner"></div></div>`;
  const r = await fetch('api/platform.php?action=tenants');
  const d = await r.json();
  if (!d.success) { document.getElementById('pa-tenant-list').innerHTML = `<div class="alert alert-danger">${d.error}</div>`; return; }
  platformTenants = d.tenants || [];

  const totalUsers = platformTenants.reduce((s,t) => s + (t.user_count||0), 0);
  const totalIdeas = platformTenants.reduce((s,t) => s + (t.idea_count||0), 0);
  const totalImpl  = platformTenants.reduce((s,t) => s + (t.implemented_count||0), 0);

  document.getElementById('pa-kpi-strip').innerHTML = `
    <div class="kpi-card" style="border-left-color:#1f2937">
      <div class="kpi-icon" style="background:#c8ccd1;color:#374151"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg></div>
      <div class="kpi-body"><div class="kpi-val">${platformTenants.length}</div><div class="kpi-label">${t('pa.active_tenants')}</div></div>
    </div>
    <div class="kpi-card" style="border-left-color:#10b981">
      <div class="kpi-icon" style="background:#bbf7d0;color:#10b981"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
      <div class="kpi-body"><div class="kpi-val">${totalUsers}</div><div class="kpi-label">${t('pa.total_users')}</div></div>
    </div>
    <div class="kpi-card" style="border-left-color:#f59e0b">
      <div class="kpi-icon" style="background:#fef3c7;color:#f59e0b"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M9 21h6M12 3a6 6 0 016 6c0 2.2-1.1 3.8-2.5 5L15 16H9l-.5-2C7 12.8 6 11.2 6 9a6 6 0 016-6z"/></svg></div>
      <div class="kpi-body"><div class="kpi-val">${totalIdeas}</div><div class="kpi-label">${t('pa.ideas_submitted')}</div></div>
    </div>`;

  if (platformTenants.length === 0) {
    document.getElementById('pa-tenant-list').innerHTML = `<div class="empty-state">${t('msg.no_ideas')}</div>`;
    return;
  }
  document.getElementById('pa-tenant-list').innerHTML = platformTenants.map(ten => {
    const implPct = ten.idea_count > 0 ? Math.round(ten.implemented_count / ten.idea_count * 100) : 0;
    const lastAct = ten.last_activity ? new Date(ten.last_activity).toLocaleDateString() : 'No activity';
    const statusDot = ten.db_error
      ? `<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;margin-right:6px"></span>${t('platform.db_error')}`
      : `<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;margin-right:6px"></span>${t('platform.active')}`;
    return `<div style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border)">
      <div style="flex:1">
        <div style="font-size:14px;font-weight:700;color:var(--heading)">${escHtml(ten.name)}</div>
        <div style="font-size:12px;color:var(--subtle);margin-top:2px">${escHtml(ten.domain)} &middot; /${escHtml(ten.slug)}</div>
        <div style="font-size:11px;color:var(--label);margin-top:4px;display:flex;align-items:center">${statusDot}</div>
      </div>
      <div style="display:flex;gap:24px;text-align:center">
        <div><div style="font-size:18px;font-weight:800;color:var(--heading)">${ten.user_count||0}</div><div style="font-size:11px;color:var(--subtle)">${t('platform.users')}</div></div>
        <div><div style="font-size:18px;font-weight:800;color:var(--heading)">${ten.idea_count||0}</div><div style="font-size:11px;color:var(--subtle)">${t('platform.ideas')}</div></div>
        <div><div style="font-size:18px;font-weight:800;color:#4b5563">${implPct}%</div><div style="font-size:11px;color:var(--subtle)">${t('platform.implemented')}</div></div>
        <div><div style="font-size:12px;color:var(--subtext);font-weight:500">${lastAct}</div><div style="font-size:11px;color:var(--subtle)">${t('platform.last_activity')}</div></div>
        <div><button class="btn btn-outline btn-sm" onclick="loadTenantHierarchy(${ten.id},'${escHtml(ten.name)}')">${t('platform.view_org')}</button></div>
      </div>
    </div>`;
  }).join('');
}

async function loadTenantHierarchy(tenantId, tenantName) {
  document.getElementById('pt-tenant-name').textContent = tenantName + ' — Org Hierarchy';
  document.getElementById('pt-stats-strip').innerHTML = '';
  document.getElementById('pt-hierarchy-body').innerHTML = '<div class="spinner"></div>';
  navigate('platform-tenants', document.getElementById('nav-platform-tenants'));

  const r = await fetch(`api/platform.php?action=tenant_hierarchy&id=${tenantId}`);
  const d = await r.json();
  if (!d.success) {
    document.getElementById('pt-hierarchy-body').innerHTML = `<div class="alert alert-danger">${d.error}</div>`;
    return;
  }
  const users = d.users || [];
  const byRole = {admin:[],executive:[],manager:[],employee:[]};
  users.forEach(u => { if (byRole[u.role]) byRole[u.role].push(u); });

  document.getElementById('pt-stats-strip').innerHTML = [
    [t('platform.admins'),     byRole.admin.length,     '#374151','#d1d5db'],
    [t('platform.executives'), byRole.executive.length, '#4b5563','#d1d5db'],
    [t('platform.managers'),   byRole.manager.length,   '#f59e0b','#fef3c7'],
    [t('platform.employees'),  byRole.employee.length,  '#10b981','#bbf7d0'],
  ].map(([label,count,color,bg]) => `
    <div class="kpi-card" style="border-left-color:${color}">
      <div class="kpi-body"><div class="kpi-val" style="color:${color}">${count}</div><div class="kpi-label">${label}</div></div>
    </div>`).join('');

  if (users.length === 0) {
    document.getElementById('pt-hierarchy-body').innerHTML = `<div class="empty-state">${t('msg.no_ideas')}</div>`;
    return;
  }

  const roleColors = {admin:'#374151',executive:'#4b5563',manager:'#f59e0b',employee:'#10b981'};
  const roleOrder  = ['admin','executive','manager','employee'];
  let html = '';
  roleOrder.forEach(role => {
    if (!byRole[role].length) return;
    const roleLabel = {admin:t('platform.admins'),executive:t('platform.executives'),manager:t('platform.managers'),employee:t('platform.employees')}[role] || role;
    html += `<div style="margin-bottom:20px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:${roleColors[role]};margin-bottom:10px;padding-bottom:6px;border-bottom:2px solid ${roleColors[role]}22">${roleLabel} (${byRole[role].length})</div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px">
        ${byRole[role].map(u => `
          <div style="display:flex;align-items:center;gap:12px;padding:11px 14px;background:var(--bg);border-radius:var(--r);border:1px solid var(--border)">
            <div style="width:36px;height:36px;border-radius:50%;background:${roleColors[u.role]}22;color:${roleColors[u.role]};display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0">${escHtml(u.avatar_initials||u.name[0])}</div>
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;font-weight:600;color:var(--heading);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escHtml(u.name)}</div>
              <div style="font-size:11px;color:var(--subtle);margin-top:2px">${escHtml(u.department||'—')} ${u.manager_name ? '· '+t('platform.reports_to')+' '+escHtml(u.manager_name) : ''}</div>
              <div style="font-size:11px;color:#f59e0b;margin-top:2px;font-weight:600">${u.idea_count} idea${u.idea_count!=1?'s':''}</div>
            </div>
          </div>`).join('')}
      </div>
    </div>`;
  });
  document.getElementById('pt-hierarchy-body').innerHTML = html;
}

async function doLogout() {
  await fetch('api/auth.php?action=logout', {method:'POST'});
  currentUser = null;
  csrfToken = null;
  // Clear login form fields
  const emailEl = document.getElementById('login-email');
  const passEl  = document.getElementById('login-pass');
  const orgEl   = document.getElementById('login-org');
  if (emailEl) emailEl.value = '';
  if (passEl)  passEl.value  = '';
  if (orgEl)   orgEl.value   = '';
  const app = document.getElementById('main-app');
  app.style.transition = 'opacity .3s ease';
  app.style.opacity = '0';
  setTimeout(() => {
    app.style.display = 'none';
    app.style.opacity = '';
    const lp = document.getElementById('login-page');
    lp.style.display = 'flex';
    lp.style.opacity = '0';
    lp.style.transition = 'opacity .35s ease';
    requestAnimationFrame(() => { lp.style.opacity = '1'; });
    setTimeout(() => { lp.style.transition = ''; lp.style.opacity = ''; }, 400);
  }, 300);
}

function initApp() {
  if (!currentUser) return;
  const u = currentUser;

  // Sync CSRF token from server session
  fetch('api/auth.php?action=me', {credentials:'same-origin'})
    .then(r => r.json())
    .then(d => { if (d.csrf_token) csrfToken = d.csrf_token; })
    .catch(() => {});

  document.getElementById('sb-avatar').textContent  = u.avatar_initials || u.name[0];
  document.getElementById('sb-name').textContent    = u.name;
  document.getElementById('sb-role').textContent    = formatRole(u.role);
  document.getElementById('sb-points').textContent  = u.points + ' pts';
  document.getElementById('top-avatar').textContent = u.avatar_initials || u.name[0];
  document.getElementById('top-name').textContent   = u.name;
  document.getElementById('top-role').textContent   = formatRole(u.role);

  document.getElementById('submit-user-banner').innerHTML =
    `${t('init.hr_banner')} <strong>${u.name}</strong> &middot; ${u.employee_id} &middot; ${u.department || '–'} &middot; ${t('init.reporting_to')} ${u.manager_name || '–'} &middot; ${u.business_unit || '–'}`;

  const isPlatformAdmin = u.role === 'platform_admin';
  const isPriv          = ['team_lead','project_lead','manager','senior_manager','executive','admin','super_admin'].includes(u.role);
  const isAdmin         = u.role === 'admin';
  const isSuperAdmin    = u.role === 'super_admin';

  // For platform admin: hide all tenant-specific nav, show only platform nav
  const tenantNavIds = ['nav-my-ideas','nav-submit','nav-challenges','nav-board',
                        'nav-review','nav-all','nav-analytics','nav-audit',
                        'nav-admin','nav-super-admin',
                        'nav-section-admin','nav-section-super-admin'];
  tenantNavIds.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = isPlatformAdmin ? 'none' : (
      id === 'nav-review' || id === 'nav-analytics' || id === 'nav-audit' ? (isPriv ? '' : 'none') :
      (id === 'nav-admin' || id === 'nav-section-admin') ? (isAdmin ? '' : 'none') :
      (id === 'nav-super-admin' || id === 'nav-section-super-admin') ? (isSuperAdmin ? '' : 'none') :
      (id === 'nav-my-ideas' || id === 'nav-submit') ? (isSuperAdmin ? 'none' : '') : ''
    );
  });

  document.getElementById('nav-section-platform').style.display = isPlatformAdmin ? '' : 'none';
  document.getElementById('nav-platform-dash').style.display    = isPlatformAdmin ? '' : 'none';
  document.getElementById('nav-platform-tenants').style.display = isPlatformAdmin ? '' : 'none';
  document.getElementById('nav-profile').style.display          = isPlatformAdmin ? 'none' : '';

  if (isPlatformAdmin) {
    document.getElementById('pa-name').textContent = u.name;
    document.getElementById('sb-points').style.display = 'none';
  }
  if (isSuperAdmin) {
    document.getElementById('sa-session-user').textContent = u.name;
    document.getElementById('sb-points').style.display = 'none';
  }

  loadNotifications();
  applyTranslations();
  // Reset page state before navigating (prevents stale page from previous session)
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));

  if (isPlatformAdmin) {
    loadPlatformDashboard();
    navigate('platform-dash', document.getElementById('nav-platform-dash'));
  } else if (isSuperAdmin) {
    navigate('super-admin', document.getElementById('nav-super-admin'));
  } else {
    loadDashboard();
    loadMyIdeas();
    navigate('dashboard', document.getElementById('nav-dashboard'));
  }
}

const pageTitles = {
  dashboard:'Dashboard', 'my-ideas':'My Ideas', submit:'Submit New Idea',
  review:'Review Queue', 'ideas-all':'All Ideas', audit:'Audit Trail',
  leaderboard:'Leaderboard & Gamification', analytics:'Analytics Dashboard',
  admin:'Admin Panel', 'super-admin':'Command Center', profile:'My Profile',
  'platform-dash':'Platform Overview', 'platform-tenants':'Tenant Hierarchy',
  challenges:'Innovation Challenges', board:'Idea Board'
};

function navigate(page, navEl) {
  _activePage = page;
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const el = document.getElementById('page-' + page);
  if (el) el.classList.add('active');
  if (navEl) navEl.classList.add('active');
  document.getElementById('page-title').textContent = getPageTitle(page);
  document.getElementById('notif-panel').classList.remove('open');
  document.getElementById('content').scrollTo({ top: 0, behavior: 'smooth' });

  if (page === 'dashboard')   loadDashboard();
  if (page === 'my-ideas')    loadMyIdeas();
  if (page === 'review')      loadReviewQueue();
  if (page === 'ideas-all')   { loadAllIdeas(); startVotePoll(); } else stopVotePoll();
  if (page === 'audit')       loadAudit();
  if (page === 'leaderboard') loadLeaderboard();
  if (page === 'analytics')   loadAnalytics();
  if (page === 'admin')       loadAdminUsers();
  if (page === 'super-admin') loadHierarchy();
  if (page === 'profile')     renderProfile();
  if (page === 'submit')      { resetWizard(); loadChallengesIntoSelect(); }
  if (page === 'challenges')  loadChallenges();
  if (page === 'board')       loadBoard();
}

function toggleDarkMode() {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const next = isDark ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('ifqm-theme', next);
  const track = document.getElementById('dm-track');
  const label = document.getElementById('dm-label');
  if (next === 'dark') { track.classList.add('on'); label.textContent = 'Light'; }
  else { track.classList.remove('on'); label.textContent = 'Dark'; }
  applyTranslations();
}
// Sync toggle button state with saved theme
document.addEventListener('DOMContentLoaded', function() {
  if (localStorage.getItem('ifqm-theme') === 'dark') {
    const t = document.getElementById('dm-track');
    const l = document.getElementById('dm-label');
    if (t) t.classList.add('on');
    if (l) l.textContent = 'Light';
  }
});

// ── Session expiry guard ──────────────────────────────────────────
async function checkSession() {
  if (!currentUser) return;
  try {
    const r = await fetch('api/auth.php?action=me', {credentials:'same-origin'});
    const d = await r.json();
    if (!d.authenticated) {
      currentUser = null;
      csrfToken = null;
      document.getElementById('main-app').style.display = 'none';
      const lp = document.getElementById('login-page');
      lp.style.display = '';
      lp.style.opacity = '1';
      document.getElementById('login-error').textContent = 'Your session has expired. Please sign in again.';
      document.getElementById('login-error').style.display = 'block';
    }
  } catch(e) { /* network error — don't force logout */ }
}
// Check when tab becomes visible after being hidden
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible') checkSession();
});
// Check every 10 minutes while page is open
setInterval(checkSession, 600000);

function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }

function toggleNotif() {
  document.getElementById('notif-panel').classList.toggle('open');
}
document.addEventListener('click', e => {
  if (!e.target.closest('.topbar-right') && !e.target.closest('#notif-panel'))
    document.getElementById('notif-panel').classList.remove('open');
  if (!e.target.closest('#lang-wrap') && !e.target.closest('#lang-menu'))
    document.getElementById('lang-wrap')?.classList.remove('open');
});

async function loadDashboard() {
  let r, d;
  try {
    r = await fetch('api/ideas.php?action=dashboard', {credentials:'same-origin'});
    d = await r.json();
  } catch(e) {
    document.getElementById('dash-kpis').innerHTML = `<div class="alert alert-danger" style="grid-column:1/-1">${t('msg.fail_dashboard')}</div>`;
    return;
  }
  if (!d.success) return;

  const counts = d.counts;
  const total  = Object.values(counts).reduce((a,b)=>a+b,0);
  const isReviewer = ['team_lead','project_lead','manager','senior_manager','executive','admin','super_admin'].includes(currentUser?.role);
  const reviewerKpis = isReviewer && (d.pending_reviews > 0 || d.overdue_reviews > 0) ? `
    <div class="kpi-card" style="border-left-color:#2563eb;cursor:pointer" onclick="navigate('review',document.getElementById('nav-review'))">
      <div class="kpi-icon" style="background:#eff6ff;color:#2563eb"><svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/><polyline points="9 14 11 16 15 12"/></svg></div>
      <div class="kpi-body"><div class="kpi-val" data-target="${d.pending_reviews||0}">0</div><div class="kpi-label" data-i18n="dash.pending_review">Pending Review</div></div>
    </div>
    ${d.overdue_reviews > 0 ? `
    <div class="kpi-card" style="border-left-color:#ef4444;cursor:pointer" onclick="navigate('review',document.getElementById('nav-review'))">
      <div class="kpi-icon" style="background:#fee2e2;color:#ef4444"><svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
      <div class="kpi-body"><div class="kpi-val" data-target="${d.overdue_reviews||0}">0</div><div class="kpi-label" data-i18n="dash.overdue_reviews">Overdue Reviews</div></div>
    </div>` : ''}` : '';
  document.getElementById('dash-kpis').innerHTML = `
    <div class="kpi-card" style="border-left-color:#1f2937">
      <div class="kpi-icon" style="background:#c8ccd1;color:#374151"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M9 21h6M12 3a6 6 0 016 6c0 2.2-1.1 3.8-2.5 5L15 16H9l-.5-2C7 12.8 6 11.2 6 9a6 6 0 016-6z"/></svg></div>
      <div class="kpi-body"><div class="kpi-val" data-target="${total}">0</div><div class="kpi-label" data-i18n="dash.total">Total Ideas</div></div>
    </div>
    <div class="kpi-card" style="border-left-color:#f59e0b">
      <div class="kpi-icon" style="background:#fef3c7;color:#f59e0b"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
      <div class="kpi-body"><div class="kpi-val" data-target="${counts['Under Review']||0}">0</div><div class="kpi-label" data-i18n="status.review">Under Review</div></div>
    </div>
    <div class="kpi-card" style="border-left-color:#10b981">
      <div class="kpi-icon" style="background:#bbf7d0;color:#10b981"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
      <div class="kpi-body"><div class="kpi-val" data-target="${counts['Approved']||0}">0</div><div class="kpi-label" data-i18n="dash.approved">Approved</div></div>
    </div>
    <div class="kpi-card" style="border-left-color:#374151">
      <div class="kpi-icon" style="background:#c8ccd1;color:#4b5563"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
      <div class="kpi-body"><div class="kpi-val" data-target="${counts['Implemented']||0}">0</div><div class="kpi-label" data-i18n="dash.implemented">Implemented</div></div>
    </div>
    ${reviewerKpis}
  `;
  document.querySelectorAll('#dash-kpis .kpi-val[data-target]').forEach(el => {
    animateCounter(el, parseInt(el.dataset.target), 900);
  });

  const statusColors = {'Submitted':'#374151','Under Review':'#f59e0b','Approved':'#10b981','Rejected':'#ef4444','Implemented':'#4b5563'};
  const maxCount = Math.max(...Object.values(counts), 1);
  document.getElementById('dash-status-chart').innerHTML =
    Object.entries(counts).map(([s,c]) => `
      <div class="bar-row">
        <span class="bar-label">${translateStatus(s)}</span>
        <div class="bar-track"><div class="bar-fill" style="width:0%;background:${statusColors[s]||'#ccc'}" data-w="${Math.round(c/maxCount*100)}"></div></div>
        <span class="bar-val">${c}</span>
      </div>`).join('');
  setTimeout(() => {
    document.querySelectorAll('#dash-status-chart .bar-fill[data-w]').forEach((bar, i) => {
      setTimeout(() => {
        bar.style.transition = 'width .7s cubic-bezier(.4,0,.2,1)';
        bar.style.width = bar.dataset.w + '%';
      }, i * 80);
    });
  }, 150);

  const actEl = document.getElementById('dash-activity');
  actEl.innerHTML = d.recent.length
    ? d.recent.map(r => `
        <div class="tl-item">
          <div class="tl-dot tl-dot-blue">${actionLabel(r.action)}</div>
          <div>
            <div class="tl-title">${r.idea_code} — ${r.action}</div>
            <div class="tl-meta">${r.actor_name} · ${timeAgo(r.created_at)}</div>
            ${r.comment ? `<div class="tl-comment">${escHtml(r.comment)}</div>` : ''}
          </div>
        </div>`).join('')
    : `<div class="empty-state">${t('msg.no_ideas')}</div>`;
  staggerAnimate([...actEl.querySelectorAll('.tl-item')], 70);
  applyTranslations();
}

async function loadMyIdeas() {
  let r, d;
  try {
    r = await fetch('api/ideas.php?action=my', {credentials:'same-origin'});
    d = await r.json();
  } catch(e) {
    document.getElementById('my-ideas-list').innerHTML = `<div class="alert alert-danger">${t('msg.fail_ideas')}</div>`;
    return;
  }
  allMyIdeas = d.ideas || [];
  renderMyIdeas(allMyIdeas);
}

function filterMyIdeas() {
  const q  = document.getElementById('my-search').value.toLowerCase();
  const st = document.getElementById('my-status-filter').value;
  renderMyIdeas(allMyIdeas.filter(i =>
    (i.title.toLowerCase().includes(q) || i.idea_code.toLowerCase().includes(q)) &&
    (!st || i.status === st)
  ));
}

function renderMyIdeas(ideas) {
  const el = document.getElementById('my-ideas-list');
  if (!ideas.length) { el.innerHTML = `<div class="empty-state">${t('msg.no_ideas')}</div>`; return; }
  el.innerHTML = ideas.map(i => `
    <div class="idea-card" data-status="${i.status}" onclick="openIdeaDetail(${i.id})">
      <div class="idea-card-header">
        <div>
          <div class="idea-card-id">#${i.idea_code}</div>
          <div class="idea-card-title">${escHtml(i.title)}</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
          <span class="badge ${statusBadge(i.status)}">${translateStatus(i.status)}</span>
          ${i.ai_score > 0 ? `<span class="${scoreBadgeClass(i.ai_score)}">${i.ai_score}/100</span>` : ''}
          ${i.status !== 'Draft' ? engBadge(i.ai_score, i.avg_rating, i.vote_count) : ''}
        </div>
      </div>
      <div class="idea-card-meta">${i.impact_areas || '—'} · ${i.submitted_at ? fmtDate(i.submitted_at) : 'Draft'}</div>
      ${i.status !== 'Draft' ? `<div style="margin-top:4px">${engMiniStats(i.avg_rating, i.vote_count)}</div>` : ''}
      <div class="idea-card-footer">
        <span class="badge ${impactBadge(i.impact_level)}">${translateImpact(i.impact_level)||'–'} ${t('idea.impact_suffix')}</span>
        <div style="display:flex;gap:8px;align-items:center">
          ${i.points_awarded ? `<span class="points-badge">+${i.points_awarded} pts</span>` : ''}
          <button class="btn btn-outline btn-sm" onclick="event.stopPropagation();openIdeaDetail(${i.id})">${t('idea.view')}</button>
        </div>
      </div>
    </div>`).join('');
  staggerAnimate([...el.querySelectorAll('.idea-card')], 55);
}

async function loadAllIdeas() {
  const s  = document.getElementById('all-search').value;
  const st = document.getElementById('all-status').value;
  const im = document.getElementById('all-impact').value;
  const p  = new URLSearchParams({action:'list'});
  if (s)  p.append('search', s);
  if (st) p.append('status', st);
  if (im) p.append('impact', im);
  const tbody = document.getElementById('all-ideas-tbody');
  tbody.innerHTML = '<tr><td colspan="10" class="text-center"><div class="spinner"></div></td></tr>';
  let r, d;
  try {
    r = await fetch('api/ideas.php?' + p, {credentials:'same-origin'});
    d = await r.json();
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="10" class="text-center"><div class="alert alert-danger">${t('msg.fail_ideas')}</div></td></tr>`;
    return;
  }
  tbody.innerHTML = (d.ideas||[]).map(i => {
    const isSelf   = parseInt(i.submitter_id) === parseInt(currentUser?.id);
    const cScore   = communityScore(i.ai_score, i.upvotes||0, i.downvotes||0);
    const scoreTip = i.ai_score > 0 ? `AI Score: ${i.ai_score}/100 · Community adjustment: ${cScore - i.ai_score >= 0 ? '+' : ''}${cScore - i.ai_score}` : '';
    const scoreCell = i.ai_score > 0
      ? `<span id="cscore-${i.id}" class="${scoreBadgeClass(cScore)}" title="${scoreTip}">${cScore}/100</span>`
      : '<span class="score-none score-badge">—</span>';
    return `
    <tr>
      <td><strong>${i.idea_code}</strong></td>
      <td title="${escHtml(i.title)}">${i.title.length > 60 ? escHtml(i.title).substring(0,60) + '…' : escHtml(i.title)}</td>
      <td>${escHtml(i.submitter_name)}</td>
      <td>${i.department||'–'}</td>
      <td><span class="badge ${impactBadge(i.impact_level)}">${translateImpact(i.impact_level)||'–'}</span></td>
      <td>${scoreCell}</td>
      <td>${i.status !== 'Draft' ? voteWidget(i.id, isSelf, i.upvotes||0, i.downvotes||0, i.user_community_vote||null) : '<span style="font-size:11px;color:var(--subtle)">—</span>'}</td>
      <td><span class="badge ${statusBadge(i.status)}">${translateStatus(i.status)}</span></td>
      <td>${i.submitted_at ? fmtDate(i.submitted_at) : '–'}</td>
      <td><button class="btn btn-outline btn-sm" onclick="openIdeaDetail(${i.id})">${t('idea.view')}</button></td>
    </tr>`;
  }).join('') || `<tr><td colspan="10" class="text-center">${t('msg.no_ideas')}</td></tr>`;
  staggerAnimate([...tbody.querySelectorAll('tr')], 40);
}

async function openIdeaDetail(id) {
  pendingIdeaId = id;
  document.getElementById('modal-idea-code').textContent = 'Loading…';
  document.getElementById('modal-idea-title-sub').textContent = '';
  document.getElementById('modal-detail-body').innerHTML = '<div class="spinner"></div>';
  document.getElementById('modal-timeline').innerHTML    = '<div class="spinner"></div>';
  document.getElementById('modal-attachments').innerHTML = '<div class="spinner"></div>';

  document.querySelectorAll('#modal-idea-detail .tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('#modal-idea-detail .tab').forEach(t => t.classList.remove('active'));
  document.getElementById('dtab1').classList.add('active');
  document.querySelectorAll('#modal-idea-detail .tab')[0].classList.add('active');

  document.getElementById('modal-idea-detail').classList.add('open');

  let d;
  try {
    const r = await fetch('api/ideas.php?action=get&id=' + id, {credentials:'same-origin'});
    d = await r.json();
  } catch(e) {
    document.getElementById('modal-detail-body').innerHTML = '<div class="alert alert-danger">Failed to load idea. Please try again.</div>';
    document.getElementById('modal-timeline').innerHTML = '';
    document.getElementById('modal-attachments').innerHTML = '';
    return;
  }
  if (!d.success) {
    document.getElementById('modal-detail-body').innerHTML = `<div class="alert alert-danger">${escHtml(d.error || 'Error loading idea.')}</div>`;
    document.getElementById('modal-timeline').innerHTML = '';
    document.getElementById('modal-attachments').innerHTML = '';
    return;
  }
  const idea = d.idea;

  document.getElementById('modal-idea-code').textContent      = '#' + idea.idea_code;
  document.getElementById('modal-idea-title-sub').textContent = idea.title;

  const aiReason  = (idea.ai_reason  && idea.ai_reason.trim())
                    ? escHtml(idea.ai_reason)
                    : t('detail.no_ai');
  const aiScoreBadge = idea.ai_score > 0
                    ? `<span class="${scoreBadgeClass(idea.ai_score)}">${idea.ai_score}/100</span>`
                    : '';

  document.getElementById('modal-detail-body').innerHTML = `
    <div class="form-row" style="margin-bottom:12px">
      <div><strong>${t('detail.submitted_by')}:</strong> ${escHtml(idea.submitter_name)} (${idea.department||'–'})</div>
      <div style="display:flex;align-items:center;gap:8px">
        <strong>${t('table.status')}:</strong> <span class="badge ${statusBadge(idea.status)}">${translateStatus(idea.status)}</span>
      </div>
    </div>

    <div class="form-group"><label>${t('detail.situation')}</label>
      <div style="background:#f8f9fe;padding:10px;border-radius:6px;font-size:13px">${escHtml(idea.present_situation)}</div>
    </div>
    <div class="form-group"><label>${t('detail.solution')}</label>
      <div style="background:#f8f9fe;padding:10px;border-radius:6px;font-size:13px">${escHtml(idea.proposed_solution)}</div>
    </div>
    <div class="form-row" style="margin-bottom:10px">
      <div><strong>${t('detail.impact_areas')}:</strong> ${idea.impact_areas||'–'}</div>
      <div><strong>${t('detail.impact_level')}:</strong> <span class="badge ${impactBadge(idea.impact_level)}">${translateImpact(idea.impact_level)||'–'}</span></div>
    </div>
    ${idea.tangible_benefit   ? `<div class="mt-8"><strong>${t('detail.tangible')}:</strong> ${escHtml(idea.tangible_benefit)}</div>`   : ''}
    ${idea.intangible_benefit ? `<div class="mt-8"><strong>${t('detail.intangible')}:</strong> ${escHtml(idea.intangible_benefit)}</div>` : ''}
    ${idea.co1_name           ? `<div class="mt-8"><strong>${t('detail.co_suggesters')}:</strong> ${escHtml(idea.co1_name)}${idea.co2_name ? ', ' + escHtml(idea.co2_name) : ''}</div>` : ''}

    <div class="ai-panel" style="margin-top:14px">
      <div class="ai-panel-title">${t('detail.ai_eval')}</div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <strong style="font-size:13px">${t('detail.score')}:</strong>
        ${aiScoreBadge || `<span class="score-badge score-none">${t('detail.not_scored')}</span>`}
      </div>
      <div style="font-size:13px;color:var(--text);line-height:1.5">${aiReason}</div>
    </div>
    <div id="community-engagement-panel" style="margin-top:14px"></div>
    ${(idea.workflow_type === 'multi_reviewer' && (idea.reviewers||[]).length > 0) ? `
    <div class="ai-panel" style="margin-top:14px;border-left-color:#0284c7;background:linear-gradient(135deg,#eff6ff,var(--panel-bg))">
      <div class="ai-panel-title" style="color:#0284c7">&#9632; ${t('review.committee_badge')} &mdash; ${idea.approval_threshold}${t('committee.approval_req')}</div>
      <div style="font-size:12px;color:var(--subtle);margin-bottom:12px">
        ${(idea.reviewers||[]).filter(r=>r.decision==='approved').length} ${t('committee.approved_count')} &middot;
        ${(idea.reviewers||[]).filter(r=>r.decision==='rejected').length} ${t('committee.rejected_count')} &middot;
        ${(idea.reviewers||[]).filter(r=>r.decision==='pending').length} ${t('committee.pending_count')}
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        ${(idea.reviewers||[]).map(rv => `
          <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:var(--r);border:1px solid var(--border);background:var(--surface);min-width:160px">
            <div class="avatar" style="width:28px;height:28px;font-size:10px;flex-shrink:0;background:${rv.decision==='approved'?'linear-gradient(135deg,#10b981,#10b981)':rv.decision==='rejected'?'linear-gradient(135deg,#ef4444,#ef4444)':'linear-gradient(135deg,#94a3b8,#cbd5e1)'}">${escHtml(rv.avatar_initials||rv.reviewer_name[0])}</div>
            <div>
              <div style="font-size:12px;font-weight:600">${escHtml(rv.reviewer_name)}</div>
              <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:${rv.decision==='approved'?'#10b981':rv.decision==='rejected'?'#ef4444':'#94a3b8'}">${rv.decision}</div>
              ${rv.comment ? `<div style="font-size:11px;color:var(--subtext);margin-top:2px;font-style:italic">&ldquo;${escHtml(rv.comment)}&rdquo;</div>` : ''}
            </div>
          </div>`).join('')}
      </div>
    </div>` : ''}
  `;

  // Community engagement panel + voting widget
  try {
    const [vr, cr] = await Promise.all([
      fetch('api/votes.php?action=stats&idea_id=' + id, {credentials:'same-origin'}),
      fetch('api/votes.php?action=community_stats&idea_id=' + id, {credentials:'same-origin'})
    ]);
    const vd = await vr.json();
    const cd = await cr.json();
    const isSelf    = parseInt(idea.submitter_id) === parseInt(currentUser?.id);
    const vc        = vd.vote_count  || 0;
    const ar        = vd.avg_rating  || 0;
    const ur        = vd.user_rating ?? null;
    const upvotes   = cd.upvotes    || 0;
    const downvotes = cd.downvotes  || 0;
    const userVote  = cd.user_vote  || null;
    const cScoreVal = cd.community_score !== undefined ? cd.community_score : communityScore(idea.ai_score, upvotes, downvotes);
    const net       = upvotes - downvotes;
    const adjStr    = (cScoreVal - (parseInt(idea.ai_score)||0)) >= 0
                        ? `+${cScoreVal - (parseInt(idea.ai_score)||0)}`
                        : `${cScoreVal - (parseInt(idea.ai_score)||0)}`;
    const panel = document.getElementById('community-engagement-panel');
    if (panel) {
      panel.innerHTML = `
        <div class="ai-panel" style="border-left-color:#374151">
          <div class="ai-panel-title" style="color:#4b5563">&#9650;&#9660; ${t('community.title')}</div>

          <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:14px">
            <div style="text-align:center">
              <div style="font-size:22px;font-weight:800;color:#065f46">&#9650; ${upvotes}</div>
              <div style="font-size:11px;color:var(--subtle)">${t('community.upvotes')}</div>
            </div>
            <div style="text-align:center">
              <div style="font-size:22px;font-weight:800;color:${net >= 0 ? '#15803d' : '#b91c1c'}">${net >= 0 ? '+' : ''}${net}</div>
              <div style="font-size:11px;color:var(--subtle)">${t('community.net')}</div>
            </div>
            <div style="text-align:center">
              <div style="font-size:22px;font-weight:800;color:#991b1b">&#9660; ${downvotes}</div>
              <div style="font-size:11px;color:var(--subtle)">${t('community.downvotes')}</div>
            </div>
            <div style="margin-left:auto;text-align:right">
              <div style="font-size:11px;color:var(--subtle);margin-bottom:4px">${t('community.score')}</div>
              <span id="modal-community-score" class="${scoreBadgeClass(cScoreVal)}" style="font-size:15px;padding:4px 12px">${cScoreVal}/100</span>
              ${idea.ai_score > 0 ? `<div style="font-size:10px;color:var(--subtle);margin-top:3px">AI: ${idea.ai_score} · Votes: ${adjStr}</div>` : ''}
            </div>
          </div>

          ${idea.status !== 'Draft' ? `
          <div style="border-top:1px solid var(--border);padding-top:12px">
            <div style="font-size:12px;color:var(--subtle);margin-bottom:8px;font-weight:600">
              ${isSelf ? t('community.your_votes') : t('community.vote_on')}
            </div>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
              ${voteWidget(id, isSelf, upvotes, downvotes, userVote)}
              ${!isSelf ? `<span style="font-size:11px;color:var(--subtle)">${t('community.vote_hint')}</span>` : ''}
            </div>
          </div>` : ''}

          ${vc > 0 ? `
          <div style="border-top:1px solid var(--border);padding-top:10px;margin-top:10px">
            <div style="font-size:11px;color:var(--subtle);margin-bottom:6px;font-weight:600">COMMUNITY RATING (1–5 stars)</div>
            <div style="display:flex;align-items:center;gap:8px">
              <span style="color:#f59e0b;font-weight:700">${ar > 0 ? ar.toFixed(1) : '—'} &#9733;</span>
              <span style="font-size:11px;color:var(--subtle)">${vc} ${t('idea.votes')}</span>
              ${!isSelf && idea.status !== 'Draft' ? starWidget(id, false, ur) : ''}
            </div>
          </div>` : (!isSelf && idea.status !== 'Draft' ? `
          <div style="border-top:1px solid var(--border);padding-top:10px;margin-top:10px">
            <div style="font-size:11px;color:var(--subtle);margin-bottom:6px;font-weight:600">COMMUNITY RATING (1–5 stars)</div>
            ${starWidget(id, false, ur)}
          </div>` : '')}
        </div>`;
    }
  } catch(e) { /* vote stats non-critical */ }

  document.getElementById('modal-timeline').innerHTML = (idea.workflow||[]).map(w => `
    <div class="tl-item">
      <div class="tl-dot tl-dot-blue">${actionLabel(w.action)}</div>
      <div>
        <div class="tl-title">${w.action}</div>
        <div class="tl-meta">${escHtml(w.actor_name)} · ${fmtDate(w.created_at)}</div>
        ${w.comment ? `<div class="tl-comment">${escHtml(w.comment)}</div>` : ''}
      </div>
    </div>`).join('') || '<div class="empty-state">No workflow history yet.</div>';

  document.getElementById('modal-attachments').innerHTML = (idea.attachments||[]).length
    ? idea.attachments.map(a => {
        const url      = 'api/uploads/' + a.filepath;
        const ext      = a.filename.split('.').pop().toLowerCase();
        const isImage  = ['png','jpg','jpeg','gif','webp'].includes(ext);
        const preview  = isImage
          ? `<div style="margin-top:8px">
               <img src="${url}" alt="${escHtml(a.filename)}"
                    style="max-width:100%;max-height:320px;border-radius:6px;border:1px solid #e0e0e0;display:block"/>
             </div>`
          : '';
        return `
          <div style="padding:10px 0;border-bottom:1px solid var(--border)">
            <div style="display:flex;align-items:center;justify-content:space-between">
              <div>
                <span style="font-size:12px;color:var(--subtle);text-transform:uppercase;margin-right:6px">${a.section}</span>
                <a href="${url}" target="_blank" style="font-size:13px;color:#374151">${escHtml(a.filename)}</a>
              </div>
              <a href="${url}" download="${escHtml(a.filename)}" class="btn btn-outline btn-sm">Download</a>
            </div>
            ${preview}
          </div>`;
      }).join('')
    : '<div class="empty-state">No attachments.</div>';

  const isPriv    = ['team_lead','project_lead','manager','senior_manager','executive','admin','super_admin'].includes(currentUser?.role);
  const isSelf    = parseInt(idea.submitter_id) === parseInt(currentUser?.id);
  const isMultiRv = idea.workflow_type === 'multi_reviewer';
  const isAssignedReviewer = isPriv && !isSelf && (idea.reviewers||[]).some(
    rv => parseInt(rv.reviewer_id) === parseInt(currentUser?.id) && rv.decision === 'pending'
  );
  const canDirectReview  = isPriv && !isSelf && !isMultiRv && ['Submitted','Under Review'].includes(idea.status);
  const canRouteReviewers= isPriv && !isSelf && !isMultiRv && ['Submitted','Under Review'].includes(idea.status);
  const selfNote  = isPriv && isSelf && ['Submitted','Under Review'].includes(idea.status)
    ? `<span style="font-size:12px;color:#f59e0b;margin-right:10px">${t('review.cannot_own')}</span>` : '';
  document.getElementById('idea-detail-footer').innerHTML = `
    <button class="btn btn-outline" onclick="closeModal('modal-idea-detail')">${t('detail.close')}</button>
    ${selfNote}
    ${canRouteReviewers ? `<button class="btn btn-outline" style="border-color:#0284c7;color:#0284c7" onclick="closeModal('modal-idea-detail');openAssignReviewersModal(${idea.id},'${idea.idea_code}')">${t('review.route_committee')}</button>` : ''}
    ${isAssignedReviewer ? `<button class="btn btn-primary" onclick="closeModal('modal-idea-detail');openReviewerDecisionModal(${idea.id},'${idea.idea_code}')">${t('review.submit_mine')}</button>` : ''}
    ${canDirectReview ? `<button class="btn btn-success" onclick="closeModal('modal-idea-detail');openReviewModal(${idea.id},'${idea.idea_code}')">${t('review.decide')}</button>` : ''}
  `;
}

async function loadReviewQueue() {
  const el = document.getElementById('review-list');
  el.innerHTML = `<div class="empty-state"><div class="spinner"></div> ${t('msg.loading')}</div>`;
  let r, d;
  try {
    r = await fetch('api/ideas.php?action=review', {credentials:'same-origin'});
    d = await r.json();
  } catch(e) {
    el.innerHTML = `<div class="alert alert-danger">${t('msg.fail_queue')}</div>`;
    return;
  }
  if (!d.success) {
    el.innerHTML = `<div class="alert alert-danger">${d.error||'Error loading review queue.'}</div>`;
    return;
  }
  if (!d.ideas?.length) { el.innerHTML = `<div class="empty-state">${t('msg.no_review')}</div>`; return; }
  el.innerHTML = d.ideas.map(i => {
    const isSelf        = parseInt(i.submitter_id) === parseInt(currentUser?.id);
    const isMultiRv     = i.workflow_type === 'multi_reviewer';
    const isMyPending   = i.my_reviewer_decision === 'pending';
    const pending       = Math.max(0, (parseInt(i.reviewer_count)||0) - (parseInt(i.approved_count)||0) - (parseInt(i.rejected_count)||0));

    // SLA badge
    const dueDate  = i.review_due_date ? new Date(i.review_due_date) : null;
    const isOverdue = dueDate && dueDate < new Date();
    const slaBadge  = dueDate ? `<span style="font-size:11px;padding:2px 8px;border-radius:20px;font-weight:600;border:1px solid ${isOverdue?'#fecaca':'#e2e8f0'};background:${isOverdue?'#fee2e2':'var(--chip-bg)'};color:${isOverdue?'#ef4444':'var(--text-muted)'}">${isOverdue?'⚠ Overdue':'⏱ Due'} ${fmtDate(i.review_due_date)}</span>` : '';
    // Escalation badge
    const escalBadge = (parseInt(i.escalation_level) > 0) ? `<span style="font-size:11px;padding:2px 8px;border-radius:20px;font-weight:600;border:1px solid #e9d5ff;background:#f5f3ff;color:#4b5563">↑ L${i.escalation_level}</span>` : '';

    const committeeInfo = isMultiRv ? `
      <div style="margin-top:6px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="font-size:11px;background:#eff6ff;color:#1d4ed8;padding:2px 9px;border-radius:var(--r-full);font-weight:600;border:1px solid #bfdbfe">${t('review.committee_badge')}</span>
        <span style="font-size:11px;color:var(--subtle)">${i.approved_count||0} ${t('committee.approved_count')} &middot; ${i.rejected_count||0} ${t('committee.rejected_count')} &middot; ${pending} ${t('committee.pending_count')}</span>
        ${isMyPending ? `<span style="font-size:11px;background:#fef3c7;color:#92400e;padding:2px 9px;border-radius:var(--r-full);font-weight:600;border:1px solid #fde68a">${t('review.vote_needed')}</span>` : ''}
      </div>` : '';
    const actionBtns = isSelf
      ? `<span style="font-size:11px;color:#f59e0b">${t('review.own_idea')}</span>
         <button class="btn btn-outline btn-sm" onclick="openIdeaDetail(${i.id})">${t('idea.view')}</button>`
      : isMultiRv && isMyPending
      ? `<button class="btn btn-outline btn-sm" onclick="openIdeaDetail(${i.id})">${t('idea.view')}</button>
         <button class="btn btn-primary btn-sm" onclick="openReviewerDecisionModal(${i.id},'${i.idea_code}')">${t('review.my_review')}</button>`
      : isMultiRv
      ? `<button class="btn btn-outline btn-sm" onclick="openIdeaDetail(${i.id})">${t('review.view_details')}</button>`
      : `<button class="btn btn-outline btn-sm" onclick="openIdeaDetail(${i.id})">${t('review.view_details')}</button>
         <button class="btn btn-success btn-sm" onclick="openReviewModal(${i.id},'${i.idea_code}')">${t('review.review_btn')}</button>`;
    return `
    <div class="idea-card" data-status="${i.status}" data-id="${i.id}">
      <div class="idea-card-header">
        <div style="display:flex;align-items:flex-start;gap:10px">
          ${!isSelf && !isMultiRv ? `<input type="checkbox" class="bulk-chk" data-id="${i.id}" style="margin-top:4px;accent-color:var(--primary)" onchange="updateBulkBar()"/>` : ''}
          <div>
            <div class="idea-card-id">#${i.idea_code}</div>
            <div class="idea-card-title">${escHtml(i.title)}</div>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
          <span class="badge ${statusBadge(i.status)}">${translateStatus(i.status)}</span>
          ${i.ai_score > 0 ? `<span class="${scoreBadgeClass(i.ai_score)}">AI: ${i.ai_score}/100</span>` : ''}
        </div>
      </div>
      <div class="idea-card-meta">By ${escHtml(i.submitter_name)} · ${i.department||'–'} · ${i.submitted_at ? fmtDate(i.submitted_at) : '–'}</div>
      ${slaBadge || escalBadge ? `<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">${slaBadge}${escalBadge}</div>` : ''}
      ${committeeInfo}
      <div style="margin-top:4px">${engMiniStats(i.avg_rating, i.vote_count)}</div>
      <div class="idea-card-footer">
        <div style="display:flex;align-items:center;gap:8px">
          <span class="badge ${impactBadge(i.impact_level)}">${translateImpact(i.impact_level)||'–'} ${t('idea.impact_suffix')}</span>
          ${engBadge(i.ai_score, i.avg_rating, i.vote_count)}
        </div>
        <div style="display:flex;gap:8px;align-items:center">${actionBtns}</div>
      </div>
    </div>`;
  }).join('');
}

function openReviewModal(id, code) {
  pendingIdeaId = id;
  document.getElementById('review-id').textContent    = '#' + code;
  document.getElementById('review-comment').value     = '';
  document.getElementById('review-decision').value    = 'Approved';
  const submitBtn = document.querySelector('#modal-review .modal-footer .btn-primary');
  if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = t('btn.submit_decision'); }
  document.getElementById('modal-review').classList.add('open');
}

async function submitReview() {
  const decision  = document.getElementById('review-decision').value;
  const comment   = document.getElementById('review-comment').value;
  const submitBtn = document.querySelector('#modal-review .modal-footer .btn-primary');

  const label = {
    'Approved':'Approve','Rejected':'Reject','Implemented':'Mark as Implemented','Under Review':'Move to Under Review'
  }[decision] || decision;
  const labelText = t('bulk.' + (decision === 'Approved' ? 'approve_all' : decision === 'Rejected' ? 'reject_all' : '')) || label;
  if (!confirm(`Confirm: ${labelText} this idea?\n\nThis action will be recorded in the audit trail and the submitter will be notified.`)) return;

  if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = t('msg.loading'); }

  let r, d;
  try {
    r = await fetch('api/ideas.php?action=review_action', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify({idea_id: pendingIdeaId, decision, comment})
    });
    d = await r.json();
  } catch(e) {
    alert(t('msg.server_error'));
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = t('btn.submit_decision'); }
    return;
  }

  closeModal('modal-review');
  if (d.success) {
    const isEscalated = d.decision === 'Escalated';
    const successMsg  = isEscalated
      ? `Idea approved at this level and escalated to ${d.escalated_to} for final decision.`
      : `Idea marked as "${decision}". Submitter notified.${d.points_awarded ? ' +' + d.points_awarded + ' pts awarded.' : ''}`;
    document.getElementById('success-title').textContent = isEscalated ? 'Escalated for Final Review' : t('msg.decision_ok');
    document.getElementById('success-msg').textContent   = successMsg;
    document.getElementById('modal-success').classList.add('open');
    showToast(isEscalated ? `Escalated to ${d.escalated_to}` : `${t('msg.decision_ok')}: ${decision}${d.points_awarded ? ` · +${d.points_awarded} pts` : ''}`, 'success');
    loadReviewQueue();
    loadDashboard();
  } else {
    showToast('Error: ' + (d.error || 'Unknown error'), 'danger');
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = t('btn.submit_decision'); }
  }
}

function resetWizard() {
  draftIdeaId = null;
  ['idea-title','idea-situation','idea-solution','idea-tangible','idea-intangible','co1-search','co2-search'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  document.getElementById('co1-id').value = '';
  document.getElementById('co2-id').value = '';
  document.getElementById('co1-name-display').textContent = '';
  document.getElementById('co2-name-display').textContent = '';
  document.querySelectorAll('.impact-chip').forEach(c => c.classList.remove('selected'));
  ['situation','solution'].forEach(s => {
    const inp = document.getElementById('file-'+s);
    if (inp) inp.value = '';
    const lbl = document.getElementById('file-'+s+'-name');
    if (lbl) lbl.textContent = '';
  });
  const anonEl = document.getElementById('idea-anonymous');
  if (anonEl) anonEl.checked = false;
  const tmplEl = document.getElementById('idea-template');
  if (tmplEl) tmplEl.value = '';
  const chalEl = document.getElementById('idea-challenge');
  if (chalEl) chalEl.value = '';
  const dupW = document.getElementById('duplicate-warning');
  if (dupW) dupW.style.display = 'none';
  goStep(1);
}

function goStep(n) {
  for (let i = 1; i <= totalSteps; i++) {
    const el = document.getElementById('step-' + i);
    if (el) el.style.display = 'none';
  }
  const stepEl = document.getElementById('step-' + n);
  if (stepEl) {
    stepEl.style.display = 'block';
    stepEl.style.animation = 'none';
    requestAnimationFrame(() => { stepEl.style.animation = 'fadeInUp .25s cubic-bezier(.4,0,.2,1)'; });
  }
  document.querySelectorAll('.w-step').forEach((s,idx) => {
    s.classList.remove('active','done');
    if (idx+1 < n) s.classList.add('done');
    else if (idx+1 === n) s.classList.add('active');
  });
  currentStep = n;
  const isLast = n === totalSteps;
  document.getElementById('wizard-nav').style.display        = isLast ? 'none' : 'flex';
  document.getElementById('wizard-submit-row').style.display = isLast ? 'flex' : 'none';
  if (!isLast) {
    document.getElementById('btn-back').style.visibility = n > 1 ? 'visible' : 'hidden';
    document.getElementById('btn-next').textContent      = n === totalSteps-1 ? 'Review' : 'Next';
  }
  if (isLast) buildReviewPreview();
}

function nextStep() {
  if (currentStep === 1) {
    if (!document.getElementById('idea-title').value.trim() ||
        document.getElementById('idea-situation').value.trim().length < 20) {
      alert(t('msg.fill_situation')); return;
    }
  }
  if (currentStep === 2 && !document.getElementById('idea-solution').value.trim()) {
    alert(t('msg.fill_solution')); return;
  }
  if (currentStep < totalSteps) goStep(currentStep + 1);
}
function prevStep() { if (currentStep > 1) goStep(currentStep - 1); }

function toggleImpact(el) { el.classList.toggle('selected'); }

function buildReviewPreview() {
  const impacts = [...document.querySelectorAll('.impact-chip.selected')].map(c => c.dataset.val).join(', ');
  document.getElementById('review-preview').innerHTML = `
    <div class="form-group"><label>${t('preview.title_label')}</label><div class="form-control" style="background:#f8f9fe">${escHtml(document.getElementById('idea-title').value)}</div></div>
    <div class="form-group"><label>${t('preview.situation')}</label><div class="form-control" style="background:#f8f9fe;height:auto;min-height:60px">${escHtml(document.getElementById('idea-situation').value)}</div></div>
    <div class="form-group"><label>${t('preview.solution')}</label><div class="form-control" style="background:#f8f9fe;height:auto;min-height:60px">${escHtml(document.getElementById('idea-solution').value)}</div></div>
    <div class="form-row">
      <div><label>${t('preview.impact_areas')}</label><div class="form-control" style="background:#f8f9fe">${impacts||t('preview.none_selected')}</div></div>
      <div><label>${t('preview.impact_level')}</label><div class="form-control" style="background:#f8f9fe">${document.getElementById('idea-impact-level').value}</div></div>
    </div>
    ${document.getElementById('co1-name-display').textContent ? `<div><label>${t('preview.co_suggesters')}</label><div class="form-control" style="background:#f8f9fe">${document.getElementById('co1-name-display').textContent}${document.getElementById('co2-name-display').textContent ? ', ' + document.getElementById('co2-name-display').textContent : ''}</div></div>` : ''}
  `;
}

async function saveDraft() {
  const body = buildIdeaPayload();
  body.id = draftIdeaId;
  const r = await fetch('api/ideas.php?action=draft', {
    method:'POST', headers:{'Content-Type':'application/json', ...csrfHeaders()}, credentials:'same-origin', body: JSON.stringify(body)
  });
  const d = await r.json();
  if (d.success) { draftIdeaId = d.idea_id; alert(t('msg.draft_prefix') + d.idea_code); }
}

async function submitIdea() {
  const submitBtn = document.querySelector('#wizard-submit-row .btn-success');
  if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = t('msg.loading'); }

  const body = buildIdeaPayload();
  body.id = draftIdeaId;
  let r, d;
  try {
    r = await fetch('api/ideas.php?action=submit', {
      method:'POST', headers:{'Content-Type':'application/json', ...csrfHeaders()},
      credentials:'same-origin', body: JSON.stringify(body)
    });
    d = await r.json();
  } catch(e) {
    alert(t('msg.server_error'));
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = t('form.submit_idea'); }
    return;
  }

  if (d.success) {
    await uploadFiles(d.idea_id);
    currentUser.points += (d.points_added || 0);
    document.getElementById('sb-points').textContent = currentUser.points + ' pts';
    closeModal('modal-idea-detail');
    document.getElementById('success-title').textContent = t('msg.idea_ok');
    const ptsMsg = d.points_added > 0 ? ` +${d.points_added} points credited.` : ' No new points (already submitted before).';
    document.getElementById('success-msg').textContent   = `Idea #${d.idea_code} submitted and routed to your manager for review.${ptsMsg} AI Quality Score: ${d.ai_score}/100.`;
    document.getElementById('modal-success').classList.add('open');
    showToast(`Idea #${d.idea_code} submitted!${d.points_added > 0 ? ' +' + d.points_added + ' pts earned' : ''}`, 'success');
    draftIdeaId = null;
    loadMyIdeas();
    loadDashboard();
  } else {
    alert('Error: ' + d.error);
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = t('form.submit_idea'); }
  }
}

function buildIdeaPayload() {
  const impacts = [...document.querySelectorAll('.impact-chip.selected')].map(c => c.dataset.val).join(',');
  return {
    title:              document.getElementById('idea-title').value,
    present_situation:  document.getElementById('idea-situation').value,
    proposed_solution:  document.getElementById('idea-solution').value,
    impact_areas:       impacts,
    impact_level:       document.getElementById('idea-impact-level').value,
    tangible_benefit:   document.getElementById('idea-tangible').value,
    intangible_benefit: document.getElementById('idea-intangible').value,
    co_suggester_1_id:  document.getElementById('co1-id').value || null,
    co_suggester_2_id:  document.getElementById('co2-id').value || null,
    is_anonymous:       document.getElementById('idea-anonymous')?.checked ? 1 : 0,
    template_type:      document.getElementById('idea-template')?.value || null,
    challenge_id:       document.getElementById('idea-challenge')?.value || null,
  };
}

async function uploadFiles(ideaId) {
  for (const [inputId, section] of [['file-situation','situation'],['file-solution','solution']]) {
    const input = document.getElementById(inputId);
    if (!input.files.length) continue;
    const fd = new FormData();
    fd.append('file', input.files[0]);
    fd.append('idea_id', ideaId);
    fd.append('section', section);
    await fetch('api/upload.php?action=upload', {method:'POST', credentials:'same-origin', body: fd});
  }
}

const searchTimers = {};
async function searchUsers(input, resultsId, hiddenId, displayId) {
  clearTimeout(searchTimers[resultsId]);
  const q = input.value.trim();
  const el = document.getElementById(resultsId);
  if (q.length < 2) { el.style.display = 'none'; return; }
  searchTimers[resultsId] = setTimeout(async () => {
    const r = await fetch('api/users.php?action=list&q=' + encodeURIComponent(q), {credentials:'same-origin'});
    const d = await r.json();
    if (!d.users?.length) { el.innerHTML = `<div class="uitem">${t('msg.no_ideas')}</div>`; el.style.display = 'block'; return; }
    el.innerHTML = d.users.map(u => `
      <div class="uitem"
        data-id="${u.id}"
        data-name="${escHtml(u.name)}"
        data-empid="${escHtml(u.employee_id)}"
        data-hidden="${hiddenId}"
        data-display="${displayId}"
        data-results="${resultsId}"
        data-input="${input.id}"
        onclick="selectUserFromEl(this)">
        ${escHtml(u.name)} · ${escHtml(u.employee_id)} · ${escHtml(u.department||'–')}
      </div>`).join('');
    el.style.display = 'block';
  }, 300);
}

function selectUserFromEl(el) {
  const d = el.dataset;
  selectUser(d.id, d.name, d.empid, d.hidden, d.display, d.results, d.input);
}

function selectUser(id, name, empId, hiddenId, displayId, resultsId, inputId) {
  document.getElementById(hiddenId).value          = id;
  document.getElementById(displayId).textContent   = `${name} (${empId})`;
  document.getElementById(resultsId).style.display = 'none';
  document.getElementById(inputId).value           = name;
}

document.addEventListener('click', e => {
  if (!e.target.closest('.pos-rel')) {
    document.querySelectorAll('.user-search-results').forEach(el => el.style.display='none');
  }
});

// ── DUPLICATE DETECTION ────────────────────────────────────────────
const _dupTimer = {};
async function checkDuplicateTitle(title) {
  clearTimeout(_dupTimer.t);
  const warnEl = document.getElementById('duplicate-warning');
  if (!warnEl) return;
  if (title.length < 8) { warnEl.style.display = 'none'; return; }
  _dupTimer.t = setTimeout(async () => {
    try {
      const r = await fetch('api/ideas.php?action=check_duplicate&title=' + encodeURIComponent(title), {credentials:'same-origin'});
      const d = await r.json();
      if (d.duplicates?.length) {
        warnEl.style.display = 'block';
        warnEl.innerHTML = `⚠ Similar ideas already exist — please review before submitting:<ul style="margin:4px 0 0 16px">${d.duplicates.map(x=>`<li><strong>${x.idea_code}</strong>: ${escHtml(x.title)} <span style="color:var(--text-muted)">(${x.status})</span></li>`).join('')}</ul>`;
      } else {
        warnEl.style.display = 'none';
      }
    } catch(e) {}
  }, 600);
}

// ── LOAD CHALLENGES PAGE ───────────────────────────────────────────
async function loadChallenges() {
  const el = document.getElementById('challenges-list');
  el.innerHTML = `<div class="empty-state"><div class="spinner"></div> Loading…</div>`;
  const isPriv = ['team_lead','project_lead','manager','senior_manager','executive','admin','super_admin'].includes(currentUser?.role);
  const newChalBtn = document.getElementById('btn-new-challenge');
  if (newChalBtn) newChalBtn.style.display = isPriv ? '' : 'none';
  let d;
  try {
    const r = await fetch('api/challenges.php?action=list', {credentials:'same-origin'});
    d = await r.json();
  } catch(e) { el.innerHTML = `<div class="alert alert-danger">Failed to load challenges.</div>`; return; }
  if (!d.success || !d.challenges?.length) {
    el.innerHTML = `<div class="empty-state">No active challenges at the moment.</div>`; return;
  }
  el.innerHTML = d.challenges.map(c => `
    <div class="card" style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
          <div style="font-size:15px;font-weight:600;color:var(--heading)">${escHtml(c.title)}</div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:2px">By ${escHtml(c.creator_name||'Admin')} · ${c.deadline ? 'Deadline: ' + fmtDate(c.deadline) : 'No deadline'} · ${c.idea_count||0} ideas</div>
        </div>
        <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:${c.status==='active'?'#bbf7d0':'#f1f5f9'};color:${c.status==='active'?'#10b981':'#64748b'};border:1px solid ${c.status==='active'?'#bbf7d0':'#e2e8f0'}">${c.status}</span>
      </div>
      ${c.description ? `<div style="margin-top:10px;font-size:13px;color:var(--text)">${escHtml(c.description)}</div>` : ''}
      <div style="margin-top:12px;display:flex;gap:8px">
        <button class="btn btn-primary btn-sm" onclick="navigate('submit',document.getElementById('nav-submit'));document.getElementById('idea-challenge').value='${c.id}'">Submit Idea for This Challenge</button>
        ${isPriv && c.status==='active' ? `<button class="btn btn-outline btn-sm" onclick="closeChallengePrompt(${c.id})">Close Challenge</button>` : ''}
      </div>
    </div>`).join('');
}

async function loadChallengesIntoSelect() {
  const sel = document.getElementById('idea-challenge');
  if (!sel) return;
  try {
    const r = await fetch('api/challenges.php?action=list', {credentials:'same-origin'});
    const d = await r.json();
    if (d.success && d.challenges?.length) {
      sel.innerHTML = '<option value="">— No Challenge —</option>' +
        d.challenges.filter(c=>c.status==='active').map(c=>`<option value="${c.id}">${escHtml(c.title)}</option>`).join('');
    }
  } catch(e) {}
}

async function closeChallengePrompt(id) {
  if (!confirm('Close this challenge? Submissions will stop.')) return;
  const r = await fetch('api/challenges.php?action=update', {method:'POST',headers:{'Content-Type':'application/json', ...csrfHeaders()},credentials:'same-origin',body:JSON.stringify({id,status:'closed'})});
  const d = await r.json();
  if (d.success) { showToast('Challenge closed.','success'); loadChallenges(); }
  else showToast(d.error||'Error','danger');
}

function openChallengeModal() {
  // Simple prompt-based create for now
  const title = prompt('Challenge title:');
  if (!title?.trim()) return;
  const desc    = prompt('Description (optional):') || '';
  const deadline= prompt('Deadline (YYYY-MM-DD, optional):') || null;
  fetch('api/challenges.php?action=create',{method:'POST',headers:{'Content-Type':'application/json', ...csrfHeaders()},credentials:'same-origin',
    body:JSON.stringify({title:title.trim(),description:desc,deadline:deadline||null})})
    .then(r=>r.json()).then(d=>{
      if(d.success){showToast('Challenge created.','success');loadChallenges();}
      else showToast(d.error||'Error','danger');
    });
}

// ── COMMUNITY VOTING BOARD ─────────────────────────────────────────
async function loadBoard() {
  const el = document.getElementById('board-list');
  el.innerHTML = `<div class="empty-state"><div class="spinner"></div> Loading…</div>`;
  const sort = document.getElementById('board-sort')?.value || 'votes';
  let d;
  try {
    const r = await fetch(`api/ideas.php?action=board&sort=${sort}`, {credentials:'same-origin'});
    d = await r.json();
  } catch(e) { el.innerHTML = `<div class="alert alert-danger">Failed to load board.</div>`; return; }
  if (!d.success || !d.ideas?.length) {
    el.innerHTML = `<div class="empty-state">No ideas on the board yet.</div>`; return;
  }
  el.innerHTML = d.ideas.map(i => {
    const upvotes   = parseInt(i.upvotes)||0;
    const downvotes = parseInt(i.downvotes)||0;
    const userVote  = i.user_vote;
    const isSelf    = parseInt(i.submitter_id) === parseInt(currentUser?.id);
    return `
    <div class="idea-card" id="board-card-${i.id}">
      <div style="display:flex;gap:12px">
        <div style="display:flex;flex-direction:column;align-items:center;gap:4px;min-width:44px">
          <button class="btn btn-sm" style="padding:4px 8px;border-radius:8px;font-size:13px;font-weight:700;background:${userVote==='up'?'#bbf7d0':'var(--chip-bg)'};color:${userVote==='up'?'#10b981':'var(--text-muted)'};border:1px solid ${userVote==='up'?'#bbf7d0':'var(--border)'}"
            onclick="${isSelf?'':'castCommunityVote('+i.id+',\'up\')'}">▲</button>
          <span style="font-size:14px;font-weight:700;color:var(--heading)">${upvotes - downvotes}</span>
          <button class="btn btn-sm" style="padding:4px 8px;border-radius:8px;font-size:13px;font-weight:700;background:${userVote==='down'?'#fee2e2':'var(--chip-bg)'};color:${userVote==='down'?'#ef4444':'var(--text-muted)'};border:1px solid ${userVote==='down'?'#fecaca':'var(--border)'}"
            onclick="${isSelf?'':'castCommunityVote('+i.id+',\'down\')'}">▼</button>
        </div>
        <div style="flex:1">
          <div style="font-size:15px;font-weight:600;color:var(--heading)">${escHtml(i.title)}</div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:2px">${escHtml(i.submitter_name)} · ${i.department||'–'} · ${fmtDate(i.created_at)}</div>
          <div style="font-size:13px;color:var(--text);margin-top:6px;-webkit-line-clamp:2;display:-webkit-box;-webkit-box-orient:vertical;overflow:hidden">${escHtml(i.present_situation)}</div>
          <div style="display:flex;gap:8px;margin-top:8px;align-items:center;flex-wrap:wrap">
            <span class="badge ${statusBadge(i.status)}">${translateStatus(i.status)}</span>
            <span class="badge ${impactBadge(i.impact_level)}">${i.impact_level} Impact</span>
            ${i.ai_score>0?`<span class="${scoreBadgeClass(i.ai_score)}">AI: ${i.ai_score}/100</span>`:''}
            <button class="btn btn-outline btn-sm" onclick="openIdeaDetail(${i.id})">View</button>
          </div>
        </div>
      </div>
    </div>`;
  }).join('');
}

async function castCommunityVote(ideaId, voteType) {
  try {
    const r = await fetch('api/ideas.php?action=community_vote', {
      method:'POST', headers:{'Content-Type':'application/json', ...csrfHeaders()},
      credentials:'same-origin', body:JSON.stringify({idea_id:ideaId,vote_type:voteType})
    });
    const d = await r.json();
    if (d.success) loadBoard();
    else showToast(d.error||'Error','danger');
  } catch(e) { showToast('Network error','danger'); }
}

// ── BULK REVIEW ────────────────────────────────────────────────────
function updateBulkBar() {
  const checked = [...document.querySelectorAll('.bulk-chk:checked')];
  const bar     = document.getElementById('bulk-action-bar');
  const label   = document.getElementById('bulk-count-label');
  if (!bar) return;
  if (checked.length > 0) {
    bar.style.display = 'flex';
    if (label) label.textContent = `${checked.length} idea${checked.length>1?'s':''} selected`;
  } else {
    bar.style.display = 'none';
  }
}

function toggleBulkAll(checked) {
  document.querySelectorAll('.bulk-chk').forEach(c => { c.checked = checked; });
  updateBulkBar();
}

function clearBulkSelection() {
  document.querySelectorAll('.bulk-chk').forEach(c => { c.checked = false; });
  const bar = document.getElementById('bulk-action-bar');
  if (bar) bar.style.display = 'none';
  const selectAll = document.getElementById('bulk-select-all');
  if (selectAll) selectAll.checked = false;
}

async function submitBulkReview(decision) {
  const checked  = [...document.querySelectorAll('.bulk-chk:checked')];
  if (!checked.length) return;
  const ideaIds  = checked.map(c => parseInt(c.dataset.id));
  const comment  = decision === 'Rejected' ? (prompt('Rejection reason (optional):') || '') : '';
  if (!confirm(`${decision} ${ideaIds.length} idea(s)?`)) return;

  let r, d;
  try {
    r = await fetch('api/ideas.php?action=bulk_review', {
      method:'POST', headers:{'Content-Type':'application/json', ...csrfHeaders()},
      credentials:'same-origin', body:JSON.stringify({idea_ids:ideaIds, decision, comment})
    });
    d = await r.json();
  } catch(e) { showToast('Network error','danger'); return; }

  if (d.success) {
    showToast(`${d.processed} idea(s) ${decision.toLowerCase()}d.`, 'success');
    clearBulkSelection();
    loadReviewQueue();
    loadDashboard();
  } else {
    showToast(d.error||'Error','danger');
  }
}

async function loadAudit() {
  if (!['team_lead','project_lead','manager','senior_manager','executive','admin','super_admin'].includes(currentUser?.role)) {
    document.getElementById('audit-tbody').innerHTML = `<tr><td colspan="5" class="text-center"><div class="alert alert-warning">${t('msg.audit_restricted')}</div></td></tr>`;
    return;
  }
  document.getElementById('audit-tbody').innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner"></div> ${t('msg.loading')}</td></tr>`;
  let r, d;
  try {
    r = await fetch('api/users.php?action=audit', {credentials:'same-origin'});
    d = await r.json();
  } catch(e) {
    document.getElementById('audit-tbody').innerHTML = `<tr><td colspan="5" class="text-center alert alert-danger">${t('msg.fail_audit')}</td></tr>`;
    return;
  }
  if (!d.success) {
    document.getElementById('audit-tbody').innerHTML = `<tr><td colspan="5" class="text-center"><div class="alert alert-danger">${d.error||t('msg.fail_audit')}</div></td></tr>`;
    return;
  }
  document.getElementById('audit-tbody').innerHTML = (d.audit||[]).map(w => `
    <tr>
      <td>${fmtDate(w.created_at)}</td>
      <td><strong>${w.idea_code}</strong><br><small>${escHtml(w.idea_title||'').substring(0,40)}</small></td>
      <td><span class="badge ${statusBadge(w.action)}">${translateStatus(w.action)}</span></td>
      <td>${escHtml(w.actor_name)} <small>(${w.actor_role})</small></td>
      <td>${w.comment ? escHtml(w.comment) : '—'}</td>
    </tr>`).join('') || `<tr><td colspan="5" class="text-center">${t('msg.no_audit')}</td></tr>`;
}

let lbPeriod = 'all';
async function loadLeaderboard() {
  document.getElementById('lb-individuals').innerHTML = '<div class="spinner"></div>';
  document.getElementById('lb-departments').innerHTML  = '<div class="spinner"></div>';
  document.getElementById('lb-top-ideas').innerHTML    = '<div class="spinner"></div>';
  let r, d;
  try {
    r = await fetch('api/users.php?action=leaderboard&period=' + lbPeriod, {credentials:'same-origin'});
    d = await r.json();
  } catch(e) {
    document.getElementById('lb-individuals').innerHTML = `<div class="alert alert-danger">${t('msg.fail_leaderboard')}</div>`;
    return;
  }

  const maxPts = Math.max(...(d.individuals||[]).map(u=>u.points), 1);
  const lbEl = document.getElementById('lb-individuals');
  lbEl.innerHTML = (d.individuals||[]).map((u,i) => {
    const ei = engagementIndex(u.avg_score, u.avg_community_rating, u.total_votes_received);
    return `
    <div class="lb-row">
      <div class="lb-rank ${i===0?'rank-1':i===1?'rank-2':i===2?'rank-3':'rank-n'}">${i+1}</div>
      <div class="avatar">${u.avatar_initials||u.name[0]}</div>
      <div style="flex:1">
        <div class="lb-name">${escHtml(u.name)} ${u.id==currentUser?.id?`<span style="font-size:11px;color:#f59e0b">${t('lb.you')}</span>`:''}</div>
        <div class="lb-dept">${u.department||'–'}</div>
        <div class="progress-bar mt-8"><div class="progress-fill" style="width:0%" data-w="${Math.round(u.points/maxPts*100)}"></div></div>
        <div style="margin-top:4px">${engMiniStats(u.avg_community_rating, u.total_votes_received)}</div>
      </div>
      <div style="text-align:right">
        <div class="lb-points">${u.points} ${t('lb.points')}</div>
        <div class="lb-ideas">${u.idea_count||0} ${t('lb.ideas')}</div>
        ${u.avg_score > 0 ? `<span class="${scoreBadgeClass(u.avg_score)}" style="margin-top:2px;display:inline-block">${t('lb.avg_score')}: ${u.avg_score}</span>` : ''}
        <div style="margin-top:4px">${engBadge(u.avg_score, u.avg_community_rating, u.total_votes_received)}</div>
      </div>
    </div>`;
  }).join('') || `<div class="empty-state">${t('msg.no_leaderboard')}</div>`;
  staggerAnimate([...lbEl.querySelectorAll('.lb-row')], 60);
  setTimeout(() => {
    lbEl.querySelectorAll('.progress-fill[data-w]').forEach(bar => {
      bar.style.transition = 'width .8s cubic-bezier(.4,0,.2,1)';
      bar.style.width = bar.dataset.w + '%';
    });
  }, 200);

  const maxDeptPts = Math.max(...(d.departments||[]).map(dep=>dep.dept_points), 1);
  document.getElementById('lb-departments').innerHTML = `
    <div class="bar-chart">${(d.departments||[]).map(dept => `
      <div class="bar-row">
        <span class="bar-label">${escHtml(dept.department||'–').substring(0,12)}</span>
        <div class="bar-track"><div class="bar-fill" style="width:0%;background:linear-gradient(90deg,#374151,#6b7280)" data-w="${Math.round((dept.dept_points||0)/maxDeptPts*100)}"></div></div>
        <span class="bar-val">${dept.dept_points||0}</span>
      </div>`).join('')}</div>`;
  setTimeout(() => {
    document.querySelectorAll('#lb-departments .bar-fill[data-w]').forEach((bar, i) => {
      setTimeout(() => {
        bar.style.transition = 'width .7s cubic-bezier(.4,0,.2,1)';
        bar.style.width = bar.dataset.w + '%';
      }, i * 80);
    });
  }, 100);

  if (d.top_ideas && d.top_ideas.length) {
    document.getElementById('lb-top-ideas').innerHTML = d.top_ideas.map((idea, idx) => `
      <div class="top-idea-row">
        <div class="top-idea-rank">#${idx+1}</div>
        <div style="flex:1">
          <div style="font-size:13px;font-weight:600;color:var(--heading)">${escHtml(idea.title)}</div>
          <div style="font-size:11px;color:var(--subtle)">${idea.idea_code} · ${escHtml(idea.submitter_name)} · ${idea.department||'–'}</div>
        </div>
        <div style="text-align:right">
          <span class="${scoreBadgeClass(idea.ai_score)}">${idea.ai_score}/100</span>
          <div style="font-size:11px;color:var(--subtle);margin-top:2px"><span class="badge ${statusBadge(idea.status)}">${translateStatus(idea.status)}</span></div>
        </div>
      </div>`).join('');
  } else {
    document.getElementById('lb-top-ideas').innerHTML = `<div class="empty-state">${t('msg.no_leaderboard')}</div>`;
  }
}

function activateChip(el, group) {
  document.querySelectorAll(`.chip`).forEach(c => { if (c.closest('.chip-filter') === el.closest('.chip-filter')) c.classList.remove('active'); });
  el.classList.add('active');
  if (group === 'lb-period') { lbPeriod = el.dataset.val; loadLeaderboard(); }
}

async function loadAnalytics() {
  if (!['team_lead','project_lead','manager','senior_manager','executive','admin','super_admin'].includes(currentUser?.role)) {
    document.getElementById('analytics-kpis').innerHTML = `<div class="alert alert-warning" style="grid-column:1/-1">${t('msg.analytics_restricted')}</div>`;
    return;
  }
  document.getElementById('analytics-kpis').innerHTML = '<div class="kpi-card"><div class="spinner"></div></div><div class="kpi-card"><div class="spinner"></div></div><div class="kpi-card"><div class="spinner"></div></div><div class="kpi-card"><div class="spinner"></div></div>';
  document.getElementById('analytics-impact').innerHTML  = '<div class="spinner"></div>';
  document.getElementById('analytics-status').innerHTML  = '<div class="spinner"></div>';
  document.getElementById('analytics-trend').innerHTML   = '<div class="spinner"></div>';
  document.getElementById('analytics-score-dist').innerHTML = '<div class="spinner"></div>';
  let r, d;
  try {
    r = await fetch('api/users.php?action=analytics', {credentials:'same-origin'});
    d = await r.json();
  } catch(e) {
    document.getElementById('analytics-kpis').innerHTML = `<div class="alert alert-danger" style="grid-column:1/-1">${t('msg.fail_analytics')}</div>`;
    return;
  }
  if (!d.success) {
    document.getElementById('analytics-kpis').innerHTML = `<div class="alert alert-danger" style="grid-column:1/-1">${d.error || t('msg.fail_analytics')}</div>`;
    return;
  }

  const counts = {};
  (d.status_summary||[]).forEach(s => counts[s.status] = s.cnt);
  const total   = Object.values(counts).reduce((a,b)=>a+b,0);
  const approved = (counts['Approved']||0) + (counts['Implemented']||0);
  const impl    = counts['Implemented']||0;
  const ss      = d.score_stats || {};

  document.getElementById('analytics-kpis').innerHTML = `
    <div class="kpi-card" style="border-left-color:#1f2937">
      <div class="kpi-icon" style="background:#c8ccd1;color:#374151"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M9 21h6M12 3a6 6 0 016 6c0 2.2-1.1 3.8-2.5 5L15 16H9l-.5-2C7 12.8 6 11.2 6 9a6 6 0 016-6z"/></svg></div>
      <div class="kpi-body"><div class="kpi-val" data-target="${total}">0</div><div class="kpi-label">${t('dash.total')}</div></div>
    </div>
    <div class="kpi-card" style="border-left-color:#10b981">
      <div class="kpi-icon" style="background:#bbf7d0;color:#10b981"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
      <div class="kpi-body"><div class="kpi-val" data-target="${total ? Math.round(approved/total*100) : 0}" data-suffix="%">0%</div><div class="kpi-label">${t('analytics.approval_rate')}</div></div>
    </div>
    <div class="kpi-card" style="border-left-color:#374151">
      <div class="kpi-icon" style="background:#c8ccd1;color:#4b5563"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
      <div class="kpi-body"><div class="kpi-val" data-target="${total ? Math.round(impl/total*100) : 0}" data-suffix="%">0%</div><div class="kpi-label">${t('analytics.impl_rate')}</div></div>
    </div>
    <div class="kpi-card" style="border-left-color:#1f2937">
      <div class="kpi-icon" style="background:#c8ccd1;color:#374151"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg></div>
      <div class="kpi-body"><div class="kpi-val" data-target="${ss.overall_avg || 0}">0</div><div class="kpi-label">${t('analytics.avg_score')}</div></div>
    </div>
  `;
  document.querySelectorAll('#analytics-kpis .kpi-val[data-target]').forEach(el => {
    const suffix = el.dataset.suffix || '';
    const target = parseInt(el.dataset.target);
    const start = performance.now();
    (function step(now) {
      const p = Math.min((now - start) / 900, 1);
      const ease = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * ease) + suffix;
      if (p < 1) requestAnimationFrame(step);
    })(start);
  });

  const impColors = ['#374151','#4b5563','#4b5563','#9ca3af','#9ca3af','#d1d5db'];
  const maxImp = Math.max(...Object.values(d.impact_distribution||{}), 1);
  document.getElementById('analytics-impact').innerHTML = Object.entries(d.impact_distribution||{}).map(([k,v],i) => `
    <div class="bar-row">
      <span class="bar-label">${k}</span>
      <div class="bar-track"><div class="bar-fill" style="width:${Math.round(v/maxImp*100)}%;background:${impColors[i%impColors.length]}"></div></div>
      <span class="bar-val">${v}</span>
    </div>`).join('') || `<div class="empty-state">${t('msg.no_ideas')}</div>`;

  const statusColors = {'Submitted':'#374151','Under Review':'#f59e0b','Approved':'#10b981','Rejected':'#ef4444','Implemented':'#4b5563','Draft':'#94a3b8'};
  document.getElementById('analytics-status').innerHTML = `
    <div class="bar-chart">${(d.status_summary||[]).map(s => `
      <div class="bar-row">
        <span class="bar-label">${translateStatus(s.status)}</span>
        <div class="bar-track"><div class="bar-fill" style="width:0%;background:${statusColors[s.status]||'#ccc'}" data-w="${Math.round(s.cnt/Math.max(total,1)*100)}"></div></div>
        <span class="bar-val">${s.cnt}</span>
      </div>`).join('')}</div>`;
  setTimeout(() => {
    document.querySelectorAll('#analytics-status .bar-fill[data-w]').forEach((bar, i) => {
      setTimeout(() => { bar.style.transition='width .7s cubic-bezier(.4,0,.2,1)'; bar.style.width=bar.dataset.w+'%'; }, i*80);
    });
  }, 150);

  const maxTrend = Math.max(...(d.trend||[]).map(t=>t.total), 1);
  document.getElementById('analytics-trend').innerHTML = (d.trend||[]).reverse().map(t => `
    <div class="bar-row">
      <span class="bar-label">${t.month}</span>
      <div class="bar-track"><div class="bar-fill" style="width:0%;background:linear-gradient(90deg,#374151,#6b7280)" data-w="${Math.round(t.total/maxTrend*100)}"></div></div>
      <span class="bar-val">${t.total}</span>
    </div>`).join('') || '<div class="empty-state">No trend data yet.</div>';
  setTimeout(() => {
    document.querySelectorAll('#analytics-trend .bar-fill[data-w]').forEach((bar, i) => {
      setTimeout(() => { bar.style.transition='width .7s cubic-bezier(.4,0,.2,1)'; bar.style.width=bar.dataset.w+'%'; }, i*80);
    });
  }, 150);

  const hq = parseInt(ss.high_quality || 0);
  const mq = parseInt(ss.medium_quality || 0);
  const lq = parseInt(ss.low_quality || 0);
  const maxQ = Math.max(hq, mq, lq, 1);
  document.getElementById('analytics-score-dist').innerHTML = `
    <div class="bar-chart">
      <div class="bar-row"><span class="bar-label">${t('analytics.high')}</span><div class="bar-track"><div class="bar-fill" style="width:${Math.round(hq/maxQ*100)}%;background:#10b981"></div></div><span class="bar-val">${hq}</span></div>
      <div class="bar-row"><span class="bar-label">${t('analytics.med')}</span><div class="bar-track"><div class="bar-fill" style="width:${Math.round(mq/maxQ*100)}%;background:#f59e0b"></div></div><span class="bar-val">${mq}</span></div>
      <div class="bar-row"><span class="bar-label">${t('analytics.low_score')}</span><div class="bar-track"><div class="bar-fill" style="width:${Math.round(lq/maxQ*100)}%;background:#ef4444"></div></div><span class="bar-val">${lq}</span></div>
    </div>
    <div style="font-size:11px;color:var(--subtle);margin-top:8px">${t('analytics.avg_note')} <strong>${ss.overall_avg || 0}/100</strong></div>`;
}

function buildRoleOptions() {
  const opts = [
    ['trainee','Trainee'],['employee','Employee'],['team_lead','Team Lead'],
    ['project_lead','Project Lead'],['manager','Manager'],['senior_manager','Senior Manager'],
    ['executive','Executive']
  ];
  if (currentUser?.role === 'super_admin') opts.push(['admin','Org Admin']);
  return opts.map(([v,l]) => `<option value="${v}">${l}</option>`).join('');
}

function formatRole(role) {
  const map = {
    super_admin:'Super Admin', admin:'Org Admin', executive:'Executive',
    senior_manager:'Senior Manager', manager:'Manager',
    project_lead:'Project Lead', team_lead:'Team Lead',
    employee:'Employee', trainee:'Trainee', platform_admin:'Platform Admin'
  };
  return map[role] || (role.charAt(0).toUpperCase() + role.slice(1));
}

const ROLE_COLORS = {
  admin:'#374151', executive:'#4b5563', senior_manager:'#6b7280',
  manager:'#f59e0b', project_lead:'#0891b2', team_lead:'#0284c7',
  employee:'#10b981', trainee:'#64748b'
};

function switchSaTab(el, tabId) {
  document.querySelectorAll('#page-super-admin .tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.sa-pane').forEach(p => p.style.display = 'none');
  el.classList.add('active');
  const pane = document.getElementById(tabId);
  if (pane) pane.style.display = 'block';
}

let _saUsersData = [];
function filterSaUsers() {
  const q = (document.getElementById('sa-user-search').value || '').toLowerCase();
  const rows = document.querySelectorAll('#hierarchy-users-tbody tr');
  rows.forEach(r => {
    r.style.display = !q || r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

function renderHierarchyNode(node, depth) {
  const color = ROLE_COLORS[node.role] || '#888';
  const ml = depth * 36;
  let connector = '';
  if (depth > 0) {
    connector = `<div style="position:absolute;left:${ml - 18}px;top:50%;width:14px;height:1px;background:var(--border)"></div>`;
  }
  let html = `
    <div style="position:relative;margin-left:${ml}px;margin-bottom:8px">
      ${connector}
      <div style="border-left:3px solid ${color};padding:11px 16px;background:var(--surface);border-radius:var(--r);box-shadow:var(--shadow-sm);display:flex;align-items:center;gap:12px;flex-wrap:wrap;transition:box-shadow .15s">
        <div class="avatar" style="background:linear-gradient(135deg,${color},${color}cc);flex-shrink:0;font-weight:800">${escHtml(node.avatar_initials||node.name[0])}</div>
        <div style="flex:1;min-width:180px">
          <div style="font-weight:700;font-size:13px;color:var(--text)">${escHtml(node.name)}</div>
          <div style="font-size:11px;color:var(--subtle);margin-top:2px">${escHtml(node.employee_id)} &middot; ${escHtml(node.department||'–')} &middot; ${escHtml(node.location||'–')}</div>
        </div>
        <span class="badge" style="background:${color}18;color:${color};border:1px solid ${color}40;font-weight:700">${formatRole(node.role)}</span>
        <div style="display:flex;gap:16px;font-size:12px;color:var(--subtle)">
          <span title="Points"><strong style="color:var(--text)">${node.points}</strong> ${t('lb.points')}</span>
          <span title="Ideas submitted"><strong style="color:var(--text)">${node.idea_count}</strong> ${t('lb.ideas')}</span>
        </div>
      </div>
    </div>`;
  if (node.children && node.children.length) {
    const sorted = [...node.children].sort((a,b) => {
      const o = {manager:0,employee:1};
      return (o[a.role]??2) - (o[b.role]??2) || a.name.localeCompare(b.name);
    });
    sorted.forEach(c => { html += renderHierarchyNode(c, depth + 1); });
  }
  return html;
}

async function loadHierarchy() {
  const [hierRes, dashRes] = await Promise.all([
    fetch('api/users.php?action=hierarchy',    {credentials:'same-origin'}),
    fetch('api/ideas.php?action=dashboard',    {credentials:'same-origin'}),
  ]);
  const hier = await hierRes.json();
  const dash = await dashRes.json();
  if (!hier.success) return;

  // ── KPI Strip ──────────────────────────────────────────────────
  const s = hier.stats;
  const counts = dash.counts || {};
  const pending = (counts['Submitted']||0) + (counts['Under Review']||0);
  document.getElementById('sa-last-updated').textContent =
    t('sa.last_refreshed') + ' ' + new Date().toLocaleTimeString();
  document.getElementById('sa-kpi-strip').innerHTML = [
    [t('dash.total'),       dash.total||0,            '#374151', 'Excluding drafts'],
    [t('status.review'),    pending,                  '#ef4444', 'Submitted + Under Review'],
    [t('dash.approved'),    counts['Approved']||0,    '#f59e0b', 'Awaiting implementation'],
    [t('dash.implemented'), counts['Implemented']||0, '#10b981', 'Completed ideas'],
    [t('pa.total_users'),   s.total,                  '#4b5563', `${s.admins} admins · ${s.managers} mgrs · ${s.employees} emp`],
    [t('sa.executives'),    s.executives,             '#2563eb', 'Executive-level accounts'],
  ].map(([label, val, color, sub]) => `
    <div class="kpi-card" style="border-left-color:${color}">
      <div class="kpi-val" style="color:${color}">${val}</div>
      <div class="kpi-label">${label}</div>
      <div style="font-size:10px;color:var(--subtle);margin-top:3px">${sub}</div>
    </div>`).join('');

  // ── Overview Tab ───────────────────────────────────────────────
  const statuses = ['Submitted','Under Review','Approved','Rejected','Implemented'];
  const statusColors = {'Submitted':'#2563eb','Under Review':'#f59e0b','Approved':'#10b981','Rejected':'#ef4444','Implemented':'#4b5563'};
  const maxCount = Math.max(...statuses.map(s => counts[s]||0), 1);
  document.getElementById('sa-status-dist').innerHTML = `
    <div class="bar-chart">
      ${statuses.map(s => `
        <div class="bar-row">
          <span class="bar-label">${translateStatus(s)}</span>
          <div class="bar-track">
            <div class="bar-fill" style="width:${Math.round((counts[s]||0)/maxCount*100)}%;background:${statusColors[s]}"></div>
          </div>
          <span class="bar-val">${counts[s]||0}</span>
        </div>`).join('')}
    </div>`;

  const tlColors = {Submitted:'blue',Approved:'green',Rejected:'red',Implemented:'purple',Reviewed:'orange',Commented:'orange',Reopened:'blue'};
  document.getElementById('sa-recent-activity').innerHTML = (dash.recent||[]).length
    ? (dash.recent||[]).map(r => `
        <div class="tl-item">
          <div class="tl-dot tl-dot-${tlColors[r.action]||'blue'}" style="font-size:9px;font-weight:800">${(r.action||'').substring(0,3).toUpperCase()}</div>
          <div>
            <div class="tl-title">${escHtml(r.idea_code)} — ${escHtml(r.title||'')}</div>
            <div style="font-size:11px;color:var(--subtle);margin-top:2px">${r.action} by <strong>${escHtml(r.actor_name)}</strong> &middot; ${timeAgo(r.created_at)}</div>
          </div>
        </div>`).join('')
    : `<div style="color:var(--subtle);font-size:13px;padding:10px 0">${t('msg.no_ideas')}</div>`;

  // ── Hierarchy Tree ─────────────────────────────────────────────
  const byId = {};
  hier.users.forEach(u => { byId[u.id] = {...u, children: []}; });
  const roots = [];
  hier.users.forEach(u => {
    if (u.manager_id && byId[u.manager_id]) byId[u.manager_id].children.push(byId[u.id]);
    else roots.push(byId[u.id]);
  });
  const rootOrder = {admin:0,executive:1,manager:2,employee:3};
  roots.sort((a,b) => (rootOrder[a.role]??9) - (rootOrder[b.role]??9) || a.name.localeCompare(b.name));
  document.getElementById('hierarchy-tree').innerHTML = roots.length
    ? roots.map(n => renderHierarchyNode(n, 0)).join('')
    : `<div style="color:var(--subtle);padding:16px">${t('msg.no_ideas')}</div>`;

  // ── User Management Table ──────────────────────────────────────
  _saUsersData = hier.users;
  document.getElementById('hierarchy-users-tbody').innerHTML = hier.users.map(u => `
    <tr>
      <td><div style="display:flex;align-items:center;gap:9px">
        <div class="avatar" style="background:linear-gradient(135deg,${ROLE_COLORS[u.role]||'#888'},${ROLE_COLORS[u.role]||'#888'}99)">${escHtml(u.avatar_initials||u.name[0])}</div>
        <div>
          <div style="font-weight:600">${escHtml(u.name)}</div>
          <div style="font-size:11px;color:var(--subtle)">${escHtml(u.email)}</div>
        </div>
      </div></td>
      <td style="font-size:12px;color:var(--subtle)">${escHtml(u.employee_id)}</td>
      <td><span class="badge" style="background:${ROLE_COLORS[u.role]||'#888'}18;color:${ROLE_COLORS[u.role]||'#888'};border:1px solid ${ROLE_COLORS[u.role]||'#888'}40">${formatRole(u.role)}</span></td>
      <td>${escHtml(u.department||'–')}</td>
      <td>${escHtml(u.business_unit||'–')}</td>
      <td style="font-size:12px">${escHtml(u.email)}</td>
      <td style="font-size:12px;color:var(--subtle)">${escHtml(u.manager_name||'—')}</td>
      <td><strong>${u.points}</strong></td>
      <td>${u.idea_count}</td>
    </tr>`).join('') || `<tr><td colspan="9" class="text-center">${t('msg.no_ideas')}</td></tr>`;
}

async function batchRescoreSa() {
  const btn = document.getElementById('sa-rescore-btn');
  if (btn) { btn.disabled = true; btn.querySelector('span').textContent = t('msg.rescoring'); }
  try {
    const r = await fetch('api/score.php?action=batch_rescore', {method:'POST', credentials:'same-origin'});
    const d = await r.json();
    document.getElementById('sa-rescore-result').innerHTML = d.success
      ? `<span class="alert alert-success" style="display:inline-block;padding:6px 14px">${t('rescore.ok').replace('{n}', d.updated)}</span>`
      : `<span class="alert alert-danger"  style="display:inline-block;padding:6px 14px">${escHtml(d.error||'Error.')}</span>`;
  } catch(e) {
    document.getElementById('sa-rescore-result').innerHTML = `<span class="alert alert-danger" style="display:inline-block;padding:6px 14px">${t('msg.server_error')}</span>`;
  }
  if (btn) {
    btn.disabled = false;
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg> <span data-i18n="btn.rescore_all">${t('btn.rescore_all')}</span>`;
  }
}

async function batchRescore() {
  const btn = document.querySelector('#atab4 .btn-warning');
  if (btn) { btn.disabled = true; btn.textContent = t('msg.rescoring'); }
  try {
    const r = await fetch('api/score.php?action=batch_rescore', {method:'POST', credentials:'same-origin'});
    const d = await r.json();
    document.getElementById('rescore-result').innerHTML = d.success
      ? `<span class="alert alert-success" style="display:inline-block">${t('rescore.ok').replace('{n}', d.updated)}</span>`
      : `<span class="alert alert-danger" style="display:inline-block">${d.error || 'Error.'}</span>`;
  } catch(e) {
    document.getElementById('rescore-result').innerHTML = `<span class="alert alert-danger" style="display:inline-block">${t('msg.server_error')}</span>`;
  }
  if (btn) { btn.disabled = false; btn.textContent = t('btn.rescore_all'); }
}

// ═══════════════════════════════════════════════════════════════
// ORGANISATION CREATION (Platform Admin)
// ═══════════════════════════════════════════════════════════════
function openCreateOrgModal() {
  ['co-org-name','co-slug','co-admin-name','co-admin-email','co-admin-pass'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  document.getElementById('create-org-error').style.display = 'none';
  const btn = document.getElementById('co-submit-btn');
  btn.disabled = false; btn.textContent = 'Create Organisation';
  document.getElementById('modal-create-org').classList.add('open');
}

// Auto-generate slug from org name
document.getElementById('co-org-name')?.addEventListener('input', function() {
  const slug = document.getElementById('co-slug');
  if (slug && !slug.dataset.edited) {
    slug.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }
});
document.getElementById('co-slug')?.addEventListener('input', function() {
  this.dataset.edited = '1';
});

async function submitCreateOrg() {
  const btn = document.getElementById('co-submit-btn');
  const err = document.getElementById('create-org-error');
  err.style.display = 'none';

  const orgName   = document.getElementById('co-org-name').value.trim();
  const slug      = document.getElementById('co-slug').value.trim();
  const adminName = document.getElementById('co-admin-name').value.trim();
  const adminEmail= document.getElementById('co-admin-email').value.trim();
  const adminPass = document.getElementById('co-admin-pass').value;

  if (!orgName || !slug || !adminName || !adminEmail || !adminPass) {
    err.textContent = 'All fields are required.';
    err.style.display = 'block'; return;
  }

  btn.disabled = true; btn.textContent = 'Creating…';

  try {
    const r = await fetch('api/platform.php?action=create_tenant', {
      method: 'POST', headers: {'Content-Type':'application/json', ...csrfHeaders()},
      body: JSON.stringify({org_name: orgName, slug, admin_name: adminName, admin_email: adminEmail, admin_password: adminPass})
    });
    const d = await r.json();
    if (d.success) {
      closeModal('modal-create-org');
      showToast(`Organisation "${d.org_name}" created! Login URL: ?org=${d.slug}`, 'success');
      // Show success details
      setTimeout(() => {
        alert(`✅ Organisation created!\n\nOrg Code: ${d.slug}\nAdmin Email: ${d.admin_email}\nLogin URL: index.php?org=${d.slug}\n\nShare the org code and admin credentials with the organisation.`);
      }, 300);
      loadPlatformDashboard();
    } else {
      err.textContent = d.error || 'Failed to create organisation.';
      err.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Create Organisation';
    }
  } catch(e) {
    err.textContent = 'Server error. Please try again.';
    err.style.display = 'block';
    btn.disabled = false; btn.textContent = 'Create Organisation';
  }
}

// ═══════════════════════════════════════════════════════════════
// ORG SETTINGS (Feature 13)
// ═══════════════════════════════════════════════════════════════
async function loadOrgSettings() {
  const el = document.getElementById('org-settings-form');
  if (!el) return;
  el.innerHTML = '<div class="spinner"></div>';
  let d;
  try {
    const r = await fetch('api/settings.php?action=get', {credentials:'same-origin'});
    d = await r.json();
  } catch(e) { el.innerHTML = '<div class="alert alert-danger">Failed to load settings.</div>'; return; }
  if (!d.success) { el.innerHTML = `<div class="alert alert-danger">${d.error||'Error'}</div>`; return; }
  const s = d.settings;

  // Pre-compute approval role check states to avoid complex expressions in template
  const revDefault = 'team_lead,project_lead,manager,senior_manager';
  const finDefault = 'executive,admin,super_admin';
  const revRoles = (s.approval_reviewer_roles || revDefault).split(',');
  const finRoles = (s.approval_final_approver_roles || finDefault).split(',');
  const revCheckboxes = ['team_lead','project_lead','manager','senior_manager'].map(r =>
    `<label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:12px;background:var(--surface);padding:4px 10px;border-radius:4px;border:1px solid var(--border)">
      <input type="checkbox" name="approval_reviewer_roles" value="${r}"${revRoles.includes(r)?' checked':''}/>
      ${r.replace(/_/g,' ')}
    </label>`).join('');
  const finCheckboxes = ['executive','admin','super_admin'].map(r =>
    `<label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:12px;background:var(--surface);padding:4px 10px;border-radius:4px;border:1px solid var(--border)">
      <input type="checkbox" name="approval_final_approver_roles" value="${r}"${finRoles.includes(r)?' checked':''}/>
      ${r.replace(/_/g,' ')}
    </label>`).join('');

  el.innerHTML = `
    <form onsubmit="saveOrgSettings(event)" style="max-width:600px">
      <div style="font-size:13px;font-weight:600;color:var(--heading);margin-bottom:16px">Review &amp; SLA</div>
      <div class="form-row">
        <div class="form-group">
          <label>Review SLA Days</label>
          <input class="form-control" name="review_sla_days" type="number" min="1" max="90" value="${escHtml(s.review_sla_days||'7')}"/>
        </div>
        <div class="form-group">
          <label>Escalation Days</label>
          <input class="form-control" name="escalation_days" type="number" min="1" max="180" value="${escHtml(s.escalation_days||'14')}"/>
        </div>
      </div>
      <div style="font-size:13px;font-weight:600;color:var(--heading);margin:16px 0 12px">Feature Flags</div>
      <div class="form-row">
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="anonymous_allowed" value="1"${s.anonymous_allowed==='1'?' checked':''} style="accent-color:var(--primary)"/>
            Allow Anonymous Submissions
          </label>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="public_board_enabled" value="1"${s.public_board_enabled==='1'?' checked':''} style="accent-color:var(--primary)"/>
            Enable Public Idea Board
          </label>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="challenges_enabled" value="1"${s.challenges_enabled==='1'?' checked':''} style="accent-color:var(--primary)"/>
            Enable Challenges
          </label>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="email_enabled" value="1"${s.email_enabled==='1'?' checked':''} style="accent-color:var(--primary)"/>
            Enable Email Notifications
          </label>
        </div>
      </div>
      <div style="font-size:13px;font-weight:600;color:var(--heading);margin:16px 0 12px">Approval Workflow</div>
      <div class="form-group" style="margin-bottom:14px">
        <label style="font-weight:500;margin-bottom:8px;display:block">Workflow Mode</label>
        <div style="display:flex;gap:20px">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
            <input type="radio" name="approval_mode" value="default"${s.approval_mode!=='custom'?' checked':''}
              onclick="document.getElementById('custom-hierarchy-panel').style.display='none'"/>
            Use Platform Default
          </label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
            <input type="radio" name="approval_mode" value="custom"${s.approval_mode==='custom'?' checked':''}
              onclick="document.getElementById('custom-hierarchy-panel').style.display='block'"/>
            Customize My Approval Hierarchy
          </label>
        </div>
        <div style="font-size:11px;color:var(--subtle);margin-top:4px">Platform Default: Team Lead → Project Lead → Manager → Senior Manager → (Executive/Admin/Super Admin final)</div>
      </div>
      <div id="custom-hierarchy-panel" style="${s.approval_mode==='custom'?'':'display:none'};border-left:2px solid var(--primary);padding-left:14px;margin-bottom:14px">
        <div class="form-group" style="margin-bottom:12px">
          <label style="font-weight:500;margin-bottom:6px;display:block">Reviewer Roles (escalation chain — who can review before final approval)</label>
          <div style="display:flex;flex-wrap:wrap;gap:6px">${revCheckboxes}</div>
          <div style="font-size:11px;color:var(--subtle);margin-top:4px">Ideas move up the chain through selected roles. At least one role must be selected.</div>
        </div>
        <div class="form-group" style="margin-bottom:12px">
          <label style="font-weight:500;margin-bottom:6px;display:block">Final Approver Roles (can give final approval — no further escalation)</label>
          <div style="display:flex;flex-wrap:wrap;gap:6px">${finCheckboxes}</div>
          <div style="font-size:11px;color:var(--subtle);margin-top:4px">When an idea reaches any of these roles, it gets a final Approved or Rejected decision — no more escalation.</div>
        </div>
        <div class="form-group" style="margin-bottom:8px;max-width:200px">
          <label style="font-weight:500;margin-bottom:6px;display:block">Default Approval Threshold (%) — for multi-reviewer mode</label>
          <input class="form-control" name="approval_threshold" type="number" min="1" max="100"
            value="${escHtml(s.approval_threshold||'100')}"/>
          <div style="font-size:11px;color:var(--subtle);margin-top:4px">% of reviewers who must approve. 100% = all must approve.</div>
        </div>
        <button type="button" class="btn btn-outline" style="font-size:12px;margin-bottom:10px"
          onclick="resetApprovalWorkflow()">Reset to Platform Defaults</button>
      </div>
      <div style="font-size:13px;font-weight:600;color:var(--heading);margin:16px 0 12px">SMTP Email Settings</div>
      <div class="form-row">
        <div class="form-group"><label>SMTP Host</label><input class="form-control" name="smtp_host" value="${escHtml(s.smtp_host||'')}"/></div>
        <div class="form-group"><label>SMTP Port</label><input class="form-control" name="smtp_port" type="number" value="${escHtml(s.smtp_port||'587')}"/></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>SMTP User</label><input class="form-control" name="smtp_user" value="${escHtml(s.smtp_user||'')}"/></div>
        <div class="form-group"><label>SMTP Password</label><input class="form-control" name="smtp_pass" type="password" placeholder="(unchanged if blank)"/></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>From Email</label><input class="form-control" name="smtp_from" type="email" value="${escHtml(s.smtp_from||'')}"/></div>
        <div class="form-group"><label>From Name</label><input class="form-control" name="smtp_from_name" value="${escHtml(s.smtp_from_name||'IFQM Ideation')}"/></div>
      </div>
      <div style="display:flex;gap:8px;margin-top:16px">
        <button type="submit" class="btn btn-primary">Save Settings</button>
        <button type="button" class="btn btn-outline" onclick="sendTestEmail()">Send Test Email</button>
      </div>
      <div id="settings-save-msg" style="margin-top:10px;font-size:13px"></div>
    </form>`;
}

async function saveOrgSettings(e) {
  e.preventDefault();
  const form = e.target;
  const fd   = new FormData(form);
  const data = {};
  // Collect text/number fields
  ['review_sla_days','escalation_days','smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from','smtp_from_name','approval_threshold'].forEach(k => {
    data[k] = fd.get(k) || '';
  });
  // Radio buttons
  data['approval_mode'] = fd.get('approval_mode') || 'default';
  // Checkboxes (binary flags)
  ['anonymous_allowed','public_board_enabled','challenges_enabled','email_enabled'].forEach(k => {
    data[k] = fd.get(k) === '1' ? '1' : '0';
  });
  // Multi-select: approval role lists (comma-joined)
  ['approval_reviewer_roles','approval_final_approver_roles'].forEach(key => {
    const vals = [...fd.getAll(key)];
    data[key] = vals.join(',');
  });
  const msgEl = document.getElementById('settings-save-msg');
  try {
    const r = await fetch('api/settings.php?action=update', {
      method:'POST', headers:{'Content-Type':'application/json', ...csrfHeaders()},
      credentials:'same-origin', body:JSON.stringify(data)
    });
    const d = await r.json();
    if (d.success) {
      if (msgEl) { msgEl.style.color='#10b981'; msgEl.textContent='Settings saved successfully.'; }
      showToast('Org settings saved.','success');
    } else {
      if (msgEl) { msgEl.style.color='#ef4444'; msgEl.textContent=d.error||'Failed to save.'; }
    }
  } catch(e) { if (msgEl) { msgEl.style.color='#ef4444'; msgEl.textContent='Network error.'; } }
}

async function sendTestEmail() {
  showToast('Sending test email…','info');
  try {
    const r = await fetch('api/settings.php?action=send_test_email', {credentials:'same-origin'});
    const d = await r.json();
    if (d.success) showToast('Test email sent!','success');
    else showToast(d.error||'Failed','danger');
  } catch(e) { showToast('Network error','danger'); }
}

// ═══════════════════════════════════════════════════════════════
// USER MANAGEMENT (Org Admin)
// ═══════════════════════════════════════════════════════════════
let _allAdminUsers = [];
let _managersCache = [];

async function loadAdminUsers() {
  const r = await fetch('api/users.php?action=admin_users', {credentials:'same-origin'});
  const d = await r.json();
  _allAdminUsers = d.users || [];
  renderAdminUsers(_allAdminUsers);

  // Show tenant DB name
  const dbEl = document.getElementById('admin-db-name');
  if (dbEl && currentUser?.org_slug) {
    dbEl.innerHTML = `<strong>Database:</strong> ifqm_${currentUser.org_slug}`;
  }
}

function filterAdminUsers() {
  const q = document.getElementById('admin-user-search')?.value.toLowerCase() || '';
  renderAdminUsers(_allAdminUsers.filter(u =>
    u.name.toLowerCase().includes(q) ||
    u.email.toLowerCase().includes(q) ||
    (u.employee_id||'').toLowerCase().includes(q)
  ));
}

const ROLE_BADGE_STYLE = {
  admin:     'background:#c8ccd1;color:#374151;border:1px solid #6b7280',
  executive: 'background:#c8ccd1;color:#4b5563;border:1px solid #9ca3af',
  manager:   'background:#fef3c7;color:#92400e;border:1px solid #fde68a',
  employee:  'background:#a7f3d0;color:#065f46;border:1px solid #a7f3d0',
};

function renderAdminUsers(users) {
  const tbody = document.getElementById('admin-users-tbody');
  if (!users.length) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center"><div class="empty-state">No users yet. Click "+ Add User" to get started.</div></td></tr>`;
    return;
  }
  tbody.innerHTML = users.map(u => {
    const badgeStyle = ROLE_BADGE_STYLE[u.role] || '';
    const statusBadge = u.status === 'inactive'
      ? '<span style="font-size:10px;background:#fee2e2;color:#ef4444;padding:1px 8px;border-radius:99px;border:1px solid #fca5a5">Inactive</span>'
      : '<span style="font-size:10px;background:#bbf7d0;color:#166534;padding:1px 8px;border-radius:99px;border:1px solid #bbf7d0">Active</span>';
    const isProtected = u.role === 'super_admin' || u.id === currentUser?.id;
    return `<tr>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="avatar" style="width:30px;height:30px;font-size:11px">${escHtml(u.avatar_initials||u.name[0])}</div>
          <div>
            <div style="font-weight:600;font-size:13px">${escHtml(u.name)}</div>
            <div style="font-size:11px;color:var(--subtle)">${escHtml(u.employee_id)} · ${escHtml(u.email)}</div>
          </div>
        </div>
      </td>
      <td><span class="badge" style="${badgeStyle}">${formatRole(u.role)}</span></td>
      <td style="font-size:12px">${escHtml(u.department||'–')}</td>
      <td style="font-size:12px;color:var(--subtle)">${escHtml(u.manager_name||'–')}</td>
      <td><strong>${u.points}</strong></td>
      <td>${statusBadge}</td>
      <td>
        ${isProtected ? '<span style="font-size:11px;color:var(--subtle)">—</span>' : `
          <div style="display:flex;gap:6px">
            <button class="btn btn-outline btn-sm" onclick="openEditUserModal(${u.id})">Edit</button>
            <button class="btn btn-sm" style="background:#fee2e2;color:#ef4444;border:1px solid #fca5a5" onclick="deleteUser(${u.id},'${escHtml(u.name)}')">Remove</button>
          </div>`}
      </td>
    </tr>`;
  }).join('');
}

async function loadManagersCache() {
  if (_managersCache.length) return;
  try {
    const r = await fetch('api/users.php?action=managers', {credentials:'same-origin'});
    const d = await r.json();
    _managersCache = d.managers || [];
  } catch(e) {}
}

function populateManagerDropdown(excludeId = null) {
  const sel = document.getElementById('uf-manager');
  sel.innerHTML = '<option value="">— None —</option>';
  _managersCache.forEach(m => {
    if (m.id === excludeId) return;
    const opt = document.createElement('option');
    opt.value = m.id; opt.textContent = `${m.name} (${formatRole(m.role)})`;
    sel.appendChild(opt);
  });
}

async function openCreateUserModal() {
  await loadManagersCache();
  document.getElementById('uf-id').value = '';
  document.getElementById('user-form-title').textContent = 'Add User';
  ['uf-name','uf-emp-id','uf-email','uf-password','uf-dept','uf-bu','uf-location'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  document.getElementById('uf-role').value = 'employee';
  document.getElementById('uf-status').value = 'active';
  document.getElementById('uf-pass-group').style.display = '';
  document.getElementById('uf-status-group').style.display = 'none';
  document.getElementById('user-form-error').style.display = 'none';
  document.getElementById('uf-submit-btn').disabled = false;
  document.getElementById('uf-submit-btn').textContent = 'Save User';

  const roleSel = document.getElementById('uf-role');
  roleSel.innerHTML = buildRoleOptions();

  populateManagerDropdown();
  document.getElementById('modal-user-form').classList.add('open');
}

async function openEditUserModal(userId) {
  await loadManagersCache();
  const u = _allAdminUsers.find(x => x.id === userId);
  if (!u) return;

  document.getElementById('uf-id').value = u.id;
  document.getElementById('user-form-title').textContent = 'Edit User';
  document.getElementById('uf-name').value     = u.name || '';
  document.getElementById('uf-emp-id').value   = u.employee_id || '';
  document.getElementById('uf-email').value    = u.email || '';
  document.getElementById('uf-dept').value     = u.department || '';
  document.getElementById('uf-bu').value       = u.business_unit || '';
  document.getElementById('uf-location').value = u.location || '';
  document.getElementById('uf-role').value     = u.role;
  document.getElementById('uf-status').value   = u.status || 'active';
  document.getElementById('uf-pass-group').style.display = 'none';
  document.getElementById('uf-status-group').style.display = '';
  document.getElementById('user-form-error').style.display = 'none';
  document.getElementById('uf-submit-btn').disabled = false;
  document.getElementById('uf-submit-btn').textContent = 'Save Changes';

  const roleSel = document.getElementById('uf-role');
  roleSel.innerHTML = buildRoleOptions();
  roleSel.value = u.role;

  populateManagerDropdown(u.id);
  document.getElementById('uf-manager').value = u.manager_id || '';
  document.getElementById('modal-user-form').classList.add('open');
}

async function submitUserForm() {
  const btn = document.getElementById('uf-submit-btn');
  const err = document.getElementById('user-form-error');
  err.style.display = 'none';

  const id = document.getElementById('uf-id').value;
  const isEdit = !!id;

  const payload = {
    name:          document.getElementById('uf-name').value.trim(),
    email:         document.getElementById('uf-email').value.trim(),
    employee_id:   document.getElementById('uf-emp-id').value.trim(),
    role:          document.getElementById('uf-role').value,
    manager_id:    document.getElementById('uf-manager').value || null,
    department:    document.getElementById('uf-dept').value.trim(),
    business_unit: document.getElementById('uf-bu').value.trim(),
    location:      document.getElementById('uf-location').value.trim(),
  };

  if (isEdit) {
    payload.id = parseInt(id);
    payload.status = document.getElementById('uf-status').value;
  } else {
    payload.password = document.getElementById('uf-password').value;
  }

  btn.disabled = true; btn.textContent = 'Saving…';

  try {
    const action = isEdit ? 'update_user' : 'create_user';
    const r = await fetch(`api/users.php?action=${action}`, {
      method: 'POST', headers: {'Content-Type':'application/json', ...csrfHeaders()},
      credentials: 'same-origin', body: JSON.stringify(payload)
    });
    const d = await r.json();
    if (d.success) {
      closeModal('modal-user-form');
      _managersCache = []; // Invalidate cache
      showToast(isEdit ? 'User updated.' : 'User created successfully.', 'success');
      loadAdminUsers();
    } else {
      err.textContent = d.error || 'Failed to save user.';
      err.style.display = 'block';
      btn.disabled = false; btn.textContent = isEdit ? 'Save Changes' : 'Save User';
    }
  } catch(e) {
    err.textContent = 'Server error. Please try again.';
    err.style.display = 'block';
    btn.disabled = false; btn.textContent = isEdit ? 'Save Changes' : 'Save User';
  }
}

async function deleteUser(id, name) {
  if (!confirm(`Remove "${name}" from the organisation?\n\nIf they have submitted ideas, they will be deactivated instead of deleted.`)) return;
  try {
    const r = await fetch('api/users.php?action=delete_user', {
      method: 'POST', headers: {'Content-Type':'application/json', ...csrfHeaders()},
      credentials: 'same-origin', body: JSON.stringify({id})
    });
    const d = await r.json();
    if (d.success) {
      showToast(d.deactivated ? `${name} deactivated (has submitted ideas).` : `${name} removed.`, 'info');
      _managersCache = [];
      loadAdminUsers();
    } else {
      showToast('Error: ' + (d.error || 'Unknown error'), 'danger');
    }
  } catch(e) {
    showToast('Server error.', 'danger');
  }
}

function renderProfile() {
  if (!currentUser) return;
  const u = currentUser;
  document.getElementById('profile-avatar').textContent   = u.avatar_initials || u.name[0];
  document.getElementById('profile-name').textContent     = u.name;
  document.getElementById('profile-empid').textContent    = u.employee_id;
  document.getElementById('profile-role-badge').textContent = formatRole(u.role);
  document.getElementById('profile-table').innerHTML = `
    <tr><td style="color:var(--subtle);padding:5px 0">${t('profile.dept')}</td><td>${u.department||'–'}</td></tr>
    <tr><td style="color:var(--subtle);padding:5px 0">${t('profile.email_lbl')}</td><td>${u.email}</td></tr>
    <tr><td style="color:var(--subtle);padding:5px 0">${t('profile.phone')}</td><td>${u.phone||'–'}</td></tr>
    <tr><td style="color:var(--subtle);padding:5px 0">${t('profile.reports_to')}</td><td>${u.manager_name||'–'}</td></tr>
    <tr><td style="color:var(--subtle);padding:5px 0">${t('profile.bu')}</td><td>${u.business_unit||'–'}</td></tr>
    <tr><td style="color:var(--subtle);padding:5px 0">${t('profile.loc')}</td><td>${u.location||'–'}</td></tr>
  `;
  document.getElementById('profile-stats').innerHTML = `
    <div class="mini-stat"><div class="mini-stat-val">${u.points}</div><div class="mini-stat-label">${t('profile.total_pts')}</div></div>
  `;
}

async function loadNotifications() {
  const r = await fetch('api/users.php?action=notifications', {credentials:'same-origin'});
  const d = await r.json();
  const count = d.unread_count || 0;
  const badge = document.getElementById('notif-count');
  badge.textContent    = count;
  badge.style.display  = count > 0 ? 'flex' : 'none';
  document.getElementById('notif-list').innerHTML = (d.notifications||[]).map(n => `
    <div class="notif-item ${n.is_read ? '' : 'unread'}" ${n.idea_id ? `onclick="openNotifIdea(${n.idea_id})"` : ''} style="${n.idea_id ? 'cursor:pointer' : 'cursor:default'}">
      <div class="notif-item-title">${escHtml(n.title)}</div>
      <div class="notif-item-meta">${n.message ? escHtml(n.message).substring(0,80) : ''}</div>
      <div class="notif-item-meta">${timeAgo(n.created_at)}</div>
    </div>`).join('') || `<div class="empty-state">${t('msg.no_notif')}</div>`;
}

async function markAllRead() {
  await fetch('api/users.php?action=mark_read', {method:'POST', credentials:'same-origin'});
  document.getElementById('notif-count').style.display = 'none';
  document.getElementById('notif-panel').classList.remove('open');
  loadNotifications();
}

function openNotifIdea(ideaId) {
  document.getElementById('notif-panel').classList.remove('open');
  navigate('my-ideas', document.querySelector('.nav-item[onclick*="my-ideas"]'));
  openIdeaDetail(ideaId);
}

function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

function switchTab(el, tabId) {
  const parent = el.closest('.card, .modal-body, .page');
  parent.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  parent.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  document.getElementById(tabId).classList.add('active');
}

['situation','solution'].forEach(s => {
  document.getElementById('file-'+s).addEventListener('change', function() {
    document.getElementById('file-'+s+'-name').textContent =
      this.files.length ? t('attach.prefix') + this.files[0].name : '';
  });
});

function statusBadge(s) {
  return {'Submitted':'badge-submitted','Under Review':'badge-review','Approved':'badge-approved',
          'Rejected':'badge-rejected','Implemented':'badge-implemented','Draft':'badge-draft',
          'Reviewed':'badge-review'}[s] || 'badge-draft';
}
function translateStatus(s) {
  const map = {'Submitted':t('status.submitted'),'Under Review':t('status.review'),
    'Approved':t('status.approved'),'Rejected':t('status.rejected'),
    'Implemented':t('status.implemented'),'Draft':t('status.draft')};
  return map[s] || s;
}
function translateImpact(l) {
  const map = {'Low':t('impact.low'),'Medium':t('impact.medium'),'High':t('impact.high')};
  return map[l] || l;
}
function impactBadge(l) {
  return {'Low':'badge-low','Medium':'badge-medium','High':'badge-high'}[l]||'badge-draft';
}
function scoreBadgeClass(score) {
  const n = parseInt(score) || 0;
  if (n >= 75) return 'score-badge score-high';
  if (n >= 50) return 'score-badge score-med';
  if (n >  0)  return 'score-badge score-low';
  return 'score-badge score-none';
}
function actionLabel(a) {
  return {'Submitted':'SUB','Approved':'APR','Rejected':'REJ',
          'Implemented':'IMP','Reviewed':'REV','Commented':'CMT','Reopened':'ROP'}[a] || 'ACT';
}
function escHtml(str) {
  return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtDate(dt) {
  if (!dt) return '–';
  return new Date(dt).toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'});
}
function timeAgo(dt) {
  if (!dt) return '–';
  const diff = (Date.now() - new Date(dt)) / 1000;
  if (diff < 60) return t('time.just_now');
  if (diff < 3600) return Math.floor(diff/60) + t('time.min_ago');
  if (diff < 86400) return Math.floor(diff/3600) + t('time.hr_ago');
  return Math.floor(diff/86400) + t('time.day_ago');
}

if (currentUser) initApp();
</script>

<div class="modal-overlay" id="modal-assign-reviewers">
  <div class="modal" style="width:560px">
    <div class="modal-header">
      <div class="modal-title"><span data-i18n="ar.title">Route to Committee</span> — <span id="ar-idea-code"></span></div>
      <span class="modal-close" onclick="closeModal('modal-assign-reviewers')">&#10005;</span>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label data-i18n="ar.search_add">Search &amp; Add Reviewers</label>
        <div class="pos-rel">
          <input class="form-control" id="ar-search" data-i18n-ph="placeholder.search_user" placeholder="Search by name or employee ID…" autocomplete="off" oninput="searchReviewersForAssign(this.value)"/>
          <div class="user-search-results" id="ar-results"></div>
        </div>
      </div>
      <div id="ar-selected-list" style="min-height:36px;margin-bottom:16px">
        <div style="font-size:12px;color:var(--subtle);padding:6px 0" data-i18n="ar.no_reviewers">No reviewers added yet.</div>
      </div>
      <div class="form-group">
        <label data-i18n="ar.threshold">Approval Threshold</label>
        <select class="form-control" id="ar-threshold">
          <option value="100" data-i18n="ar.unanimous">All reviewers must approve (unanimous)</option>
          <option value="67" data-i18n="ar.supermajority">Supermajority — at least 2/3 must approve</option>
          <option value="50" data-i18n="ar.simple_majority">Simple majority — more than half must approve</option>
        </select>
      </div>
      <div class="alert alert-info" style="font-size:12px;margin:0" data-i18n="ar.info">Idea moves to <strong>Under Review</strong>. All assigned reviewers will be notified immediately.</div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('modal-assign-reviewers')" data-i18n="btn.cancel">Cancel</button>
      <button class="btn btn-primary" id="ar-submit-btn" onclick="submitAssignReviewers()" data-i18n="review.route_committee">Route to Committee</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-reviewer-decision">
  <div class="modal" style="width:460px">
    <div class="modal-header">
      <div class="modal-title"><span data-i18n="rd.title">My Review</span> — <span id="rd-idea-code"></span></div>
      <span class="modal-close" onclick="closeModal('modal-reviewer-decision')">&#10005;</span>
    </div>
    <div class="modal-body">
      <div class="form-group" style="margin-bottom:20px">
        <label data-i18n="rd.decision">Your Decision</label>
        <div style="display:flex;gap:10px;margin-top:8px">
          <button class="btn btn-outline" id="rd-approve-btn" style="flex:1;padding:10px;font-size:14px" onclick="selectReviewerDecision('approved')" data-i18n="rd.approve_btn">&#10003; Approve</button>
          <button class="btn btn-outline" id="rd-reject-btn" style="flex:1;padding:10px;font-size:14px" onclick="selectReviewerDecision('rejected')" data-i18n="rd.reject_btn">&#10007; Reject</button>
        </div>
        <input type="hidden" id="rd-decision" value=""/>
      </div>
      <div class="form-group">
        <label data-i18n="rd.feedback">Feedback / Comment <span style="color:var(--subtle);font-weight:400;text-transform:none">(optional)</span></label>
        <textarea class="form-control" id="rd-comment" rows="3" data-i18n-ph="rd.feedback_ph" placeholder="Share your reasoning with the submitter…"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('modal-reviewer-decision')" data-i18n="btn.cancel">Cancel</button>
      <button class="btn btn-primary" id="rd-submit-btn" disabled onclick="submitReviewerDecision()" data-i18n="btn.submit_decision">Submit Decision</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL — Create Organisation (Platform Admin)
════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-create-org">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <div class="modal-title">New Organisation</div>
      <span class="modal-close" onclick="closeModal('modal-create-org')">&#10005;</span>
    </div>
    <div class="modal-body">
      <div id="create-org-error" class="alert alert-danger" style="display:none"></div>
      <div class="form-row">
        <div class="form-group">
          <label>Organisation Name <span style="color:#ef4444">*</span></label>
          <input class="form-control" id="co-org-name" type="text" placeholder="Jain Manufacturing Ltd."/>
        </div>
        <div class="form-group">
          <label>Org Code (slug) <span style="color:#ef4444">*</span></label>
          <input class="form-control" id="co-slug" type="text" placeholder="jain-mfg" style="text-transform:lowercase" oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9-]/g,'')"/>
          <div style="font-size:11px;color:var(--subtle);margin-top:3px">Employees use this code to log in. Cannot be changed later.</div>
        </div>
      </div>
      <div style="border-top:1px solid var(--border);margin:16px 0 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--subtle)">First Admin Account</div>
      <div class="form-row">
        <div class="form-group">
          <label>Admin Full Name <span style="color:#ef4444">*</span></label>
          <input class="form-control" id="co-admin-name" type="text" placeholder="Priya Sharma"/>
        </div>
        <div class="form-group">
          <label>Admin Email <span style="color:#ef4444">*</span></label>
          <input class="form-control" id="co-admin-email" type="email" placeholder="admin@jain-mfg.com"/>
        </div>
      </div>
      <div class="form-group">
        <label>Admin Password <span style="color:#ef4444">*</span></label>
        <input class="form-control" id="co-admin-pass" type="password" placeholder="Min. 6 characters"/>
        <div style="font-size:11px;color:var(--subtle);margin-top:3px">Share this securely with the admin. They can change it after login.</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('modal-create-org')">Cancel</button>
      <button class="btn btn-primary" id="co-submit-btn" onclick="submitCreateOrg()">Create Organisation</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL — Create / Edit User (Org Admin)
════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-user-form">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <div class="modal-title" id="user-form-title">Add User</div>
      <span class="modal-close" onclick="closeModal('modal-user-form')">&#10005;</span>
    </div>
    <div class="modal-body">
      <div id="user-form-error" class="alert alert-danger" style="display:none"></div>
      <input type="hidden" id="uf-id"/>
      <div class="form-row">
        <div class="form-group">
          <label>Full Name <span style="color:#ef4444">*</span></label>
          <input class="form-control" id="uf-name" type="text" placeholder="Rahul Verma"/>
        </div>
        <div class="form-group">
          <label>Employee ID <span style="color:#ef4444">*</span></label>
          <input class="form-control" id="uf-emp-id" type="text" placeholder="EMP-001"/>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Email <span style="color:#ef4444">*</span></label>
          <input class="form-control" id="uf-email" type="email" placeholder="rahul@yourorg.com"/>
        </div>
        <div class="form-group" id="uf-pass-group">
          <label>Password <span style="color:#ef4444">*</span></label>
          <input class="form-control" id="uf-password" type="password" placeholder="Min. 6 characters"/>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Role <span style="color:#ef4444">*</span></label>
          <select class="form-control" id="uf-role">
            <option value="employee">Employee</option>
            <option value="manager">Manager</option>
            <option value="executive">Executive</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label>Reports To</label>
          <select class="form-control" id="uf-manager">
            <option value="">— None —</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Department</label>
          <input class="form-control" id="uf-dept" type="text" placeholder="Production"/>
        </div>
        <div class="form-group">
          <label>Business Unit</label>
          <input class="form-control" id="uf-bu" type="text" placeholder="Operations"/>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Location</label>
          <input class="form-control" id="uf-location" type="text" placeholder="Mumbai"/>
        </div>
        <div class="form-group" id="uf-status-group" style="display:none">
          <label>Status</label>
          <select class="form-control" id="uf-status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('modal-user-form')">Cancel</button>
      <button class="btn btn-primary" id="uf-submit-btn" onclick="submitUserForm()">Save User</button>
    </div>
  </div>
</div>

</body>
</html>
