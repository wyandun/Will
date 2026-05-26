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
import CatalogPage from './pages/catalog/CatalogPage';
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
          <Route path="/feed"        element={<FeedPage />} />
          <Route path="/contracts"   element={<StubPage title={t('nav.contracts')} />} />
          <Route path="/repository"  element={<StubPage title={t('nav.repository')} />} />
          <Route path="/processes"   element={<StubPage title={t('nav.process_maps')} />} />
          <Route path="/accounting"  element={<StubPage title={t('nav.accounting')} />} />
          <Route path="/inventory"   element={<StubPage title={t('nav.inventory')} />} />
          <Route path="/tracking"    element={<StubPage title={t('nav.tracking')} />} />
          <Route
            path="/catalog"
            element={
              <RoleRoute roles={['superadmin']}>
                <CatalogPage />
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
          <Route path="/calendar"    element={<EventsPage />} />
          <Route path="/profile"     element={<ProfilePage />} />
        </Route>

        {/* Catch-all: redirect unknown paths to root */}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  );
}
