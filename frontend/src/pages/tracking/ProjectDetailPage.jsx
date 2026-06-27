import PropTypes from 'prop-types';
import { useState, useEffect, useMemo, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { projectsApi } from '../../api/projects';

function IconCalendar() {
  return (
    <svg className="w-5 h-5 text-slate-400 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
    </svg>
  );
}

// ─── Status badge ─────────────────────────────────────────────────────────────

const STATUS_CONFIG = {
  pending:     { label: 'Pending',     cls: 'bg-slate-100 text-slate-600' },
  in_progress: { label: 'In progress', cls: 'bg-blue-100 text-blue-700' },
  completed:   { label: 'Completed',   cls: 'bg-green-100 text-green-700' },
  blocked:     { label: 'Blocked',     cls: 'bg-red-100 text-red-700' },
};

function DeliverableStatusBadge({ status }) {
  const cfg = STATUS_CONFIG[status] ?? STATUS_CONFIG.pending;
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${cfg.cls}`}>
      {cfg.label}
    </span>
  );
}

DeliverableStatusBadge.propTypes = { status: PropTypes.string.isRequired };

// ─── KPI Card ─────────────────────────────────────────────────────────────────

function KpiCard({ label, value, valueClassName }) {
  return (
    <div className="bg-white rounded-xl border border-black/8 shadow-sm px-5 py-4">
      <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">{label}</p>
      <p className={`text-2xl font-bold text-[#1C3755] ${valueClassName ?? ''}`}>{value}</p>
    </div>
  );
}

KpiCard.propTypes = {
  label: PropTypes.string.isRequired,
  value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
  valueClassName: PropTypes.string,
};

// ─── Gantt chart ──────────────────────────────────────────────────────────────

/**
 * Returns the number of calendar days between two date strings (inclusive).
 */
function daysBetween(startStr, endStr) {
  if (!startStr || !endStr) return 0;
  const start = new Date(startStr);
  const end = new Date(endStr);
  return Math.max(0, Math.round((end - start) / 86_400_000));
}

function GanttBar({ deliverable, projectStartDate, projectEndDate }) {
  const totalDays = daysBetween(projectStartDate, projectEndDate) || 1;
  const offsetDays = daysBetween(projectStartDate, deliverable.estimated_start_date);
  const durationDays = daysBetween(deliverable.estimated_start_date, deliverable.estimated_end_date) + 1;

  const offsetPct = Math.min((offsetDays / totalDays) * 100, 100);
  const widthPct  = Math.min((durationDays / totalDays) * 100, 100 - offsetPct);

  const barColors = {
    pending:     'bg-slate-300',
    in_progress: 'bg-blue-500',
    completed:   'bg-green-500',
    blocked:     'bg-red-500',
  };
  const barColor = barColors[deliverable.status] ?? barColors.pending;

  return (
    <div className="relative h-5 w-full bg-slate-100 rounded-full overflow-hidden">
      <div
        className={`absolute top-0 h-full rounded-full ${barColor}`}
        style={{ left: `${offsetPct}%`, width: `${Math.max(widthPct, 2)}%` }}
      />
    </div>
  );
}

GanttBar.propTypes = {
  deliverable: PropTypes.object.isRequired,
  projectStartDate: PropTypes.string.isRequired,
  projectEndDate: PropTypes.string.isRequired,
};

function GanttPhaseGroup({ phase, deliverables, projectStartDate, projectEndDate }) {
  const [expanded, setExpanded] = useState(true);

  const completed = deliverables.filter((d) => d.status === 'completed').length;
  const total = deliverables.length;

  return (
    <div>
      {/* Phase header */}
      <button
        type="button"
        onClick={() => setExpanded((v) => !v)}
        className="w-full flex items-center gap-2 px-4 py-2.5 bg-slate-50 border-b border-black/5 text-left hover:bg-slate-100 transition"
      >
        <svg
          className={`w-4 h-4 text-slate-400 transition-transform ${expanded ? 'rotate-90' : ''}`}
          fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span className="text-sm font-semibold text-slate-700 flex-1">{phase ?? 'General'}</span>
        <span className="text-xs text-slate-500">
          {completed}/{total} completed
        </span>
      </button>

      {/* Deliverable rows */}
      {expanded && deliverables.map((d) => (
        <div
          key={d.id}
          className="grid grid-cols-[1fr_2fr] gap-4 items-center px-4 py-2.5 border-b border-black/5 last:border-0"
        >
          {/* Deliverable info */}
          <div className="flex items-center gap-2 min-w-0">
            <span className="text-sm text-slate-700 truncate">{d.name}</span>
            <DeliverableStatusBadge status={d.status} />
          </div>
          {/* Timeline bar */}
          <GanttBar
            deliverable={d}
            projectStartDate={projectStartDate}
            projectEndDate={projectEndDate}
          />
        </div>
      ))}
    </div>
  );
}

GanttPhaseGroup.propTypes = {
  phase: PropTypes.string,
  deliverables: PropTypes.array.isRequired,
  projectStartDate: PropTypes.string.isRequired,
  projectEndDate: PropTypes.string.isRequired,
};

function GanttTab({ project }) {
  const grouped = useMemo(() => {
    const map = new Map();
    for (const d of project.deliverables ?? []) {
      const key = d.phase ?? '';
      if (!map.has(key)) map.set(key, []);
      map.get(key).push(d);
    }
    return map;
  }, [project.deliverables]);

  const projectEndDate = project.estimated_end_date ?? project.start_date;

  return (
    <div className="bg-white rounded-xl border border-black/8 shadow-sm overflow-hidden">
      {/* Column headers */}
      <div className="grid grid-cols-[1fr_2fr] gap-4 px-4 py-2 bg-slate-50 border-b border-black/8">
        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Deliverable</span>
        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Timeline</span>
      </div>

      {/* Groups */}
      {[...grouped.entries()].map(([phase, deliverables]) => (
        <GanttPhaseGroup
          key={phase}
          phase={phase || null}
          deliverables={deliverables}
          projectStartDate={project.start_date}
          projectEndDate={projectEndDate}
        />
      ))}

      {(!project.deliverables || project.deliverables.length === 0) && (
        <div className="px-4 py-8 text-center text-sm text-slate-400">
          No deliverables found for this project.
        </div>
      )}

      {/* Status legend */}
      <div className="flex flex-wrap items-center gap-4 px-4 py-3 border-t border-black/5 bg-slate-50">
        {Object.entries(STATUS_CONFIG).map(([key, { label, cls }]) => (
          <div key={key} className="flex items-center gap-1.5">
            <span className={`inline-block w-3 h-3 rounded-full ${cls.split(' ')[0]}`} />
            <span className="text-xs text-slate-500">{label}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

GanttTab.propTypes = { project: PropTypes.object.isRequired };

// ─── Deliverables tab ─────────────────────────────────────────────────────────

const DELIVERABLE_STATUSES = ['pending', 'in_progress', 'completed', 'blocked'];

const STATUS_I18N_KEY = {
  pending:     'tracking.deliverable_status_pending',
  in_progress: 'tracking.deliverable_status_in_progress',
  completed:   'tracking.deliverable_status_completed',
  blocked:     'tracking.deliverable_status_blocked',
};

function durationDays(startStr, endStr) {
  if (!startStr || !endStr) return 0;
  const start = new Date(startStr);
  const end = new Date(endStr);
  return Math.max(1, Math.round((end - start) / 86_400_000) + 1);
}

function overdueDays(endStr) {
  if (!endStr) return 0;
  const end = new Date(endStr);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const diff = Math.floor((today - end) / 86_400_000);
  return Math.max(0, diff);
}

function DeliverableRow({ deliverable, projectId, onStatusChange }) {
  const { t } = useTranslation('common');
  const [isUpdating, setIsUpdating] = useState(false);

  const duration = durationDays(
    deliverable.estimated_start_date,
    deliverable.estimated_end_date,
  );
  const overdue = deliverable.status !== 'completed'
    ? overdueDays(deliverable.estimated_end_date)
    : 0;

  const handleStatusChange = useCallback(async (e) => {
    const newStatus = e.target.value;
    setIsUpdating(true);
    try {
      const result = await projectsApi.updateDeliverableStatus(
        projectId,
        deliverable.id,
        newStatus,
      );
      onStatusChange(deliverable.id, newStatus, result);
    } finally {
      setIsUpdating(false);
    }
  }, [projectId, deliverable.id, onStatusChange]);

  return (
    <div className="grid grid-cols-[2fr_1fr_auto_auto] gap-3 items-center px-4 py-3 border-b border-black/5 last:border-0 hover:bg-slate-50/60 transition-colors">
      {/* Name + overdue badge */}
      <div className="flex items-center gap-2 min-w-0">
        <span className="text-sm text-slate-700 truncate">{deliverable.name}</span>
        {overdue > 0 && (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 shrink-0">
            {t('tracking.overdue', { days: overdue })}
          </span>
        )}
        {deliverable.status === 'completed' && (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 shrink-0">
            {t('tracking.deliverable_status_completed')}
          </span>
        )}
      </div>

      {/* Dates + duration */}
      <div className="text-xs text-slate-500 space-y-0.5">
        <div>{formatDate(deliverable.estimated_start_date)} → {formatDate(deliverable.estimated_end_date)}</div>
        <div className="text-slate-400">
          {t('tracking.duration_days', { count: duration })}
        </div>
      </div>

      {/* Status badge (current) */}
      <div>
        <DeliverableStatusBadge status={deliverable.status} />
      </div>

      {/* Status selector */}
      <div className="relative">
        <select
          value={deliverable.status}
          onChange={handleStatusChange}
          disabled={isUpdating}
          className={[
            'text-xs border rounded-lg px-2 py-1.5 pr-6 appearance-none bg-white text-slate-700 cursor-pointer',
            'border-slate-200 hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500/30',
            'disabled:opacity-50 disabled:cursor-not-allowed',
          ].join(' ')}
          aria-label={`Change status of ${deliverable.name}`}
        >
          {DELIVERABLE_STATUSES.map((s) => (
            <option key={s} value={s}>
              {t(STATUS_I18N_KEY[s])}
            </option>
          ))}
        </select>
        {isUpdating && (
          <div className="absolute inset-0 flex items-center justify-center bg-white/70 rounded-lg">
            <div className="w-3 h-3 border-2 border-blue-500 border-t-transparent rounded-full animate-spin" />
          </div>
        )}
      </div>
    </div>
  );
}

DeliverableRow.propTypes = {
  deliverable: PropTypes.object.isRequired,
  projectId: PropTypes.number.isRequired,
  onStatusChange: PropTypes.func.isRequired,
};

function DeliverablePhaseGroup({ phase, deliverables, projectId, onStatusChange }) {
  const [expanded, setExpanded] = useState(true);
  const completed = deliverables.filter((d) => d.status === 'completed').length;

  return (
    <div>
      <button
        type="button"
        onClick={() => setExpanded((v) => !v)}
        className="w-full flex items-center gap-2 px-4 py-2.5 bg-slate-50 border-b border-black/5 text-left hover:bg-slate-100 transition"
      >
        <svg
          className={`w-4 h-4 text-slate-400 transition-transform ${expanded ? 'rotate-90' : ''}`}
          fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span className="text-sm font-semibold text-slate-700 flex-1">{phase ?? 'General'}</span>
        <span className="text-xs text-slate-500">
          {completed}/{deliverables.length} completed
        </span>
      </button>

      {expanded && deliverables.map((d) => (
        <DeliverableRow
          key={d.id}
          deliverable={d}
          projectId={projectId}
          onStatusChange={onStatusChange}
        />
      ))}
    </div>
  );
}

DeliverablePhaseGroup.propTypes = {
  phase: PropTypes.string,
  deliverables: PropTypes.array.isRequired,
  projectId: PropTypes.number.isRequired,
  onStatusChange: PropTypes.func.isRequired,
};

function DeliverablesTab({ project, onKpiUpdate }) {
  const grouped = useMemo(() => {
    const map = new Map();
    for (const d of project.deliverables ?? []) {
      const key = d.phase ?? '';
      if (!map.has(key)) map.set(key, []);
      map.get(key).push(d);
    }
    return map;
  }, [project.deliverables]);

  const handleStatusChange = useCallback((deliverableId, newStatus, kpiResult) => {
    onKpiUpdate(deliverableId, newStatus, kpiResult);
  }, [onKpiUpdate]);

  if (!project.deliverables || project.deliverables.length === 0) {
    return (
      <div className="bg-white rounded-xl border border-black/8 shadow-sm flex items-center justify-center py-20">
        <p className="text-slate-400 text-sm">No deliverables found for this project.</p>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-xl border border-black/8 shadow-sm overflow-hidden">
      {/* Column headers */}
      <div className="grid grid-cols-[2fr_1fr_auto_auto] gap-3 px-4 py-2.5 bg-slate-50 border-b border-black/8">
        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Deliverable</span>
        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Dates / Duration</span>
        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Status</span>
        <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Change</span>
      </div>

      {[...grouped.entries()].map(([phase, deliverables]) => (
        <DeliverablePhaseGroup
          key={phase}
          phase={phase || null}
          deliverables={deliverables}
          projectId={project.id}
          onStatusChange={handleStatusChange}
        />
      ))}
    </div>
  );
}

DeliverablesTab.propTypes = {
  project: PropTypes.object.isRequired,
  onKpiUpdate: PropTypes.func.isRequired,
};

// ─── Upcoming tab ─────────────────────────────────────────────────────────────

function UpcomingTab({ deliverables }) {
  const { t } = useTranslation('common');

  const upcoming = useMemo(
    () =>
      deliverables
        .filter((d) => d.status === 'pending' || d.status === 'in_progress')
        .slice()
        .sort((a, b) => {
          if (!a.estimated_end_date) return 1;
          if (!b.estimated_end_date) return -1;
          return new Date(a.estimated_end_date) - new Date(b.estimated_end_date);
        }),
    [deliverables],
  );

  const formatDate = (dateStr) => {
    if (!dateStr) return null;
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('en-US', { month: 'numeric', day: 'numeric', year: 'numeric' });
  };

  const statusLabel = (status) =>
    status === 'in_progress' ? t('tracking.status_in_progress') : t('tracking.status_pending');

  const STATUS_BADGE = {
    pending:     'bg-slate-100 text-slate-600',
    in_progress: 'bg-blue-100 text-blue-700',
  };

  return (
    <div className="space-y-4">
      <h2 className="text-base font-semibold text-slate-800">{t('tracking.upcoming_title')}</h2>
      {upcoming.length === 0 ? (
        <p className="py-10 text-center text-sm text-slate-400">{t('tracking.upcoming_empty')}</p>
      ) : (
        <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white overflow-hidden">
          {upcoming.map((d) => (
            <li key={d.id} className="flex items-center gap-4 px-5 py-4">
              <IconCalendar />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-slate-800 truncate">{d.name}</p>
                <p className="text-xs text-slate-500 mt-0.5">
                  {d.phase ? `${d.phase} · ` : ''}
                  {formatDate(d.estimated_end_date)
                    ? t('tracking.upcoming_due', { date: formatDate(d.estimated_end_date) })
                    : ''}
                </p>
              </div>
              <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${STATUS_BADGE[d.status] ?? STATUS_BADGE.pending}`}>
                {statusLabel(d.status)}
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

UpcomingTab.propTypes = {
  deliverables: PropTypes.arrayOf(PropTypes.shape({
    id: PropTypes.number.isRequired,
    name: PropTypes.string.isRequired,
    phase: PropTypes.string,
    estimated_end_date: PropTypes.string,
    status: PropTypes.string.isRequired,
  })).isRequired,
};

// ─── Tab bar ──────────────────────────────────────────────────────────────────

const TABS = [
  { key: 'gantt',       label: 'Gantt Chart' },
  { key: 'deliverables', label: 'Deliverables' },
  { key: 'upcoming',    label: 'Upcoming' },
];

// ─── Progress bar ─────────────────────────────────────────────────────────────

function ProgressBar({ percentage }) {
  return (
    <div className="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
      <div
        className="h-full bg-blue-500 rounded-full transition-all duration-500"
        style={{ width: `${percentage}%` }}
      />
    </div>
  );
}

ProgressBar.propTypes = { percentage: PropTypes.number.isRequired };

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatDate(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function ProjectDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { t } = useTranslation('common');

  const [project, setProject] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [fetchError, setFetchError] = useState('');
  const [activeTab, setActiveTab] = useState('gantt');

  // KPI state lifted from the project so it can be updated without a full reload.
  const [progressPercentage, setProgressPercentage] = useState(0);
  const [deliverablesCompleted, setDeliverablesCompleted] = useState(0);
  const [deliverablesTotal, setDeliverablesTotal] = useState(0);

  useEffect(() => {
    let cancelled = false;
    setIsLoading(true);
    setFetchError('');

    projectsApi
      .getProject(id)
      .then((data) => {
        if (!cancelled) {
          setProject(data);
          setProgressPercentage(data.progress_percentage ?? 0);
          setDeliverablesCompleted(data.deliverables_completed ?? 0);
          setDeliverablesTotal(data.deliverables_total ?? 0);
        }
      })
      .catch(() => {
        if (!cancelled) setFetchError(t('common.load_error'));
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [id, t]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-24">
        <div className="w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  if (fetchError || !project) {
    return (
      <div className="py-20 text-center space-y-4">
        <p className="text-sm text-red-600">{fetchError || t('common.load_error')}</p>
        <button
          type="button"
          onClick={() => navigate('/tracking')}
          className="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-800"
        >
          Back to Tracking
        </button>
      </div>
    );
  }

  const companyName = project.company?.name ?? project.company_name ?? '—';
  const franchiseName = project.franchise?.name ?? project.franchise_name ?? '—';
  const catalogItemTitle = project.catalog_item_name ?? `Project #${project.id}`;

  // Update deliverable status in local project state and refresh KPI counters.
  const handleKpiUpdate = useCallback((deliverableId, newStatus, kpiResult) => {
    setProject((prev) => {
      if (!prev) return prev;
      return {
        ...prev,
        deliverables: prev.deliverables.map((d) =>
          d.id === deliverableId ? { ...d, status: newStatus } : d,
        ),
      };
    });
    setProgressPercentage(kpiResult.progress_percentage);
    setDeliverablesCompleted(kpiResult.deliverables_completed);
    setDeliverablesTotal(kpiResult.deliverables_total);
  }, []);

  return (
    <div className="min-h-screen bg-[#F4F6F9]">
      {/* Header band */}
      <div className="bg-gradient-to-r from-[#1C3755] to-[#2d5a8f] px-6 py-4 shadow-md">
        {/* Breadcrumb */}
        <div className="flex items-center gap-1.5 text-white/70 text-sm flex-wrap">
          <Link to="/tracking" className="hover:text-white transition">
            {t('tracking.page_title')}
          </Link>
          <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
          </svg>
          <span className="font-semibold text-[#D5B170] truncate max-w-xs">{catalogItemTitle}</span>
        </div>

        {/* Project title and meta */}
        <div className="mt-3">
          <h1 className="text-xl font-bold text-white">{catalogItemTitle}</h1>
          <div className="flex items-center gap-3 mt-1 text-white/60 text-sm flex-wrap">
            <span>{companyName}</span>
            <span>·</span>
            <span>{franchiseName}</span>
            <span>·</span>
            <span>Started {formatDate(project.start_date)}</span>
          </div>
        </div>
      </div>

      <div className="p-4 md:p-6 space-y-5">
        {/* KPI cards — driven by lifted state so they update on deliverable changes */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <KpiCard label="Progress" value={`${progressPercentage}%`} />
          <KpiCard
            label="Completed"
            value={`${deliverablesCompleted}/${deliverablesTotal}`}
            valueClassName="text-green-600"
          />
          <KpiCard label="Start" value={formatDate(project.start_date)} />
          <KpiCard label="Est. End" value={formatDate(project.estimated_end_date)} />
        </div>

        {/* Overall progress bar */}
        <div className="bg-white rounded-xl border border-black/8 shadow-sm px-5 py-4">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm font-semibold text-slate-700">Overall progress</span>
            <span className="text-sm font-bold text-[#1C3755]">{progressPercentage}%</span>
          </div>
          <ProgressBar percentage={progressPercentage} />
        </div>

        {/* Tab bar */}
        <div className="flex gap-1 border-b border-black/8">
          {TABS.map((tab) => (
            <button
              key={tab.key}
              type="button"
              onClick={() => setActiveTab(tab.key)}
              className={[
                'px-4 py-2.5 text-sm font-semibold transition border-b-2 -mb-px',
                activeTab === tab.key
                  ? 'border-[#1C3755] text-[#1C3755]'
                  : 'border-transparent text-slate-500 hover:text-slate-700',
              ].join(' ')}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {/* Tab content */}
        {activeTab === 'gantt' && <GanttTab project={project} />}
        {activeTab === 'deliverables' && (
          <DeliverablesTab project={project} onKpiUpdate={handleKpiUpdate} />
        )}
        {activeTab === 'upcoming' && (
          <UpcomingTab deliverables={project.deliverables ?? []} />
        )}
      </div>
    </div>
  );
}
