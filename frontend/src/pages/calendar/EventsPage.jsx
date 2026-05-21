import { useEffect, useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { eventsApi } from '../../api/events';
import { useAuthStore } from '../../store/authStore';
import EventFormModal from './EventFormModal';
import CalendarListView from './CalendarListView';
import CalendarMonthView from './CalendarMonthView';
import CalendarWeekView from './CalendarWeekView';
import UpcomingEventsSidebar from '../../components/UpcomingEventsSidebar';
import SearchResultsPanel from './SearchResultsPanel';

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
  const [sidebarKey, setSidebarKey] = useState(0);

  const [searchResults, setSearchResults] = useState([]);
  const [searchLoading, setSearchLoading] = useState(false);
  const [showSearchPanel, setShowSearchPanel] = useState(false);

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

  // Global search (debounced) — fires a separate API call without date filters
  useEffect(() => {
    if (!search.trim() || view === 'list') {
      setShowSearchPanel(false);
      setSearchResults([]);
      return;
    }

    const timer = setTimeout(async () => {
      setSearchLoading(true);
      setShowSearchPanel(true);
      try {
        const result = await eventsApi.getEvents({ search, per_page: 20 });
        setSearchResults(result.data);
      } catch {
        setSearchResults([]);
      } finally {
        setSearchLoading(false);
      }
    }, 300);

    return () => clearTimeout(timer);
  }, [search, view]);

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

  function handleSearchResultSelect(event) {
    setCurrentDate(new Date(event.start_at));
    setSearch('');
    setShowSearchPanel(false);
    setSearchResults([]);
    openEdit(event);
  }

  function openCreate() {
    setInitialDate(new Date());
    setEditingEvent(null);
    setShowModal(true);
  }

  function openCreateWithDate(date) {
    setInitialDate(date);
    setEditingEvent(null);
    setShowModal(true);
  }

  async function openEdit(event) {
    setInitialDate(null);
    // Sidebar events only have id/title/start_at/end_at/all_day/color —
    // fetch the full event so the modal can show description, location, etc.
    if (event.description === undefined) {
      try {
        const full = await eventsApi.getEvent(event.id);
        setEditingEvent(full);
      } catch {
        setEditingEvent(event);
      }
    } else {
      setEditingEvent(event);
    }
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
    setSidebarKey((k) => k + 1);
  }

  async function handleDelete(id) {
    try {
      await eventsApi.deleteEvent(id);
      setDeleteConfirmId(null);
      closeModal();
      fetchEvents();
      setSidebarKey((k) => k + 1);
    } catch {
      setError(t('calendar.load_error'));
    }
  }

  const canManage = (event) =>
    user?.id === event.created_by?.id || role === 'superadmin';

  const periodLabel = formatPeriodLabel(view, currentDate, i18n.language);

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <div className="flex gap-6">
        {/* Main content — calendar */}
        <div className="flex-1 min-w-0">
          {/* Header — hidden in list view (title moves into toolbar) */}
          {view !== 'list' && (
            <div className="mb-6">
              <h1 className="text-2xl font-bold text-slate-900">{t('calendar.title')}</h1>
            </div>
          )}

          {/* Toolbar: navigation + search + view selector */}
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
              <div>
                <h1 className="text-2xl font-bold text-slate-900">{t('calendar.title')}</h1>
                {meta && (
                  <p className="text-sm text-slate-500 mt-0.5">
                    {t('calendar.events_total', { count: meta.total })}
                  </p>
                )}
              </div>
            )}

            {/* Search */}
            <div className="flex-1 max-w-xs relative">
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                  </svg>
                </div>
                <input
                  type="text"
                  value={search}
                  onChange={handleSearch}
                  placeholder={t('calendar.search_placeholder')}
                  className={`w-full pl-10 ${search ? 'pr-9' : 'pr-4'} py-2 rounded-lg border border-slate-300 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition`}
                />
                {search && (
                  <button
                    onClick={() => { setSearch(''); setShowSearchPanel(false); setSearchResults([]); }}
                    className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"
                    aria-label={t('calendar.clear_search')}
                  >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                )}
              </div>

              {/* Search results panel (month/week views only) */}
              {showSearchPanel && view !== 'list' && (
                <SearchResultsPanel
                  query={search}
                  results={searchResults}
                  loading={searchLoading}
                  onSelectEvent={handleSearchResultSelect}
                  onClose={() => { setSearch(''); setShowSearchPanel(false); setSearchResults([]); }}
                />
              )}
            </div>

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
        </div>

        {/* Right sidebar — upcoming events */}
        <aside className="w-72 flex-shrink-0 flex flex-col gap-4 sticky top-20 self-start">
          <UpcomingEventsSidebar hideFooter paginated refreshKey={sidebarKey} onEventClick={openEdit} onCreateClick={openCreate} />
        </aside>
      </div>

      {/* Modal */}
      {showModal && (
        <EventFormModal
          event={editingEvent}
          initialDate={initialDate}
          onClose={closeModal}
          onSaved={handleSaved}
          onDelete={handleDelete}
        />
      )}
    </div>
  );
}
