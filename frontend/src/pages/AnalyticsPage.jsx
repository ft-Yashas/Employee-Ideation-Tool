import { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { useLang } from '../context/LangContext';
import { usersApi } from '../services/api';
import { isPrivileged, translateStatus } from '../utils/helpers';

const STATUS_COLORS = {
  'Submitted':'#374151','Under Review':'#f59e0b','Approved':'#10b981',
  'Rejected':'#ef4444','Implemented':'#4b5563','Draft':'#94a3b8',
};
const IMP_COLORS = ['#374151','#4b5563','#4b5563','#9ca3af','#9ca3af','#d1d5db'];

export default function AnalyticsPage() {
  const { user }   = useAuth();
  const { t }      = useLang();
  const [data,    setData]    = useState(null);
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState('');

  useEffect(() => { load(); }, []);

  async function load() {
    if (!isPrivileged(user?.role)) {
      setError(t('msg.analytics_restricted'));
      setLoading(false);
      return;
    }
    setLoading(true);
    setError('');
    try {
      const res = await usersApi.analytics();
      if (res.data.success) setData(res.data);
      else setError(res.data.error || t('msg.fail_analytics'));
    } catch { setError(t('msg.fail_analytics')); }
    setLoading(false);
  }

  // Animate bars and counters after data
  useEffect(() => {
    if (!data) return;
    document.querySelectorAll('#analytics-kpis .kpi-val[data-target]').forEach(el => {
      const suffix = el.dataset.suffix || '';
      const target = parseInt(el.dataset.target);
      const start  = performance.now();
      (function step(now) {
        const p    = Math.min((now - start) / 900, 1);
        const ease = 1 - Math.pow(1-p, 3);
        el.textContent = Math.round(target * ease) + suffix;
        if (p < 1) requestAnimationFrame(step);
      })(start);
    });
    setTimeout(() => {
      ['#analytics-status','#analytics-trend'].forEach(sel => {
        document.querySelectorAll(`${sel} .bar-fill[data-w]`).forEach((bar, i) => {
          setTimeout(() => {
            bar.style.transition = 'width .7s cubic-bezier(.4,0,.2,1)';
            bar.style.width = bar.dataset.w + '%';
          }, i * 80);
        });
      });
    }, 150);
  }, [data]);

  if (loading) return <div className="empty-state"><div className="spinner"></div></div>;
  if (error)   return <div className="alert alert-warning">{error}</div>;
  if (!data)   return null;

  const counts  = {};
  (data.status_summary||[]).forEach(s => { counts[s.status] = s.cnt; });
  const total   = Object.values(counts).reduce((a,b)=>a+b,0);
  const approved = (counts['Approved']||0) + (counts['Implemented']||0);
  const impl     = counts['Implemented']||0;
  const ss       = data.score_stats || {};
  const hq = parseInt(ss.high_quality||0);
  const mq = parseInt(ss.medium_quality||0);
  const lq = parseInt(ss.low_quality||0);
  const maxQ = Math.max(hq,mq,lq,1);
  const trend = [...(data.trend||[])].reverse();
  const maxTrend = Math.max(...trend.map(x=>x.total), 1);
  const impDist  = Object.entries(data.impact_distribution||{});
  const maxImp   = Math.max(...impDist.map(([,v])=>v), 1);

  return (
    <>
      {/* KPI Grid */}
      <div className="kpi-grid" id="analytics-kpis">
        <div className="kpi-card" style={{ borderLeftColor:'#1f2937' }}>
          <div className="kpi-icon" style={{ background:'#c8ccd1',color:'#374151' }}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="22" height="22">
              <path d="M9 21h6M12 3a6 6 0 016 6c0 2.2-1.1 3.8-2.5 5L15 16H9l-.5-2C7 12.8 6 11.2 6 9a6 6 0 016-6z"/>
            </svg>
          </div>
          <div className="kpi-body">
            <div className="kpi-val" data-target={total}>0</div>
            <div className="kpi-label">{t('dash.total')}</div>
          </div>
        </div>
        <div className="kpi-card" style={{ borderLeftColor:'#10b981' }}>
          <div className="kpi-icon" style={{ background:'#bbf7d0',color:'#10b981' }}>
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <div className="kpi-body">
            <div className="kpi-val" data-target={total ? Math.round(approved/total*100) : 0} data-suffix="%">0%</div>
            <div className="kpi-label">{t('analytics.approval_rate')}</div>
          </div>
        </div>
        <div className="kpi-card" style={{ borderLeftColor:'#374151' }}>
          <div className="kpi-icon" style={{ background:'#c8ccd1',color:'#4b5563' }}>
            <svg viewBox="0 0 24 24">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
          </div>
          <div className="kpi-body">
            <div className="kpi-val" data-target={total ? Math.round(impl/total*100) : 0} data-suffix="%">0%</div>
            <div className="kpi-label">{t('analytics.impl_rate')}</div>
          </div>
        </div>
        <div className="kpi-card" style={{ borderLeftColor:'#1f2937' }}>
          <div className="kpi-icon" style={{ background:'#c8ccd1',color:'#374151' }}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="22" height="22">
              <path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/>
            </svg>
          </div>
          <div className="kpi-body">
            <div className="kpi-val" data-target={ss.overall_avg||0}>0</div>
            <div className="kpi-label">{t('analytics.avg_score')}</div>
          </div>
        </div>
      </div>

      <div style={{ display:'grid',gridTemplateColumns:'1fr 1fr',gap:20,marginTop:20 }}>
        {/* Status Distribution */}
        <div className="card">
          <div style={{ fontWeight:700,fontSize:13,marginBottom:12 }}>{t('analytics.status_dist')}</div>
          <div className="bar-chart" id="analytics-status">
            {(data.status_summary||[]).map(s => (
              <div className="bar-row" key={s.status}>
                <span className="bar-label">{translateStatus(s.status,t)}</span>
                <div className="bar-track">
                  <div className="bar-fill" style={{ width:'0%',background:STATUS_COLORS[s.status]||'#ccc' }}
                    data-w={Math.round(s.cnt/Math.max(total,1)*100)}></div>
                </div>
                <span className="bar-val">{s.cnt}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Impact Distribution */}
        <div className="card">
          <div style={{ fontWeight:700,fontSize:13,marginBottom:12 }}>{t('analytics.impact_dist')}</div>
          <div className="bar-chart" id="analytics-impact">
            {impDist.map(([k,v], i) => (
              <div className="bar-row" key={k}>
                <span className="bar-label">{k}</span>
                <div className="bar-track">
                  <div className="bar-fill" style={{ width:`${Math.round(v/maxImp*100)}%`,background:IMP_COLORS[i%IMP_COLORS.length] }}></div>
                </div>
                <span className="bar-val">{v}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Monthly Trend */}
        <div className="card">
          <div style={{ fontWeight:700,fontSize:13,marginBottom:12 }}>{t('analytics.monthly_trend')}</div>
          <div className="bar-chart" id="analytics-trend">
            {trend.length
              ? trend.map(row => (
                <div className="bar-row" key={row.month}>
                  <span className="bar-label">{row.month}</span>
                  <div className="bar-track">
                    <div className="bar-fill" style={{ width:'0%',background:'linear-gradient(90deg,#374151,#6b7280)' }}
                      data-w={Math.round(row.total/maxTrend*100)}></div>
                  </div>
                  <span className="bar-val">{row.total}</span>
                </div>
              ))
              : <div className="empty-state">No trend data yet.</div>
            }
          </div>
        </div>

        {/* Score Distribution */}
        <div className="card">
          <div style={{ fontWeight:700,fontSize:13,marginBottom:12 }}>{t('analytics.score_dist')}</div>
          <div id="analytics-score-dist">
            <div className="bar-chart">
              <div className="bar-row">
                <span className="bar-label">{t('analytics.high')}</span>
                <div className="bar-track"><div className="bar-fill" style={{ width:`${Math.round(hq/maxQ*100)}%`,background:'#10b981' }}></div></div>
                <span className="bar-val">{hq}</span>
              </div>
              <div className="bar-row">
                <span className="bar-label">{t('analytics.med')}</span>
                <div className="bar-track"><div className="bar-fill" style={{ width:`${Math.round(mq/maxQ*100)}%`,background:'#f59e0b' }}></div></div>
                <span className="bar-val">{mq}</span>
              </div>
              <div className="bar-row">
                <span className="bar-label">{t('analytics.low_score')}</span>
                <div className="bar-track"><div className="bar-fill" style={{ width:`${Math.round(lq/maxQ*100)}%`,background:'#ef4444' }}></div></div>
                <span className="bar-val">{lq}</span>
              </div>
            </div>
            <div style={{ fontSize:11,color:'var(--subtle)',marginTop:8 }}>
              {t('analytics.avg_note')} <strong>{ss.overall_avg||0}/100</strong>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
