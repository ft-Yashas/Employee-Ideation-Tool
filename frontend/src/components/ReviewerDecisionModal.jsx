import { useState } from 'react';
import { useLang } from '../context/LangContext';
import { useToast } from '../context/ToastContext';
import { ideasApi } from '../services/api';

export default function ReviewerDecisionModal({ ideaId, ideaCode, onClose }) {
  const { t }         = useLang();
  const { showToast } = useToast();
  const [decision, setDecision] = useState('approved');
  const [comment,  setComment]  = useState('');
  const [loading,  setLoading]  = useState(false);

  async function handleSubmit() {
    if (!confirm(`Submit your decision: ${decision} for idea #${ideaCode}?`)) return;
    setLoading(true);
    try {
      const res = await ideasApi.reviewerDecision({ idea_id: ideaId, decision, comment });
      if (res.data.success) {
        showToast(`Your decision (${decision}) recorded.${res.data.final_decision ? ` Idea ${res.data.final_decision} — committee complete.` : ''}`, 'success');
        onClose();
      } else showToast(res.data.error || 'Failed to record decision.', 'danger');
    } catch { showToast('Server error.', 'danger'); }
    setLoading(false);
  }

  return (
    <div className="modal-overlay open" onClick={e => e.target===e.currentTarget && onClose()}>
      <div className="modal" style={{ maxWidth:440 }}>
        <div className="modal-header">
          <span>{t('review.my_review')} — #{ideaCode}</span>
          <button className="modal-close" onClick={onClose}>✕</button>
        </div>
        <div className="modal-body">
          <div style={{ fontSize:13,color:'var(--subtle)',marginBottom:14 }}>
            You have been assigned as a reviewer for this idea. Submit your individual decision:
          </div>
          <div className="form-group">
            <label>Your Decision</label>
            <select className="form-control" value={decision} onChange={e => setDecision(e.target.value)}>
              <option value="approved">Approve</option>
              <option value="rejected">Reject</option>
            </select>
          </div>
          <div className="form-group">
            <label>Comments (optional)</label>
            <textarea className="form-control" rows="4" value={comment}
              onChange={e => setComment(e.target.value)}
              placeholder="Provide your reasoning or feedback…" />
          </div>
        </div>
        <div className="modal-footer">
          <button className="btn btn-outline" onClick={onClose}>Cancel</button>
          <button className="btn btn-primary" disabled={loading} onClick={handleSubmit}>
            {loading ? t('msg.loading') : t('review.submit_mine')}
          </button>
        </div>
      </div>
    </div>
  );
}
