import PropTypes from 'prop-types';
import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../../store/authStore';
import { franchisesApi } from '../../api/franchises';
import FranchiseFormModal from './FranchiseFormModal';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getInitials(name) {
  if (!name) return '?';
  return name
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('');
}

function getAvatarColor(name) {
  const colors = [
    'bg-blue-500', 'bg-emerald-500', 'bg-violet-500', 'bg-amber-500',
    'bg-rose-500', 'bg-cyan-500', 'bg-indigo-500', 'bg-teal-500',
  ];
  let hash = 0;
  for (let i = 0; i < (name || '').length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return colors[Math.abs(hash) % colors.length];
}

// ─── Card de franquicia ──────────────────────────────────────────────────────

function FranchiseCard({ franchise, onEdit, onToggleStatus, onDelete, isSuperadmin }) {
  const { t } = useTranslation('common');
  const isActive = franchise.is_active !== false;

  return (
    <div
      className={`bg-white rounded-xl border shadow-sm hover:shadow-md transition-all ${
        isActive ? 'border-slate-200' : 'border-slate-200 saturate-[0.25] opacity-80'
      }`}
    >
      {/* Header de la card: avatar + nombre + badge */}
      <div className="p-5 pb-3">
        <div className="flex items-start gap-3">
          <div
            className={`w-12 h-12 rounded-xl ${getAvatarColor(franchise.name)} flex items-center justify-center shrink-0`}
          >
            <span className="text-white text-base font-bold">
              {getInitials(franchise.name)}
            </span>
          </div>
          <div className="flex-1 min-w-0">
            <h3 className="text-base font-semibold text-slate-800 truncate">
              {franchise.name}
            </h3>
            <p className="text-sm text-slate-500 mt-0.5">
              {franchise.country ?? <span className="text-slate-400">—</span>}
            </p>
          </div>
          <span
            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
              isActive
                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'
                : 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
            }`}
          >
            {isActive ? t('franchises.active') : t('franchises.inactive')}
          </span>
        </div>
      </div>

      {/* Datos de contacto */}
      <div className="px-5 pb-3">
        {franchise.email && (
          <p className="text-sm text-slate-500 flex items-center gap-1.5">
            <svg className="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
            </svg>
            {franchise.email}
          </p>
        )}
      </div>

      {/* Contadores de Admins y Clients */}
      <div className="px-5 pb-3 flex gap-4">
        <div className="flex items-center gap-1.5 text-sm text-slate-500">
          <svg className="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
          </svg>
          <span className="font-medium text-slate-700">{franchise.admins_count ?? 0}</span>
          <span>{t('franchises.admins')}</span>
        </div>
        <div className="flex items-center gap-1.5 text-sm text-slate-500">
          <svg className="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 0h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
          </svg>
          <span className="font-medium text-slate-700">{franchise.clients_count ?? 0}</span>
          <span>{t('franchises.clients')}</span>
        </div>
      </div>

      {/* Botones de acción */}
      {isSuperadmin && (
        <div className="px-5 pb-4 flex items-center gap-2 border-t border-slate-100 pt-3">
          <button
            onClick={() => onEdit(franchise)}
            className="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors"
          >
            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
            </svg>
            {t('common.edit')}
          </button>
          <button
            onClick={() => onToggleStatus(franchise)}
            className={`flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors ${
              isActive
                ? 'text-amber-700 bg-amber-50 hover:bg-amber-100'
                : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100'
            }`}
          >
            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              {isActive ? (
                <path strokeLinecap="round" strokeLinejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
              ) : (
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              )}
            </svg>
            {isActive ? t('franchises.deactivate') : t('franchises.activate')}
          </button>
          <button
            onClick={() => onDelete(franchise)}
            className="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 transition-colors"
          >
            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
            </svg>
            {t('common.delete')}
          </button>
        </div>
      )}
    </div>
  );
}

// ─── Empty state ──────────────────────────────────────────────────────────────

function EmptyState({ onAdd, isSuperadmin }) {
  const { t } = useTranslation('common');
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center">
      <div className="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-4">
        <svg className="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
        </svg>
      </div>
      <p className="text-sm font-semibold text-slate-700">{t('franchises.empty_title')}</p>
      <p className="mt-1 text-sm text-slate-400">
        {isSuperadmin ? t('franchises.empty_admin') : t('franchises.empty_user')}
      </p>
      {isSuperadmin && (
        <button
          onClick={onAdd}
          className="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          {t('franchises.new')}
        </button>
      )}
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function FranchisesPage() {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const role = useAuthStore((s) => s.role);
  const isSuperadmin = role === 'superadmin';

  const [franchises, setFranchises] = useState([]);
  const [franchisesTotal, setFranchisesTotal] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [fetchError, setFetchError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');

  // Modal state
  const [modalFranchise, setModalFranchise] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);

  // ── Fetch ──────────────────────────────────────────────────────────────────

  const loadFranchises = useCallback(async () => {
    setIsLoading(true);
    setFetchError('');
    try {
      const { data, meta } = await franchisesApi.getFranchises();
      setFranchises(Array.isArray(data) ? data : []);
      setFranchisesTotal(meta?.total ?? null);
    } catch (error) {
      setFetchError(
        error?.response?.data?.message ?? t('franchises.load_error')
      );
    } finally {
      setIsLoading(false);
    }
  }, [t]);

  useEffect(() => {
    loadFranchises();
  }, [loadFranchises]);

  // ── Filtrado por búsqueda ─────────────────────────────────────────────────

  const filteredFranchises = searchTerm.trim()
    ? franchises.filter((f) =>
        f.name.toLowerCase().includes(searchTerm.toLowerCase())
      )
    : franchises;

  // ── Modal helpers ──────────────────────────────────────────────────────────

  function openCreateModal() {
    setModalFranchise(null);
    setIsModalOpen(true);
  }

  function openEditModal(franchise) {
    setModalFranchise(franchise);
    setIsModalOpen(true);
  }

  function closeModal() {
    setIsModalOpen(false);
    setModalFranchise(null);
  }

  async function handleSave(payload, id) {
    if (id !== undefined) {
      await franchisesApi.updateFranchise(id, payload);
    } else {
      await franchisesApi.createFranchise(payload);
    }
    closeModal();
    await loadFranchises();
  }

  // ── Toggle status ──────────────────────────────────────────────────────────

  async function handleToggleStatus(franchise) {
    const action = franchise.is_active !== false ? 'deactivate' : 'activate';
    const confirmed = window.confirm(
      t(`franchises.${action}_confirm`, { name: franchise.name })
    );
    if (!confirmed) return;

    try {
      await franchisesApi.toggleFranchiseStatus(franchise.id);
      await loadFranchises();
    } catch (error) {
      const message =
        error?.response?.data?.message ?? t('franchises.toggle_error');
      window.alert(message);
    }
  }

  // ── Delete ─────────────────────────────────────────────────────────────────

  async function handleDelete(franchise) {
    const confirmed = window.confirm(
      t('franchises.delete_confirm', { name: franchise.name })
    );
    if (!confirmed) return;

    try {
      await franchisesApi.deleteFranchise(franchise.id);
      await loadFranchises();
    } catch (error) {
      const message =
        error?.response?.data?.message ?? t('franchises.delete_error');
      window.alert(message);
    }
  }

  // ── Render ─────────────────────────────────────────────────────────────────

  const totalCount = franchisesTotal ?? franchises.length;
  const filteredCount = filteredFranchises.length;
  const isFiltered = searchTerm.trim().length > 0;
  const displayCount = isFiltered ? filteredCount : totalCount;
  const countLabel = displayCount === 1 ? t('franchises.count_one') : t('franchises.count_other');

  return (
    <>
      <div className="space-y-5">
        {/* Page header */}
        <div className="flex items-center justify-between">
          <div>
            <button
              onClick={() => navigate(-1)}
              className="text-sm text-slate-500 hover:text-slate-700 inline-flex items-center gap-1 mb-1"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
              </svg>
              {t('common.back')}
            </button>
            <h1 className="text-2xl font-semibold text-slate-800">{t('franchises.title')}</h1>
            <p className="mt-0.5 text-sm text-slate-500">
              {displayCount} {t('franchises.registered', { count: displayCount })}
              {isFiltered && (
                <span className="text-slate-400">
                  {' '}— {t('franchises.results', { count: filteredCount })}
                </span>
              )}
            </p>
          </div>
          {isSuperadmin && (
            <button
              onClick={openCreateModal}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              {t('franchises.new')}
            </button>
          )}
        </div>

        {/* Barra de búsqueda */}
        <div className="relative">
          <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
          </svg>
          <input
            type="text"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            placeholder={t('franchises.search_placeholder')}
            className="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-300 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
          />
          {searchTerm && (
            <button
              onClick={() => setSearchTerm('')}
              className="absolute right-3 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          )}
        </div>

        {/* Loading */}
        {isLoading && (
          <div className="flex items-center justify-center py-20 gap-3">
            <svg className="w-6 h-6 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p className="text-sm text-slate-500">{t('franchises.loading')}</p>
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
                onClick={loadFranchises}
                className="mt-1 text-xs text-red-600 underline hover:text-red-800"
              >
                {t('common.try_again')}
              </button>
            </div>
          </div>
        )}

        {/* Empty state */}
        {!isLoading && !fetchError && filteredFranchises.length === 0 && !searchTerm && (
          <EmptyState onAdd={openCreateModal} isSuperadmin={isSuperadmin} />
        )}

        {/* No search results */}
        {!isLoading && !fetchError && filteredFranchises.length === 0 && searchTerm && (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <svg className="w-12 h-12 text-slate-300 mb-3" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <p className="text-sm text-slate-500">{t('franchises.no_results')}</p>
          </div>
        )}

        {/* Card Grid */}
        {!isLoading && !fetchError && filteredFranchises.length > 0 && (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {filteredFranchises.map((franchise) => (
              <FranchiseCard
                key={franchise.id}
                franchise={franchise}
                onEdit={openEditModal}
                onToggleStatus={handleToggleStatus}
                onDelete={handleDelete}
                isSuperadmin={isSuperadmin}
              />
            ))}
          </div>
        )}
      </div>

      {/* Modal */}
      {isModalOpen && (
        <FranchiseFormModal
          franchise={modalFranchise}
          onClose={closeModal}
          onSave={handleSave}
        />
      )}
    </>
  );
}