import { useState, useEffect, useRef } from 'react';
import { Outlet, useNavigate, useLocation } from 'react-router-dom';
import { SECTION_BY_PATH } from './navConfig';

function useActiveSection() {
  const { pathname } = useLocation();
  return SECTION_BY_PATH[pathname] ?? { label: 'SM Portal', icon: null };
}
import { useAuthVerify } from '../hooks/useAuthVerify';
import { useAuthStore } from '../store/authStore';
import { authApi } from '../api/auth';
import Sidebar from './Sidebar';

const ROLE_LABELS = {
  superadmin: 'Super Admin',
  admin_sm: 'SM Admin',
  sb_owner: 'SB Owner',
  sb_employee: 'Employee',
  bb: 'Business Bishop',
  sub_franchise_owner: 'SF Owner',
  sub_franchise_admin: 'SF Admin',
};

// ─── Language selector ────────────────────────────────────────────────────────

function LanguageSelector() {
  const [lang, setLang] = useState('EN');
  const [open, setOpen] = useState(false);
  const ref = useRef(null);

  useEffect(() => {
    if (!open) return;
    const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen((v) => !v)}
        className="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors focus:outline-none"
      >
        <svg className="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253M3 12c0 .778.099 1.533.284 2.253" />
        </svg>
        {lang}
        <svg className={`w-3.5 h-3.5 text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      {open && (
        <div className="absolute right-0 mt-1.5 w-24 bg-white rounded-xl shadow-lg border border-slate-200 py-1 z-50">
          {['EN', 'ES'].map((l) => (
            <button
              key={l}
              onClick={() => { setLang(l); setOpen(false); }}
              className={`w-full px-4 py-2 text-sm text-left transition-colors ${lang === l ? 'text-blue-600 font-semibold bg-blue-50' : 'text-slate-700 hover:bg-slate-50'}`}
            >
              {l === 'EN' ? '🇺🇸 English' : '🇪🇸 Español'}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

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

  const initials = user?.name
    ? user.name.trim().split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('')
    : '?';
  const roleLabel = ROLE_LABELS[useAuthStore.getState().role] ?? useAuthStore.getState().role;

  return (
    <div ref={containerRef} className="relative">
      {/* Trigger button */}
      <button
        onClick={() => setOpen((v) => !v)}
        className="flex items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-slate-100 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
        aria-haspopup="true"
        aria-expanded={open}
      >
        <div className="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shrink-0">
          <span className="text-white text-xs font-bold leading-none">{initials}</span>
        </div>
        <div className="hidden sm:flex flex-col items-start leading-none max-w-[120px]">
          <span className="text-sm font-medium text-slate-700 truncate w-full">{user?.name ?? 'User'}</span>
          <span className="text-xs text-slate-400 mt-0.5 truncate w-full">{roleLabel}</span>
        </div>
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
  const activeSection = useActiveSection();

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
    <div className="min-h-screen bg-slate-50">
      {/* Header — fixed full-width at top, always above sidebar */}
      <header className="fixed top-0 left-0 right-0 h-14 bg-white border-b border-slate-200 z-30 flex items-center px-6">
        {/* Left — logo */}
        <div className="w-64 flex items-center shrink-0 pl-0">
          <img src="/logo.png" alt="Strategic Mates" className="h-8 w-auto object-contain" />
        </div>

        {/* Center — active section icon + name */}
        <div className="flex-1 flex items-center justify-center gap-2 text-slate-700">
          {activeSection.icon && (
            <span className="text-slate-500">{activeSection.icon}</span>
          )}
          <span className="text-sm font-semibold">{activeSection.label}</span>
        </div>

        {/* Right — language selector + user dropdown */}
        <div className="flex items-center gap-2 shrink-0">
          <LanguageSelector />
          <div className="w-px h-5 bg-slate-200" />
          <UserDropdown />
        </div>
      </header>

      {/* Sidebar — fixed, starts below the header */}
      <Sidebar />

      {/* Page content — offset right by sidebar, down by header */}
      <main className="ml-64 pt-14 min-h-screen">
        <div className="p-6">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
