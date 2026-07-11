import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useLang } from '../context/LangContext';
import { useToast } from '../context/ToastContext';
import { platformApi } from '../services/api';

export default function PlatformDashPage() {
  const { t }         = useLang();
  const { showToast } = useToast();
  const navigate      = useNavigate();
  const [tenants, setTenants] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState('');
  const [showCreate, setShowCreate] = useState(false);

  useEffect(() => { load(); }, []);

  async function load() {
    setLoading(true);
    setError('');
    try {
      const res = await platformApi.tenants();
      if (res.data.success) setTenants(res.data.tenants || []);
      else setError(res.data.error || 'Failed to load tenants.');
    } catch { setError('Failed to load platform data.'); }
    setLoading(false);
  }

  const totalUsers = tenants.reduce((s, t) => s + (t.user_count||0), 0);
  const totalIdeas = tenants.reduce((s, t) => s + (t.idea_count||0), 0);

  return (
    <>
      {/* KPI Strip */}
      <div className="kpi-grid" id="pa-kpi-strip">
        <div className="kpi-card" style={{ borderLeftColor:'#1f2937' }}>
          <div className="kpi-icon" style={{ background:'#c8ccd1',color:'#374151' }}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="22" height="22">
              <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            </svg>
          </div>
          <div className="kpi-body">
            <div className="kpi-val">{tenants.length}</div>
            <div className="kpi-label">{t('pa.active_tenants')}</div>
          </div>
        </div>
        <div className="kpi-card" style={{ borderLeftColor:'#10b981' }}>
          <div className="kpi-icon" style={{ background:'#bbf7d0',color:'#10b981' }}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="22" height="22">
              <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
            </svg>
          </div>
          <div className="kpi-body">
            <div className="kpi-val">{totalUsers}</div>
            <div className="kpi-label">{t('pa.total_users')}</div>
          </div>
        </div>
        <div className="kpi-card" style={{ borderLeftColor:'#f59e0b' }}>
          <div className="kpi-icon" style={{ background:'#fef3c7',color:'#f59e0b' }}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="22" height="22">
              <path d="M9 21h6M12 3a6 6 0 016 6c0 2.2-1.1 3.8-2.5 5L15 16H9l-.5-2C7 12.8 6 11.2 6 9a6 6 0 016-6z"/>
            </svg>
          </div>
          <div className="kpi-body">
            <div className="kpi-val">{totalIdeas}</div>
            <div className="kpi-label">{t('pa.ideas_submitted')}</div>
          </div>
        </div>
      </div>

      <div style={{ display:'flex',justifyContent:'flex-end',margin:'20px 0 12px' }}>
        <button className="btn btn-primary btn-sm" onClick={() => setShowCreate(true)}>+ New Organisation</button>
      </div>

      {loading && <div className="empty-state"><div className="spinner"></div></div>}
      {error   && <div className="alert alert-danger">{error}</div>}

      <div className="card" id="pa-tenant-list">
        {!loading && !error && !tenants.length && (
          <div className="empty-state">{t('msg.no_ideas')}</div>
        )}
        {tenants.map(ten => {
          const implPct = ten.idea_count > 0 ? Math.round(ten.implemented_count / ten.idea_count * 100) : 0;
          const lastAct = ten.last_activity ? new Date(ten.last_activity).toLocaleDateString() : 'No activity';
          return (
            <div key={ten.id} style={{ display:'flex',alignItems:'center',justifyContent:'space-between',padding:'14px 0',borderBottom:'1px solid var(--border)' }}>
              <div style={{ flex:1 }}>
                <div style={{ fontSize:14,fontWeight:700,color:'var(--heading)' }}>{ten.name}</div>
                <div style={{ fontSize:12,color:'var(--subtle)',marginTop:2 }}>{ten.domain} · /{ten.slug}</div>
                <div style={{ fontSize:11,color:'var(--label)',marginTop:4,display:'flex',alignItems:'center' }}>
                  <span style={{ display:'inline-block',width:8,height:8,borderRadius:'50%',background:ten.db_error?'#ef4444':'#10b981',marginRight:6 }}></span>
                  {ten.db_error ? t('platform.db_error') : t('platform.active')}
                </div>
              </div>
              <div style={{ display:'flex',gap:24,textAlign:'center' }}>
                <div><div style={{ fontSize:18,fontWeight:800,color:'var(--heading)' }}>{ten.user_count||0}</div><div style={{ fontSize:11,color:'var(--subtle)' }}>{t('platform.users')}</div></div>
                <div><div style={{ fontSize:18,fontWeight:800,color:'var(--heading)' }}>{ten.idea_count||0}</div><div style={{ fontSize:11,color:'var(--subtle)' }}>{t('platform.ideas')}</div></div>
                <div><div style={{ fontSize:18,fontWeight:800,color:'#4b5563' }}>{implPct}%</div><div style={{ fontSize:11,color:'var(--subtle)' }}>{t('platform.implemented')}</div></div>
                <div><div style={{ fontSize:12,color:'var(--subtext)',fontWeight:500 }}>{lastAct}</div><div style={{ fontSize:11,color:'var(--subtle)' }}>{t('platform.last_activity')}</div></div>
                <div>
                  <button className="btn btn-outline btn-sm" onClick={() => navigate(`/platform/tenants/${ten.id}?name=${encodeURIComponent(ten.name)}`)}>
                    {t('platform.view_org')}
                  </button>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {showCreate && <CreateOrgModal onClose={() => setShowCreate(false)} onCreated={() => { setShowCreate(false); load(); showToast('Organisation created!','success'); }} t={t} />}
    </>
  );
}

function CreateOrgModal({ onClose, onCreated, t }) {
  const [orgName,    setOrgName]    = useState('');
  const [slug,       setSlug]       = useState('');
  const [slugEdited, setSlugEdited] = useState(false);
  const [adminName,  setAdminName]  = useState('');
  const [adminEmail, setAdminEmail] = useState('');
  const [adminPass,  setAdminPass]  = useState('');
  const [error,      setError]      = useState('');
  const [saving,     setSaving]     = useState(false);

  function handleOrgNameChange(v) {
    setOrgName(v);
    if (!slugEdited) setSlug(v.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,''));
  }

  async function handleSubmit() {
    if (!orgName||!slug||!adminName||!adminEmail||!adminPass) { setError('All fields are required.'); return; }
    setSaving(true); setError('');
    try {
      const res = await platformApi.createTenant({ org_name: orgName, slug, admin_name: adminName, admin_email: adminEmail, admin_password: adminPass });
      if (res.data.success) {
        alert(`✅ Organisation created!\n\nOrg Code: ${res.data.slug}\nAdmin Email: ${res.data.admin_email}\n\nShare the org code and admin credentials with the organisation.`);
        onCreated();
      } else { setError(res.data.error || 'Failed to create organisation.'); }
    } catch { setError('Server error. Please try again.'); }
    setSaving(false);
  }

  return (
    <div className="modal-overlay open" onClick={e => e.target===e.currentTarget && onClose()}>
      <div className="modal" id="modal-create-org" style={{ maxWidth:480 }}>
        <div className="modal-header">
          <span>Create New Organisation</span>
          <button className="modal-close" onClick={onClose}>✕</button>
        </div>
        <div className="modal-body">
          {error && <div className="alert alert-danger" id="create-org-error">{error}</div>}
          <div className="form-group"><label>Organisation Name *</label>
            <input className="form-control" id="co-org-name" value={orgName} onChange={e => handleOrgNameChange(e.target.value)} /></div>
          <div className="form-group"><label>Org Code (URL slug) *</label>
            <input className="form-control" id="co-slug" value={slug}
              onChange={e => { setSlug(e.target.value); setSlugEdited(true); }} style={{ textTransform:'lowercase' }} />
            <div style={{ fontSize:11,color:'var(--subtle)',marginTop:3 }}>Used in login URL: ?org={slug||'your-code'}</div>
          </div>
          <div style={{ fontSize:13,fontWeight:600,color:'var(--heading)',margin:'14px 0 10px' }}>Super Admin Account</div>
          <div className="form-group"><label>Admin Name *</label>
            <input className="form-control" id="co-admin-name" value={adminName} onChange={e => setAdminName(e.target.value)} /></div>
          <div className="form-group"><label>Admin Email *</label>
            <input className="form-control" type="email" id="co-admin-email" value={adminEmail} onChange={e => setAdminEmail(e.target.value)} /></div>
          <div className="form-group"><label>Admin Password *</label>
            <input className="form-control" type="password" id="co-admin-pass" value={adminPass} onChange={e => setAdminPass(e.target.value)} /></div>
        </div>
        <div className="modal-footer">
          <button className="btn btn-outline" onClick={onClose}>Cancel</button>
          <button className="btn btn-primary" id="co-submit-btn" disabled={saving} onClick={handleSubmit}>
            {saving?'Creating…':'Create Organisation'}
          </button>
        </div>
      </div>
    </div>
  );
}
