import PropTypes from 'prop-types';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { processMapsApi } from '../../api/processMaps';
import { franchisesApi } from '../../api/franchises';
import { companiesApi } from '../../api/companies';
import { usePermissions } from '../../hooks/usePermissions';
import ProcessMapCard from './ProcessMapCard';
import ProcessMapFormModal from './ProcessMapFormModal';

// ─── Confirm dialog (inline; no shared component exists yet) ─────────────────

function ConfirmDeleteDialog({ map, onCancel, onConfirm, busy }) {
  const { t, i18n } = useTranslation('common');
  const displayName =
    (i18n.language?.startsWith('es') ? map.name_es : map.name_en) ||
    map.name_en ||
    map.name_es ||
    '—';

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget && !busy) onCancel();
      }}
    >
      <div className="relative w-full max-w-md mx-4 bg-white rounded-2xl shadow-xl p-6">
        <h3 className="text-base font-semibold text-slate-800">
          {t('processMaps.delete_confirm_title')}
        </h3>
        <p className="mt-2 text-sm text-slate-600">
          {t('processMaps.delete_confirm_message', { name: displayName })}
        </p>
        <div className="mt-5 flex items-center justify-end gap-3">
          <button
            type="button"
            onClick={onCancel}
            disabled={busy}
            className="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 disabled:opacity-50 transition-colors"
          >
            {t('processMaps.modal_cancel')}
          </button>
          <button
            type="button"
            onClick={onConfirm}
            disabled={busy}
            className="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 transition-colors"
          >
            {busy ? t('common.saving') : t('processMaps.delete_btn')}
          </button>
        </div>
      </div>
    </div>
  );
}

