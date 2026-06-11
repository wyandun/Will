import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { repositoriesApi } from '../../api/repositories';

// ─── Icons ────────────────────────────────────────────────────────────────────

function IconArrowLeft() {
  return (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
    </svg>
  );
}

function IconFolder() {
  return (
    <svg className="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776" />
    </svg>
  );
}

// ─── Tab bar ──────────────────────────────────────────────────────────────────

const TABS = ['tab_setup', 'tab_process_docs', 'tab_records'];

function ComingSoonTab() {
  const { t } = useTranslation('common');
  return (
    <div className="flex flex-col items-center justify-center py-16 text-center">
      <div className="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mb-4">
        <svg className="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
      </div>
      <p className="text-sm text-slate-400">{t('common.coming_soon')}</p>
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function RepositoryDetailPage() {
  const { t } = useTranslation('common');
  const { id } = useParams();
  const navigate = useNavigate();

  const [repository, setRepository] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [fetchError, setFetchError] = useState('');
  const [activeTab, setActiveTab] = useState('tab_setup');

  useEffect(() => {
    let cancelled = false;
    setIsLoading(true);
    setFetchError('');

    repositoriesApi
      .show(id)
      .then((data) => {
        if (!cancelled) setRepository(data);
      })
      .catch(() => {
        if (!cancelled) setFetchError(t('repository.load_error'));
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
      <div className="py-20 text-center">
        <p className="text-sm text-slate-400">{t('common.loading')}</p>
      </div>
    );
  }

  if (fetchError || !repository) {
    return (
      <div className="py-20 text-center space-y-4">
        <p className="text-sm text-red-600">{fetchError || t('repository.load_error')}</p>
        <button
          type="button"
          onClick={() => navigate('/repository')}
          className="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-800"
        >
          <IconArrowLeft />
          {t('repository.back')}
        </button>
      </div>
    );
  }

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
    <div className="space-y-0">
      {/* Dark header banner */}
      <div className="rounded-xl bg-slate-800 text-white px-6 py-5 mb-6">
        <div className="flex items-start justify-between gap-4">
          <div className="space-y-1">
            {franchiseName && (
              <p className="text-xs font-medium text-slate-400 uppercase tracking-wider">
                {franchiseName}
              </p>
            )}
            <div className="flex items-center gap-2">
              <IconFolder />
              <h1 className="text-lg font-bold text-white">{companyName}</h1>
            </div>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1">
              <span className="text-sm text-slate-300">
                {t('repository.docs_count', { count: docsCount })}
              </span>
              <span className="text-xs text-slate-500">{createdAt}</span>
            </div>
          </div>

          <button
            type="button"
            onClick={() => navigate('/repository')}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-300 border border-slate-600 hover:bg-slate-700 transition-colors shrink-0"
          >
            <IconArrowLeft />
            {t('repository.back')}
          </button>
        </div>
      </div>

      {/* Tab bar */}
      <div className="flex border-b border-slate-200 mb-6">
        {TABS.map((tab) => (
          <button
            key={tab}
            type="button"
            onClick={() => setActiveTab(tab)}
            className={[
              'px-4 py-2.5 text-sm font-medium border-b-2 transition-colors',
              activeTab === tab
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-slate-500 hover:text-slate-700',
            ].join(' ')}
          >
            {t(`repository.${tab}`)}
          </button>
        ))}
      </div>

      {/* Tab content — all stubs for now */}
      <ComingSoonTab />
    </div>
  );
}
