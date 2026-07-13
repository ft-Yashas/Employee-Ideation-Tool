import { useState } from 'react';
import { useLang } from '../context/LangContext';
import { useToast } from '../context/ToastContext';
import { ideasApi } from '../services/api';

export default function ReviewActionModal({ ideaId, ideaCode, onClose }) {
  const { t }         = useLang();
  const { showToast } = useToast();
  const [decision, setDecision] = useState('Approved');
  const [comment,  setComment]  = useState('');
  const [loading,  setLoading]  = useState(false);

  const DECISIONS = [
    ['Approved',     'review.approve'],
    ['Rejected',     'review.reject'],
    ['Implemented',  'review.implement'],
    ['Under Review', 'review.to_review'],
  ];

  async function handleSubmit() {
    const label = DECISIONS.find(([v]) => v === decision)?.[1];
    if (!confirm(t('review.confirm', { action: label ? t(label) : decision }))) return;

    setLoading(true);
    try {
      const res = await ideasApi.reviewAction({ idea_id: ideaId, decision, comment });
      if (res.data.success) {
        const d = res.data;
        const isEsc = d.decision === 'Escalated';
        const pts = d.points_awarded ? ` · ${t('msg.pts_earned', { n: d.points_awarded })}` : '';
        showToast(
          isEsc
            ? `${t('status.escalated')}: ${d.escalated_to}`
            : `${t('msg.decision_ok')}: ${t(label || 'review.decision_label')}${pts}`,
          'success'
        );
        onClose();
      } else {
        showToast(`${t('msg.error')}: ` + (res.data.error || t('msg.server_error')), 'danger');
      }
    } catch { showToast(t('msg.server_error'), 'danger'); }
    setLoading(false);
  }

  return (
    <div className="modal-overlay open" id="modal-review" onClick={e => e.target===e.currentTarget && onClose()}>
      <div className="modal" style={{ maxWidth:440 }}>
        <div className="modal-header">
          <span>{t('review.decide')} — <span id="review-id">#{ideaCode}</span></span>
          <button className="modal-close" onClick={onClose}>✕</button>
        </div>
        <div className="modal-body">
          <div className="form-group">
            <label>{t('review.decision_label')}</label>
            <select className="form-control" id="review-decision" value={decision} onChange={e => setDecision(e.target.value)}>
              {DECISIONS.map(([val, key]) => (
                <option key={val} value={val}>{t(key)}</option>
              ))}
            </select>
          </div>
          <div className="form-group">
            <label>{t('review.comment_label')}</label>
            <textarea className="form-control" id="review-comment" rows="4" value={comment}
              onChange={e => setComment(e.target.value)}
              placeholder={t('review.comment_ph')} />
          </div>
        </div>
        <div className="modal-footer">
          <button className="btn btn-outline" onClick={onClose}>{t('btn.cancel')}</button>
          <button className="btn btn-primary" disabled={loading} onClick={handleSubmit}>
            {loading ? t('msg.loading') : t('btn.submit_decision')}
          </button>
        </div>
      </div>
    </div>
  );
}
