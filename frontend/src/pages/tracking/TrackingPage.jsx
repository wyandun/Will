import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { catalogApi } from '../../api/catalog';
import { franchisesApi } from '../../api/franchises';
import { projectsApi } from '../../api/projects';
import AssignServiceModal from './AssignServiceModal';

/**
 * Tracking module — root page (WILT-56 will build the full Gantt/Kanban view on top of this).
 * This page currently provides the "Assign service" entry point and project listing.
 */
export default function TrackingPage() {
  const { t } = useTranslation('common');

  const [showAssignModal, setShowAssignModal] = useState(false);
  const [franchises, setFranchises] = useState([]);
  const [catalogTree, setCatalogTree] = useState({ bundles: [], services: [] });
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);

    Promise.all([
      franchisesApi.getFranchises(),
      catalogApi.getTree(),
      projectsApi.getProjects(),
    ])
      .then(([franchiseRes, tree, projectList]) => {
        setFranchises(franchiseRes.data ?? []);
        setCatalogTree(tree);
        setProjects(Array.isArray(projectList) ? projectList : []);
      })
      .catch(() => {
        setError(t('common.load_error'));
      })
      .finally(() => setLoading(false));
  }, [t]);

  async function handleAssign(payload) {
    const project = await projectsApi.createProject(payload);
    setProjects((prev) => [project, ...prev]);
    setShowAssignModal(false);
  }

  return (
    <div className="p-6 max-w-7xl mx-auto">
      {/* Page header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-xl font-semibold text-slate-800">
            {t('tracking.page_title')}
          </h1>
          <p className="text-sm text-slate-500 mt-0.5">
            {t('tracking.page_subtitle')}
          </p>
        </div>
        <button
          type="button"
          onClick={() => setShowAssignModal(true)}
          className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {t('tracking.assign_service_button')}
        </button>
      </div>

      {/* Content */}
      {loading && (
        <div className="flex items-center justify-center py-20">
          <div className="w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin" />
        </div>
      )}

      {!loading && error && (
        <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
          <p className="text-sm text-red-700">{error}</p>
        </div>
      )}

      {!loading && !error && projects.length === 0 && (
        <div className="text-center py-20">
          <p className="text-slate-400 text-sm">{t('tracking.no_projects')}</p>
          <button
            type="button"
            onClick={() => setShowAssignModal(true)}
            className="mt-4 px-4 py-2 rounded-lg text-sm font-medium text-blue-600 border border-blue-200 hover:bg-blue-50 transition-colors"
          >
            {t('tracking.assign_first_service')}
          </button>
        </div>
      )}

      {!loading && !error && projects.length > 0 && (
        <div className="space-y-3">
          {projects.map((project) => (
            <Link
              key={project.id}
              to={`/tracking/${project.id}`}
              className="block bg-white rounded-xl border border-slate-200 px-5 py-4 flex items-center justify-between hover:border-blue-300 hover:shadow-sm transition"
            >
              <div>
                <p className="text-sm font-semibold text-slate-800">
                  {project.company_name ?? `Company #${project.company_id}`}
                </p>
                <p className="text-xs text-slate-500 mt-0.5">
                  {project.catalog_item_name ?? `Item #${project.catalog_item_id}`}
                  {' · '}
                  {project.start_date}
                  {' · '}
                  <span className="capitalize">{project.status}</span>
                </p>
              </div>
              <span className="text-xs font-medium text-slate-500 shrink-0">
                {(project.deliverables ?? []).length} {t('tracking.deliverables_count')}
              </span>
            </Link>
          ))}
        </div>
      )}

      {/* Assign service modal */}
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
