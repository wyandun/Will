import PropTypes from 'prop-types';
import { NavLink, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuthStore } from '../store/authStore';
import { authApi } from '../api/auth';
import { NAV_SECTIONS } from './navConfig';


function IconLogout() {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
    </svg>
  );
}

function buildNavItems(role, permissions) {
  const adminRoles = ['superadmin', 'admin_sm'];
  const isAdmin = adminRoles.includes(role);
  const permMap = {};
  if (Array.isArray(permissions)) permissions.forEach((p) => { permMap[p.module] = p; });
  const canRead = (module) => permMap[module]?.can_read === true;

  const SHOW = {
    dashboard:        true,
    franchises:       isAdmin,
    companies:        isAdmin,
    'sb-applications': isAdmin,
    users:            isAdmin,
    system_admins:    role === 'superadmin',
    feed:             canRead('feed'),
    contracts:        canRead('contracts'),
    repository:       canRead('repository'),
    processes:        canRead('processes'),
    accounting:       canRead('accounting'),
    inventory:        canRead('inventory'),
    tracking:         canRead('tracking'),
    catalog:          role === 'superadmin',
    calendar:         canRead('calendar'),
  };

  return NAV_SECTIONS.filter((s) => s.key !== 'profile' && SHOW[s.key]);
}

// ─── Component ───────────────────────────────────────────────────────────────

export default function Sidebar({ open, onClose }) {
  const { t } = useTranslation('common');
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

  const roleLabel = t(`roles.${role}`, { defaultValue: role });

  return (
    <aside className={`fixed top-14 left-0 bottom-0 w-64 bg-slate-800 flex flex-col z-30 transition-transform duration-200 ${open ? 'translate-x-0' : '-translate-x-full'} lg:translate-x-0`}>
      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
        {navItems.map((item) => (
          <NavLink
            key={item.key}
            to={item.path}
            end={item.path === '/'}
            className={linkClass}
            onClick={onClose}
          >
            {item.icon}
            {t(item.labelKey)}
          </NavLink>
        ))}
      </nav>

      {/* Footer: avatar + logout */}
      <div className="border-t border-slate-700 px-4 py-4 flex items-center gap-3">
        {/* Avatar — rounded square, 2 initials, clickeable → profile */}
        <NavLink
          to="/profile"
          title={t('nav.profile')}
          className="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center shrink-0 hover:bg-blue-500 transition-colors overflow-hidden"
        >
          {user?.avatar_url ? (
            <img src={user.avatar_url} alt={user.name} className="w-full h-full object-cover" />
          ) : (
            <span className="text-white text-xs font-bold leading-none uppercase">
              {user?.name
                ? user.name.trim().split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('')
                : '?'}
            </span>
          )}
        </NavLink>

        {/* Name + role */}
        <div className="flex-1 min-w-0">
          <p className="text-white text-sm font-medium truncate leading-tight">
            {user?.name ?? 'User'}
          </p>
          <span className="text-xs text-slate-400 truncate">{roleLabel}</span>
        </div>

        {/* Logout icon button */}
        <button
          onClick={handleLogout}
          title={t('auth.sign_out')}
          className="p-1.5 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors shrink-0"
        >
          <IconLogout />
        </button>
      </div>
    </aside>
  );
}

Sidebar.propTypes = {
  open: PropTypes.bool,
  onClose: PropTypes.func,
};
