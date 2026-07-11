import { createContext, useContext, useState, useCallback, useEffect } from 'react';
import { notifApi } from '../services/api';
import { useAuth } from './AuthContext';

const NotifContext = createContext(null);

export function NotifProvider({ children }) {
  const { user } = useAuth();
  const [notifs, setNotifs]         = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);

  const loadNotifications = useCallback(async () => {
    if (!user) return;
    try {
      const res = await notifApi.list();
      if (res.data.success) {
        setNotifs(res.data.notifications || []);
        setUnreadCount(res.data.unread_count || 0);
      }
    } catch { /* ignore */ }
  }, [user]);

  // Poll every 60 seconds
  useEffect(() => {
    if (!user) return;
    loadNotifications();
    const interval = setInterval(loadNotifications, 60000);
    return () => clearInterval(interval);
  }, [user, loadNotifications]);

  const markAllRead = useCallback(async () => {
    const ids = notifs.filter(n => !n.is_read).map(n => n.id);
    if (!ids.length) return;
    try {
      await notifApi.markRead(ids);
      setNotifs(prev => prev.map(n => ({ ...n, is_read: true })));
      setUnreadCount(0);
    } catch { /* ignore */ }
  }, [notifs]);

  return (
    <NotifContext.Provider value={{ notifs, unreadCount, loadNotifications, markAllRead }}>
      {children}
    </NotifContext.Provider>
  );
}

export function useNotif() {
  return useContext(NotifContext);
}
