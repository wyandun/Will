import { NavLink, useNavigate } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';
import { authApi } from '../api/auth';

// ─── Icons (inline SVG, no external icon library required) ──────────────────

function IconDashboard() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M3 9.75L12 3l9 6.75V21H15v-6H9v6H3V9.75z" />
    </svg>
  );
}

function IconFranchises() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
    </svg>
  );
}

function IconCompanies() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" />
    </svg>
  );
}

function IconUsers() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
    </svg>
  );
}

function IconFeed() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-1.5 3h1.5m-3-6H7.5m3 3H7.5m3 3H7.5M6 19.5H18a1.5 1.5 0 001.5-1.5V6A1.5 1.5 0 0018 4.5H6A1.5 1.5 0 004.5 6v12A1.5 1.5 0 006 19.5z" />
    </svg>
  );
}

function IconContracts() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
    </svg>
  );
}

function IconRepository() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776" />
    </svg>
  );
}

function IconProcesses() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605" />
    </svg>
  );
}

function IconAccounting() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
    </svg>
  );
}

function IconInventory() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
    </svg>
  );
}

function IconTracking() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
    </svg>
  );
}

function IconCalendar() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
    </svg>
  );
}

function IconCatalog() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
      <path strokeLinecap="round" strokeLinejoin="round" d="M6 6h.008v.008H6V6z" />
    </svg>
  );
}

function IconSBApp() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
    </svg>
  );
}

function IconProfile() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
    </svg>
  );
}

function IconLogout() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
    </svg>
  );
}

// ─── Role badge colours ──────────────────────────────────────────────────────

const ROLE_LABELS = {
  superadmin: 'Superadmin',
  admin_sm: 'SM Admin',
  sb_owner: 'SB Owner',
  sb_employee: 'Employee',
  bb: 'Business Bishop',
  sub_franchise_owner: 'SF Owner',
  sub_franchise_admin: 'SF Admin',
};

// ─── Navigation definition ───────────────────────────────────────────────────

/**
 * Returns the list of nav items the current user should see.
 * role: string, permissions: array of { module, can_read, ... }
 */
function buildNavItems(role, permissions) {
  const adminRoles = ['superadmin', 'admin_sm'];
  const isAdmin = adminRoles.includes(role);

  // Build a quick lookup: module name → permission object
  const permMap = {};
  if (Array.isArray(permissions)) {
    permissions.forEach((p) => {
      permMap[p.module] = p;
    });
  }

  const canRead = (module) => permMap[module]?.can_read === true;

  const items = [
    { key: 'dashboard',   label: 'Dashboard',   path: '/',             icon: <IconDashboard />,  show: true },
    { key: 'franchises',  label: 'Franchises',  path: '/franchises',   icon: <IconFranchises />, show: isAdmin },
    { key: 'companies',      label: 'Companies',      path: '/companies',       icon: <IconCompanies />,  show: isAdmin },
    { key: 'sb-applications', label: 'SB Applications', path: '/sb-applications', icon: <IconSBApp />,      show: isAdmin },
    { key: 'users',          label: 'Users',          path: '/users',           icon: <IconUsers />,      show: isAdmin },
    { key: 'feed',        label: 'Feed',        path: '/feed',         icon: <IconFeed />,       show: canRead('feed') },
    { key: 'contracts',   label: 'Contracts',   path: '/contracts',    icon: <IconContracts />,  show: canRead('contracts') },
    { key: 'repository',  label: 'Repository',  path: '/repository',   icon: <IconRepository />, show: canRead('repository') },
    { key: 'processes',   label: 'Processes',   path: '/processes',    icon: <IconProcesses />,  show: canRead('processes') },
    { key: 'accounting',  label: 'Accounting',  path: '/accounting',   icon: <IconAccounting />, show: canRead('accounting') },
    { key: 'inventory',   label: 'Inventory',   path: '/inventory',    icon: <IconInventory />,  show: canRead('inventory') },
    { key: 'tracking',    label: 'Tracking',    path: '/tracking',     icon: <IconTracking />,   show: canRead('tracking') },
    { key: 'catalog',     label: 'Catalog',     path: '/catalog',      icon: <IconCatalog />,    show: role === 'superadmin' },
    { key: 'calendar',    label: 'Calendar',    path: '/calendar',     icon: <IconCalendar />,   show: canRead('calendar') },
  ];

  return items.filter((item) => item.show);
}

// ─── Component ───────────────────────────────────────────────────────────────

export default function Sidebar() {
  const navigate = useNavigate();
  const user = useAuthStore((s) => s.user);
  const role = useAuthStore((s) => s.role);
  const permissions = useAuthStore((s) => s.permissions);
  const clearAuth = useAuthStore((s) => s.clearAuth);

  const navItems = buildNavItems(role, permissions);

  const handleLogout = async () => {
    try {
      await authApi.logout();
    } catch {
      // Server error on logout is non-critical — clear local state regardless.
    } finally {
      clearAuth();
      navigate('/login', { replace: true });
    }
  };

  // NavLink active class helper — Dashboard uses exact match, others use
  // prefix match (React Router default for non-index routes).
  const linkClass = ({ isActive }) =>
    [
      'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
      isActive
        ? 'bg-slate-700 text-white'
        : 'text-slate-400 hover:bg-slate-700/60 hover:text-white',
    ].join(' ');

  return (
    <aside className="fixed top-14 left-0 bottom-0 w-64 bg-slate-800 flex flex-col z-20">
      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
        {navItems.map((item) => (
          <NavLink
            key={item.key}
            to={item.path}
            end={item.path === '/'}
            className={linkClass}
          >
            {item.icon}
            {item.label}
          </NavLink>
        ))}
      </nav>

      {/* Footer: avatar + logout */}
      <div className="border-t border-slate-700 px-4 py-4 flex items-center gap-3">
        {/* Avatar — rounded square, 2 initials, clickeable → profile */}
        <NavLink
          to="/profile"
          title="Account settings"
          className="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center shrink-0 hover:bg-blue-500 transition-colors"
        >
          <span className="text-white text-xs font-bold leading-none uppercase">
            {user?.name
              ? user.name.trim().split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('')
              : '?'}
          </span>
        </NavLink>

        {/* Name + role */}
        <div className="flex-1 min-w-0">
          <p className="text-white text-sm font-medium truncate leading-tight">
            {user?.name ?? 'User'}
          </p>
          <span className="text-xs text-slate-400 truncate">{ROLE_LABELS[role] ?? role}</span>
        </div>

        {/* Logout icon button */}
        <button
          onClick={handleLogout}
          title="Sign out"
          className="p-1.5 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors shrink-0"
        >
          <IconLogout />
        </button>
      </div>
    </aside>
  );
}
