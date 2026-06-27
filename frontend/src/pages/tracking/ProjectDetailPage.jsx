import PropTypes from 'prop-types';
import { useState, useEffect, useMemo } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { projectsApi } from '../../api/projects';

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

// ─── Stub tabs ────────────────────────────────────────────────────────────────

function StubTab({ title }) {
  return (
    <div className="bg-white rounded-xl border border-black/8 shadow-sm flex items-center justify-center py-20">
      <div className="text-center">
        <p className="text-slate-400 text-sm font-medium">{title}</p>
        <p className="text-slate-300 text-xs mt-1">Coming soon</p>
      </div>
    </div>
  );
}

StubTab.propTypes = { title: PropTypes.string.isRequired };

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

  useEffect(() => {
    let cancelled = false;
    setIsLoading(true);
    setFetchError('');

    projectsApi
      .getProject(id)
      .then((data) => {
        if (!cancelled) setProject(data);
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

  const progressPercentage = project.progress_percentage ?? 0;
  const companyName = project.company?.name ?? project.company_name ?? '—';
  const franchiseName = project.franchise?.name ?? project.franchise_name ?? '—';
  const catalogItemTitle = project.catalog_item_name ?? `Project #${project.id}`;

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
        {/* KPI cards */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <KpiCard label="Progress" value={`${progressPercentage}%`} />
          <KpiCard
            label="Completed"
            value={`${project.deliverables_completed ?? 0}/${project.deliverables_total ?? 0}`}
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
        {activeTab === 'deliverables' && <StubTab title="Deliverables" />}
        {activeTab === 'upcoming' && <StubTab title="Upcoming" />}
      </div>
    </div>
  );
}
