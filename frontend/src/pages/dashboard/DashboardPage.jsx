import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuthStore } from '../../store/authStore';
import { dashboardApi } from '../../api/dashboard';
import { timeAgo } from '../../utils/time';
import UpcomingEventsSidebar from '../../components/UpcomingEventsSidebar';

// ─── Icons ────────────────────────────────────────────────────────────────────

function IconCalendar({ className = 'w-5 h-5' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
    </svg>
  );
}

function IconPen({ className = 'w-5 h-5' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M15.232 5.232l3.536 3.536M9 11l6.586-6.586a2 2 0 112.828 2.828L11.828 13.828A2 2 0 0110 14.414l-2.828.414.414-2.828A2 2 0 019 11z" />
    </svg>
  );
}

function IconBriefcase({ className = 'w-5 h-5' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    </svg>
  );
}

function IconEye({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
      <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
    </svg>
  );
}

function IconCheck({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
    </svg>
  );
}

function IconFeed({ className = 'w-5 h-5' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
    </svg>
  );
}

function IconContract({ className = 'w-5 h-5' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
    </svg>
  );
}

function IconTracking({ className = 'w-5 h-5' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
    </svg>
  );
}

function IconDocument({ className = 'w-5 h-5' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
    </svg>
  );
}

function IconMap({ className = 'w-5 h-5' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
    </svg>
  );
}

function IconHeart({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="currentColor" viewBox="0 0 24 24">
      <path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z" />
    </svg>
  );
}

