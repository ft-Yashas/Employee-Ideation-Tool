import { BrowserRouter, Routes, Route, Navigate, useLocation } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import { ToastProvider } from './context/ToastContext';
import { LangProvider } from './context/LangContext';
import { NotifProvider } from './context/NotifContext';
import LoginPage from './pages/LoginPage';
import AppShell from './components/Layout/AppShell';
import DashboardPage from './pages/DashboardPage';
import MyIdeasPage from './pages/MyIdeasPage';
import SubmitPage from './pages/SubmitPage';
import ReviewQueuePage from './pages/ReviewQueuePage';
import AllIdeasPage from './pages/AllIdeasPage';
import BoardPage from './pages/BoardPage';
import ChallengesPage from './pages/ChallengesPage';
import AuditPage from './pages/AuditPage';
import LeaderboardPage from './pages/LeaderboardPage';
import AnalyticsPage from './pages/AnalyticsPage';
import AdminPage from './pages/AdminPage';
import SuperAdminPage from './pages/SuperAdminPage';
import ProfilePage from './pages/ProfilePage';
import PlatformDashPage from './pages/PlatformDashPage';
import PlatformTenantsPage from './pages/PlatformTenantsPage';

function PrivateRoute({ children }) {
  const { user, loading } = useAuth();
  const location = useLocation();
  if (loading) return (
    <div style={{ display:'flex',alignItems:'center',justifyContent:'center',height:'100vh',background:'var(--bg)' }}>
      <div className="spinner" style={{ width:36,height:36,borderWidth:3 }}></div>
    </div>
  );
  if (!user) return <Navigate to="/" replace />;
  // Platform admin can only access /platform routes
  if (user.role === 'platform_admin' && !location.pathname.startsWith('/platform')) {
    return <Navigate to="/platform" replace />;
  }
  // Super admin home is /super-admin, not /dashboard
  if (user.role === 'super_admin' && location.pathname === '/dashboard') {
    return <Navigate to="/super-admin" replace />;
  }
  return children;
}

function PublicRoute({ children }) {
  const { user, loading } = useAuth();
  if (loading) return (
    <div style={{ display:'flex',alignItems:'center',justifyContent:'center',height:'100vh',background:'var(--bg)' }}>
      <div className="spinner" style={{ width:36,height:36,borderWidth:3 }}></div>
    </div>
  );
  if (user) {
    const role = user.role;
    if (role === 'platform_admin') return <Navigate to="/platform" replace />;
    if (role === 'super_admin')    return <Navigate to="/super-admin" replace />;
    return <Navigate to="/dashboard" replace />;
  }
  return children;
}

function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<PublicRoute><LoginPage /></PublicRoute>} />
      <Route path="/app" element={<PrivateRoute><AppShell /></PrivateRoute>}>
        <Route index element={<Navigate to="/dashboard" replace />} />
      </Route>
      <Route path="/dashboard"       element={<PrivateRoute><AppShell><DashboardPage /></AppShell></PrivateRoute>} />
      <Route path="/my-ideas"        element={<PrivateRoute><AppShell><MyIdeasPage /></AppShell></PrivateRoute>} />
      <Route path="/submit"          element={<PrivateRoute><AppShell><SubmitPage /></AppShell></PrivateRoute>} />
      <Route path="/review"          element={<PrivateRoute><AppShell><ReviewQueuePage /></AppShell></PrivateRoute>} />
      <Route path="/all-ideas"       element={<PrivateRoute><AppShell><AllIdeasPage /></AppShell></PrivateRoute>} />
      <Route path="/board"           element={<PrivateRoute><AppShell><BoardPage /></AppShell></PrivateRoute>} />
      <Route path="/challenges"      element={<PrivateRoute><AppShell><ChallengesPage /></AppShell></PrivateRoute>} />
      <Route path="/audit"           element={<PrivateRoute><AppShell><AuditPage /></AppShell></PrivateRoute>} />
      <Route path="/leaderboard"     element={<PrivateRoute><AppShell><LeaderboardPage /></AppShell></PrivateRoute>} />
      <Route path="/analytics"       element={<PrivateRoute><AppShell><AnalyticsPage /></AppShell></PrivateRoute>} />
      <Route path="/admin"           element={<PrivateRoute><AppShell><AdminPage /></AppShell></PrivateRoute>} />
      <Route path="/super-admin"     element={<PrivateRoute><AppShell><SuperAdminPage /></AppShell></PrivateRoute>} />
      <Route path="/profile"         element={<PrivateRoute><AppShell><ProfilePage /></AppShell></PrivateRoute>} />
      <Route path="/platform"        element={<PrivateRoute><AppShell><PlatformDashPage /></AppShell></PrivateRoute>} />
      <Route path="/platform/tenants/:id" element={<PrivateRoute><AppShell><PlatformTenantsPage /></AppShell></PrivateRoute>} />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <LangProvider>
          <ToastProvider>
            <NotifProvider>
              <AppRoutes />
            </NotifProvider>
          </ToastProvider>
        </LangProvider>
      </AuthProvider>
    </BrowserRouter>
  );
}
