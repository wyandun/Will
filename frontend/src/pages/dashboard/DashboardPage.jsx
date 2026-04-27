import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuthStore } from '../../store/authStore';
import { dashboardApi } from '../../api/dashboard';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getGreeting(t) {
  const hour = new Date().getHours();
  if (hour < 12) return t('dashboard.good_morning');
  if (hour < 18) return t('dashboard.good_afternoon');
  return t('dashboard.good_evening');
}

function getFormattedDate(language) {
  return new Date().toLocaleDateString(language === 'es' ? 'es-ES' : 'en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

// ─── Sub-components ───────────────────────────────────────────────────────────

/**
 * Pill badge shown inside the banner. Navigates to a route on click.
 */
function BannerBadge({ count, label, onClick, loading }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex items-center gap-2 bg-white/10 hover:bg-white/20 transition-colors rounded-full px-4 py-2 text-white cursor-pointer"
    >
      {loading ? (
        <span className="w-6 h-4 rounded bg-white/20 animate-pulse inline-block" />
      ) : (
        <span className="text-lg font-bold leading-none">{count}</span>
      )}
      <span className="text-sm">{label}</span>
    </button>
  );
}

/**
 * Single KPI card. Navigates to a route when clicked.
 */
function KpiCard({ value, title, subtitle, onClick, loading }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex flex-col items-start bg-white border border-slate-200 rounded-xl shadow-sm hover:shadow-md transition-shadow px-6 py-5 cursor-pointer text-left w-full"
    >
      {loading ? (
        <div className="w-12 h-8 rounded bg-slate-200 animate-pulse mb-2" />
      ) : (
        <span className="text-4xl font-bold text-slate-800 leading-none mb-1">
          {value}
        </span>
      )}
      <span className="text-sm font-semibold text-slate-700">{title}</span>
      <span className="text-xs text-slate-400 mt-0.5">{subtitle}</span>
    </button>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function DashboardPage() {
  const { t, i18n } = useTranslation('common');
  const navigate = useNavigate();
  const user = useAuthStore((s) => s.user);

  const [kpis, setKpis] = useState({
    events_next_14_days: 0,
    pending_signature: 0,
    projects_active: 0,
    unreviewed_docs: 0,
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    dashboardApi
      .getKpis()
      .then(setKpis)
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const greeting = getGreeting(t);
  const today = getFormattedDate(i18n.language);
  const userName = user?.name ?? '';

  return (
    <div className="flex flex-col gap-6">
      {/* ── Banner ─────────────────────────────────────────────────────────── */}
      <div className="bg-gradient-to-r from-slate-900 to-slate-700 rounded-2xl px-8 py-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
        {/* Left: greeting + date */}
        <div className="flex flex-col gap-1">
          <h1 className="text-3xl font-bold text-white leading-tight">
            {greeting}{userName ? `, ${userName}` : ''}
          </h1>
          <p className="text-sm text-slate-300 capitalize">{today}</p>
        </div>

        {/* Right: badges */}
        <div className="flex flex-row sm:flex-col gap-3 flex-shrink-0">
          <BannerBadge
            count={kpis.unreviewed_docs}
            label={t('dashboard.unreviewed_docs')}
            onClick={() => navigate('/repository')}
            loading={loading}
          />
          <BannerBadge
            count={kpis.projects_active}
            label={t('dashboard.active_projects')}
            onClick={() => navigate('/tracking')}
            loading={loading}
          />
        </div>
      </div>

      {/* ── KPI Cards ──────────────────────────────────────────────────────── */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <KpiCard
          value={kpis.events_next_14_days}
          title={t('dashboard.kpi_events')}
          subtitle={t('dashboard.kpi_events_sub')}
          onClick={() => navigate('/calendar')}
          loading={loading}
        />
        <KpiCard
          value={kpis.pending_signature}
          title={t('dashboard.kpi_pending_sig')}
          subtitle={t('dashboard.kpi_pending_sig_sub')}
          onClick={() => navigate('/contracts')}
          loading={loading}
        />
        <KpiCard
          value={kpis.projects_active}
          title={t('dashboard.kpi_projects')}
          subtitle={t('dashboard.kpi_projects_sub')}
          onClick={() => navigate('/tracking')}
          loading={loading}
        />
        <KpiCard
          value={kpis.unreviewed_docs}
          title={t('dashboard.kpi_to_review')}
          subtitle={t('dashboard.kpi_to_review_sub')}
          onClick={() => navigate('/repository')}
          loading={loading}
        />
      </div>
    </div>
  );
}
