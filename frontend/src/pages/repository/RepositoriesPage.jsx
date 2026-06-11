import PropTypes from 'prop-types';
import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { repositoriesApi } from '../../api/repositories';
import NewRepositoryModal from './NewRepositoryModal';

// ─── Icons ────────────────────────────────────────────────────────────────────

function IconFolder({ className = 'w-10 h-10' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776" />
    </svg>
  );
}
IconFolder.propTypes = { className: PropTypes.string };

function IconPlus({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
    </svg>
  );
}
IconPlus.propTypes = { className: PropTypes.string };

function IconBuilding({ className = 'w-3.5 h-3.5' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" />
    </svg>
  );
}
IconBuilding.propTypes = { className: PropTypes.string };

// ─── Repository card ──────────────────────────────────────────────────────────

function RepositoryCard({ repository, onOpen }) {
  const { t } = useTranslation('common');

  const companyName = repository.company?.name ?? '—';
  const franchiseName = repository.franchise?.name ?? null;
  const docsCount = repository.documents_count ?? 0;

  const createdAt = repository.created_at
    ? new Date(repository.created_at).toLocaleDateString(undefined, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      })
    : '—';

  return (
    <div className="group relative bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow flex flex-col p-5">
      {/* Docs badge */}
      <div className="absolute top-4 right-4">
        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500">
          {t('repository.docs_count', { count: docsCount })}
        </span>
      </div>

      {/* Folder icon */}
      <div className="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center mb-4">
        <IconFolder className="w-7 h-7 text-amber-500" />
      </div>

      {/* Company name */}
      <p className="font-semibold text-slate-800 text-sm leading-snug pr-14">{companyName}</p>

      {/* Franchise */}
      {franchiseName && (
        <div className="flex items-center gap-1 mt-1.5">
          <IconBuilding className="w-3.5 h-3.5 text-slate-400 shrink-0" />
          <span className="text-xs text-slate-500 truncate">{franchiseName}</span>
        </div>
      )}

      {/* Created date */}
      <p className="text-xs text-slate-400 mt-2">{createdAt}</p>

      {/* Open button */}
      <div className="mt-4 pt-4 border-t border-slate-100 flex justify-end">
        <button
          type="button"
          onClick={() => onOpen(repository.id)}
          className="text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors"
        >
          {t('repository.open')}
        </button>
      </div>
    </div>
  );
}

RepositoryCard.propTypes = {
  repository: PropTypes.object.isRequired,
  onOpen: PropTypes.func.isRequired,
};

// ─── Empty state ──────────────────────────────────────────────────────────────

function EmptyState({ onAdd }) {
  const { t } = useTranslation('common');
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center">
      <div className="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-4">
        <IconFolder className="w-7 h-7 text-slate-400" />
      </div>
      <p className="text-sm font-semibold text-slate-700">{t('repository.empty_title')}</p>
      <p className="mt-1 text-sm text-slate-400">{t('repository.empty_subtitle')}</p>
      {onAdd && (
        <button
          type="button"
          onClick={onAdd}
          className="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
        >
          <IconPlus />
          {t('repository.new')}
        </button>
      )}
    </div>
  );
}

EmptyState.propTypes = {
  onAdd: PropTypes.func,
};

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function RepositoriesPage() {
  const { t } = useTranslation('common');
  const navigate = useNavigate();

  const [repositories, setRepositories] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [fetchError, setFetchError] = useState('');
  const [isModalOpen, setIsModalOpen] = useState(false);

  const loadRepositories = useCallback(() => {
    setIsLoading(true);
    setFetchError('');

    repositoriesApi
      .list()
      .then((data) => setRepositories(Array.isArray(data) ? data : []))
      .catch(() => setFetchError(t('repository.load_error')))
      .finally(() => setIsLoading(false));
  }, [t]);

  useEffect(() => {
    loadRepositories();
  }, [loadRepositories]);

  const handleCreated = (newRepo) => {
    setIsModalOpen(false);
    setRepositories((prev) => [newRepo, ...prev]);
  };

  const handleOpen = (id) => {
    navigate(`/repository/${id}`);
  };

  const count = repositories.length;
  const subtitle =
    count === 1
      ? `1 ${t('repository.count_one')}`
      : `${count} ${t('repository.count_other')}`;

  return (
    <div className="space-y-6">
      {/* Page header */}
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold text-slate-800">{t('repository.title')}</h1>
          {!isLoading && !fetchError && (
            <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>
          )}
        </div>

        <button
          type="button"
          onClick={() => setIsModalOpen(true)}
          className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
        >
          <IconPlus />
          {t('repository.new')}
        </button>
      </div>

      {/* Loading */}
      {isLoading && (
        <p className="text-sm text-slate-400">{t('common.loading')}</p>
      )}

      {/* Error */}
      {!isLoading && fetchError && (
        <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 flex items-center justify-between gap-4">
          <p className="text-sm text-red-600">{fetchError}</p>
          <button
            type="button"
            onClick={loadRepositories}
            className="text-sm font-medium text-red-600 hover:text-red-700 underline shrink-0"
          >
            {t('common.try_again')}
          </button>
        </div>
      )}

      {/* Empty */}
      {!isLoading && !fetchError && repositories.length === 0 && (
        <EmptyState onAdd={() => setIsModalOpen(true)} />
      )}

      {/* Grid */}
      {!isLoading && !fetchError && repositories.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {repositories.map((repo) => (
            <RepositoryCard key={repo.id} repository={repo} onOpen={handleOpen} />
          ))}
        </div>
      )}

      {/* New repository modal */}
      {isModalOpen && (
        <NewRepositoryModal
          onClose={() => setIsModalOpen(false)}
          onCreated={handleCreated}
        />
      )}
    </div>
  );
}
