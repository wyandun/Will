import { useState, useEffect, useRef } from 'react';
import { Outlet, useNavigate } from 'react-router-dom';
import { useAuthVerify } from '../hooks/useAuthVerify';
import { useAuthStore } from '../store/authStore';
import { authApi } from '../api/auth';
import Sidebar from './Sidebar';

// ─── User dropdown ────────────────────────────────────────────────────────────

function UserDropdown() {
  const navigate = useNavigate();
  const user = useAuthStore((s) => s.user);
  const clearAuth = useAuthStore((s) => s.clearAuth);

  const [open, setOpen] = useState(false);
  const containerRef = useRef(null);

  // Close when clicking outside the dropdown container
  useEffect(() => {
    if (!open) return;

    const handleOutsideClick = (e) => {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setOpen(false);
      }
    };

    document.addEventListener('mousedown', handleOutsideClick);
    return () => document.removeEventListener('mousedown', handleOutsideClick);
  }, [open]);

  const handleLogout = async () => {
    setOpen(false);
    try {
      await authApi.logout();
    } catch {
      // Non-critical — clear local state regardless.
    } finally {
      clearAuth();
      navigate('/login', { replace: true });
    }
  };

  const handleViewProfile = () => {
    setOpen(false);
    navigate('/profile');
  };

  return (
    <div ref={containerRef} className="relative">
      {/* Trigger button */}
      <button
        onClick={() => setOpen((v) => !v)}
        className="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
        aria-haspopup="true"
        aria-expanded={open}
      >
        <div className="w-7 h-7 rounded-full bg-slate-700 flex items-center justify-center shrink-0">
          <span className="text-white text-xs font-semibold uppercase leading-none">
            {user?.name?.charAt(0) ?? '?'}
          </span>
        </div>
        <span className="text-sm font-medium text-slate-700 hidden sm:block">
          {user?.name ?? 'User'}
        </span>
        {/* Chevron */}
        <svg
          className={`w-4 h-4 text-slate-400 transition-transform hidden sm:block ${open ? 'rotate-180' : ''}`}
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          viewBox="0 0 24 24"
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      {/* Dropdown panel */}
      {open && (
        <div className="absolute right-0 mt-1.5 w-48 bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-50">
          <button
            onClick={handleViewProfile}
            className="flex items-center gap-2.5 w-full px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
          >
            <svg className="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            </svg>
            View Profile
          </button>

          <div className="my-1 border-t border-slate-100" />

          <button
            onClick={handleLogout}
            className="flex items-center gap-2.5 w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors"
          >
            <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
            </svg>
            Sign out
          </button>
        </div>
      )}
    </div>
  );
}

// ─── Layout ───────────────────────────────────────────────────────────────────

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
        <header className="h-14 bg-white border-b border-slate-200 flex items-center justify-between px-6 shrink-0">
          <p className="text-sm text-slate-400 font-medium tracking-wide uppercase select-none">
            SM Portal
          </p>
          <UserDropdown />
        </header>

        {/* Page content */}
        <main className="flex-1 p-6 overflow-auto">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
