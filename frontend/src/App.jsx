import PropTypes from 'prop-types';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import LoginPage from './pages/LoginPage';
import ProtectedRoute from './components/ProtectedRoute';
import AuthenticatedLayout from './components/AuthenticatedLayout';
import FranchisesPage from './pages/franchises/FranchisesPage';
import FranchiseDetailPage from './pages/franchises/FranchiseDetailPage';
import CompaniesPage from './pages/companies/CompaniesPage';
import DashboardPage from './pages/dashboard/DashboardPage';
import FeedPage from './pages/feed/FeedPage';
import ProfilePage from './pages/profile/ProfilePage';
import SystemAdminsPage from './pages/system_admins/SystemAdminsPage';
import InvitationsPage from './pages/users/InvitationsPage';
import AcceptInvitationPage from './pages/invitations/AcceptInvitationPage';
import EventsPage from './pages/calendar/EventsPage';
import { useAuthStore } from './store/authStore';

/**
 * Generic placeholder rendered for modules that are not yet implemented.
 * Replace each one with its real page component as development progresses.
 */
function StubPage({ title }) {
  const { t } = useTranslation('common');
  return (
    <div className="flex flex-col items-center justify-center py-24 text-center">
      <div className="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-5">
        <svg className="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
      </div>
      <h2 className="text-xl font-semibold text-slate-700">{title}</h2>
      <p className="mt-2 text-sm text-slate-400">{t('common.coming_soon')}</p>
    </div>
  );
}

StubPage.propTypes = {
  title: PropTypes.string.isRequired,
};

// ─── Role guard ───────────────────────────────────────────────────────────────

const ADMIN_ROLES = ['superadmin', 'system_admin', 'system_admin_readonly', 'admin_sm'];

/**
 * Renders children only when the current user holds one of the allowed roles.
 * Redirects to "/" otherwise, so unauthenticated direct URL access is blocked
 * even if the sidebar hides the link.
 */
function RoleRoute({ roles, children }) {
  const role = useAuthStore((s) => s.role);
  if (!roles.includes(role)) {
    return <Navigate to="/" replace />;
  }
  return children;
}

RoleRoute.propTypes = {
  roles: PropTypes.arrayOf(PropTypes.string).isRequired,
  children: PropTypes.node.isRequired,
};

// ─── Module permission guard ──────────────────────────────────────────────────

const MODULE_BYPASS_ROLES = ['superadmin', 'system_admin', 'system_admin_readonly'];

/**
 * Renders children only when the user has can_read for the given module.
 * Superadmin and system_admin roles bypass the check (they always have access).
 * Redirects to "/" when the permission is absent or revoked.
 */
function ModuleRoute({ module, children }) {
  const role = useAuthStore((s) => s.role);
  const permissions = useAuthStore((s) => s.permissions);
  if (MODULE_BYPASS_ROLES.includes(role)) return children;
  const perm = Array.isArray(permissions) ? permissions.find((p) => p.module === module) : null;
  return perm?.can_read === true ? children : <Navigate to="/" replace />;
}

ModuleRoute.propTypes = {
  module: PropTypes.string.isRequired,
  children: PropTypes.node.isRequired,
};

// ─── App ─────────────────────────────────────────────────────────────────────

export default function App() {
  const { t } = useTranslation('common');
  return (
    <BrowserRouter>
      <Routes>
        {/* Public routes */}
        <Route path="/login" element={<LoginPage />} />
        <Route path="/invite/:token" element={<AcceptInvitationPage />} />

        {/* Protected routes — all share AuthenticatedLayout */}
        <Route
          element={
            <ProtectedRoute>
              <AuthenticatedLayout />
            </ProtectedRoute>
          }
        >
          <Route index element={<DashboardPage />} />
          <Route
            path="/franchises"
            element={
              <RoleRoute roles={ADMIN_ROLES}>
                <FranchisesPage />
              </RoleRoute>
            }
          />
          <Route
            path="/franchises/:id"
            element={
              <RoleRoute roles={ADMIN_ROLES}>
                <FranchiseDetailPage />
              </RoleRoute>
            }
          />
          <Route
            path="/companies"
            element={
              <RoleRoute roles={ADMIN_ROLES}>
                <CompaniesPage />
              </RoleRoute>
            }
          />
          <Route
            path="/users"
            element={
              <RoleRoute roles={ADMIN_ROLES}>
                <InvitationsPage />
              </RoleRoute>
            }
          />
          <Route
            path="/system-admins"
            element={
              <RoleRoute roles={['superadmin']}>
                <SystemAdminsPage />
              </RoleRoute>
            }
          />
          <Route path="/feed"       element={<ModuleRoute module="feed"><FeedPage /></ModuleRoute>} />
          <Route path="/contracts"  element={<ModuleRoute module="contracts"><StubPage title={t('nav.contracts')} /></ModuleRoute>} />
          <Route path="/repository" element={<ModuleRoute module="repository"><StubPage title={t('nav.repository')} /></ModuleRoute>} />
          <Route path="/processes"  element={<ModuleRoute module="processes"><StubPage title={t('nav.process_maps')} /></ModuleRoute>} />
          <Route path="/accounting" element={<ModuleRoute module="accounting"><StubPage title={t('nav.accounting')} /></ModuleRoute>} />
          <Route path="/inventory"  element={<ModuleRoute module="inventory"><StubPage title={t('nav.inventory')} /></ModuleRoute>} />
          <Route path="/tracking"   element={<ModuleRoute module="tracking"><StubPage title={t('nav.tracking')} /></ModuleRoute>} />
          <Route
            path="/catalog"
            element={
              <RoleRoute roles={ADMIN_ROLES}>
                <ModuleRoute module="catalog">
                  <StubPage title={t('nav.service_catalog')} />
                </ModuleRoute>
              </RoleRoute>
            }
          />
          <Route
            path="/sb-applications"
            element={
              <RoleRoute roles={ADMIN_ROLES}>
                <StubPage title={t('nav.sb_applications')} />
              </RoleRoute>
            }
          />
          <Route path="/calendar" element={<ModuleRoute module="calendar"><EventsPage /></ModuleRoute>} />
          <Route path="/profile"     element={<ProfilePage />} />
        </Route>

        {/* Catch-all: redirect unknown paths to root */}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  );
}
