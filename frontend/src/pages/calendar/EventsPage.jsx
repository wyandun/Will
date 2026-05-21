import { useEffect, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { eventsApi } from '../../api/events';
import { useAuthStore } from '../../store/authStore';
import EventFormModal from './EventFormModal';
import CalendarListView from './CalendarListView';
import CalendarMonthView from './CalendarMonthView';
import CalendarWeekView from './CalendarWeekView';

// ─── Date helpers ─────────────────────────────────────────────────────────────

/** Returns { start: Date, end: Date } for the visible range of a given view. */
function getViewDateRange(view, date) {
  if (view === 'month') {
    const start = new Date(date.getFullYear(), date.getMonth(), 1);
    // Include padding days (up to 6 weeks visible)
    start.setDate(start.getDate() - start.getDay());
    const end = new Date(date.getFullYear(), date.getMonth() + 1, 0);
    end.setDate(end.getDate() + (6 - end.getDay()));
    return { start, end };
  }
  if (view === 'week') {
    const start = new Date(date);
    start.setDate(start.getDate() - start.getDay()); // Sunday
    start.setHours(0, 0, 0, 0);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    end.setHours(23, 59, 59, 999);
    return { start, end };
  }
  return { start: null, end: null };
}

function formatPeriodLabel(view, date, locale) {
  if (view === 'month') {
    return date.toLocaleDateString(locale, { month: 'long', year: 'numeric' });
  }
  if (view === 'week') {
    const start = new Date(date);
    start.setDate(start.getDate() - start.getDay());
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    const startStr = start.toLocaleDateString(locale, { month: 'short', day: 'numeric' });
    const endStr = end.toLocaleDateString(locale, { month: 'short', day: 'numeric', year: 'numeric' });
    return `${startStr} – ${endStr}`;
  }
  return '';
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function EventsPage() {
  const { t, i18n } = useTranslation('common');
  const user = useAuthStore((s) => s.user);
  const role = useAuthStore((s) => s.role);

  const [view, setView] = useState('month');
  const [currentDate, setCurrentDate] = useState(new Date());

  const [events, setEvents] = useState([]);
  const [meta, setMeta] = useState(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [showModal, setShowModal] = useState(false);
  const [editingEvent, setEditingEvent] = useState(null);
  const [initialDate, setInitialDate] = useState(null);
  const [deleteConfirmId, setDeleteConfirmId] = useState(null);

  const fetchEvents = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      if (view === 'list') {
        const result = await eventsApi.getEvents({ page, per_page: 10, search: search || undefined });
        setEvents(result.data);
        setMeta(result.meta);
      } else {
        const { start, end } = getViewDateRange(view, currentDate);
        const result = await eventsApi.getEvents({
          start_from: start.toISOString(),
          end_before: end.toISOString(),
          per_page: 200,
        });
        setEvents(result.data);
        setMeta(null);
      }
    } catch {
      setError(t('calendar.load_error'));
    } finally {
      setLoading(false);
    }
  }, [view, currentDate, page, search, t]);

  useEffect(() => {
    fetchEvents();
  }, [fetchEvents]);

  // Reset page when switching to list view
  function handleViewChange(newView) {
    setView(newView);
    setPage(1);
  }

  function navigate(direction) {
    const d = new Date(currentDate);
    if (view === 'month') d.setMonth(d.getMonth() + direction);
    if (view === 'week') d.setDate(d.getDate() + direction * 7);
    setCurrentDate(d);
  }

  function goToday() {
    setCurrentDate(new Date());
  }

  function handleSearch(e) {
    setSearch(e.target.value);
    setPage(1);
  }

  function openCreate() {
    setInitialDate(null);
    setEditingEvent(null);
    setShowModal(true);
  }

  function openCreateWithDate(date) {
    setInitialDate(date);
    setEditingEvent(null);
    setShowModal(true);
  }

  function openEdit(event) {
    setEditingEvent(event);
    setInitialDate(null);
    setShowModal(true);
  }

  function closeModal() {
    setShowModal(false);
    setEditingEvent(null);
    setInitialDate(null);
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

  const periodLabel = formatPeriodLabel(view, currentDate, i18n.language);

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">{t('calendar.title')}</h1>
          {view === 'list' && meta && (
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

      {/* Toolbar: navigation + view selector */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-5">
        {/* Navigation (hidden in list view) */}
        {view !== 'list' ? (
          <div className="flex items-center gap-2">
            <button
              onClick={() => navigate(-1)}
              className="p-2 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
              aria-label="Previous"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <button
              onClick={goToday}
              className="px-3 py-1.5 rounded-lg text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition-colors"
            >
              {t('calendar.today')}
            </button>
            <button
              onClick={() => navigate(1)}
              className="p-2 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
              aria-label="Next"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </button>
            <span className="text-sm font-semibold text-slate-800 ml-1 capitalize">
              {periodLabel}
            </span>
          </div>
        ) : (
          <div /> /* spacer */
        )}

        {/* View selector */}
        <div className="inline-flex rounded-lg border border-slate-200 overflow-hidden bg-white shadow-sm">
          {['month', 'week', 'list'].map((v) => (
            <button
              key={v}
              onClick={() => handleViewChange(v)}
              className={`px-4 py-2 text-sm font-medium transition-colors border-r border-slate-200 last:border-r-0
                ${view === v
                  ? 'bg-blue-600 text-white'
                  : 'text-slate-600 hover:bg-slate-50'}`}
            >
              {t(`calendar.view_${v}`)}
            </button>
          ))}
        </div>
      </div>

      {/* Error */}
      {error && (
        <div className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3">
          <p className="text-sm text-red-700">{error}</p>
        </div>
      )}

      {/* Views */}
      {view === 'list' && (
        <CalendarListView
          events={events}
          meta={meta}
          page={page}
          onPageChange={setPage}
          search={search}
          onSearchChange={handleSearch}
          onEdit={openEdit}
          onDelete={handleDelete}
          onDeleteConfirm={setDeleteConfirmId}
          deleteConfirmId={deleteConfirmId}
          loading={loading}
          canManage={canManage}
        />
      )}

      {view === 'month' && (
        <>
          {loading && (
            <div className="flex items-center justify-center py-12">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
            </div>
          )}
          {!loading && (
            <CalendarMonthView
              year={currentDate.getFullYear()}
              month={currentDate.getMonth()}
              events={events}
              onEdit={openEdit}
              onCreateWithDate={openCreateWithDate}
            />
          )}
        </>
      )}

      {view === 'week' && (
        <>
          {loading && (
            <div className="flex items-center justify-center py-12">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
            </div>
          )}
          {!loading && (
            <CalendarWeekView
              currentDate={currentDate}
              events={events}
              onEdit={openEdit}
              onCreateWithDate={openCreateWithDate}
            />
          )}
        </>
      )}

      {/* Modal */}
      {showModal && (
        <EventFormModal
          event={editingEvent}
          initialDate={initialDate}
          onClose={closeModal}
          onSaved={handleSaved}
        />
      )}
    </div>
  );
}
