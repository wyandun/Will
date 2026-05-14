import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { newsApi } from '../../api/news';
import { timeAgo } from '../../utils/time';

// ─── Icons ─────────────────────────────────────────────────────────────────────

function IconX({ className = 'w-5 h-5' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
    </svg>
  );
}

function IconRefresh({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
    </svg>
  );
}

function IconExternalLink({ className = 'w-3.5 h-3.5' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
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

function IconBan({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
    </svg>
  );
}

// ─── Skeleton ──────────────────────────────────────────────────────────────────

function Skeleton({ className }) {
  return <div className={`animate-pulse bg-slate-200 rounded-xl ${className}`} />;
}

// ─── ArticleCard ───────────────────────────────────────────────────────────────

function ArticleCard({ article, onPublish, onReject }) {
  const { t } = useTranslation('common');
  const [busy, setBusy] = useState(false);

  const isPublished = article.status === 'published';

  function handlePublish() {
    if (busy || isPublished) return;
    setBusy(true);
    onPublish(article.id).finally(() => setBusy(false));
  }

  function handleReject() {
    if (busy) return;
    setBusy(true);
    onReject(article.id).finally(() => setBusy(false));
  }

  return (
    <div className="flex flex-col gap-3 p-4 bg-white rounded-2xl border border-slate-100 hover:border-slate-200 transition-all">
      {/* Source + date */}
      <div className="flex items-center gap-2">
        <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700">
          {article.source}
        </span>
        {isPublished && (
          <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">
            {t('news.published')}
          </span>
        )}
        <span className="ml-auto text-xs text-slate-400">
          {article.published_at ? timeAgo(article.published_at) : timeAgo(article.fetched_at)}
        </span>
      </div>

      {/* Title */}
      <a
        href={article.article_url}
        target="_blank"
        rel="noopener noreferrer"
        className="flex items-start gap-1.5 group"
      >
        <p className="text-sm font-semibold text-slate-800 group-hover:text-blue-600 transition-colors leading-snug">
          {article.title}
        </p>
        <IconExternalLink className="w-3.5 h-3.5 flex-shrink-0 mt-0.5 text-slate-400 group-hover:text-blue-500 transition-colors" />
      </a>

      {/* AI Summary */}
      {article.ai_summary && (
        <div className="bg-amber-50 border border-amber-100 rounded-xl px-3 py-2.5">
          <p className="text-[11px] font-semibold text-amber-700 mb-1">{t('news.ai_summary')}</p>
          <p className="text-xs text-slate-600 leading-relaxed">{article.ai_summary}</p>
        </div>
      )}

      {/* Keywords */}
      {article.keywords_matched?.length > 0 && (
        <div className="flex flex-wrap gap-1">
          {article.keywords_matched.slice(0, 5).map((kw) => (
            <span key={kw} className="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500">
              {kw}
            </span>
          ))}
        </div>
      )}

      {/* Actions */}
      {!isPublished && (
        <div className="flex items-center gap-2 pt-1 border-t border-slate-50">
          <button
            type="button"
            onClick={handlePublish}
            disabled={busy}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <IconCheck />
            {t('news.publish_to_feed')}
          </button>
          <button
            type="button"
            onClick={handleReject}
            disabled={busy}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-red-50 hover:text-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <IconBan />
            {t('news.reject')}
          </button>
        </div>
      )}
    </div>
  );
}

// ─── NewsModal ─────────────────────────────────────────────────────────────────

export default function NewsModal({ onClose }) {
  const { t } = useTranslation('common');
  const [articles, setArticles] = useState([]);
  const [meta, setMeta] = useState(null);
  const [lastFetchAt, setLastFetchAt] = useState(null);
  const [loading, setLoading] = useState(true);
  const [fetching, setFetching] = useState(false);
  const [toast, setToast] = useState('');
  const backdropRef = useRef(null);

  const loadArticles = (page = 1) => {
    setLoading(true);
    newsApi.getArticles(page)
      .then((res) => {
        const data = res.data.data ?? {};
        setArticles(data.items ?? []);
        setMeta(data.meta ?? null);
        setLastFetchAt(data.last_fetch_at ?? null);
      })
      .catch(() => setToast(t('common.unexpected_error')))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadArticles(1);
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // Reload articles after fetch job is queued (poll once after 8s)
  useEffect(() => {
    if (!fetching) return;
    const timer = setTimeout(() => {
      loadArticles(1);
      setFetching(false);
    }, 8000);
    return () => clearTimeout(timer);
  }, [fetching]); // eslint-disable-line react-hooks/exhaustive-deps

  function handleFetch() {
    if (fetching) return;
    setFetching(true);
    newsApi.fetchNews()
      .then((res) => {
        const msg = res.data?.message ?? t('news.fetch_queued');
        setToast(msg);
        if (!res.data?.data?.queued) {
          // Already cached — just reload immediately
          setFetching(false);
          loadArticles(1);
        }
      })
      .catch(() => {
        setFetching(false);
        setToast(t('common.unexpected_error'));
      });
  }

  function handlePublish(id) {
    return newsApi.publishArticle(id)
      .then(() => {
        setToast(t('news.published_success'));
        setArticles((prev) => prev.map((a) => a.id === id ? { ...a, status: 'published' } : a));
      })
      .catch(() => setToast(t('common.unexpected_error')));
  }

  function handleReject(id) {
    return newsApi.rejectArticle(id)
      .then(() => {
        setToast(t('news.rejected_success'));
        setArticles((prev) => prev.filter((a) => a.id !== id));
      })
      .catch(() => setToast(t('common.unexpected_error')));
  }

  function handleBackdrop(e) {
    if (e.target === backdropRef.current) onClose();
  }

  // Dismiss toast automatically
  useEffect(() => {
    if (!toast) return;
    const timer = setTimeout(() => setToast(''), 3500);
    return () => clearTimeout(timer);
  }, [toast]);

  return (
    <div
      ref={backdropRef}
      onClick={handleBackdrop}
      className="fixed inset-0 z-50 bg-black/40 flex items-start justify-end"
    >
      <div className="h-full w-full max-w-xl bg-slate-50 shadow-2xl flex flex-col overflow-hidden">
        {/* Header */}
        <div className="flex items-center gap-3 px-5 py-4 bg-white border-b border-slate-100 flex-shrink-0">
          <div className="flex-1 min-w-0">
            <h2 className="text-base font-bold text-slate-800">{t('news.modal_title')}</h2>
            {lastFetchAt && (
              <p className="text-xs text-slate-400 mt-0.5">
                {t('news.last_updated')}: {timeAgo(lastFetchAt)}
              </p>
            )}
          </div>

          <button
            type="button"
            onClick={handleFetch}
            disabled={fetching}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-amber-700 bg-amber-100 hover:bg-amber-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex-shrink-0"
          >
            <IconRefresh className={`w-3.5 h-3.5 ${fetching ? 'animate-spin' : ''}`} />
            {fetching ? t('news.fetching') : t('news.fetch_btn')}
          </button>

          <button
            type="button"
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors flex-shrink-0"
            aria-label="Close"
          >
            <IconX />
          </button>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-4 flex flex-col gap-3">
          {loading ? (
            <>
              {[1, 2, 3].map((i) => <Skeleton key={i} className="h-40" />)}
            </>
          ) : articles.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-20 gap-3 text-center">
              <p className="text-slate-500 text-sm font-medium">{t('news.no_articles')}</p>
              <p className="text-slate-400 text-xs max-w-xs">{t('news.no_articles_hint')}</p>
              <button
                type="button"
                onClick={handleFetch}
                disabled={fetching}
                className="mt-2 flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 transition-colors"
              >
                <IconRefresh className={`w-4 h-4 ${fetching ? 'animate-spin' : ''}`} />
                {t('news.fetch_btn')}
              </button>
            </div>
          ) : (
            <>
              {articles.map((article) => (
                <ArticleCard
                  key={article.id}
                  article={article}
                  onPublish={handlePublish}
                  onReject={handleReject}
                />
              ))}

              {/* Pagination */}
              {meta && meta.last_page > 1 && (
                <div className="flex items-center justify-between pt-2">
                  <button
                    type="button"
                    onClick={() => loadArticles(meta.current_page - 1)}
                    disabled={meta.current_page <= 1 || loading}
                    className="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-600 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                  >
                    ‹ {t('feed.prev')}
                  </button>
                  <span className="text-xs text-slate-400">
                    {t('feed.page_info', { current: meta.current_page, total: meta.last_page })}
                  </span>
                  <button
                    type="button"
                    onClick={() => loadArticles(meta.current_page + 1)}
                    disabled={meta.current_page >= meta.last_page || loading}
                    className="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-600 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                  >
                    {t('feed.next')} ›
                  </button>
                </div>
              )}
            </>
          )}
        </div>

        {/* Toast */}
        {toast && (
          <div className="absolute bottom-5 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-xs px-4 py-2.5 rounded-xl shadow-lg z-10">
            {toast}
          </div>
        )}
      </div>
    </div>
  );
}

// ─── PropTypes ─────────────────────────────────────────────────────────────────

Skeleton.propTypes = { className: PropTypes.string };
IconX.propTypes = { className: PropTypes.string };
IconRefresh.propTypes = { className: PropTypes.string };
IconExternalLink.propTypes = { className: PropTypes.string };
IconCheck.propTypes = { className: PropTypes.string };
IconBan.propTypes = { className: PropTypes.string };

ArticleCard.propTypes = {
  article: PropTypes.shape({
    id: PropTypes.number.isRequired,
    source: PropTypes.string.isRequired,
    title: PropTypes.string.isRequired,
    article_url: PropTypes.string.isRequired,
    ai_summary: PropTypes.string,
    keywords_matched: PropTypes.arrayOf(PropTypes.string),
    status: PropTypes.string.isRequired,
    published_at: PropTypes.string,
    fetched_at: PropTypes.string.isRequired,
  }).isRequired,
  onPublish: PropTypes.func.isRequired,
  onReject: PropTypes.func.isRequired,
};

NewsModal.propTypes = {
  onClose: PropTypes.func.isRequired,
};
