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

  async function handleSubmit() {
    const labels = {
      'Approved':       'Approve',
      'Rejected':       'Reject',
      'Implemented':    'Mark as Implemented',
      'Under Review':   'Move to Under Review',
    };
    if (!confirm(`Confirm: ${labels[decision]||decision} this idea?\n\nThis action will be recorded in the audit trail and the submitter will be notified.`)) return;

    setLoading(true);
    try {
      const res = await ideasApi.reviewAction({ idea_id: ideaId, decision, comment });
      if (res.data.success) {
        const d = res.data;
        const isEsc = d.decision === 'Escalated';
        showToast(
          isEsc ? `Escalated to ${d.escalated_to}` : `${t('msg.decision_ok')}: ${decision}${d.points_awarded ? ` · +${d.points_awarded} pts` : ''}`,
          'success'
        );
        onClose();
      } else {
        showToast('Error: ' + (res.data.error || 'Unknown error'), 'danger');
      }
    } catch { showToast('Server error. Please try again.', 'danger'); }
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
              <option value="Approved">Approve</option>
              <option value="Rejected">Reject</option>
              <option value="Implemented">Mark as Implemented</option>
              <option value="Under Review">Move to Under Review</option>
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
          <button className="btn btn-outline" onClick={onClose}>Cancel</button>
          <button className="btn btn-primary" disabled={loading} onClick={handleSubmit}>
            {loading ? t('msg.loading') : t('btn.submit_decision')}
          </button>
        </div>
      </div>
    </div>
  );
}
