import { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { useLang } from '../context/LangContext';
import { useToast } from '../context/ToastContext';
import { usersApi, ideasApi, settingsApi, scoreApi } from '../services/api';
import { formatRole, statusBadge, fmtDate } from '../utils/helpers';
import IdeaDetailModal from '../components/IdeaDetailModal';

const ROLE_BADGE_STYLE = {
  admin:     'background:#c8ccd1;color:#374151;border:1px solid #6b7280',
  executive: 'background:#c8ccd1;color:#4b5563;border:1px solid #9ca3af',
  manager:   'background:#fef3c7;color:#92400e;border:1px solid #fde68a',
  employee:  'background:#a7f3d0;color:#065f46;border:1px solid #a7f3d0',
};

const TABS = ['Overview','Idea Management','User List','System'];

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
    if (!confirm(`Remove "${name}" from the organisation?\n\nIf they have submitted ideas, they will be deactivated instead of deleted.`)) return;
    try {
      const res = await usersApi.deleteUser(id);
      if (res.data.success) {
        showToast(res.data.deactivated ? `${name} deactivated (has submitted ideas).` : `${name} removed.`, 'info');
        loadUsers();
      } else showToast('Error: ' + (res.data.error || 'Unknown'), 'danger');
    } catch { showToast('Server error.', 'danger'); }
  }

  async function handleRescore() {
    setRescoreMsg('');
    try {
      const res = await scoreApi.batchRescore();
      if (res.data.success) setRescoreMsg(`✓ ${t('rescore.ok').replace('{n}', res.data.updated)}`);
      else setRescoreMsg('Error: ' + (res.data.error || 'Unknown'));
    } catch { setRescoreMsg('Server error.'); }
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
      if (res.data.success) { setSettingsMsg('Settings saved successfully.'); showToast('Org settings saved.','success'); }
      else setSettingsMsg(res.data.error || 'Failed to save.');
    } catch { setSettingsMsg('Network error.'); }
  }

  async function handleTestEmail() {
    showToast('Sending test email…','info');
    try {
      const res = await settingsApi.testEmail();
      if (res.data.success) showToast('Test email sent!','success');
      else showToast(res.data.error||'Failed','danger');
    } catch { showToast('Network error','danger'); }
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
        {TABS.map((label, i) => (
          <div key={i} className={`tab${tab===i?' active':''}`} onClick={() => setTab(i)}>{label}</div>
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
                  <div className="kpi-label">{s}</div>
                </div>
              </div>
            ))}
          </div>
          <div className="card" style={{ marginTop:16 }}>
            <div style={{ fontWeight:700,fontSize:13,marginBottom:14 }}>Database</div>
            <div id="admin-db-name" style={{ fontSize:13,color:'var(--subtle)' }}>
              <strong>Database:</strong> ifqm_{user?.org_slug}
            </div>
          </div>
        </div>
      )}

      {/* Idea Management */}
      {tab === 1 && (
        <div>
          <div className="filter-bar" style={{ marginTop:16 }}>
            <input className="form-control" type="search" placeholder="Search ideas…"
              value={ideasSearch} onChange={e => { setIdeasSearch(e.target.value); loadIdeas(); }} style={{ maxWidth:260 }} />
            <select className="form-control" value={ideasStatus} onChange={e => { setIdeasStatus(e.target.value); loadIdeas(); }} style={{ width:160 }}>
              <option value="">All Statuses</option>
              <option value="Submitted">Submitted</option>
              <option value="Under Review">Under Review</option>
              <option value="Approved">Approved</option>
              <option value="Rejected">Rejected</option>
              <option value="Implemented">Implemented</option>
            </select>
          </div>
          <div className="card" style={{ overflowX:'auto',marginTop:8 }}>
            <table className="table">
              <thead>
                <tr><th>Code</th><th>Title</th><th>Submitter</th><th>Status</th><th>Date</th><th></th></tr>
              </thead>
              <tbody>
                {!filteredIdeas.length && <tr><td colSpan="6" className="text-center">No ideas found.</td></tr>}
                {filteredIdeas.map(i => (
                  <tr key={i.id}>
                    <td><strong>{i.idea_code}</strong></td>
                    <td>{i.title.length>50?i.title.substring(0,50)+'…':i.title}</td>
                    <td>{i.submitter_name}</td>
                    <td><span className={`badge ${statusBadge(i.status)}`}>{i.status}</span></td>
                    <td>{i.submitted_at?fmtDate(i.submitted_at):'–'}</td>
                    <td><button className="btn btn-outline btn-sm" onClick={() => setOpenIdeaId(i.id)}>View</button></td>
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
            <input className="form-control" type="search" placeholder="Search users…"
              value={usersSearch} onChange={e => setUsersSearch(e.target.value)} style={{ maxWidth:280 }} id="admin-user-search" />
            <button className="btn btn-primary btn-sm" onClick={() => { setEditUser(null); setShowUserForm(true); }}>+ Add User</button>
          </div>
          <div className="card" style={{ overflowX:'auto' }}>
            <table className="table">
              <thead>
                <tr><th>User</th><th>Role</th><th>Dept</th><th>Manager</th><th>Points</th><th>Status</th><th></th></tr>
              </thead>
              <tbody id="admin-users-tbody">
                {!filteredUsers.length && <tr><td colSpan="7" className="text-center">No users yet.</td></tr>}
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
                      <td><span className="badge" style={ROLE_BADGE_STYLE[u.role]||''}>{formatRole(u.role)}</span></td>
                      <td style={{ fontSize:12 }}>{u.department||'–'}</td>
                      <td style={{ fontSize:12,color:'var(--subtle)' }}>{u.manager_name||'–'}</td>
                      <td><strong>{u.points}</strong></td>
                      <td>
                        <span style={{ fontSize:10,padding:'1px 8px',borderRadius:99,border:'1px solid',
                          background:u.status==='inactive'?'#fee2e2':'#bbf7d0',
                          color:u.status==='inactive'?'#ef4444':'#166534',
                          borderColor:u.status==='inactive'?'#fca5a5':'#bbf7d0' }}>
                          {u.status==='inactive'?'Inactive':'Active'}
                        </span>
                      </td>
                      <td>
                        {isProtected
                          ? <span style={{ fontSize:11,color:'var(--subtle)' }}>—</span>
                          : (
                            <div style={{ display:'flex',gap:6 }}>
                              <button className="btn btn-outline btn-sm" onClick={() => { setEditUser(u); setShowUserForm(true); }}>Edit</button>
                              <button className="btn btn-sm" style={{ background:'#fee2e2',color:'#ef4444',border:'1px solid #fca5a5' }}
                                onClick={() => handleDeleteUser(u.id, u.name)}>Remove</button>
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
            <div style={{ fontSize:13,fontWeight:600,color:'var(--heading)',marginBottom:16 }}>Review &amp; SLA</div>
            <div className="form-row">
              <div className="form-group">
                <label>Review SLA Days</label>
                <input className="form-control" name="review_sla_days" type="number" min="1" max="90" defaultValue={settings.review_sla_days||7} />
              </div>
              <div className="form-group">
                <label>Escalation Days</label>
                <input className="form-control" name="escalation_days" type="number" min="1" max="180" defaultValue={settings.escalation_days||14} />
              </div>
            </div>

            <div style={{ fontSize:13,fontWeight:600,color:'var(--heading)',margin:'16px 0 12px' }}>Feature Flags</div>
            <div className="form-row">
              {[['anonymous_allowed','Allow Anonymous Submissions'],['public_board_enabled','Enable Public Idea Board'],
                ['challenges_enabled','Enable Challenges'],['email_enabled','Enable Email Notifications']].map(([k,label]) => (
                <div key={k} className="form-group">
                  <label style={{ display:'flex',alignItems:'center',gap:8,cursor:'pointer' }}>
                    <input type="checkbox" name={k} value="1" defaultChecked={settings[k]==='1'} style={{ accentColor:'var(--primary)' }} />
                    {label}
                  </label>
                </div>
              ))}
            </div>

            <div style={{ fontSize:13,fontWeight:600,color:'var(--heading)',margin:'16px 0 12px' }}>SMTP Email Settings</div>
            <div className="form-row">
              <div className="form-group"><label>SMTP Host</label><input className="form-control" name="smtp_host" defaultValue={settings.smtp_host||''} /></div>
              <div className="form-group"><label>SMTP Port</label><input className="form-control" name="smtp_port" type="number" defaultValue={settings.smtp_port||587} /></div>
            </div>
            <div className="form-row">
              <div className="form-group"><label>SMTP User</label><input className="form-control" name="smtp_user" defaultValue={settings.smtp_user||''} /></div>
              <div className="form-group"><label>SMTP Password</label><input className="form-control" name="smtp_pass" type="password" placeholder="(unchanged if blank)" /></div>
            </div>
            <div className="form-row">
              <div className="form-group"><label>From Email</label><input className="form-control" name="smtp_from" type="email" defaultValue={settings.smtp_from||''} /></div>
              <div className="form-group"><label>From Name</label><input className="form-control" name="smtp_from_name" defaultValue={settings.smtp_from_name||'IFQM Ideation'} /></div>
            </div>

            <div style={{ display:'flex',gap:8,marginTop:16 }}>
              <button type="submit" className="btn btn-primary">Save Settings</button>
              <button type="button" className="btn btn-outline" onClick={handleTestEmail}>Send Test Email</button>
            </div>
            {settingsMsg && <div style={{ marginTop:10,fontSize:13,color:settingsMsg.includes('success')||settingsMsg.startsWith('Settings')?'#10b981':'#ef4444' }}>{settingsMsg}</div>}
          </form>

          <div className="card" style={{ marginTop:24 }}>
            <div style={{ fontWeight:700,fontSize:13,marginBottom:12 }}>AI Scoring</div>
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
    ['trainee','Trainee'],['employee','Employee'],['team_lead','Team Lead'],
    ['project_lead','Project Lead'],['manager','Manager'],['senior_manager','Senior Manager'],
    ['executive','Executive'],
    ...(currentUserRole==='super_admin'?[['admin','Org Admin']]:[]),
  ];

  async function handleSubmit() {
    setError('');
    setSaving(true);
    const payload = { name, email, employee_id: empId, role, manager_id: mgr||null, department: dept, business_unit: bu, location: loc };
    if (isEdit) { payload.id = editUser.id; payload.status = status; }
    else payload.password = pass;
    try {
      const action = isEdit ? 'update_user' : 'create_user';
      const res = await usersApi[isEdit ? 'updateUser' : 'createUser'](payload);
      if (res.data.success) { showToast(isEdit?'User updated.':'User created.','success'); onSaved(); }
      else { setError(res.data.error||'Failed to save user.'); }
    } catch { setError('Server error.'); }
    setSaving(false);
  }

  return (
    <div className="modal-overlay open" onClick={e => e.target===e.currentTarget && onClose()}>
      <div className="modal" style={{ maxWidth:520 }}>
        <div className="modal-header">
          <span id="user-form-title">{isEdit?'Edit User':'Add User'}</span>
          <button className="modal-close" onClick={onClose}>✕</button>
        </div>
        <div className="modal-body">
          {error && <div className="alert alert-danger" id="user-form-error">{error}</div>}
          <div className="form-row">
            <div className="form-group"><label>Full Name *</label><input className="form-control" value={name} onChange={e=>setName(e.target.value)} id="uf-name" /></div>
            <div className="form-group"><label>Employee ID *</label><input className="form-control" value={empId} onChange={e=>setEmpId(e.target.value)} id="uf-emp-id" /></div>
          </div>
          <div className="form-group"><label>Email *</label><input className="form-control" type="email" value={email} onChange={e=>setEmail(e.target.value)} id="uf-email" /></div>
          {!isEdit && <div className="form-group" id="uf-pass-group"><label>Password *</label><input className="form-control" type="password" value={pass} onChange={e=>setPass(e.target.value)} id="uf-password" /></div>}
          <div className="form-row">
            <div className="form-group"><label>Role</label>
              <select className="form-control" id="uf-role" value={role} onChange={e=>setRole(e.target.value)}>
                {roleOptions.map(([v,l]) => <option key={v} value={v}>{l}</option>)}
              </select>
            </div>
            <div className="form-group"><label>Manager</label>
              <select className="form-control" id="uf-manager" value={mgr} onChange={e=>setMgr(e.target.value)}>
                <option value="">— None —</option>
                {managers.filter(m=>m.id!==editUser?.id).map(m => <option key={m.id} value={m.id}>{m.name} ({formatRole(m.role)})</option>)}
              </select>
            </div>
          </div>
          <div className="form-row">
            <div className="form-group"><label>Department</label><input className="form-control" value={dept} onChange={e=>setDept(e.target.value)} id="uf-dept" /></div>
            <div className="form-group"><label>Business Unit</label><input className="form-control" value={bu} onChange={e=>setBu(e.target.value)} id="uf-bu" /></div>
          </div>
          <div className="form-group"><label>Location</label><input className="form-control" value={loc} onChange={e=>setLoc(e.target.value)} id="uf-location" /></div>
          {isEdit && (
            <div className="form-group" id="uf-status-group"><label>Status</label>
              <select className="form-control" id="uf-status" value={status} onChange={e=>setStatus(e.target.value)}>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          )}
        </div>
        <div className="modal-footer">
          <button className="btn btn-outline" onClick={onClose}>Cancel</button>
          <button className="btn btn-primary" id="uf-submit-btn" disabled={saving} onClick={handleSubmit}>
            {saving?'Saving…':isEdit?'Save Changes':'Save User'}
          </button>
        </div>
      </div>
    </div>
  );
}
