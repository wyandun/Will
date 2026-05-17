import PropTypes from 'prop-types';
import { useEffect, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { eventsApi } from '../../api/events';
import { useAuthStore } from '../../store/authStore';
import EventFormModal from './EventFormModal';

// ─── Icons ───────────────────────────────────────────────────────────────────

const iconPropTypes = { className: PropTypes.string };

function IconSearch({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
    </svg>
  );
}

function IconEdit({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
    </svg>
  );
}

function IconTrash({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m-7 0a2 2 0 012-2h4a2 2 0 012 2M4 7h16" />
    </svg>
  );
}

function IconClock({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
    </svg>
  );
}

function IconLocation({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
      <path strokeLinecap="round" strokeLinejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
  );
}

IconSearch.propTypes = iconPropTypes;
IconEdit.propTypes = iconPropTypes;
IconTrash.propTypes = iconPropTypes;
IconClock.propTypes = iconPropTypes;
IconLocation.propTypes = iconPropTypes;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatEventDate(isoString, allDay) {
  if (!isoString) return '';
  const d = new Date(isoString);
  if (allDay) {
    return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
  }
  return d.toLocaleString(undefined, {
    weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

export default function EventsPage() {
  const { t } = useTranslation('common');
  const user = useAuthStore((s) => s.user);
  const role = useAuthStore((s) => s.role);

  const [events, setEvents] = useState([]);
  const [meta, setMeta] = useState(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [showModal, setShowModal] = useState(false);
  const [editingEvent, setEditingEvent] = useState(null);
  const [deleteConfirmId, setDeleteConfirmId] = useState(null);

  const fetchEvents = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const result = await eventsApi.getEvents({ page, per_page: 10, search: search || undefined });
      setEvents(result.data);
      setMeta(result.meta);
    } catch {
      setError(t('calendar.load_error'));
    } finally {
      setLoading(false);
    }
  }, [page, search, t]);

  useEffect(() => {
    fetchEvents();
  }, [fetchEvents]);

  function handleSearch(e) {
    setSearch(e.target.value);
    setPage(1);
  }

  function openCreate() {
    setEditingEvent(null);
    setShowModal(true);
  }

  function openEdit(event) {
    setEditingEvent(event);
    setShowModal(true);
  }

  function closeModal() {
    setShowModal(false);
    setEditingEvent(null);
  }

  function handleSaved() {
    closeModal();
    fetchEvents();
  }

  async function handleDelete(id) {
    try {
      await eventsApi.deleteEvent(id);
      setDeleteConfirmId(null);
      fetchEvents();
    } catch {
      setError(t('calendar.load_error'));
    }
  }

  const canManage = (event) =>
    user?.id === event.created_by?.id || role === 'superadmin';

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">{t('calendar.title')}</h1>
          {meta && (
            <p className="text-sm text-slate-500 mt-1">
              {t('calendar.events_total', { count: meta.total })}
            </p>
          )}
        </div>
        <button
          onClick={openCreate}
          className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {t('calendar.new_event')}
        </button>
      </div>

      {/* Search */}
      <div className="relative mb-6">
        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
          <IconSearch />
        </div>
        <input
          type="text"
          value={search}
          onChange={handleSearch}
          placeholder={t('calendar.search_placeholder')}
          className="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-300 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
        />
      </div>

      {/* Error */}
      {error && (
        <div className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3">
          <p className="text-sm text-red-700">{error}</p>
        </div>
      )}

      {/* Loading */}
      {loading && (
        <div className="flex items-center justify-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
        </div>
      )}

      {/* Empty state */}
      {!loading && events.length === 0 && (
        <div className="text-center py-12">
          <svg className="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
          </svg>
          <p className="mt-4 text-sm text-slate-500">
            {search ? t('calendar.no_events_search') : t('calendar.no_events')}
          </p>
        </div>
      )}

      {/* Event cards */}
      {!loading && events.length > 0 && (
        <div className="space-y-3">
          {events.map((event) => (
            <div
              key={event.id}
              className="bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow overflow-hidden"
            >
              <div className="flex">
                {/* Color bar */}
                <div className="w-1.5 flex-shrink-0" style={{ backgroundColor: event.color }} />

                <div className="flex-1 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex-1 min-w-0">
                      <h3 className="text-sm font-semibold text-slate-900 truncate">{event.title}</h3>

                      {event.description && (
                        <p className="text-sm text-slate-500 mt-1 line-clamp-2">{event.description}</p>
                      )}

                      <div className="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-xs text-slate-500">
                        <span className="inline-flex items-center gap-1">
                          <IconClock className="w-3.5 h-3.5" />
                          {event.all_day
                            ? t('calendar.all_day')
                            : formatEventDate(event.start_at, false)}
                          {!event.all_day && event.end_at && (
                            <> — {new Date(event.end_at).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}</>
                          )}
                        </span>

                        {event.all_day && (
                          <span className="text-xs text-slate-500">
                            {formatEventDate(event.start_at, true)}
                            {event.end_at && event.start_at !== event.end_at && (
                              <> — {formatEventDate(event.end_at, true)}</>
                            )}
                          </span>
                        )}

                        {event.location && (
                          <span className="inline-flex items-center gap-1">
                            <IconLocation className="w-3.5 h-3.5" />
                            {event.location}
                          </span>
                        )}
                      </div>

                      {event.created_by && (
                        <p className="text-xs text-slate-400 mt-1.5">
                          {event.created_by.name}
                        </p>
                      )}
                    </div>

                    {/* Actions */}
                    {canManage(event) && (
                      <div className="flex items-center gap-1 flex-shrink-0">
                        <button
                          onClick={() => openEdit(event)}
                          className="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                          title={t('common.edit')}
                        >
                          <IconEdit />
                        </button>
                        <button
                          onClick={() => setDeleteConfirmId(event.id)}
                          className="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                          title={t('common.delete')}
                        >
                          <IconTrash />
                        </button>
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Delete confirmation inline */}
              {deleteConfirmId === event.id && (
                <div className="px-4 pb-3 flex items-center gap-3 border-t border-slate-100 pt-3">
                  <p className="text-xs text-red-600 flex-1">{t('calendar.delete_confirm')}</p>
                  <button
                    onClick={() => setDeleteConfirmId(null)}
                    className="px-3 py-1 rounded text-xs font-medium text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 transition-colors"
                  >
                    {t('common.cancel')}
                  </button>
                  <button
                    onClick={() => handleDelete(event.id)}
                    className="px-3 py-1 rounded text-xs font-medium text-white bg-red-600 hover:bg-red-700 transition-colors"
                  >
                    {t('common.delete')}
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Pagination */}
      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between mt-6">
          <p className="text-sm text-slate-500">
            {t('calendar.page_info', { current: meta.current_page, total: meta.last_page })}
          </p>
          <div className="flex gap-2">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page <= 1}
              className="px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {t('calendar.prev')}
            </button>
            <button
              onClick={() => setPage((p) => p + 1)}
              disabled={page >= meta.last_page}
              className="px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {t('calendar.next')}
            </button>
          </div>
        </div>
      )}

      {/* Modal */}
      {showModal && (
        <EventFormModal
          event={editingEvent}
          onClose={closeModal}
          onSaved={handleSaved}
        />
      )}
    </div>
  );
}
