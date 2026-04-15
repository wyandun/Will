import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import LoginPage from './pages/LoginPage';
import ProtectedRoute from './components/ProtectedRoute';
import AuthenticatedLayout from './components/AuthenticatedLayout';
import FranchisesPage from './pages/franchises/FranchisesPage';
import CompaniesPage from './pages/companies/CompaniesPage';

// ─── Pages ───────────────────────────────────────────────────────────────────

function DashboardPage() {
  return (
    <div>
      <h1 className="text-2xl font-semibold text-slate-800 mb-1">Dashboard</h1>
      <p className="text-sm text-slate-500">Welcome to the Strategic Mates portal.</p>
    </div>
  );
}

/**
 * Generic placeholder rendered for modules that are not yet implemented.
 * Replace each one with its real page component as development progresses.
 */
function StubPage({ title }) {
  return (
    <div className="flex flex-col items-center justify-center py-24 text-center">
      <div className="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-5">
        <svg className="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
      </div>
      <h2 className="text-xl font-semibold text-slate-700">{title}</h2>
      <p className="mt-2 text-sm text-slate-400">This module is coming soon.</p>
    </div>
  );
}

// ─── App ─────────────────────────────────────────────────────────────────────

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        {/* Public routes */}
        <Route path="/login" element={<LoginPage />} />

        {/* Protected routes — all share AuthenticatedLayout */}
        <Route
          element={
            <ProtectedRoute>
              <AuthenticatedLayout />
            </ProtectedRoute>
          }
        >
          <Route index element={<DashboardPage />} />
          <Route path="/franchises"  element={<FranchisesPage />} />
          <Route path="/companies"   element={<CompaniesPage />} />
          <Route path="/users"       element={<StubPage title="Users & Permissions" />} />
          <Route path="/feed"        element={<StubPage title="Feed" />} />
          <Route path="/contracts"   element={<StubPage title="Contracts" />} />
          <Route path="/repository"  element={<StubPage title="Document Repository" />} />
          <Route path="/processes"   element={<StubPage title="Process Maps" />} />
          <Route path="/accounting"  element={<StubPage title="Accounting & Finance" />} />
          <Route path="/inventory"   element={<StubPage title="Inventory" />} />
          <Route path="/tracking"    element={<StubPage title="Tracking" />} />
          <Route path="/calendar"    element={<StubPage title="Calendar" />} />
          <Route path="/profile"     element={<StubPage title="Profile" />} />
        </Route>

        {/* Catch-all: redirect unknown paths to root */}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  );
}
