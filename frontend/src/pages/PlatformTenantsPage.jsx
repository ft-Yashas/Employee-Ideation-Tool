import { useState, useEffect } from 'react';
import { useParams, useSearchParams, useNavigate } from 'react-router-dom';
import { useLang } from '../context/LangContext';
import { platformApi } from '../services/api';
import { formatRole } from '../utils/helpers';

const ROLE_COLORS = {
  admin:'#374151', executive:'#4b5563', manager:'#f59e0b', employee:'#10b981',
};
const ROLE_ORDER = ['admin','executive','manager','employee'];

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

  const byRole = { admin:[], executive:[], manager:[], employee:[] };
  users.forEach(u => { if (byRole[u.role]) byRole[u.role].push(u); });

  const statsStrip = [
    [t('platform.admins'),     byRole.admin.length,     '#374151','#d1d5db'],
    [t('platform.executives'), byRole.executive.length, '#4b5563','#d1d5db'],
    [t('platform.managers'),   byRole.manager.length,   '#f59e0b','#fef3c7'],
    [t('platform.employees'),  byRole.employee.length,  '#10b981','#bbf7d0'],
  ];

  const roleLabels = {
    admin: t('platform.admins'), executive: t('platform.executives'),
    manager: t('platform.managers'), employee: t('platform.employees'),
  };

  return (
    <>
      <div style={{ display:'flex',alignItems:'center',gap:10,marginBottom:20 }}>
        <button className="btn btn-outline btn-sm" onClick={() => navigate('/platform')}>← {t('btn.back')}</button>
        <h2 id="pt-tenant-name" style={{ fontSize:16,fontWeight:700,color:'var(--heading)',margin:0 }}>
          {tenantName} — {t('pa.org_hierarchy')}
        </h2>
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
            {!users.length
              ? <div className="empty-state">{t('sa.no_users')}</div>
              : ROLE_ORDER.map(role => {
                if (!byRole[role].length) return null;
                const color = ROLE_COLORS[role] || '#888';
                return (
                  <div key={role} style={{ marginBottom:20 }}>
                    <div style={{ fontSize:11,fontWeight:700,textTransform:'uppercase',letterSpacing:1,color,marginBottom:10,paddingBottom:6,borderBottom:`2px solid ${color}22` }}>
                      {roleLabels[role]} ({byRole[role].length})
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
