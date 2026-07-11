import { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { useLang } from '../context/LangContext';
import { useToast } from '../context/ToastContext';
import { votesApi } from '../services/api';
import { statusBadge, impactBadge, scoreBadgeClass, translateStatus, fmtDate } from '../utils/helpers';
import IdeaDetailModal from '../components/IdeaDetailModal';

export default function BoardPage() {
  const { user }      = useAuth();
  const { t }         = useLang();
  const { showToast } = useToast();
  const [ideas,   setIdeas]   = useState([]);
  const [sort,    setSort]    = useState('votes');
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState('');
  const [openId,  setOpenId]  = useState(null);

  useEffect(() => { load(); }, [sort]);

  async function load() {
    setLoading(true);
    setError('');
    try {
      const res = await votesApi.board({ sort });
      if (res.data.success) setIdeas(res.data.ideas || []);
      else setError(res.data.error || 'Failed to load board.');
    } catch { setError('Failed to load board.'); }
    setLoading(false);
  }

  async function castVote(ideaId, voteType) {
    try {
      const res = await votesApi.communityVote({ idea_id: ideaId, vote_type: voteType }); // POST /votes/community
      if (res.data.success) load();
      else showToast(res.data.error || 'Error', 'danger');
    } catch { showToast('Network error', 'danger'); }
  }

  return (
    <>
      <div className="filter-bar">
        <select className="form-control" value={sort} onChange={e => setSort(e.target.value)} style={{ width:180 }}>
          <option value="votes">Sort: Most Votes</option>
          <option value="newest">Sort: Newest</option>
          <option value="score">Sort: AI Score</option>
        </select>
      </div>

      {loading && <div className="empty-state"><div className="spinner"></div> Loading…</div>}
      {error   && <div className="alert alert-danger">{error}</div>}

      {!loading && !error && !ideas.length && (
        <div className="empty-state">No ideas on the board yet.</div>
      )}

      <div id="board-list">
        {ideas.map(i => {
          const upvotes   = parseInt(i.upvotes)||0;
          const downvotes = parseInt(i.downvotes)||0;
          const userVote  = i.user_vote;
          const isSelf    = parseInt(i.submitter_id) === parseInt(user?.id);
          const net       = upvotes - downvotes;
          return (
            <div key={i.id} className="idea-card" id={`board-card-${i.id}`}>
              <div style={{ display:'flex',gap:12 }}>
                <div style={{ display:'flex',flexDirection:'column',alignItems:'center',gap:4,minWidth:44 }}>
                  <button
                    className="btn btn-sm"
                    style={{
                      padding:'4px 8px',borderRadius:8,fontSize:13,fontWeight:700,
                      background:userVote==='up'?'#bbf7d0':'var(--chip-bg)',
                      color:userVote==='up'?'#10b981':'var(--text-muted)',
                      border:`1px solid ${userVote==='up'?'#bbf7d0':'var(--border)'}`
                    }}
                    onClick={() => !isSelf && castVote(i.id, 'up')}
                    disabled={isSelf}
                  >▲</button>
                  <span style={{ fontSize:14,fontWeight:700,color:'var(--heading)' }}>{net}</span>
                  <button
                    className="btn btn-sm"
                    style={{
                      padding:'4px 8px',borderRadius:8,fontSize:13,fontWeight:700,
                      background:userVote==='down'?'#fee2e2':'var(--chip-bg)',
                      color:userVote==='down'?'#ef4444':'var(--text-muted)',
                      border:`1px solid ${userVote==='down'?'#fecaca':'var(--border)'}`
                    }}
                    onClick={() => !isSelf && castVote(i.id, 'down')}
                    disabled={isSelf}
                  >▼</button>
                </div>
                <div style={{ flex:1 }}>
                  <div style={{ fontSize:15,fontWeight:600,color:'var(--heading)' }}>{i.title}</div>
                  <div style={{ fontSize:12,color:'var(--text-muted)',marginTop:2 }}>
                    {i.submitter_name} · {i.department||'–'} · {fmtDate(i.created_at)}
                  </div>
                  <div style={{ fontSize:13,color:'var(--text)',marginTop:6,WebkitLineClamp:2,display:'-webkit-box',WebkitBoxOrient:'vertical',overflow:'hidden' }}>
                    {i.present_situation}
                  </div>
                  <div style={{ display:'flex',gap:8,marginTop:8,alignItems:'center',flexWrap:'wrap' }}>
                    <span className={`badge ${statusBadge(i.status)}`}>{translateStatus(i.status,t)}</span>
                    <span className={`badge ${impactBadge(i.impact_level)}`}>{i.impact_level} Impact</span>
                    {i.ai_score > 0 && <span className={scoreBadgeClass(i.ai_score)}>AI: {i.ai_score}/100</span>}
                    <button className="btn btn-outline btn-sm" onClick={() => setOpenId(i.id)}>View</button>
                  </div>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {openId && <IdeaDetailModal ideaId={openId} onClose={() => { setOpenId(null); load(); }} />}
    </>
  );
}
