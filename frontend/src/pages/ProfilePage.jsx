import { useAuth } from '../context/AuthContext';
import { useLang } from '../context/LangContext';
import { formatRole } from '../utils/helpers';

export default function ProfilePage() {
  const { user } = useAuth();
  const { t }    = useLang();

  if (!user) return null;

  return (
    <div style={{ maxWidth:600 }}>
      <div className="card" style={{ textAlign:'center',padding:32 }}>
        <div id="profile-avatar" className="avatar" style={{ width:64,height:64,fontSize:24,margin:'0 auto 12px',background:'linear-gradient(135deg,var(--primary),#6366f1)' }}>
          {user.avatar_initials || user.name?.[0] || '?'}
        </div>
        <div id="profile-name" style={{ fontSize:20,fontWeight:700,color:'var(--heading)' }}>{user.name}</div>
        <div style={{ fontSize:13,color:'var(--subtle)',marginTop:2 }} id="profile-empid">{user.employee_id}</div>
        <span id="profile-role-badge" className="badge" style={{ marginTop:8,display:'inline-block',background:'var(--chip-bg)',color:'var(--text)',border:'1px solid var(--border)' }}>
          {formatRole(user.role)}
        </span>

        <div id="profile-stats" style={{ display:'flex',justifyContent:'center',gap:32,marginTop:20 }}>
          <div className="mini-stat">
            <div className="mini-stat-val">{user.points || 0}</div>
            <div className="mini-stat-label">{t('profile.total_pts')}</div>
          </div>
        </div>
      </div>

      <div className="card" style={{ marginTop:16 }}>
        <div style={{ fontWeight:700,fontSize:13,marginBottom:14,color:'var(--heading)' }}>Profile Details</div>
        <table className="table" id="profile-table">
          <tbody>
            <tr><td style={{ color:'var(--subtle)',padding:'5px 0' }}>{t('profile.dept')}</td><td>{user.department||'–'}</td></tr>
            <tr><td style={{ color:'var(--subtle)',padding:'5px 0' }}>{t('profile.email_lbl')}</td><td>{user.email}</td></tr>
            <tr><td style={{ color:'var(--subtle)',padding:'5px 0' }}>{t('profile.phone')}</td><td>{user.phone||'–'}</td></tr>
            <tr><td style={{ color:'var(--subtle)',padding:'5px 0' }}>{t('profile.reports_to')}</td><td>{user.manager_name||'–'}</td></tr>
            <tr><td style={{ color:'var(--subtle)',padding:'5px 0' }}>{t('profile.bu')}</td><td>{user.business_unit||'–'}</td></tr>
            <tr><td style={{ color:'var(--subtle)',padding:'5px 0' }}>{t('profile.loc')}</td><td>{user.location||'–'}</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  );
}
