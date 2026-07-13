import { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { useLang } from '../context/LangContext';
import { useToast } from '../context/ToastContext';
import { usersApi, ideasApi, settingsApi, scoreApi } from '../services/api';
import { formatRole, statusBadge, translateStatus, fmtDate } from '../utils/helpers';
import IdeaDetailModal from '../components/IdeaDetailModal';

const ROLE_BADGE_STYLE = {
  admin:     'background:#c8ccd1;color:#374151;border:1px solid #6b7280',
  executive: 'background:#c8ccd1;color:#4b5563;border:1px solid #9ca3af',
  manager:   'background:#fef3c7;color:#92400e;border:1px solid #fde68a',
  employee:  'background:#a7f3d0;color:#065f46;border:1px solid #a7f3d0',
};

const TAB_KEYS = ['admin.tab_overview','admin.tab_ideas','admin.tab_users','admin.tab_system'];

export default function AdminPage() {
  const { user }      = useAuth();
  const { t }         = useLang();
  const { showToast } = useToast();

  const [tab,         setTab]         = useState(0);
  const [dash,        setDash]        = useState(null);
  const [ideas,       setIdeas]       = useState([]);
  const [ideasSearch, setIdeasSearch] = useState('');
  const [ideasStatus, setIdeasStatus] = useState('');
  const [users,       setUsers]       = useState([]);
  const [usersSearch, setUsersSearch] = useState('');
  const [managers,    setManagers]    = useState([]);
  const [settings,    setSettings]    = useState(null);
  const [loading,     setLoading]     = useState(false);
  const [openIdeaId,  setOpenIdeaId]  = useState(null);
  const [showUserForm,setShowUserForm]= useState(false);
  const [editUser,    setEditUser]    = useState(null);
  const [rescoreMsg,  setRescoreMsg]  = useState('');
  const [settingsMsg, setSettingsMsg] = useState('');

  useEffect(() => {
    if (tab === 0) loadDash();
    if (tab === 1) loadIdeas();
    if (tab === 2) loadUsers();
    if (tab === 3) loadSettings();
  }, [tab]);

  async function loadDash() {
    try {
      const res = await ideasApi.dashboard();
      setDash(res.data);
    } catch {}
  }

  async function loadIdeas() {
    try {
      const res = await ideasApi.list({ search: ideasSearch, status: ideasStatus });
      setIdeas(res.data.ideas || []);
    } catch {}
  }

  async function loadUsers() {
    try {
      const [uRes, mRes] = await Promise.all([
        usersApi.adminList(),
        usersApi.managers(),
      ]);
      setUsers(uRes.data.users || []);
      setManagers(mRes.data.managers || []);
    } catch {}
  }

  async function loadSettings() {
    try {
      const res = await settingsApi.get();
      if (res.data.success) setSettings(res.data.settings);
    } catch {}
  }

  async function handleDeleteUser(id, name) {
    if (!confirm(t('admin.confirm_remove', { name }))) return;
    try {
      const res = await usersApi.deleteUser(id);
      if (res.data.success) {
        showToast(t(res.data.deactivated ? 'admin.deactivated' : 'admin.removed', { name }), 'info');
        loadUsers();
      } else showToast(`${t('msg.error')}: ` + (res.data.error || ''), 'danger');
    } catch { showToast(t('msg.server_error'), 'danger'); }
  }

  async function handleRescore() {
    setRescoreMsg(t('admin.rescoring'));
    try {
      const res = await scoreApi.batchRescore();
      if (res.data.success) setRescoreMsg(`✓ ${t('msg.rescore_ok', { n: res.data.updated })}`);
      else setRescoreMsg(`${t('msg.error')}: ` + (res.data.error || ''));
    } catch { setRescoreMsg(t('msg.server_error')); }
  }

  async function handleSaveSettings(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = {};
    ['review_sla_days','escalation_days','smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from','smtp_from_name','approval_threshold'].forEach(k => { data[k] = fd.get(k)||''; });
    data['approval_mode'] = fd.get('approval_mode') || 'default';
    ['anonymous_allowed','public_board_enabled','challenges_enabled','email_enabled'].forEach(k => { data[k] = fd.get(k)==='1'?'1':'0'; });
    ['approval_reviewer_roles','approval_final_approver_roles'].forEach(key => { data[key] = [...fd.getAll(key)].join(','); });
    setSettingsMsg('');
    try {
      const res = await settingsApi.update(data);
      if (res.data.success) { setSettingsMsg(t('admin.settings_saved')); showToast(t('admin.settings_saved'),'success'); }
      else setSettingsMsg(res.data.error || t('admin.settings_failed'));
    } catch { setSettingsMsg(t('msg.network_error')); }
  }

  async function handleTestEmail() {
    showToast(t('admin.sending_test'),'info');
    try {
      const res = await settingsApi.testEmail();
      if (res.data.success) showToast(t('admin.test_sent'),'success');
      else showToast(res.data.error||t('msg.error'),'danger');
    } catch { showToast(t('msg.network_error'),'danger'); }
  }

  const filteredIdeas = ideas.filter(i => {
    const q = ideasSearch.toLowerCase();
    return (!q || i.title.toLowerCase().includes(q) || i.idea_code.toLowerCase().includes(q)) &&
           (!ideasStatus || i.status === ideasStatus);
  });

  const filteredUsers = users.filter(u => {
    const q = usersSearch.toLowerCase();
    return !q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q) || (u.employee_id||'').toLowerCase().includes(q);
  });

  const counts = dash?.counts || {};

  return (
    <>
      <div className="tab-bar">
        {TAB_KEYS.map((key, i) => (
          <div key={key} className={`tab${tab===i?' active':''}`} onClick={() => setTab(i)}>{t(key)}</div>
        ))}
      </div>

      {/* Overview */}
      {tab === 0 && dash && (
        <div>
          <div className="kpi-grid" style={{ marginTop:16 }}>
            {Object.entries(counts).map(([s,c]) => (
              <div key={s} className="kpi-card">
                <div className="kpi-body">
                  <div className="kpi-val">{c}</div>
                  <div className="kpi-label">{translateStatus(s, t)}</div>
                </div>
              </div>
            ))}
          </div>
          <div className="card" style={{ marginTop:16 }}>
            <div style={{ fontWeight:700,fontSize:13,marginBottom:14 }}>{t('admin.database')}</div>
            <div id="admin-db-name" style={{ fontSize:13,color:'var(--subtle)' }}>
              <strong>{t('admin.database')}:</strong> ifqm_{user?.org_slug}
            </div>
          </div>
        </div>
      )}

      {/* Idea Management */}
      {tab === 1 && (
        <div>
          <div className="filter-bar" style={{ marginTop:16 }}>
            <input className="form-control" type="search" placeholder={t('filter.search_ideas')}
              value={ideasSearch} onChange={e => { setIdeasSearch(e.target.value); loadIdeas(); }} style={{ maxWidth:260 }} />
            <select className="form-control" value={ideasStatus} onChange={e => { setIdeasStatus(e.target.value); loadIdeas(); }} style={{ width:160 }}>
              <option value="">{t('filter.all_statuses')}</option>
              {['Submitted','Under Review','Approved','Rejected','Implemented'].map(s => (
                <option key={s} value={s}>{translateStatus(s, t)}</option>
              ))}
            </select>
          </div>
          <div className="card" style={{ overflowX:'auto',marginTop:8 }}>
            <table className="table">
              <thead>
                <tr><th>{t('table.code')}</th><th>{t('table.title')}</th><th>{t('table.submitter')}</th><th>{t('table.status')}</th><th>{t('table.date')}</th><th></th></tr>
              </thead>
              <tbody>
                {!filteredIdeas.length && <tr><td colSpan="6" className="text-center">{t('msg.no_ideas')}</td></tr>}
                {filteredIdeas.map(i => (
                  <tr key={i.id}>
                    <td><strong>{i.idea_code}</strong></td>
                    <td>{i.title.length>50?i.title.substring(0,50)+'…':i.title}</td>
                    <td>{i.submitter_name}</td>
                    <td><span className={`badge ${statusBadge(i.status)}`}>{translateStatus(i.status, t)}</span></td>
                    <td>{i.submitted_at?fmtDate(i.submitted_at):'–'}</td>
                    <td><button className="btn btn-outline btn-sm" onClick={() => setOpenIdeaId(i.id)}>{t('btn.view')}</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* User List */}
      {tab === 2 && (
        <div>
          <div style={{ display:'flex',justifyContent:'space-between',alignItems:'center',marginTop:16,marginBottom:12 }}>
            <input className="form-control" type="search" placeholder={t('filter.search_users')}
              value={usersSearch} onChange={e => setUsersSearch(e.target.value)} style={{ maxWidth:280 }} id="admin-user-search" />
            <button className="btn btn-primary btn-sm" onClick={() => { setEditUser(null); setShowUserForm(true); }}>{t('btn.add_user')}</button>
          </div>
          <div className="card" style={{ overflowX:'auto' }}>
            <table className="table">
              <thead>
                <tr><th>{t('table.user')}</th><th>{t('table.role')}</th><th>{t('table.dept')}</th><th>{t('table.manager')}</th><th>{t('table.points')}</th><th>{t('table.status')}</th><th></th></tr>
              </thead>
              <tbody id="admin-users-tbody">
                {!filteredUsers.length && <tr><td colSpan="7" className="text-center">{t('admin.no_users')}</td></tr>}
                {filteredUsers.map(u => {
                  const isProtected = u.role === 'super_admin' || u.id === user?.id;
                  return (
                    <tr key={u.id}>
                      <td>
                        <div style={{ display:'flex',alignItems:'center',gap:8 }}>
                          <div className="avatar" style={{ width:30,height:30,fontSize:11 }}>{u.avatar_initials||u.name?.[0]||'?'}</div>
                          <div>
                            <div style={{ fontWeight:600,fontSize:13 }}>{u.name}</div>
                            <div style={{ fontSize:11,color:'var(--subtle)' }}>{u.employee_id} · {u.email}</div>
                          </div>
                        </div>
                      </td>
                      <td><span className="badge" style={ROLE_BADGE_STYLE[u.role]||''}>{formatRole(u.role, t)}</span></td>
                      <td style={{ fontSize:12 }}>{u.department||'–'}</td>
                      <td style={{ fontSize:12,color:'var(--subtle)' }}>{u.manager_name||'–'}</td>
                      <td><strong>{u.points}</strong></td>
                      <td>
                        <span style={{ fontSize:10,padding:'1px 8px',borderRadius:99,border:'1px solid',
                          background:u.status==='inactive'?'#fee2e2':'#bbf7d0',
                          color:u.status==='inactive'?'#ef4444':'#166534',
                          borderColor:u.status==='inactive'?'#fca5a5':'#bbf7d0' }}>
                          {t(u.status==='inactive' ? 'admin.inactive' : 'admin.active')}
                        </span>
                      </td>
                      <td>
                        {isProtected
                          ? <span style={{ fontSize:11,color:'var(--subtle)' }}>—</span>
                          : (
                            <div style={{ display:'flex',gap:6 }}>
                              <button className="btn btn-outline btn-sm" onClick={() => { setEditUser(u); setShowUserForm(true); }}>{t('btn.edit')}</button>
                              <button className="btn btn-sm" style={{ background:'#fee2e2',color:'#ef4444',border:'1px solid #fca5a5' }}
                                onClick={() => handleDeleteUser(u.id, u.name)}>{t('btn.remove')}</button>
                            </div>
                          )
                        }
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* System */}
      {tab === 3 && settings && (
        <div style={{ maxWidth:600,marginTop:16 }}>
          <form onSubmit={handleSaveSettings}>
            <div style={{ fontSize:13,fontWeight:600,color:'var(--heading)',marginBottom:16 }}>{t('admin.sla_heading')}</div>
            <div className="form-row">
              <div className="form-group">
                <label>{t('admin.sla_days')}</label>
                <input className="form-control" name="review_sla_days" type="number" min="1" max="90" defaultValue={settings.review_sla_days||7} />
              </div>
              <div className="form-group">
                <label>{t('admin.escalation_days')}</label>
                <input className="form-control" name="escalation_days" type="number" min="1" max="180" defaultValue={settings.escalation_days||14} />
              </div>
            </div>

            <div style={{ fontSize:13,fontWeight:600,color:'var(--heading)',margin:'16px 0 12px' }}>{t('admin.flags_heading')}</div>
            <div className="form-row">
              {[['anonymous_allowed','admin.flag_anonymous'],['public_board_enabled','admin.flag_board'],
                ['challenges_enabled','admin.flag_challenges'],['email_enabled','admin.flag_email']].map(([k,labelKey]) => (
                <div key={k} className="form-group">
                  <label style={{ display:'flex',alignItems:'center',gap:8,cursor:'pointer' }}>
                    <input type="checkbox" name={k} value="1" defaultChecked={settings[k]==='1'} style={{ accentColor:'var(--primary)' }} />
                    {t(labelKey)}
                  </label>
                </div>
              ))}
            </div>

            <div style={{ fontSize:13,fontWeight:600,color:'var(--heading)',margin:'16px 0 12px' }}>{t('admin.smtp_heading')}</div>
            <div className="form-row">
              <div className="form-group"><label>{t('admin.smtp_host')}</label><input className="form-control" name="smtp_host" defaultValue={settings.smtp_host||''} /></div>
              <div className="form-group"><label>{t('admin.smtp_port')}</label><input className="form-control" name="smtp_port" type="number" defaultValue={settings.smtp_port||587} /></div>
            </div>
            <div className="form-row">
              <div className="form-group"><label>{t('admin.smtp_user')}</label><input className="form-control" name="smtp_user" defaultValue={settings.smtp_user||''} /></div>
              <div className="form-group"><label>{t('admin.smtp_pass')}</label><input className="form-control" name="smtp_pass" type="password" placeholder={t('admin.smtp_pass_ph')} /></div>
            </div>
            <div className="form-row">
              <div className="form-group"><label>{t('admin.smtp_from')}</label><input className="form-control" name="smtp_from" type="email" defaultValue={settings.smtp_from||''} /></div>
              <div className="form-group"><label>{t('admin.smtp_from_name')}</label><input className="form-control" name="smtp_from_name" defaultValue={settings.smtp_from_name||'IFQM Ideation'} /></div>
            </div>

            <div style={{ display:'flex',gap:8,marginTop:16 }}>
              <button type="submit" className="btn btn-primary">{t('admin.save_settings')}</button>
              <button type="button" className="btn btn-outline" onClick={handleTestEmail}>{t('admin.test_email')}</button>
            </div>
            {settingsMsg && <div style={{ marginTop:10,fontSize:13,color:settingsMsg===t('admin.settings_saved')?'#10b981':'#ef4444' }}>{settingsMsg}</div>}
          </form>

          <div className="card" style={{ marginTop:24 }}>
            <div style={{ fontWeight:700,fontSize:13,marginBottom:12 }}>{t('admin.ai_scoring')}</div>
            <button className="btn btn-warning btn-sm" onClick={handleRescore}>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" style={{ verticalAlign:'middle',marginRight:4 }}>
                <polyline points="23 4 23 10 17 10"/>
                <path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
              </svg>
              {t('btn.rescore_all')}
            </button>
            {rescoreMsg && <div id="rescore-result" style={{ marginTop:8,fontSize:13 }}>{rescoreMsg}</div>}
          </div>
        </div>
      )}

      {openIdeaId && <IdeaDetailModal ideaId={openIdeaId} onClose={() => { setOpenIdeaId(null); loadIdeas(); }} />}

      {showUserForm && (
        <UserFormModal
          user={editUser}
          managers={managers}
          currentUserRole={user?.role}
          currentUserId={user?.id}
          onClose={() => setShowUserForm(false)}
          onSaved={() => { setShowUserForm(false); loadUsers(); }}
          showToast={showToast}
          t={t}
        />
      )}
    </>
  );
}

function UserFormModal({ user: editUser, managers, currentUserRole, currentUserId, onClose, onSaved, showToast, t }) {
  const isEdit = !!editUser;
  const [name,    setName]    = useState(editUser?.name||'');
  const [empId,   setEmpId]   = useState(editUser?.employee_id||'');
  const [email,   setEmail]   = useState(editUser?.email||'');
  const [pass,    setPass]    = useState('');
  const [role,    setRole]    = useState(editUser?.role||'employee');
  const [mgr,     setMgr]     = useState(editUser?.manager_id||'');
  const [dept,    setDept]    = useState(editUser?.department||'');
  const [bu,      setBu]      = useState(editUser?.business_unit||'');
  const [loc,     setLoc]     = useState(editUser?.location||'');
  const [status,  setStatus]  = useState(editUser?.status||'active');
  const [error,   setError]   = useState('');
  const [saving,  setSaving]  = useState(false);

  const roleOptions = [
    'trainee','employee','team_lead','project_lead','manager','senior_manager','executive',
    ...(currentUserRole==='super_admin' ? ['admin'] : []),
  ];

  async function handleSubmit() {
    setError('');
    setSaving(true);
    const payload = { name, email, employee_id: empId, role, manager_id: mgr||null, department: dept, business_unit: bu, location: loc };
    if (isEdit) { payload.id = editUser.id; payload.status = status; }
    else payload.password = pass;
    try {
      const res = await usersApi[isEdit ? 'updateUser' : 'createUser'](payload);
      if (res.data.success) { showToast(t(isEdit ? 'admin.user_updated' : 'admin.user_created'),'success'); onSaved(); }
      else { setError(res.data.error||t('admin.user_save_failed')); }
    } catch { setError(t('msg.server_error')); }
    setSaving(false);
  }

  return (
    <div className="modal-overlay open" onClick={e => e.target===e.currentTarget && onClose()}>
      <div className="modal" style={{ maxWidth:520 }}>
        <div className="modal-header">
          <span id="user-form-title">{t(isEdit ? 'admin.edit_user' : 'admin.add_user_title')}</span>
          <button className="modal-close" onClick={onClose}>✕</button>
        </div>
        <div className="modal-body">
          {error && <div className="alert alert-danger" id="user-form-error">{error}</div>}
          <div className="form-row">
            <div className="form-group"><label>{t('admin.uf_name')} *</label><input className="form-control" value={name} onChange={e=>setName(e.target.value)} id="uf-name" /></div>
            <div className="form-group"><label>{t('admin.uf_emp_id')} *</label><input className="form-control" value={empId} onChange={e=>setEmpId(e.target.value)} id="uf-emp-id" /></div>
          </div>
          <div className="form-group"><label>{t('admin.uf_email')} *</label><input className="form-control" type="email" value={email} onChange={e=>setEmail(e.target.value)} id="uf-email" /></div>
          {!isEdit && <div className="form-group" id="uf-pass-group"><label>{t('admin.uf_password')} *</label><input className="form-control" type="password" value={pass} onChange={e=>setPass(e.target.value)} id="uf-password" /></div>}
          <div className="form-row">
            <div className="form-group"><label>{t('admin.uf_role')}</label>
              <select className="form-control" id="uf-role" value={role} onChange={e=>setRole(e.target.value)}>
                {roleOptions.map(r => <option key={r} value={r}>{formatRole(r, t)}</option>)}
              </select>
            </div>
            <div className="form-group"><label>{t('admin.uf_manager')}</label>
              <select className="form-control" id="uf-manager" value={mgr} onChange={e=>setMgr(e.target.value)}>
                <option value="">{t('admin.uf_none')}</option>
                {managers.filter(m=>m.id!==editUser?.id).map(m => <option key={m.id} value={m.id}>{m.name} ({formatRole(m.role, t)})</option>)}
              </select>
            </div>
          </div>
          <div className="form-row">
            <div className="form-group"><label>{t('admin.uf_dept')}</label><input className="form-control" value={dept} onChange={e=>setDept(e.target.value)} id="uf-dept" /></div>
            <div className="form-group"><label>{t('admin.uf_bu')}</label><input className="form-control" value={bu} onChange={e=>setBu(e.target.value)} id="uf-bu" /></div>
          </div>
          <div className="form-group"><label>{t('admin.uf_location')}</label><input className="form-control" value={loc} onChange={e=>setLoc(e.target.value)} id="uf-location" /></div>
          {isEdit && (
            <div className="form-group" id="uf-status-group"><label>{t('admin.uf_status')}</label>
              <select className="form-control" id="uf-status" value={status} onChange={e=>setStatus(e.target.value)}>
                <option value="active">{t('admin.active')}</option>
                <option value="inactive">{t('admin.inactive')}</option>
              </select>
            </div>
          )}
        </div>
        <div className="modal-footer">
          <button className="btn btn-outline" onClick={onClose}>{t('btn.cancel')}</button>
          <button className="btn btn-primary" id="uf-submit-btn" disabled={saving} onClick={handleSubmit}>
            {saving ? t('btn.saving') : t(isEdit ? 'admin.uf_save_changes' : 'admin.uf_save_user')}
          </button>
        </div>
      </div>
    </div>
  );
}