ConfirmDeleteDialog.propTypes = {
  map: PropTypes.object.isRequired,
  onCancel: PropTypes.func.isRequired,
  onConfirm: PropTypes.func.isRequired,
  busy: PropTypes.bool,
};

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function ProcessMapsPage() {
  const { t } = useTranslation('common');
  const { canWrite } = usePermissions();
  const canManage = canWrite('processes');

  const [maps, setMaps] = useState([]);
  const [franchises, setFranchises] = useState([]);
  const [companies, setCompanies] = useState([]);

  const [filters, setFilters] = useState({ franchise_id: '', company_id: '' });

  const [isLoading, setIsLoading] = useState(true);
  const [fetchError, setFetchError] = useState('');

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [mapToDelete, setMapToDelete] = useState(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [flash, setFlash] = useState('');

  // ── Load reference data (franchises + companies) once on mount ─────────────

  useEffect(() => {
    let cancelled = false;
    Promise.all([
      franchisesApi.getFranchises().catch(() => ({ data: [] })),
      companiesApi.getCompanies().catch(() => ({ data: [] })),
    ]).then(([f, c]) => {
      if (cancelled) return;
      setFranchises(Array.isArray(f.data) ? f.data : []);
      setCompanies(Array.isArray(c.data) ? c.data : []);
    });
    return () => {
      cancelled = true;
    };
  }, []);

  // ── Load maps whenever filters change ──────────────────────────────────────

  const loadMaps = useCallback(async () => {
    setIsLoading(true);
    setFetchError('');
    try {
      const params = {};
      if (filters.franchise_id) params.franchise_id = filters.franchise_id;
      if (filters.company_id) params.company_id = filters.company_id;
      const res = await processMapsApi.list(params);
      setMaps(Array.isArray(res.data) ? res.data : []);
    } catch (error) {
      setFetchError(error?.response?.data?.message ?? t('processMaps.load_error'));
      setMaps([]);
    } finally {
      setIsLoading(false);
    }
  }, [filters, t]);

  useEffect(() => {
    loadMaps();
  }, [loadMaps]);

  // ── Filter helpers ─────────────────────────────────────────────────────────

  // Clients dropdown is scoped to the selected franchise.
  const filteredCompanyOptions = useMemo(() => {
    if (!filters.franchise_id) return companies;
    const fid = String(filters.franchise_id);
    return companies.filter((c) => String(c.sm_franchise_id) === fid);
  }, [companies, filters.franchise_id]);

  function handleFranchiseFilter(e) {
    const value = e.target.value;
    // Reset company filter when franchise changes; the previously chosen
    // company may belong to a different franchise.
    setFilters({ franchise_id: value, company_id: '' });
  }

  function handleCompanyFilter(e) {
    setFilters((prev) => ({ ...prev, company_id: e.target.value }));
  }

  // ── Create flow ────────────────────────────────────────────────────────────

  async function handleSave(payload) {
    await processMapsApi.create(payload);
    setIsModalOpen(false);
    setFlash(t('processMaps.create_success'));
    await loadMaps();
  }

  // ── Delete flow ────────────────────────────────────────────────────────────

  async function confirmDelete() {
    if (!mapToDelete) return;
    setIsDeleting(true);
    try {
      await processMapsApi.delete(mapToDelete.id);
      setMapToDelete(null);
      setFlash(t('processMaps.delete_success'));
      await loadMaps();
    } catch (error) {
      const message = error?.response?.data?.message ?? t('processMaps.delete_error');
      setFetchError(message);
    } finally {
      setIsDeleting(false);
    }
  }

  // Auto-clear flash messages after a short delay
  useEffect(() => {
    if (!flash) return undefined;
    const id = setTimeout(() => setFlash(''), 3500);
    return () => clearTimeout(id);
  }, [flash]);

  // ── Render helpers ─────────────────────────────────────────────────────────

  const count = maps.length;
  const subtitle =
    count === 0
      ? t('processMaps.subtitle_zero')
      : count === 1
        ? t('processMaps.subtitle_one', { count })
        : t('processMaps.subtitle_other', { count });

  return (
    <>
      <div className="space-y-5">
        {/* Page header (dark band, matches the requested mockup style) */}
        <div className="rounded-xl bg-gradient-to-r from-[#1e3a5f] via-[#1C3755] to-[#2d5a8f] text-white px-6 py-5 flex items-center justify-between shadow-md">
          <div>
            <h1 className="text-2xl font-semibold">{t('processMaps.title')}</h1>
            <p className="mt-1 text-sm text-slate-200">{subtitle}</p>
          </div>
          {canManage && (
            <button
              onClick={() => setIsModalOpen(true)}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-900 bg-amber-400 hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:ring-offset-2 focus:ring-offset-[#1e3a5f] transition-colors"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              {t('processMaps.new_map')}
            </button>
          )}
        </div>

        {/* Flash success banner */}
        {flash && (
          <div className="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-2.5">
            <p className="text-sm text-emerald-700">{flash}</p>
          </div>
        )}

        {/* Filters card */}
        <div className="bg-white rounded-xl border border-slate-200 px-5 py-4">
          <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-3 flex-1">
              <select
                value={filters.franchise_id}
                onChange={handleFranchiseFilter}
                className="w-full sm:w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">{t('processMaps.all_franchises')}</option>
                {franchises.map((f) => (
                  <option key={f.id} value={String(f.id)}>
                    {f.name}
                  </option>
                ))}
              </select>
              <select
                value={filters.company_id}
                onChange={handleCompanyFilter}
                className="w-full sm:w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">{t('processMaps.all_clients')}</option>
                {filteredCompanyOptions.map((c) => (
                  <option key={c.id} value={String(c.id)}>
                    {c.name}
                  </option>
                ))}
              </select>
            </div>
            <p className="text-xs text-slate-500">{subtitle}</p>
          </div>
        </div>

        {/* Loading */}
        {isLoading && (
          <div className="flex items-center justify-center py-20 gap-3">
            <svg className="w-6 h-6 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p className="text-sm text-slate-500">{t('common.loading')}</p>
          </div>
        )}

        {/* Fetch error */}
        {!isLoading && fetchError && (
          <div className="rounded-xl bg-red-50 border border-red-200 px-5 py-4 flex items-start gap-3">
            <svg className="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div>
              <p className="text-sm font-medium text-red-700">{fetchError}</p>
              <button
                onClick={loadMaps}
                className="mt-1 text-xs text-red-600 underline hover:text-red-800"
              >
                {t('common.try_again')}
              </button>
            </div>
          </div>
        )}

        {/* Empty */}
        {!isLoading && !fetchError && maps.length === 0 && (
          <div className="flex flex-col items-center justify-center py-20 text-center">
            <div className="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-4">
              <svg className="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                <rect x="9" y="2" width="6" height="6" rx="1" />
                <rect x="3" y="16" width="6" height="6" rx="1" />
                <rect x="15" y="16" width="6" height="6" rx="1" />
                <path strokeLinecap="round" d="M12 8v4M6 16v-2a2 2 0 012-2h8a2 2 0 012 2v2" />
              </svg>
            </div>
            <p className="text-sm font-semibold text-slate-700">{t('processMaps.empty_title')}</p>
            <p className="mt-1 text-sm text-slate-400">
              {canManage ? t('processMaps.empty_admin') : t('processMaps.empty_user')}
            </p>
          </div>
        )}

        {/* Grid of cards */}
        {!isLoading && !fetchError && maps.length > 0 && (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {maps.map((map) => (
              <ProcessMapCard
                key={map.id}
                map={map}
                onDelete={(m) => setMapToDelete(m)}
              />
            ))}
          </div>
        )}
      </div>

      {/* Create modal */}
      {isModalOpen && (
        <ProcessMapFormModal
          franchises={franchises}
          companies={companies}
          onClose={() => setIsModalOpen(false)}
          onSave={handleSave}
        />
      )}

      {/* Delete confirm */}
      {mapToDelete && (
        <ConfirmDeleteDialog
          map={mapToDelete}
          busy={isDeleting}
          onCancel={() => setMapToDelete(null)}
          onConfirm={confirmDelete}
        />
      )}
    </>
  );
}
