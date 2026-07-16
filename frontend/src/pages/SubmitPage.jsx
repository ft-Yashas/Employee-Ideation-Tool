import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useLang } from '../context/LangContext';
import { useToast } from '../context/ToastContext';
import { useAuth } from '../context/AuthContext';
import { ideasApi, challengesApi, uploadApi, usersApi } from '../services/api';
import { translateStatus, translateImpact, translateArea } from '../utils/helpers';

const TOTAL_STEPS = 5;
const IMPACT_AREAS = ['Cost Reduction','Quality Improvement','Safety','Productivity','Customer Satisfaction','Process Efficiency','Innovation'];
const IMPACT_LEVELS = ['Low','Medium','High','Critical'];

export default function SubmitPage() {
  const { user }      = useAuth();
  const { t }         = useLang();
  const { showToast } = useToast();
  const navigate      = useNavigate();

  const [step,    setStep]    = useState(1);
  const [draftId, setDraftId] = useState(null);

  // Step 1 fields
  const [title,     setTitle]     = useState('');
  const [situation, setSituation] = useState('');
  const [dupWarning, setDupWarning] = useState([]);

  // Step 2 fields
  const [solution,    setSolution]    = useState('');
  const [tangible,    setTangible]    = useState('');
  const [intangible,  setIntangible]  = useState('');
  const [impactAreas, setImpactAreas] = useState([]);
  const [impactLevel, setImpactLevel] = useState('Medium');

  // Step 3 files
  const [fileSit, setFileSit] = useState(null);
  const [fileSol, setFileSol] = useState(null);

  // Step 4 co-suggesters
  const [co1Id, setCo1Id] = useState('');
  const [co1Name, setCo1Name] = useState('');
  const [co2Id, setCo2Id] = useState('');
  const [co2Name, setCo2Name] = useState('');
  const [co1Query, setCo1Query] = useState('');
  const [co2Query, setCo2Query] = useState('');
  const [co1Results, setCo1Results] = useState([]);
  const [co2Results, setCo2Results] = useState([]);

  // Step 5 options
  const [anonymous,    setAnonymous]    = useState(false);
  const [templateType, setTemplateType] = useState('');
  const [challengeId,  setChallengeId]  = useState('');
  const [challenges,   setChallenges]   = useState([]);

  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const dupTimerRef = useRef(null);
  const searchTimers = useRef({});

  useEffect(() => { loadChallenges(); }, []);

  async function loadChallenges() {
    try {
      const res = await challengesApi.list();
      if (res.data.success) setChallenges(res.data.challenges?.filter(c => c.status==='active') || []);
    } catch {}
  }

  async function checkDuplicate(titleVal) {
    clearTimeout(dupTimerRef.current);
    if (titleVal.length < 8) { setDupWarning([]); return; }
    dupTimerRef.current = setTimeout(async () => {
      try {
        const res = await ideasApi.checkDuplicate({ title: titleVal });
        setDupWarning(res.data.duplicates || []);
      } catch {}
    }, 600);
  }

  async function searchUsers(query, which) {
    clearTimeout(searchTimers.current[which]);
    if (query.length < 2) { which==='1' ? setCo1Results([]) : setCo2Results([]); return; }
    searchTimers.current[which] = setTimeout(async () => {
      try {
        const res = await usersApi.list({ q: query });
        which==='1' ? setCo1Results(res.data.users||[]) : setCo2Results(res.data.users||[]);
      } catch {}
    }, 300);
  }

  function toggleImpact(area) {
    setImpactAreas(prev => prev.includes(area) ? prev.filter(a=>a!==area) : [...prev, area]);
  }

  function validateStep() {
    if (step === 1) {
      if (!title.trim() || situation.trim().length < 20) {
        setError(t('msg.fill_situation')); return false;
      }
    }
    if (step === 2 && !solution.trim()) {
      setError(t('msg.fill_solution')); return false;
    }
    setError('');
    return true;
  }

  function goStep(n) {
    if (n > step && !validateStep()) return;
    setStep(n);
    setError('');
  }

  function buildPayload() {
    return {
      title,
      present_situation:  situation,
      proposed_solution:  solution,
      impact_areas:       impactAreas.join(','),
      impact_level:       impactLevel,
      tangible_benefit:   tangible,
      intangible_benefit: intangible,
      co_suggester_1_id:  co1Id || null,
      co_suggester_2_id:  co2Id || null,
      is_anonymous:       anonymous ? 1 : 0,
      template_type:      templateType || null,
      challenge_id:       challengeId || null,
    };
  }

  async function handleSaveDraft() {
    const body = { ...buildPayload(), id: draftId };
    try {
      const res = await ideasApi.saveDraft(body);
      if (res.data.success) {
        setDraftId(res.data.idea_id);
        showToast(`${t('msg.draft_prefix')} ${res.data.idea_code}`, 'success');
      }
    } catch { showToast(t('msg.draft_failed'), 'danger'); }
  }

  async function handleSubmit() {
    setSubmitting(true);
    setError('');
    const body = { ...buildPayload(), id: draftId };
    try {
      const res = await ideasApi.submit(body);
      if (res.data.success) {
        // Upload files
        await uploadFiles(res.data.idea_id);
        const msg = t('msg.idea_ok', { code: res.data.idea_code });
        const pts = res.data.points_added > 0 ? ' · ' + t('msg.pts_earned', { n: res.data.points_added }) : '';
        showToast(msg + pts, 'success');
        // Reset form
        resetForm();
        navigate('/my-ideas');
      } else {
        setError(res.data.error || t('msg.submit_failed'));
      }
    } catch { setError(t('msg.server_error')); }
    setSubmitting(false);
  }

  async function uploadFiles(ideaId) {
    const uploads = [];
    if (fileSit) uploads.push({ file: fileSit, section: 'situation' });
    if (fileSol) uploads.push({ file: fileSol, section: 'solution' });
    for (const { file, section } of uploads) {
      const fd = new FormData();
      fd.append('file', file);
      fd.append('idea_id', ideaId);
      fd.append('section', section);
      try { await uploadApi.upload(fd); } catch {}
    }
  }

  function resetForm() {
    setTitle(''); setSituation(''); setSolution(''); setTangible(''); setIntangible('');
    setImpactAreas([]); setImpactLevel('Medium'); setFileSit(null); setFileSol(null);
    setCo1Id(''); setCo1Name(''); setCo2Id(''); setCo2Name('');
    setCo1Query(''); setCo2Query('');
    setAnonymous(false); setTemplateType(''); setChallengeId('');
    setDraftId(null); setStep(1); setError(''); setDupWarning([]);
  }

  const stepLabels = [
    t('wizard.step1'), t('wizard.step2'), t('wizard.step3'),
    t('wizard.step4'), t('wizard.step5'),
  ];

  return (
    <>
      {/* Wizard Steps */}
      <div className="wizard-steps">
        {stepLabels.map((label, i) => (
          <div key={i} className={`w-step${step===i+1?' active':step>i+1?' done':''}`}>
            <div className="w-step-circle">{step > i+1 ? '✓' : i+1}</div>
            <div className="w-step-label">{label}</div>
          </div>
        ))}
      </div>

      <div className="card" style={{ marginTop:20,maxWidth:720 }}>
        {error && <div className="alert alert-danger" style={{ marginBottom:16 }}>{error}</div>}

        {/* Step 1: Situation */}
        {step === 1 && (
          <div style={{ animation:'fadeInUp .25s cubic-bezier(.4,0,.2,1)' }}>
            <div className="form-group">
              <label>{t('form.title')} *</label>
              <input className="form-control" value={title}
                onChange={e => { setTitle(e.target.value); checkDuplicate(e.target.value); }}
                placeholder={t('form.title_ph')} />
              {dupWarning.length > 0 && (
                <div id="duplicate-warning" className="alert alert-warning" style={{ marginTop:6 }}>
                  ⚠ {t('form.dup_warning')}
                  <ul style={{ margin:'4px 0 0 16px' }}>
                    {dupWarning.map(x => (
                      <li key={x.id}><strong>{x.idea_code}</strong>: {x.title} <span style={{ color:'var(--text-muted)' }}>({translateStatus(x.status, t)})</span></li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
            <div className="form-group">
              <label>{t('form.situation')} * <span style={{ fontWeight:400,fontSize:11,color:'var(--subtle)' }}>{t('form.min_chars')}</span></label>
              <textarea className="form-control" rows="5" value={situation}
                onChange={e => setSituation(e.target.value)}
                placeholder={t('form.situation_ph')} />
            </div>
          </div>
        )}

        {/* Step 2: Solution & Impact */}
        {step === 2 && (
          <div style={{ animation:'fadeInUp .25s cubic-bezier(.4,0,.2,1)' }}>
            <div className="form-group">
              <label>{t('form.solution')} *</label>
              <textarea className="form-control" rows="5" value={solution}
                onChange={e => setSolution(e.target.value)}
                placeholder={t('form.solution_ph')} />
            </div>
            <div className="form-row">
              <div className="form-group">
                <label>{t('form.tangible')}</label>
                <textarea className="form-control" rows="3" value={tangible}
                  onChange={e => setTangible(e.target.value)}
                  placeholder={t('form.tangible_ph')} />
              </div>
              <div className="form-group">
                <label>{t('form.intangible')}</label>
                <textarea className="form-control" rows="3" value={intangible}
                  onChange={e => setIntangible(e.target.value)}
                  placeholder={t('form.intangible_ph')} />
              </div>
            </div>
            <div className="form-group">
              <label>{t('form.impact_areas')}</label>
              <div style={{ display:'flex',flexWrap:'wrap',gap:8,marginTop:4 }}>
                {IMPACT_AREAS.map(a => (
                  <div key={a} className={`impact-chip${impactAreas.includes(a)?' selected':''}`}
                    data-val={a} onClick={() => toggleImpact(a)}>{translateArea(a, t)}</div>
                ))}
              </div>
            </div>
            <div className="form-group">
              <label>{t('form.impact_level')}</label>
              <select className="form-control" value={impactLevel} onChange={e => setImpactLevel(e.target.value)}>
                {IMPACT_LEVELS.map(l => <option key={l} value={l}>{translateImpact(l, t)}</option>)}
              </select>
            </div>
          </div>
        )}

        {/* Step 3: Attachments */}
        {step === 3 && (
          <div style={{ animation:'fadeInUp .25s cubic-bezier(.4,0,.2,1)' }}>
            <div className="form-group">
              <label>{t('form.attach_situation')}</label>
              <input type="file" className="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                onChange={e => setFileSit(e.target.files[0]||null)} />
              {fileSit && <div style={{ fontSize:12,color:'var(--subtle)',marginTop:4 }}>{fileSit.name}</div>}
            </div>
            <div className="form-group">
              <label>{t('form.attach_solution')}</label>
              <input type="file" className="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                onChange={e => setFileSol(e.target.files[0]||null)} />
              {fileSol && <div style={{ fontSize:12,color:'var(--subtle)',marginTop:4 }}>{fileSol.name}</div>}
            </div>
            <div style={{ fontSize:12,color:'var(--subtle)' }}>{t('form.attach_note')}</div>
          </div>
        )}

        {/* Step 4: Co-Suggesters */}
        {step === 4 && (
          <div style={{ animation:'fadeInUp .25s cubic-bezier(.4,0,.2,1)' }}>
            <div className="form-group">
              <label>{t('form.co1')}</label>
              <div className="pos-rel">
                <input className="form-control" value={co1Query}
                  onChange={e => { setCo1Query(e.target.value); setCo1Id(''); setCo1Name(''); searchUsers(e.target.value,'1'); }}
                  placeholder={t('form.co_search_ph')} />
                {co1Results.length > 0 && (
                  <div className="user-search-results" style={{ display:'block' }}>
                    {co1Results.map(u => (
                      <div key={u.id} className="uitem" onClick={() => {
                        setCo1Id(u.id); setCo1Name(`${u.name} (${u.employee_id})`);
                        setCo1Query(u.name); setCo1Results([]);
                      }}>{u.name} · {u.employee_id} · {u.department||'–'}</div>
                    ))}
                  </div>
                )}
              </div>
              {co1Name && <div style={{ fontSize:12,color:'#10b981',marginTop:4 }}>✓ {co1Name}</div>}
            </div>
            <div className="form-group">
              <label>{t('form.co2')}</label>
              <div className="pos-rel">
                <input className="form-control" value={co2Query}
                  onChange={e => { setCo2Query(e.target.value); setCo2Id(''); setCo2Name(''); searchUsers(e.target.value,'2'); }}
                  placeholder={t('form.co_search_ph')} />
                {co2Results.length > 0 && (
                  <div className="user-search-results" style={{ display:'block' }}>
                    {co2Results.map(u => (
                      <div key={u.id} className="uitem" onClick={() => {
                        setCo2Id(u.id); setCo2Name(`${u.name} (${u.employee_id})`);
                        setCo2Query(u.name); setCo2Results([]);
                      }}>{u.name} · {u.employee_id} · {u.department||'–'}</div>
                    ))}
                  </div>
                )}
              </div>
              {co2Name && <div style={{ fontSize:12,color:'#10b981',marginTop:4 }}>✓ {co2Name}</div>}
            </div>
          </div>
        )}

        {/* Step 5: Review & Submit */}
        {step === 5 && (
          <div style={{ animation:'fadeInUp .25s cubic-bezier(.4,0,.2,1)' }}>
            <div style={{ marginBottom:16,fontSize:13,fontWeight:600,color:'var(--heading)' }}>{t('form.review_heading')}</div>

            <div className="form-group">
              <label>{t('preview.title_label')}</label>
              <div className="form-control" style={{ background:'var(--panel-bg)' }}>{title}</div>
            </div>
            <div className="form-group">
              <label>{t('preview.situation')}</label>
              <div className="form-control" style={{ background:'var(--panel-bg)',height:'auto',minHeight:60 }}>{situation}</div>
            </div>
            <div className="form-group">
              <label>{t('preview.solution')}</label>
              <div className="form-control" style={{ background:'var(--panel-bg)',height:'auto',minHeight:60 }}>{solution}</div>
            </div>
            <div className="form-row">
              <div>
                <label>{t('preview.impact_areas')}</label>
                <div className="form-control" style={{ background:'var(--panel-bg)' }}>
                  {impactAreas.map(a => translateArea(a, t)).join(', ') || t('preview.none_selected')}
                </div>
              </div>
              <div>
                <label>{t('preview.impact_level')}</label>
                <div className="form-control" style={{ background:'var(--panel-bg)' }}>{translateImpact(impactLevel, t)}</div>
              </div>
            </div>
            {co1Name && (
              <div className="form-group">
                <label>{t('preview.co_suggesters')}</label>
                <div className="form-control" style={{ background:'var(--panel-bg)' }}>{co1Name}{co2Name ? ', ' + co2Name : ''}</div>
              </div>
            )}

            <div className="form-row" style={{ marginTop:16,gap:20 }}>
              <div className="form-group">
                <label>{t('form.template')}</label>
                <select className="form-control" value={templateType} onChange={e => setTemplateType(e.target.value)}>
                  <option value="">{t('form.no_template')}</option>
                  <option value="cost">{t('form.tpl_cost')}</option>
                  <option value="quality">{t('form.tpl_quality')}</option>
                  <option value="safety">{t('form.tpl_safety')}</option>
                  <option value="process">{t('form.tpl_process')}</option>
                </select>
              </div>
              {challenges.length > 0 && (
                <div className="form-group">
                  <label>{t('form.challenge')}</label>
                  <select className="form-control" id="idea-challenge" value={challengeId} onChange={e => setChallengeId(e.target.value)}>
                    <option value="">{t('form.no_challenge')}</option>
                    {challenges.map(c => <option key={c.id} value={c.id}>{c.title}</option>)}
                  </select>
                </div>
              )}
            </div>

            <label style={{ display:'flex',alignItems:'center',gap:8,cursor:'pointer',fontSize:13,marginTop:8 }}>
              <input type="checkbox" id="idea-anonymous" checked={anonymous} onChange={e => setAnonymous(e.target.checked)} style={{ accentColor:'var(--primary)' }} />
              {t('form.anonymous')}
            </label>

            <div id="wizard-submit-row" style={{ display:'flex',gap:10,marginTop:24 }}>
              <button className="btn btn-success" disabled={submitting} onClick={handleSubmit}>
                {submitting ? t('msg.loading') : t('form.submit_idea')}
              </button>
              <button className="btn btn-outline" disabled={submitting} onClick={handleSaveDraft}>
                {t('form.save_draft')}
              </button>
            </div>
          </div>
        )}

        {/* Navigation */}
        {step < 5 && (
          <div id="wizard-nav" style={{ display:'flex',justifyContent:'space-between',marginTop:24 }}>
            <button className="btn btn-outline" style={{ visibility:step>1?'visible':'hidden' }} onClick={() => goStep(step-1)}>
              ← {t('btn.back')}
            </button>
            <button className="btn btn-primary" onClick={() => goStep(step+1)}>
              {step === 4 ? t('btn.review') : t('btn.next')} →
            </button>
          </div>
        )}

        {step === 5 && (
          <div style={{ marginTop:12 }}>
            <button className="btn btn-outline btn-sm" onClick={() => goStep(step-1)}>← {t('btn.back')}</button>
          </div>
        )}
      </div>
    </>
  );
}
