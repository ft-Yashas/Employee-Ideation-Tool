import { useState, useEffect, useRef } from 'react';
import { useAuth } from '../context/AuthContext';
import { useLang } from '../context/LangContext';
import { useToast } from '../context/ToastContext';
import { ideasApi, votesApi } from '../services/api';
import { statusBadge, impactBadge, scoreBadgeClass, translateStatus, translateImpact, fmtDate, communityScore } from '../utils/helpers';
import IdeaDetailModal from '../components/IdeaDetailModal';

function VoteWidget({ ideaId, isSelf, upvotes, downvotes, userVote, onVote }) {
  return (
    <div style={{ display:'inline-flex',alignItems:'center',gap:4 }}>
      <button
        className="btn btn-sm"
        style={{ padding:'2px 6px',fontSize:11,borderRadius:6,
          background:userVote==='up'?'#bbf7d0':'var(--chip-bg)',
          color:userVote==='up'?'#10b981':'var(--text-muted)',
          border:`1px solid ${userVote==='up'?'#bbf7d0':'var(--border)'}` }}
        onClick={() => !isSelf && onVote(ideaId,'up')}
        disabled={isSelf}
      >▲ {upvotes}</button>
      <button
        className="btn btn-sm"
        style={{ padding:'2px 6px',fontSize:11,borderRadius:6,
          background:userVote==='down'?'#fee2e2':'var(--chip-bg)',
          color:userVote==='down'?'#ef4444':'var(--text-muted)',
          border:`1px solid ${userVote==='down'?'#fecaca':'var(--border)'}` }}
        onClick={() => !isSelf && onVote(ideaId,'down')}
        disabled={isSelf}
      >▼ {downvotes}</button>
    </div>
  );
}

export default function AllIdeasPage() {
  const { user }      = useAuth();
  const { t }         = useLang();
  const { showToast } = useToast();
  const [ideas,   setIdeas]   = useState([]);
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState('');
  const [search,  setSearch]  = useState('');
  const [status,  setStatus]  = useState('');
  const [impact,  setImpact]  = useState('');
  const [openId,  setOpenId]  = useState(null);
  const pollRef = useRef(null);

  useEffect(() => {
    loadIdeas();
    pollRef.current = setInterval(loadIdeas, 10000);
    return () => clearInterval(pollRef.current);
  }, [search, status, impact]);

  async function loadIdeas() {
    try {
      const res = await ideasApi.list({ search, status, impact });
      setIdeas(res.data.ideas || []);
    } catch { /* non-blocking poll */ }
    setLoading(false);
  }

  async function castVote(ideaId, voteType) {
    try {
      const res = await votesApi.communityVote({ idea_id: ideaId, vote_type: voteType });
      if (res.data.success) loadIdeas();
      else showToast(res.data.error || 'Error', 'danger');
    } catch { showToast('Network error', 'danger'); }
  }

  return (
    <>
      <div className="filter-bar">
        <input className="form-control" type="search" placeholder={t('filter.search_placeholder')}
          value={search} onChange={e => setSearch(e.target.value)} style={{ maxWidth:260 }} />
        <select className="form-control" value={status} onChange={e => setStatus(e.target.value)} style={{ width:160 }}>
          <option value="">{t('filter.all_statuses')}</option>
          <option value="Submitted">Submitted</option>
          <option value="Under Review">Under Review</option>
          <option value="Approved">Approved</option>
          <option value="Rejected">Rejected</option>
          <option value="Implemented">Implemented</option>
        </select>
        <select className="form-control" value={impact} onChange={e => setImpact(e.target.value)} style={{ width:160 }}>
          <option value="">All Impact Levels</option>
          <option value="High">High</option>
          <option value="Medium">Medium</option>
          <option value="Low">Low</option>
        </select>
      </div>

      {error && <div className="alert alert-danger">{error}</div>}

      <div className="card" style={{ overflowX:'auto' }}>
        <table className="table">
          <thead>
            <tr>
              <th>Code</th>
              <th>{t('table.title')}</th>
              <th>{t('table.submitter')}</th>
              <th>{t('table.dept')}</th>
              <th>Impact</th>
              <th>Score</th>
              <th>Votes</th>
              <th>{t('table.status')}</th>
              <th>{t('table.date')}</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="all-ideas-tbody">
            {loading && (
              <tr><td colSpan="10" className="text-center"><div className="spinner"></div></td></tr>
            )}
            {!loading && !ideas.length && (
              <tr><td colSpan="10" className="text-center">{t('msg.no_ideas')}</td></tr>
            )}
            {ideas.map(i => {
              const isSelf  = parseInt(i.submitter_id) === parseInt(user?.id);
              const cScore  = communityScore(i.ai_score, i.upvotes||0, i.downvotes||0);
              return (
                <tr key={i.id}>
                  <td><strong>{i.idea_code}</strong></td>
                  <td title={i.title}>{i.title.length > 60 ? i.title.substring(0,60)+'…' : i.title}</td>
                  <td>{i.submitter_name}</td>
                  <td>{i.department||'–'}</td>
                  <td><span className={`badge ${impactBadge(i.impact_level)}`}>{translateImpact(i.impact_level,t)||'–'}</span></td>
                  <td>
                    {i.ai_score > 0
                      ? <span id={`cscore-${i.id}`} className={scoreBadgeClass(cScore)}
                          title={`AI Score: ${i.ai_score}/100 · Community adjustment: ${cScore-i.ai_score>=0?'+':''}${cScore-i.ai_score}`}>
                          {cScore}/100
                        </span>
                      : <span className="score-none score-badge">—</span>
                    }
                  </td>
                  <td>
                    {i.status !== 'Draft'
                      ? <VoteWidget ideaId={i.id} isSelf={isSelf}
                          upvotes={i.upvotes||0} downvotes={i.downvotes||0}
                          userVote={i.user_community_vote||null} onVote={castVote} />
                      : <span style={{ fontSize:11,color:'var(--subtle)' }}>—</span>
                    }
                  </td>
                  <td><span className={`badge ${statusBadge(i.status)}`}>{translateStatus(i.status,t)}</span></td>
                  <td>{i.submitted_at ? fmtDate(i.submitted_at) : '–'}</td>
                  <td>
                    <button className="btn btn-outline btn-sm" onClick={() => setOpenId(i.id)}>
                      {t('idea.view')}
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {openId && <IdeaDetailModal ideaId={openId} onClose={() => { setOpenId(null); loadIdeas(); }} />}
    </>
  );
}