function IconComment({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    </svg>
  );
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getGreeting(t) {
  const hour = new Date().getHours();
  if (hour < 12) return t('dashboard.good_morning');
  if (hour < 18) return t('dashboard.good_afternoon');
  return t('dashboard.good_evening');
}

function formatDate() {
  const now = new Date();
  return now.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
}

function fileIcon(name = '') {
  const ext = name.split('.').pop()?.toLowerCase();
  if (ext === 'pdf') return '📄';
  if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return '🖼';
  if (['xls', 'xlsx'].includes(ext)) return '📊';
  return '📁';
}

// ─── WidgetHeader ─────────────────────────────────────────────────────────────

function WidgetHeader({ icon, title, count, onViewAll, light = false }) {
  const { t } = useTranslation('common');
  return (
    <div className="flex items-center justify-between mb-4">
      <div className="flex items-center gap-2">
        {icon}
        <span className={`font-semibold ${light ? 'text-white' : 'text-slate-700'}`}>{title}</span>
        {count != null && (
          <span className="text-xs bg-amber-100 text-amber-700 rounded-full px-2 py-0.5 font-medium">
            {count}
          </span>
        )}
      </div>
      <button
        onClick={onViewAll}
        className={`text-sm flex items-center gap-1 transition-colors ${
          light
            ? 'text-white/50 hover:text-white'
            : 'text-slate-400 hover:text-slate-600'
        }`}
      >
        {t('dashboard.view_all')} <span>›</span>
      </button>
    </div>
  );
}

// ─── EmptyState ───────────────────────────────────────────────────────────────

function EmptyState({ message, light = false }) {
  return (
    <div className={`flex items-center justify-center py-8 text-sm ${light ? 'text-slate-500' : 'text-slate-400'}`}>
      {message}
    </div>
  );
}

// ─── Skeleton ─────────────────────────────────────────────────────────────────

function Skeleton({ className }) {
  return <div className={`animate-pulse bg-slate-200 rounded-xl ${className}`} />;
}

// ─── Banner ───────────────────────────────────────────────────────────────────

function Banner({ kpis, loading }) {
  const { t } = useTranslation('common');
  const user = useAuthStore((s) => s.user);
  const navigate = useNavigate();

  if (loading) {
    return <Skeleton className="h-full min-h-[200px]" />;
  }

  return (
    <div className="bg-gradient-to-br from-slate-900 to-slate-700 rounded-2xl px-8 py-8 h-full flex flex-col justify-between">
      <div>
        <p className="text-amber-400 text-xs font-semibold tracking-widest uppercase mb-1">
          {getGreeting(t)}
        </p>
        <p className="text-white text-3xl font-light leading-snug">
          Hi,{' '}
          <span className="text-amber-400 font-bold">
            {user?.name ?? 'there'}.
          </span>
        </p>
        <p className="text-slate-400 text-sm mt-1">{formatDate()}</p>
      </div>

      <div className="flex flex-wrap gap-2 mt-6">
        {kpis.to_review > 0 && (
          <button
            onClick={() => navigate('/repository')}
            className="bg-white/10 hover:bg-white/20 transition-colors rounded-full px-3 py-1.5 text-white text-sm flex items-center gap-1.5"
          >
            <IconDocument className="w-3.5 h-3.5 text-amber-400" />
            {kpis.to_review} {t('dashboard.badge_docs')}
          </button>
        )}
        {kpis.projects_active > 0 && (
          <button
            onClick={() => navigate('/tracking')}
            className="bg-white/10 hover:bg-white/20 transition-colors rounded-full px-3 py-1.5 text-white text-sm flex items-center gap-1.5"
          >
            <IconBriefcase className="w-3.5 h-3.5 text-amber-400" />
            {kpis.projects_active} {t('dashboard.badge_projects')}
          </button>
        )}
      </div>
    </div>
  );
}

// ─── KPI Cards ────────────────────────────────────────────────────────────────

function KpiCard({ icon, value, label, sub, onClick, loading }) {
  if (loading) return <Skeleton className="h-full min-h-[90px]" />;
  return (
    <button
      onClick={onClick}
      className="w-full text-left bg-white border border-slate-100 rounded-xl p-5 hover:shadow-md transition-shadow flex flex-col gap-1 relative cursor-pointer"
    >
      <span className="text-amber-500">{icon}</span>
      <span className="text-3xl font-bold text-slate-800 leading-none mt-1">{value}</span>
      <span className="text-sm font-medium text-slate-600">{label}</span>
      <span className="text-xs text-slate-400">{sub}</span>
      <span className="absolute top-4 right-4 text-slate-300 text-lg">›</span>
    </button>
  );
}

function KpiGrid({ kpis, loading }) {
  const { t } = useTranslation('common');
  const navigate = useNavigate();

  return (
    <div className="grid grid-cols-2 gap-3 h-full">
      <KpiCard
        icon={<IconCalendar />}
        value={kpis.events_next_14_days}
        label={t('dashboard.kpi_events')}
        sub={t('dashboard.kpi_events_sub')}
        onClick={() => navigate('/calendar')}
        loading={loading}
      />
      <KpiCard
        icon={<IconPen />}
        value={kpis.pending_signature}
        label={t('dashboard.kpi_pending')}
        sub={t('dashboard.kpi_pending_sub')}
        onClick={() => navigate('/contracts')}
        loading={loading}
      />
      <KpiCard
        icon={<IconBriefcase />}
        value={kpis.projects_active}
        label={t('dashboard.kpi_projects')}
        sub={t('dashboard.kpi_projects_sub')}
        onClick={() => navigate('/tracking')}
        loading={loading}
      />
      <KpiCard
        icon={<IconDocument />}
        value={kpis.to_review}
        label={t('dashboard.kpi_review')}
        sub={t('dashboard.kpi_review_sub')}
        onClick={() => navigate('/repository')}
        loading={loading}
      />
    </div>
  );
}

// ─── Latest Posts ─────────────────────────────────────────────────────────────

function PostCard({ post }) {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const ago = timeAgo(post.created_at);
  const initial = (post.author_name ?? '?')[0].toUpperCase();

  return (
    <div className="flex flex-col gap-2 p-4 rounded-xl border border-slate-100 hover:border-slate-200 transition-colors">
      <div className="flex items-center gap-2">
        {post.author_avatar ? (
          <img src={post.author_avatar} alt={post.author_name} className="w-7 h-7 rounded-full object-cover" />
        ) : (
          <div className="w-7 h-7 rounded-full bg-amber-100 text-amber-700 text-xs font-bold flex items-center justify-center flex-shrink-0">
            {initial}
          </div>
        )}
        <span className="text-sm font-medium text-slate-700 truncate">{post.author_name}</span>
        <span className="text-xs text-slate-400 ml-auto flex-shrink-0">{ago}</span>
      </div>
      <p className="text-sm font-semibold text-slate-800 line-clamp-1">{post.title}</p>
      <p className="text-xs text-slate-500 line-clamp-2">{post.content}</p>
      <div className="flex items-center justify-between mt-1">
        <div className="flex items-center gap-3 text-xs text-slate-400">
          <span className="flex items-center gap-1">
            <IconHeart className="text-rose-400" />
            {post.likes_count ?? 0}
          </span>
          <span className="flex items-center gap-1">
            <IconComment />
            {post.comments_count ?? 0}
          </span>
        </div>
        <button className="text-xs text-amber-600 hover:text-amber-700 font-medium" onClick={() => navigate('/feed')}>
          {t('dashboard.read_more')} ›
        </button>
      </div>
    </div>
  );
}

function LatestPostsWidget({ feed }) {
  const { t } = useTranslation('common');
  const navigate = useNavigate();

  return (
    <div className="bg-white rounded-2xl p-6 border border-slate-100">
      <WidgetHeader
        icon={<IconFeed className="w-4 h-4 text-amber-500" />}
        title={t('dashboard.latest_posts')}
        onViewAll={() => navigate('/feed')}
      />
      {feed.length === 0 ? (
        <EmptyState message={t('dashboard.no_posts')} />
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {feed.slice(0, 2).map((post) => (
            <PostCard key={post.id} post={post} />
          ))}
        </div>
      )}
    </div>
  );
}

// ─── Project Tracking ─────────────────────────────────────────────────────────

function ProgressCircle({ pct }) {
  const r = 16;
  const circ = 2 * Math.PI * r;
  const offset = circ - (pct / 100) * circ;
  return (
    <svg width="40" height="40" className="flex-shrink-0 -rotate-90">
      <circle cx="20" cy="20" r={r} fill="none" stroke="#f1f5f9" strokeWidth="4" />
      <circle
        cx="20"
        cy="20"
        r={r}
        fill="none"
        stroke="#f59e0b"
        strokeWidth="4"
        strokeDasharray={circ}
        strokeDashoffset={offset}
        strokeLinecap="round"
      />
    </svg>
  );
}

function ProjectTrackingWidget({ tracking }) {
  const { t } = useTranslation('common');
  const navigate = useNavigate();

  return (
    <div className="bg-white rounded-2xl p-6 border border-slate-100">
      <WidgetHeader
        icon={<IconTracking className="w-4 h-4 text-amber-500" />}
        title={t('dashboard.project_tracking')}
        onViewAll={() => navigate('/tracking')}
      />
      {tracking.length === 0 ? (
        <EmptyState message={t('dashboard.no_projects')} />
      ) : (
        <ul className="flex flex-col gap-3">
          {tracking.slice(0, 5).map((item) => (
            <li key={item.id} className="flex items-center gap-3">
              <ProgressCircle pct={item.progress ?? 0} />
              <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold text-slate-700 truncate">{item.name}</p>
                {item.company_name && (
                  <p className="text-xs text-slate-400 truncate">{item.company_name}</p>
                )}
              </div>
              <span className="text-sm font-bold text-amber-600 flex-shrink-0">{item.progress ?? 0}%</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

// ─── Contracts ────────────────────────────────────────────────────────────────

function ContractsWidget({ contracts }) {
  const { t } = useTranslation('common');
  const navigate = useNavigate();

  return (
    <div className="bg-slate-900 rounded-2xl p-6">
      <WidgetHeader
        icon={<IconContract className="w-4 h-4 text-amber-400" />}
        title={t('dashboard.contracts')}
        onViewAll={() => navigate('/contracts')}
        light
      />

      <div className="grid grid-cols-2 gap-3 mb-4">
        <div className="bg-slate-800 rounded-xl p-4 text-center">
          <p className="text-2xl font-bold text-amber-400">{contracts.pending}</p>
          <p className="text-xs text-slate-400 mt-0.5">{t('dashboard.pending')}</p>
        </div>
        <div className="bg-slate-800 rounded-xl p-4 text-center">
          <p className="text-2xl font-bold text-amber-400">{contracts.signed}</p>
          <p className="text-xs text-slate-400 mt-0.5">{t('dashboard.signed')}</p>
        </div>
      </div>

      {contracts.recent.length === 0 ? (
        <EmptyState message={t('dashboard.no_contracts')} light />
      ) : (
        <ul className="flex flex-col gap-2">
          {contracts.recent.slice(0, 4).map((c) => (
            <li key={c.id} className="bg-slate-800 rounded-lg px-4 py-2.5">
              <p className="text-sm font-medium text-white truncate">{c.title}</p>
              {c.company_name && (
                <p className="text-xs text-slate-500 truncate">{c.company_name}</p>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

// ─── Documents to Review ─────────────────────────────────────────────────────

function DocumentsWidget({ documents }) {
  const { t } = useTranslation('common');
  const navigate = useNavigate();

  return (
    <div className="bg-white rounded-2xl p-6 border border-slate-100">
      <WidgetHeader
        icon={<IconDocument className="w-4 h-4 text-amber-500" />}
        title={t('dashboard.docs_to_review')}
        count={documents.length > 0 ? documents.length : undefined}
        onViewAll={() => navigate('/repository')}
      />
      {documents.length === 0 ? (
        <EmptyState message={t('dashboard.no_documents')} />
      ) : (
        <ul className="flex flex-col gap-2">
          {documents.slice(0, 5).map((doc) => (
            <li key={doc.id} className="flex items-center gap-3 py-2 border-b border-slate-50 last:border-0">
              <span className="text-xl flex-shrink-0">{fileIcon(doc.file_name)}</span>
              <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold text-slate-700 truncate">{doc.file_name}</p>
                <p className="text-xs text-slate-400">
                  {t('dashboard.repository_label')} · {timeAgo(doc.created_at)}
                </p>
              </div>
              <div className="flex items-center gap-1 flex-shrink-0">
                <button className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                  <IconEye />
                </button>
                <button className="p-1.5 rounded-lg text-slate-400 hover:text-green-600 hover:bg-green-50 transition-colors">
                  <IconCheck />
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

// ─── Process Maps ─────────────────────────────────────────────────────────────

function ProcessMapsWidget({ processMaps }) {
  const { t } = useTranslation('common');
  const navigate = useNavigate();

  return (
    <div className="bg-white rounded-2xl p-6 border border-slate-100 flex flex-col">
      <WidgetHeader
        icon={<IconMap className="w-4 h-4 text-amber-500" />}
        title={t('dashboard.process_maps')}
        onViewAll={() => navigate('/processes')}
      />
      {processMaps.length === 0 ? (
        <EmptyState message={t('dashboard.no_maps')} />
      ) : (
        <ul className="flex flex-col gap-2 flex-1">
          {processMaps.slice(0, 5).map((map) => (
            <li key={map.id} className="flex items-center gap-3 py-2 border-b border-slate-50 last:border-0">
              <IconMap className="w-4 h-4 text-amber-500 flex-shrink-0" />
              <p className="text-sm font-medium text-slate-700 truncate">{map.name}</p>
            </li>
          ))}
        </ul>
      )}
      <button
        onClick={() => navigate('/processes')}
        className="mt-4 w-full border border-slate-200 rounded-xl py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
      >
        {t('dashboard.view_all_maps')}
      </button>
    </div>
  );
}

// ─── DashboardPage ────────────────────────────────────────────────────────────

const DEFAULT_KPIS = {
  events_next_14_days: 0,
  pending_signature: 0,
  projects_active: 0,
  to_review: 0,
};

const DEFAULT_CONTRACTS = { pending: 0, signed: 0, recent: [] };

export default function DashboardPage() {
  const { t } = useTranslation('common');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [kpis, setKpis] = useState(DEFAULT_KPIS);
  const [feed, setFeed] = useState([]);
  const [tracking, setTracking] = useState([]);
  const [contracts, setContracts] = useState(DEFAULT_CONTRACTS);
  const [documents, setDocuments] = useState([]);
  const [processMaps, setProcessMaps] = useState([]);

  useEffect(() => {
    dashboardApi.getAll()
      .then((data) => {
        if (data.kpis)         setKpis(data.kpis);
        if (data.feed)         setFeed(data.feed);
        if (data.tracking)     setTracking(data.tracking);
        if (data.contracts)    setContracts(data.contracts);
        if (data.documents)    setDocuments(data.documents);
        if (data.process_maps) setProcessMaps(data.process_maps);
      })
      .catch(() => setError(t('common.unexpected_error')))
      .finally(() => setLoading(false));
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div className="flex flex-col gap-6">
      {/* Error banner */}
      {error && (
        <div className="rounded-xl bg-red-50 border border-red-200 px-5 py-4 text-sm text-red-700">
          {error}
        </div>
      )}

      {/* Row 1: Banner + KPIs */}
      <div className="grid grid-cols-1 lg:grid-cols-5 gap-4">
        <div className="lg:col-span-3">
          <Banner kpis={kpis} loading={loading} />
        </div>
        <div className="lg:col-span-2">
          <KpiGrid kpis={kpis} loading={loading} />
        </div>
      </div>

      {/* Row 2: Latest posts + Upcoming events */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <LatestPostsWidget feed={feed} />
        <UpcomingEventsSidebar />
      </div>

      {/* Row 3: Project tracking + Contracts */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <ProjectTrackingWidget tracking={tracking} />
        <ContractsWidget contracts={contracts} />
      </div>

      {/* Row 4: Documents to review + Process maps */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <DocumentsWidget documents={documents} />
        <ProcessMapsWidget processMaps={processMaps} />
      </div>
    </div>
  );
}

// ─── PropTypes ────────────────────────────────────────────────────────────────

const classNameProp = { className: PropTypes.string };

IconCalendar.propTypes = classNameProp;
IconPen.propTypes = classNameProp;
IconBriefcase.propTypes = classNameProp;
IconEye.propTypes = classNameProp;
IconCheck.propTypes = classNameProp;
IconFeed.propTypes = classNameProp;
IconContract.propTypes = classNameProp;
IconTracking.propTypes = classNameProp;
IconDocument.propTypes = classNameProp;
IconMap.propTypes = classNameProp;
IconHeart.propTypes = classNameProp;
IconComment.propTypes = classNameProp;

WidgetHeader.propTypes = {
  icon: PropTypes.node.isRequired,
  title: PropTypes.string.isRequired,
  count: PropTypes.number,
  onViewAll: PropTypes.func.isRequired,
  light: PropTypes.bool,
};

EmptyState.propTypes = {
  message: PropTypes.string.isRequired,
  light: PropTypes.bool,
};

Skeleton.propTypes = {
  className: PropTypes.string,
};

const kpisPropType = PropTypes.shape({
  events_next_14_days: PropTypes.number,
  pending_signature: PropTypes.number,
  projects_active: PropTypes.number,
  to_review: PropTypes.number,
});

Banner.propTypes = {
  kpis: kpisPropType.isRequired,
  loading: PropTypes.bool.isRequired,
};

KpiCard.propTypes = {
  icon: PropTypes.node.isRequired,
  value: PropTypes.number,
  label: PropTypes.string.isRequired,
  sub: PropTypes.string.isRequired,
  onClick: PropTypes.func.isRequired,
  loading: PropTypes.bool.isRequired,
};

KpiGrid.propTypes = {
  kpis: kpisPropType.isRequired,
  loading: PropTypes.bool.isRequired,
};

PostCard.propTypes = {
  post: PropTypes.shape({
    id: PropTypes.number.isRequired,
    title: PropTypes.string,
    content: PropTypes.string,
    author_name: PropTypes.string,
    author_avatar: PropTypes.string,
    created_at: PropTypes.string,
    likes_count: PropTypes.number,
    comments_count: PropTypes.number,
  }).isRequired,
};

LatestPostsWidget.propTypes = {
  feed: PropTypes.arrayOf(PropTypes.object).isRequired,
};

ProgressCircle.propTypes = {
  pct: PropTypes.number.isRequired,
};

ProjectTrackingWidget.propTypes = {
  tracking: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.number.isRequired,
      name: PropTypes.string,
      progress: PropTypes.number,
      company_name: PropTypes.string,
    })
  ).isRequired,
};

ContractsWidget.propTypes = {
  contracts: PropTypes.shape({
    pending: PropTypes.number,
    signed: PropTypes.number,
    recent: PropTypes.arrayOf(
      PropTypes.shape({
        id: PropTypes.number.isRequired,
        title: PropTypes.string,
        company_name: PropTypes.string,
      })
    ),
  }).isRequired,
};

DocumentsWidget.propTypes = {
  documents: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.number.isRequired,
      file_name: PropTypes.string,
      name: PropTypes.string,
      created_at: PropTypes.string,
    })
  ).isRequired,
};

ProcessMapsWidget.propTypes = {
  processMaps: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.number.isRequired,
      name: PropTypes.string,
    })
  ).isRequired,
};
