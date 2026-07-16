import { useState, useEffect } from 'react';
import { useParams, useSearchParams, useNavigate } from 'react-router-dom';
import { useLang } from '../context/LangContext';
import { platformApi } from '../services/api';
import { formatRole } from '../utils/helpers';

// All 8 tenant roles. The first cut of this page only knew the classic four —
// anyone bulk-imported as team_lead / project_lead / senior_manager / trainee
// was silently dropped from the hierarchy view.
const ROLE_COLORS = {
  admin:'#374151', executive:'#4b5563', senior_manager:'#6b7280',
  manager:'#f59e0b', project_lead:'#0891b2', team_lead:'#0284c7',
  employee:'#10b981', trainee:'#64748b',
};
const ROLE_ORDER = ['admin','executive','senior_manager','manager','project_lead','team_lead','employee','trainee'];

export default function PlatformTenantsPage() {
  const { id }        = useParams();
  const [params]      = useSearchParams();
  const { t }         = useLang();
  const navigate      = useNavigate();
  const tenantName    = params.get('name') || 'Organisation';

  const [users,   setUsers]   = useState([]);
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState('');

  useEffect(() => { load(); }, [id]);

  async function load() {
    setLoading(true);
    setError('');
    try {
      const res = await platformApi.tenantHierarchy(id);
      if (res.data.success) setUsers(res.data.users || []);
      else setError(res.data.error || t('msg.fail_load'));
    } catch { setError(t('msg.fail_load')); }
    setLoading(false);
  }

  const byRole = Object.fromEntries(ROLE_ORDER.map(r => [r, []]));
  users.forEach(u => { (byRole[u.role] || (byRole[u.role] = [])).push(u); });

  // The KPI strip keeps the PHP design's four headline groups; leads and
  // senior managers are counted with their nearest group so the totals add up.
  const statsStrip = [
    [t('platform.admins'),     byRole.admin.length, '#374151'],
    [t('platform.executives'), byRole.executive.length, '#4b5563'],
    [t('platform.managers'),   byRole.senior_manager.length + byRole.manager.length + byRole.project_lead.length + byRole.team_lead.length, '#f59e0b'],
    [t('platform.employees'),  byRole.employee.length + byRole.trainee.length, '#10b981'],
  ];

  const roleLabels = {
    admin: t('platform.admins'), executive: t('platform.executives'),
    manager: t('platform.managers'), employee: t('platform.employees'),
  };
  const roleLabel = (role) => roleLabels[role] || formatRole(role, t);

  return (
    <>
      <div style={{ display:'flex',alignItems:'flex-start',gap:10,marginBottom:20,justifyContent:'space-between',flexWrap:'wrap' }}>
        <div>
          <h2 id="pt-tenant-name" style={{ fontSize:16,fontWeight:700,color:'var(--heading)',margin:0 }}>
            {tenantName} — {t('pa.org_hierarchy')}
          </h2>
          <div style={{ fontSize:12,color:'var(--subtle)',marginTop:4 }}>{t('pa.hierarchy_sub')}</div>
        </div>
        <button className="btn btn-outline btn-sm" onClick={() => navigate('/platform')}>← {t('btn.back')}</button>
      </div>

      {loading && <div className="empty-state"><div className="spinner"></div></div>}
      {error   && <div className="alert alert-danger">{error}</div>}

      {!loading && !error && (
        <>
          {/* Stats strip */}
          <div className="kpi-grid" id="pt-stats-strip">
            {statsStrip.map(([label, count, color]) => (
              <div key={label} className="kpi-card" style={{ borderLeftColor:color }}>
                <div className="kpi-body">
                  <div className="kpi-val" style={{ color }}>{count}</div>
                  <div className="kpi-label">{label}</div>
                </div>
              </div>
            ))}
          </div>

          <div className="card" id="pt-hierarchy-body" style={{ marginTop:20 }}>
            <div className="card-title">{t('pa.user_hierarchy')}</div>
            {!users.length
              ? <div className="empty-state">{t('sa.no_users')}</div>
              : Object.keys(byRole).map(role => {
                if (!byRole[role].length) return null;
                const color = ROLE_COLORS[role] || '#888';
                return (
                  <div key={role} style={{ marginBottom:20 }}>
                    <div style={{ fontSize:11,fontWeight:700,textTransform:'uppercase',letterSpacing:1,color,marginBottom:10,paddingBottom:6,borderBottom:`2px solid ${color}22` }}>
                      {roleLabel(role)} ({byRole[role].length})
                    </div>
                    <div style={{ display:'grid',gridTemplateColumns:'repeat(auto-fill,minmax(260px,1fr))',gap:10 }}>
                      {byRole[role].map(u => (
                        <div key={u.id} style={{ display:'flex',alignItems:'center',gap:12,padding:'11px 14px',background:'var(--bg)',borderRadius:'var(--r)',border:'1px solid var(--border)' }}>
                          <div style={{ width:36,height:36,borderRadius:'50%',background:`${color}22`,color,display:'flex',alignItems:'center',justifyContent:'center',fontWeight:700,fontSize:13,flexShrink:0 }}>
                            {u.avatar_initials||u.name?.[0]||'?'}
                          </div>
                          <div style={{ flex:1,minWidth:0 }}>
                            <div style={{ fontSize:13,fontWeight:600,color:'var(--heading)',whiteSpace:'nowrap',overflow:'hidden',textOverflow:'ellipsis' }}>{u.name}</div>
                            <div style={{ fontSize:11,color:'var(--subtle)',marginTop:2 }}>
                              {u.department||'—'}
                              {u.manager_name ? ` · ${t('platform.reports_to')} ${u.manager_name}` : ''}
                            </div>
                            <div style={{ fontSize:11,color:'#f59e0b',marginTop:2,fontWeight:600 }}>
                              {u.idea_count} {t('unit.ideas')}
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                );
              })
            }
          </div>
        </>
      )}
    </>
  );
}
