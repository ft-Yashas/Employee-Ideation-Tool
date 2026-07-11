import { useState } from 'react';
import Sidebar from './Sidebar';
import Topbar from './Topbar';

export default function AppShell({ children }) {
  const [collapsed, setCollapsed] = useState(false);

  return (
    <div id="app">
      <Sidebar collapsed={collapsed} onToggle={() => setCollapsed(v => !v)} />
      <div id="main">
        <Topbar onToggleSidebar={() => setCollapsed(v => !v)} />
        <div id="content">
          {children}
        </div>
      </div>
    </div>
  );
}
