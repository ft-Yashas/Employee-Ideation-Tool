import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLang } from '../context/LangContext';
import { useToast } from '../context/ToastContext';
import { challengesApi } from '../services/api';
import { isPrivileged, fmtDate } from '../utils/helpers';

export default function ChallengesPage() {
  const { user }       = useAuth();
  const { t }          = useLang();
  const { showToast }  = useToast();
  const navigate       = useNavigate();
  const [list,    setList]    = useState([]);
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState('');
  const isPriv = isPrivileged(user?.role);

  useEffect(() => { load(); }, []);

  async function load() {
    setLoading(true);
    setError('');
    try {
      const res = await challengesApi.list();
      if (res.data.success) setList(res.data.challenges || []);
      else setError(res.data.error || 'Failed to load challenges.');
    } catch { setError('Failed to load challenges.'); }
    setLoading(false);
  }

  async function handleCreate() {
    const title = prompt('Challenge title:');
    if (!title?.trim()) return;
    const desc     = prompt('Description (optional):') || '';
    const deadline = prompt('Deadline (YYYY-MM-DD, optional):') || null;
    try {
      const res = await challengesApi.create({ title: title.trim(), description: desc, deadline: deadline || null });
      if (res.data.success) { showToast('Challenge created.', 'success'); load(); }
      else showToast(res.data.error || 'Error', 'danger');
    } catch { showToast('Network error.', 'danger'); }
  }

  async function handleClose(id) {
    if (!confirm('Close this challenge? Submissions will stop.')) return;
    try {
      const res = await challengesApi.update({ id, status: 'closed' });
      if (res.data.success) { showToast('Challenge closed.', 'success'); load(); }
      else showToast(res.data.error || 'Error', 'danger');
    } catch { showToast('Network error.', 'danger'); }
  }

  return (
    <>
      <div style={{ display:'flex',justifyContent:'flex-end',marginBottom:16 }}>
        {isPriv && (
          <button className="btn btn-primary btn-sm" id="btn-new-challenge" onClick={handleCreate}>
            + New Challenge
          </button>
        )}
      </div>

      {loading && <div className="empty-state"><div className="spinner"></div> Loading…</div>}
      {error   && <div className="alert alert-danger">{error}</div>}

      {!loading && !error && !list.length && (
        <div className="empty-state">No active challenges at the moment.</div>
      )}

      <div id="challenges-list">
        {list.map(c => (
          <div key={c.id} className="card" style={{ marginBottom:12 }}>
            <div style={{ display:'flex',justifyContent:'space-between',alignItems:'flex-start' }}>
              <div>
                <div style={{ fontSize:15,fontWeight:600,color:'var(--heading)' }}>{c.title}</div>
                <div style={{ fontSize:12,color:'var(--text-muted)',marginTop:2 }}>
                  By {c.creator_name||'Admin'} · {c.deadline ? 'Deadline: ' + fmtDate(c.deadline) : 'No deadline'} · {c.idea_count||0} ideas
                </div>
              </div>
              <span style={{
                padding:'3px 10px',borderRadius:20,fontSize:11,fontWeight:600,
                background:c.status==='active'?'#bbf7d0':'#f1f5f9',
                color:c.status==='active'?'#10b981':'#64748b',
                border:`1px solid ${c.status==='active'?'#bbf7d0':'#e2e8f0'}`
              }}>{c.status}</span>
            </div>
            {c.description && (
              <div style={{ marginTop:10,fontSize:13,color:'var(--text)' }}>{c.description}</div>
            )}
            <div style={{ marginTop:12,display:'flex',gap:8 }}>
              <button className="btn btn-primary btn-sm" onClick={() => navigate('/submit')}>
                Submit Idea for This Challenge
              </button>
              {isPriv && c.status === 'active' && (
                <button className="btn btn-outline btn-sm" onClick={() => handleClose(c.id)}>
                  Close Challenge
                </button>
              )}
            </div>
          </div>
        ))}
      </div>
    </>
  );
}
