import { Outlet } from 'react-router-dom';
import { useAuthVerify } from '../hooks/useAuthVerify';
import Sidebar from './Sidebar';

/**
 * Shell for every authenticated page.
 * Verifies the stored token on mount, shows a spinner while checking,
 * then renders the fixed sidebar + scrollable content area.
 */
export default function AuthenticatedLayout() {
  const { loading } = useAuthVerify();

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <div className="flex flex-col items-center gap-3">
          {/* Spinner */}
          <svg
            className="w-8 h-8 text-blue-500 animate-spin"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle
              className="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              strokeWidth="4"
            />
            <path
              className="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
            />
          </svg>
          <p className="text-sm text-slate-500">Loading…</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50 flex">
      <Sidebar />

      {/* Main area — offset by sidebar width */}
      <div className="flex-1 flex flex-col ml-64 min-h-screen">
        {/* Top header bar */}
        <header className="h-14 bg-white border-b border-slate-200 flex items-center px-6 shrink-0">
          <p className="text-sm text-slate-400 font-medium tracking-wide uppercase select-none">
            SM Portal
          </p>
        </header>

        {/* Page content */}
        <main className="flex-1 p-6 overflow-auto">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
