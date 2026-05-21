import PropTypes from 'prop-types';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';

export default function SearchResultsPanel({ query, results, loading, onSelectEvent, onClose }) {
  const { t, i18n } = useTranslation('common');
  const locale = i18n.language;

  // Close on Escape
  useEffect(() => {
    function handleKeyDown(e) {
      if (e.key === 'Escape') onClose();
    }
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [onClose]);

  return (
    <>
      {/* Backdrop */}
      <div className="fixed inset-0 z-20" onClick={onClose} />

      {/* Panel */}
      <div
        role="listbox"
        aria-label={t('calendar.search_results_title', { query })}
        className="absolute top-full left-0 right-0 mt-1 z-30 bg-white rounded-xl shadow-xl border border-slate-200 flex flex-col overflow-hidden"
        style={{ minWidth: '320px' }}
      >
        {/* Header */}
        <div className="flex items-center justify-between px-4 py-2.5 border-b border-slate-100 bg-slate-50">
          <p className="text-xs font-semibold text-slate-600">
            {t('calendar.search_results_title', { query })}
          </p>
          <button
            onClick={onClose}
            className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
            aria-label={t('calendar.clear_search')}
          >
            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Body */}
        <div className="max-h-[360px] overflow-y-auto">
          {loading && (
            <div className="flex items-center justify-center py-8">
              <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600" />
            </div>
          )}

          {!loading && results.length === 0 && (
            <p className="text-sm text-slate-400 py-6 text-center">
              {t('calendar.search_no_results', { query })}
            </p>
          )}

          {!loading && results.length > 0 && (
            <ul className="flex flex-col">
              {results.map((event) => {
                const eventDate = new Date(event.start_at);
                const dateStr = eventDate.toLocaleDateString(locale, {
                  weekday: 'short',
                  month: 'short',
                  day: 'numeric',
                  year: 'numeric',
                });
                const timeStr = event.all_day
                  ? t('calendar.all_day')
                  : eventDate.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });

                return (
                  <li key={event.id}>
                    <button
                      role="option"
                      onClick={() => onSelectEvent(event)}
                      className="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-slate-50 transition-colors border-b border-slate-50 last:border-b-0"
                    >
                      <div
                        className="w-1 self-stretch rounded-full flex-shrink-0"
                        style={{ backgroundColor: event.color ?? '#3B82F6' }}
                      />
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium text-slate-700 truncate">{event.title}</p>
                        <p className="text-xs text-slate-400">
                          {dateStr} &middot; {timeStr}
                        </p>
                        {event.location && (
                          <p className="text-xs text-slate-400 truncate">{event.location}</p>
                        )}
                      </div>
                      <svg className="w-4 h-4 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                      </svg>
                    </button>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      </div>
    </>
  );
}

SearchResultsPanel.propTypes = {
  query: PropTypes.string.isRequired,
  results: PropTypes.array.isRequired,
  loading: PropTypes.bool.isRequired,
  onSelectEvent: PropTypes.func.isRequired,
  onClose: PropTypes.func.isRequired,
};
