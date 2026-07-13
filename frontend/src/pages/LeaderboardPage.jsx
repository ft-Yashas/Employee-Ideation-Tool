import { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { useLang } from '../context/LangContext';
import { leaderboardApi } from '../services/api';
import { scoreBadgeClass, engagementIndex } from '../utils/helpers';

const PERIODS = [
  { val:'all',       label:'lb.all' },
  { val:'monthly',   label:'lb.monthly' },
  { val:'quarterly', label:'lb.quarterly' },
  { val:'yearly',    label:'lb.yearly' },
];

function EngBadge({ aiScore, avgRating, voteCount, t }) {
  const ei = engagementIndex(aiScore, avgRating, voteCount);
  if (!aiScore && !voteCount) return null;
  const tier = ei >= 70 ? { bg:'#bbf7d0',color:'#065f46',lbl:t('eng.high') }
             : ei >= 40 ? { bg:'#fef3c7',color:'#92400e',lbl:t('eng.med')  }
             : { bg:'#fee2e2',color:'#991b1b',lbl:t('eng.low') };
  return (
    <span style={{ fontSize:10,fontWeight:700,padding:'2px 6px',borderRadius:20,background:tier.bg,color:tier.color,border:`1px solid ${tier.bg}`,display:'inline-block' }}>
      EI:{ei} {tier.lbl}
    </span>
  );
}

export default function LeaderboardPage() {
  const { user }  = useAuth();
  const { t }     = useLang();
  const [period,  setPeriod]  = useState('all');
  const [data,    setData]    = useState(null);
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState('');

  useEffect(() => { load(); }, [period]);

  async function load() {
    setLoading(true);
    setError('');
    try {
      const res = await leaderboardApi.get({ period });
      setData(res.data);
    } catch { setError(t('msg.fail_leaderboard')); }
    setLoading(false);
  }

  // Animate bars after data
  useEffect(() => {
    if (!data) return;
    setTimeout(() => {
      document.querySelectorAll('.progress-fill[data-w], #lb-departments .bar-fill[data-w]').forEach((bar, i) => {
        setTimeout(() => {
          bar.style.transition = 'width .8s cubic-bezier(.4,0,.2,1)';
          bar.style.width = bar.dataset.w + '%';
        }, i * 80);
      });
    }, 200);
  }, [data]);

  const indivs = data?.individuals || [];
  const depts  = data?.departments || [];
  const top    = data?.top_ideas   || [];
  const maxPts = Math.max(...indivs.map(u => u.points), 1);
  const maxDpt = Math.max(...depts.map(d => d.dept_points||0), 1);

  return (
    <>
      {/* Period chips */}
      <div className="chip-filter" style={{ marginBottom:20 }}>
        {PERIODS.map(p => (
          <div
            key={p.val}
            className={`chip${period===p.val?' active':''}`}
            data-val={p.val}
            onClick={() => setPeriod(p.val)}
          >
            {t(p.label)}
          </div>
        ))}
      </div>

      {loading && <div className="empty-state"><div className="spinner"></div></div>}
      {error   && <div className="alert alert-danger">{error}</div>}

      {!loading && !error && (
        <div style={{ display:'grid',gridTemplateColumns:'1fr 1fr',gap:20 }}>
          {/* Individuals */}
          <div className="card" style={{ gridColumn:'1/3' }}>
            <div style={{ fontWeight:700,fontSize:13,marginBottom:14,color:'var(--heading)' }}>{t('lb.top_employees')}</div>
            <div id="lb-individuals">
              {!indivs.length
                ? <div className="empty-state">{t('msg.no_leaderboard')}</div>
                : indivs.map((u, i) => {
                  const ei = engagementIndex(u.avg_score, u.avg_community_rating, u.total_votes_received);
                  return (
                    <div className="lb-row" key={u.id}>
                      <div className={`lb-rank ${i===0?'rank-1':i===1?'rank-2':i===2?'rank-3':'rank-n'}`}>{i+1}</div>
                      <div className="avatar">{u.avatar_initials||u.name?.[0]||'?'}</div>
                      <div style={{ flex:1 }}>
                        <div className="lb-name">
                          {u.name}
                          {u.id == user?.id && <span style={{ fontSize:11,color:'#f59e0b',marginLeft:4 }}>{t('lb.you')}</span>}
                        </div>
                        <div className="lb-dept">{u.department||'–'}</div>
                        <div className="progress-bar" style={{ marginTop:8 }}>
                          <div className="progress-fill" style={{ width:'0%' }} data-w={Math.round(u.points/maxPts*100)}></div>
                        </div>
                        {(u.avg_community_rating > 0 || u.total_votes_received > 0) && (
                          <div style={{ marginTop:4,fontSize:11,color:'var(--subtle)',display:'flex',gap:6 }}>
                            {u.avg_community_rating > 0 && <span>⭐ {parseFloat(u.avg_community_rating).toFixed(1)}</span>}
                            {u.total_votes_received > 0 && <span>🗳 {u.total_votes_received}</span>}
                          </div>
                        )}
                      </div>
                      <div style={{ textAlign:'right' }}>
                        <div className="lb-points">{u.points} {t('unit.pts')}</div>
                        <div className="lb-ideas">{u.idea_count||0} {t('unit.ideas')}</div>
                        {u.avg_score > 0 && (
                          <span className={`${scoreBadgeClass(u.avg_score)}`} style={{ marginTop:2,display:'inline-block' }}>
                            {t('lb.avg_score')}: {u.avg_score}
                          </span>
                        )}
                        <div style={{ marginTop:4 }}>
                          <EngBadge aiScore={u.avg_score} avgRating={u.avg_community_rating} voteCount={u.total_votes_received} t={t} />
                        </div>
                      </div>
                    </div>
                  );
                })
              }
            </div>
          </div>

          {/* Department bar chart */}
          <div className="card">
            <div style={{ fontWeight:700,fontSize:13,marginBottom:14,color:'var(--heading)' }}>{t('lb.by_dept')}</div>
            <div id="lb-departments">
              <div className="bar-chart">
                {depts.map(dep => (
                  <div className="bar-row" key={dep.department||'–'}>
                    <span className="bar-label">{(dep.department||'–').substring(0,12)}</span>
                    <div className="bar-track">
                      <div className="bar-fill" style={{ width:'0%',background:'linear-gradient(90deg,#374151,#6b7280)' }}
                        data-w={Math.round((dep.dept_points||0)/maxDpt*100)}></div>
                    </div>
                    <span className="bar-val">{dep.dept_points||0}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Top Scored Ideas */}
          <div className="card">
            <div style={{ fontWeight:700,fontSize:13,marginBottom:14,color:'var(--heading)' }}>{t('lb.top_ideas')}</div>
            <div id="lb-top-ideas">
              {!top.length
                ? <div className="empty-state">{t('msg.no_leaderboard')}</div>
                : top.map((idea, idx) => (
                  <div className="top-idea-row" key={idea.id||idx}>
                    <div className="top-idea-rank">#{idx+1}</div>
                    <div style={{ flex:1 }}>
                      <div style={{ fontSize:13,fontWeight:600,color:'var(--heading)' }}>{idea.title}</div>
                      <div style={{ fontSize:11,color:'var(--subtle)' }}>{idea.idea_code} · {idea.submitter_name} · {idea.department||'–'}</div>
                    </div>
                    <div style={{ textAlign:'right' }}>
                      <span className={scoreBadgeClass(idea.ai_score)}>{idea.ai_score}/100</span>
                    </div>
                  </div>
                ))
              }
            </div>
          </div>
        </div>
      )}
    </>
  );
}
