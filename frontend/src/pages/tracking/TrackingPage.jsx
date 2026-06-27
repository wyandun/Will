import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { catalogApi } from '../../api/catalog';
import { franchisesApi } from '../../api/franchises';
import { projectsApi } from '../../api/projects';
import AssignServiceModal from './AssignServiceModal';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Map a project type to its i18n badge label key. */
function typeBadgeKey(type) {
  switch (type) {
    case 'bundle':
      return 'tracking.badge_type_bundle';
    case 'service':
      return 'tracking.badge_type_service';
    case 'deliverable':
      return 'tracking.badge_type_deliverable';
    default:
      return 'tracking.badge_type_service';
  }
}

/** Map a project status to Tailwind color classes. */
function statusColors(status) {
  switch (status) {
    case 'active':
      return 'bg-emerald-100 text-emerald-700';
    case 'completed':
      return 'bg-blue-100 text-blue-700';
    case 'paused':
      return 'bg-amber-100 text-amber-700';
    case 'cancelled':
      return 'bg-red-100 text-red-700';
    default:
      return 'bg-slate-100 text-slate-600';
  }
}

// ---------------------------------------------------------------------------
// Loading skeleton — mimics a project card shape
// ---------------------------------------------------------------------------

function ProjectCardSkeleton() {
  return (
    <div className="bg-white rounded-xl border border-slate-200 p-4 animate-pulse">
      <div className="flex items-start gap-3">
        <div className="w-10 h-10 rounded-lg bg-slate-200 shrink-0" />
        <div className="flex-1 space-y-2">
          <div className="h-4 bg-slate-200 rounded w-2/3" />
          <div className="flex gap-2">
            <div className="h-5 bg-slate-200 rounded-full w-16" />
            <div className="h-5 bg-slate-200 rounded-full w-20" />
          </div>
          <div className="h-3 bg-slate-200 rounded w-1/2" />
          <div className="h-2 bg-slate-200 rounded-full w-full" />
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Project card
// ---------------------------------------------------------------------------

function ProjectCard({ project, onClick }) {
  const { t } = useTranslation('common');

  const statusLabel = t(`tracking.status_${project.status}`, {
    defaultValue: project.status,
  });
  const typeLabel = t(typeBadgeKey(project.type));
  const progressPct = project.progress_percentage ?? 0;
  const delTotal = project.deliverables_total ?? 0;
  const delDone = project.deliverables_completed ?? 0;

  return (
    <button
      type="button"
      onClick={onClick}
      className="w-full text-left bg-white rounded-xl border border-slate-200 p-4 hover:border-[#1C3755] hover:shadow-md transition-all group"
    >
      <div className="flex items-start gap-3">
        {/* Cube icon */}
        <div className="shrink-0 w-10 h-10 rounded-lg bg-[#1C3755]/10 flex items-center justify-center">
          <svg
            className="w-5 h-5 text-[#1C3755]"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.75"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"
            />
          </svg>
        </div>

        {/* Content */}
        <div className="flex-1 min-w-0">
          {/* Title + status badge + type badge */}
          <div className="flex items-center gap-2 flex-wrap">
            <span className="font-semibold text-slate-800 truncate">
              {project.catalog_item_name ?? `Project #${project.id}`}
            </span>
            <span
              className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold ${statusColors(project.status)}`}
            >
              {statusLabel}
            </span>
            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600">
              {typeLabel}
            </span>
          </div>

          {/* Meta row — client, franchise, start date */}
          <div className="flex items-center gap-3 flex-wrap mt-1.5">
            {project.company_name && (
              <span className="inline-flex items-center gap-1 text-xs text-slate-500">
                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3 21V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v16M9 21V9h6v12" />
                </svg>
                {project.company_name}
              </span>
            )}
            {project.franchise_name && (
              <span className="inline-flex items-center gap-1 text-xs text-slate-500">
                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <path strokeLinecap="round" strokeLinejoin="round" d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
                {project.franchise_name}
              </span>
            )}
            {project.start_date && (
              <span className="inline-flex items-center gap-1 text-xs text-slate-500">
                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                  <line x1="16" y1="2" x2="16" y2="6" />
                  <line x1="8" y1="2" x2="8" y2="6" />
                  <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
                {project.start_date}
              </span>
            )}
          </div>

          {/* Progress bar */}
          <div className="mt-2.5">
            <div className="flex items-center justify-between mb-1">
              <span className="text-[11px] text-slate-500">
                {t('tracking.deliverables_count', {
                  completed: delDone,
                  total: delTotal,
                })}
              </span>
              <span className="text-[11px] font-semibold text-slate-600">
                {progressPct}%
              </span>
            </div>
            <div className="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
              <div
                className="h-full bg-emerald-500 rounded-full transition-all"
                style={{ width: `${progressPct}%` }}
              />
            </div>
          </div>
        </div>

        {/* Navigation arrow */}
        <svg
          className="shrink-0 w-5 h-5 text-slate-300 group-hover:text-[#1C3755] transition-colors self-center"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.75"
          viewBox="0 0 24 24"
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M9 18l6-6-6-6" />
        </svg>
      </div>
    </button>
  );
}

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

const STATUS_OPTIONS = ['active', 'completed', 'paused', 'cancelled'];

/**
 * Tracking module — main project listing view.
 * Shows all projects with search + status filtering, progress bars, and card navigation.
 */
export default function TrackingPage() {
  const { t } = useTranslation('common');
  const navigate = useNavigate();

  // Modal dependencies
  const [showAssignModal, setShowAssignModal] = useState(false);
  const [franchises, setFranchises] = useState([]);
  const [catalogTree, setCatalogTree] = useState({ bundles: [], services: [] });

  // Projects state
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // Filter state
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  // Debounced search — only sends request after 300 ms of inactivity
  const searchTimer = useRef(null);
  const [debouncedSearch, setDebouncedSearch] = useState('');

  // Load franchises + catalog tree once (for the AssignServiceModal)
  useEffect(() => {
    Promise.all([franchisesApi.getFranchises(), catalogApi.getTree()]).then(
      ([franchiseRes, tree]) => {
        setFranchises(franchiseRes.data ?? []);
        setCatalogTree(tree);
      }
    );
  }, []);

  // Propagate search to debouncedSearch after 300 ms
  useEffect(() => {
    if (searchTimer.current) clearTimeout(searchTimer.current);
    searchTimer.current = setTimeout(() => setDebouncedSearch(search), 300);
    return () => clearTimeout(searchTimer.current);
  }, [search]);

  // Fetch projects from API whenever filters change
  const loadProjects = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const params = {};
      if (debouncedSearch.trim()) params.search = debouncedSearch.trim();
      if (statusFilter) params.status = statusFilter;
      const list = await projectsApi.getProjects(params);
      setProjects(Array.isArray(list) ? list : []);
    } catch {
      setError(t('tracking.load_error'));
    } finally {
      setLoading(false);
    }
  }, [debouncedSearch, statusFilter, t]);

  useEffect(() => {
    loadProjects();
  }, [loadProjects]);

  async function handleAssign(payload) {
    const project = await projectsApi.createProject(payload);
    setProjects((prev) => [project, ...prev]);
    setShowAssignModal(false);
  }

  const hasFilters = debouncedSearch.trim() || statusFilter;
  // Show skeleton only on the very first load (no data yet)
  const showSkeleton = loading && projects.length === 0 && !error;

  return (
    <div className="p-4 md:p-6 space-y-4">

      {/* ── Header ─────────────────────────────────────────────────────────── */}
      <div className="rounded-xl bg-gradient-to-r from-[#1e3a5f] via-[#1C3755] to-[#2d5a8f] text-white px-6 py-5 flex items-center justify-between shadow-md">
        <div>
          <h1 className="text-2xl font-semibold">{t('tracking.page_title')}</h1>
          <p className="mt-1 text-sm text-slate-200">
            {projects.length === 1
              ? t('tracking.project_counter', { shown: projects.length, total: projects.length })
              : t('tracking.project_counter_plural', { shown: projects.length, total: projects.length })}
          </p>
        </div>
        <button
          type="button"
          onClick={() => setShowAssignModal(true)}
          className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-900 bg-amber-400 hover:bg-amber-500 shadow-sm transition"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {t('tracking.assign_service_button')}
        </button>
      </div>

      {/* ── Filters ─────────────────────────────────────────────────────────── */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-3 flex flex-col sm:flex-row gap-3">
        {/* Search input */}
        <div className="relative flex-1">
          <svg
            className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.75"
            viewBox="0 0 24 24"
          >
            <circle cx="11" cy="11" r="8" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35" />
          </svg>
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t('tracking.search_placeholder')}
            className="w-full pl-9 pr-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        {/* Status dropdown */}
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="w-full sm:w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">{t('tracking.all_statuses')}</option>
          {STATUS_OPTIONS.map((s) => (
            <option key={s} value={s}>
              {t(`tracking.status_${s}`)}
            </option>
          ))}
        </select>
      </div>

      {/* ── Error ───────────────────────────────────────────────────────────── */}
      {error && (
        <div className="rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 flex items-center justify-between">
          {error}
          <button
            type="button"
            onClick={loadProjects}
            className="font-semibold underline"
          >
            ↻
          </button>
        </div>
      )}

      {/* ── Loading skeleton ─────────────────────────────────────────────────── */}
      {showSkeleton && (
        <div className="space-y-3">
          {[1, 2, 3].map((n) => (
            <ProjectCardSkeleton key={n} />
          ))}
        </div>
      )}

      {/* ── Empty state ──────────────────────────────────────────────────────── */}
      {!loading && !error && projects.length === 0 && (
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <div className="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-4">
            <svg
              className="w-7 h-7 text-slate-400"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.5"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"
              />
            </svg>
          </div>
          <p className="text-slate-600 font-medium">
            {hasFilters ? t('tracking.no_projects_filtered') : t('tracking.no_projects')}
          </p>
          {!hasFilters && (
            <button
              type="button"
              onClick={() => setShowAssignModal(true)}
              className="mt-4 px-4 py-2 rounded-lg text-sm font-medium text-blue-600 border border-blue-200 hover:bg-blue-50 transition-colors"
            >
              {t('tracking.assign_first_service')}
            </button>
          )}
        </div>
      )}

      {/* ── Project cards ─────────────────────────────────────────────────────── */}
      {!showSkeleton && !error && projects.length > 0 && (
        <div className="space-y-2.5">
          {projects.map((project) => (
            <ProjectCard
              key={project.id}
              project={project}
              onClick={() => navigate(`/tracking/${project.id}`)}
            />
          ))}
        </div>
      )}

      {/* ── Assign service modal ─────────────────────────────────────────────── */}
      {showAssignModal && (
        <AssignServiceModal
          franchises={franchises}
          catalogTree={catalogTree}
          onClose={() => setShowAssignModal(false)}
          onSave={handleAssign}
        />
      )}
    </div>
  );
}
