import PropTypes from 'prop-types';
import { useState, useEffect, useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { eventsApi } from '../../api/events';
import EventFormModal from './EventFormModal';

// ─── Type badge colours ───────────────────────────────────────────────────────
const TYPE_BADGE = {
  casual:   'bg-slate-100 text-slate-600',
  meeting:  'bg-blue-100 text-blue-700',
  deadline: 'bg-red-100 text-red-700',
  reminder: 'bg-amber-100 text-amber-700',
  training: 'bg-purple-100 text-purple-700',
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Parse an ISO string as local time by stripping the timezone offset.
 * The backend stores what the user typed without UTC conversion, so stripping
 * the offset avoids off-by-one-day shifts in timezones behind UTC.
 */
function parseLocal(isoString) {
  if (!isoString) return null;
  const local = isoString.replace(/([+-]\d{2}:\d{2}|Z)$/, '');
  const d = new Date(local);
  return isNaN(d) ? null : d;
}

function isSameDay(a, b) {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth()    === b.getMonth()    &&
    a.getDate()     === b.getDate()
  );
}

/** Returns the Sunday at or before the given date (start of week). */
function getWeekStart(date) {
  const d = new Date(date);
  d.setDate(d.getDate() - d.getDay());
  d.setHours(0, 0, 0, 0);
  return d;
}

/** Returns an array of 42 Date objects forming a 6-week month grid. */
function buildMonthGrid(year, month) {
  const firstDay = new Date(year, month, 1);
  const start    = new Date(firstDay);
  start.setDate(start.getDate() - start.getDay()); // back to Sunday
  const days = [];
  const d    = new Date(start);
  for (let i = 0; i < 42; i++) {
    days.push(new Date(d));
    d.setDate(d.getDate() + 1);
  }
  return days;
}

/** Returns events whose start_at falls on the given day, sorted by time. */
function getEventsOnDay(events, day) {
  return events
    .filter((ev) => {
      const d = parseLocal(ev.start_at);
      return d && isSameDay(d, day);
    })
    .sort((a, b) => {
      const da = parseLocal(a.start_at);
      const db = parseLocal(b.start_at);
      return (da?.getTime() ?? 0) - (db?.getTime() ?? 0);
    });
}

/** Returns formatted HH:MM string or null for all-day events. */
function formatTime(isoString, allDay) {
  if (allDay) return null;
  const d = parseLocal(isoString);
  if (!d) return null;
  return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

// ─── Shared event shape ───────────────────────────────────────────────────────
const eventShape = PropTypes.shape({
  id:       PropTypes.number.isRequired,
  title:    PropTypes.string.isRequired,
  all_day:  PropTypes.bool,
  color:    PropTypes.string,
  start_at: PropTypes.string,
  type:     PropTypes.string,
});

// ─── Shared action buttons ────────────────────────────────────────────────────
function ActionButtons({ ev, onEdit, onDelete, t }) {
  return (
    <div className="flex items-center gap-1 shrink-0">
      <button
        onClick={(e) => { e.stopPropagation(); onEdit(ev); }}
        aria-label={t('common.edit')}
        className="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
      >
        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
      </button>
      <button
        onClick={(e) => { e.stopPropagation(); onDelete(ev); }}
        aria-label={t('common.delete')}
        className="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
      >
        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
      </button>
    </div>
  );
}

ActionButtons.propTypes = {
  ev:       eventShape.isRequired,
  onEdit:   PropTypes.func.isRequired,
  onDelete: PropTypes.func.isRequired,
  t:        PropTypes.func.isRequired,
};

// ─── Month View ───────────────────────────────────────────────────────────────
function MonthView({ events, currentDate, onEdit }) {
  const year  = currentDate.getFullYear();
  const month = currentDate.getMonth();
  const days  = buildMonthGrid(year, month);
  const today = new Date();

  const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      {/* Weekday header row */}
      <div className="grid grid-cols-7 bg-slate-50 border-b border-slate-200">
        {WEEKDAYS.map((wd) => (
          <div
            key={wd}
            className="py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider"
          >
            {wd}
          </div>
        ))}
      </div>

      {/* 6-week grid */}
      <div className="grid grid-cols-7">
        {days.map((day, idx) => {
          const isCurrentMonth = day.getMonth() === month;
          const isToday        = isSameDay(day, today);
          const dayEvents      = getEventsOnDay(events, day);

          return (
            <div
              key={idx}
              className={[
                'border-b border-r border-slate-100 min-h-24 p-1.5',
                idx % 7 === 6      ? 'border-r-0'    : '',
                idx >= 35          ? 'border-b-0'    : '',
                !isCurrentMonth    ? 'bg-slate-50/60' : '',
              ].join(' ')}
            >
              {/* Day number */}
              <div className="flex items-center justify-end mb-1">
                <span
                  className={[
                    'flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium',
                    isToday        ? 'bg-blue-600 text-white'
                    : isCurrentMonth ? 'text-slate-700'
                    : 'text-slate-300',
                  ].join(' ')}
                >
                  {day.getDate()}
                </span>
              </div>

              {/* Events — max 2 visible + overflow count */}
              {dayEvents.slice(0, 2).map((ev) => (
                <div
                  key={ev.id}
                  onClick={() => onEdit(ev)}
                  title={ev.title}
                  className="flex items-center gap-1 text-xs rounded px-1 py-0.5 mb-0.5 cursor-pointer text-white font-medium hover:opacity-90 transition-opacity truncate"
                  style={{ backgroundColor: ev.color || '#6B7280' }}
                >
                  {!ev.all_day && (
                    <span className="opacity-80 shrink-0 tabular-nums">
                      {formatTime(ev.start_at, ev.all_day)}
                    </span>
                  )}
                  <span className="truncate">{ev.title}</span>
                </div>
              ))}
              {dayEvents.length > 2 && (
                <div className="text-xs text-slate-400 px-1">
                  +{dayEvents.length - 2}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

MonthView.propTypes = {
  events:      PropTypes.arrayOf(eventShape).isRequired,
  currentDate: PropTypes.instanceOf(Date).isRequired,
  onEdit:      PropTypes.func.isRequired,
};

// ─── Week View ────────────────────────────────────────────────────────────────
function WeekView({ events, currentDate, onEdit }) {
  const weekStart = getWeekStart(currentDate);
  const days = Array.from({ length: 7 }, (_, i) => {
    const d = new Date(weekStart);
    d.setDate(d.getDate() + i);
    return d;
  });
  const today = new Date();

  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div className="grid grid-cols-7 divide-x divide-slate-200">
        {days.map((day, i) => {
          const isToday   = isSameDay(day, today);
          const dayEvents = getEventsOnDay(events, day);

          return (
            <div key={i} className="flex flex-col">
              {/* Day header */}
              <div
                className={[
                  'py-3 text-center border-b border-slate-200',
                  isToday ? 'bg-blue-50' : 'bg-slate-50',
                ].join(' ')}
              >
                <div className="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                  {day.toLocaleDateString(undefined, { weekday: 'short' })}
                </div>
                <div
                  className={[
                    'mx-auto mt-1 w-8 h-8 flex items-center justify-center rounded-full text-sm font-bold',
                    isToday ? 'bg-blue-600 text-white' : 'text-slate-700',
                  ].join(' ')}
                >
                  {day.getDate()}
                </div>
              </div>

              {/* Event list for this day */}
              <div className="flex-1 p-1.5 space-y-0.5 min-h-40">
                {dayEvents.map((ev) => (
                  <div
                    key={ev.id}
                    onClick={() => onEdit(ev)}
                    title={ev.title}
                    className="text-xs rounded px-1.5 py-1 cursor-pointer text-white font-medium hover:opacity-90 transition-opacity"
                    style={{ backgroundColor: ev.color || '#6B7280' }}
                  >
                    {!ev.all_day && (
                      <span className="block opacity-80 tabular-nums text-[10px]">
                        {formatTime(ev.start_at, ev.all_day)}
                      </span>
                    )}
                    <span className="truncate block">{ev.title}</span>
                  </div>
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

WeekView.propTypes = {
  events:      PropTypes.arrayOf(eventShape).isRequired,
  currentDate: PropTypes.instanceOf(Date).isRequired,
  onEdit:      PropTypes.func.isRequired,
};

// ─── List View ────────────────────────────────────────────────────────────────
function ListView({ events, onEdit, onDelete, t }) {
  const grouped = useMemo(() => {
    const map = new Map();
    events.forEach((ev) => {
      const d = parseLocal(ev.start_at);
      if (!d) return;
      const key = d.toDateString();
      if (!map.has(key)) map.set(key, { date: d, items: [] });
      map.get(key).items.push(ev);
    });
    return Array.from(map.values())
      .sort((a, b) => a.date - b.date)
      .map(({ date, items }) => ({
        date,
        items: [...items].sort((a, b) => {
          const da = parseLocal(a.start_at);
          const db = parseLocal(b.start_at);
          return (da?.getTime() ?? 0) - (db?.getTime() ?? 0);
        }),
      }));
  }, [events]);

  if (grouped.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-20 text-center bg-white rounded-xl border border-slate-200 shadow-sm">
        <div className="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mb-4">
          <svg className="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <p className="text-sm font-medium text-slate-600">{t('calendar.no_events_period')}</p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {grouped.map(({ date, items }) => (
        <div
          key={date.toDateString()}
          className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden"
        >
          {/* Date header */}
          <div className="px-5 py-3 bg-slate-50 border-b border-slate-200">
            <h3 className="text-sm font-semibold text-slate-700 capitalize">
              {date.toLocaleDateString(undefined, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
              })}
            </h3>
          </div>

          {/* Events for this day */}
          <div className="divide-y divide-slate-100">
            {items.map((ev) => (
              <div
                key={ev.id}
                className="flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50 transition-colors"
              >
                {/* Colour identifier dot */}
                <span
                  className="w-2.5 h-2.5 rounded-full shrink-0"
                  style={{ backgroundColor: ev.color || '#6B7280' }}
                />

                {/* Time */}
                <span className="text-sm text-slate-500 w-16 shrink-0 tabular-nums">
                  {ev.all_day
                    ? t('calendar.all_day_badge')
                    : (formatTime(ev.start_at, ev.all_day) ?? '—')}
                </span>

                {/* Title */}
                <span className="flex-1 text-sm font-medium text-slate-800">
                  {ev.title}
                </span>

                {/* Type badge */}
                <span
                  className={`hidden sm:inline-flex items-center px-2 py-0.5 rounded text-xs font-medium shrink-0 ${
                    TYPE_BADGE[ev.type] ?? TYPE_BADGE.casual
                  }`}
                >
                  {t(`calendar.types.${ev.type ?? 'casual'}`)}
                </span>

                <ActionButtons ev={ev} onEdit={onEdit} onDelete={onDelete} t={t} />
              </div>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}

ListView.propTypes = {
  events:   PropTypes.arrayOf(eventShape).isRequired,
  onEdit:   PropTypes.func.isRequired,
  onDelete: PropTypes.func.isRequired,
  t:        PropTypes.func.isRequired,
};

// ─── Main Component ───────────────────────────────────────────────────────────
export default function CalendarPage() {
  const { t } = useTranslation('common');

  const [events,      setEvents]      = useState([]);
  const [loading,     setLoading]     = useState(true);
  const [loadError,   setLoadError]   = useState('');
  const [modalEvent,  setModalEvent]  = useState(undefined); // undefined=closed, null=new, object=edit
  const [deleteError, setDeleteError] = useState('');
  const [successMsg,  setSuccessMsg]  = useState('');
  const [view,        setView]        = useState('month');  // 'month' | 'week' | 'list'
  const [currentDate, setCurrentDate] = useState(() => new Date());

  // ── Load events ─────────────────────────────────────────────────────────────
  const loadEvents = useCallback(async () => {
    setLoading(true);
    setLoadError('');
    try {
      const data = await eventsApi.list();
      setEvents(data);
    } catch {
      setLoadError(t('calendar.load_error'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => { loadEvents(); }, [loadEvents]);

  // ── Auto-clear flash messages ────────────────────────────────────────────────
  useEffect(() => {
    if (!successMsg) return;
    const id = setTimeout(() => setSuccessMsg(''), 3500);
    return () => clearTimeout(id);
  }, [successMsg]);

  // ── Save (create / update) ───────────────────────────────────────────────────
  async function handleSave(payload, id) {
    if (id) {
      const updated = await eventsApi.update(id, payload);
      setEvents((prev) => prev.map((e) => (e.id === updated.id ? updated : e)));
    } else {
      const created = await eventsApi.create(payload);
      setEvents((prev) => [...prev, created]);
    }
    setModalEvent(undefined);
    setSuccessMsg(t('calendar.save_success'));
  }

  // ── Delete ───────────────────────────────────────────────────────────────────
  async function handleDelete(event) {
    if (!window.confirm(t('calendar.delete_confirm', { title: event.title }))) return;
    setDeleteError('');
    try {
      await eventsApi.delete(event.id);
      setEvents((prev) => prev.filter((e) => e.id !== event.id));
      setSuccessMsg(t('calendar.delete_success'));
    } catch {
      setDeleteError(t('calendar.delete_error'));
    }
  }

  // ── Navigation ───────────────────────────────────────────────────────────────
  function navigate(dir) {
    setCurrentDate((prev) => {
      const d = new Date(prev);
      if (view === 'week') {
        d.setDate(d.getDate() + dir * 7);
      } else {
        d.setMonth(d.getMonth() + dir);
      }
      return d;
    });
  }

  function goToday() {
    setCurrentDate(new Date());
  }

  // ── Period label ─────────────────────────────────────────────────────────────
  const periodLabel = useMemo(() => {
    if (view === 'week') {
      const ws = getWeekStart(currentDate);
      const we = new Date(ws);
      we.setDate(we.getDate() + 6);
      const startStr = ws.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
      const endStr   = we.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
      return `${startStr} – ${endStr}`;
    }
    return currentDate.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
  }, [view, currentDate]);

  // ── Events filtered to the current period ────────────────────────────────────
  const periodEvents = useMemo(() => {
    if (view === 'week') {
      const start = getWeekStart(currentDate);
      const end   = new Date(start);
      end.setDate(end.getDate() + 7);
      return events.filter((ev) => {
        const d = parseLocal(ev.start_at);
        return d && d >= start && d < end;
      });
    }
    const year  = currentDate.getFullYear();
    const month = currentDate.getMonth();
    return events.filter((ev) => {
      const d = parseLocal(ev.start_at);
      return d && d.getFullYear() === year && d.getMonth() === month;
    });
  }, [events, view, currentDate]);

  // ─────────────────────────────────────────────────────────────────────────────
  return (
    <div className="space-y-5">

      {/* Page header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">{t('calendar.title')}</h1>
          <p className="mt-0.5 text-sm text-slate-500">{t('calendar.subtitle')}</p>
        </div>
        <button
          onClick={() => setModalEvent(null)}
          className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {t('calendar.new')}
        </button>
      </div>

      {/* Calendar toolbar */}
      <div className="flex flex-wrap items-center gap-3">

        {/* Navigation arrows + Today button */}
        <div className="flex items-center gap-1">
          <button
            onClick={() => navigate(-1)}
            aria-label="Previous"
            className="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
          </button>

          <button
            onClick={goToday}
            className="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 transition-colors"
          >
            {t('calendar.today')}
          </button>

          <button
            onClick={() => navigate(1)}
            aria-label="Next"
            className="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>

        {/* Period label */}
        <h2 className="text-base font-semibold text-slate-800 capitalize">
          {periodLabel}
        </h2>

        <div className="flex-1" />

        {/* View selector: Month | Week | List */}
        <div className="flex rounded-lg border border-slate-200 overflow-hidden">
          {['month', 'week', 'list'].map((v, idx) => (
            <button
              key={v}
              onClick={() => setView(v)}
              className={[
                'px-3.5 py-1.5 text-sm font-medium transition-colors',
                view === v
                  ? 'bg-blue-600 text-white'
                  : 'bg-white text-slate-600 hover:bg-slate-50',
                idx < 2 ? 'border-r border-slate-200' : '',
              ].join(' ')}
            >
              {t(`calendar.view_${v}`)}
            </button>
          ))}
        </div>
      </div>

      {/* Flash messages */}
      {successMsg && (
        <div className="rounded-lg bg-green-50 border border-green-200 px-4 py-3">
          <p className="text-sm text-green-700">{successMsg}</p>
        </div>
      )}
      {deleteError && (
        <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
          <p className="text-sm text-red-700">{deleteError}</p>
        </div>
      )}

      {/* Content area */}
      {loading ? (
        <div className="flex items-center gap-2 text-sm text-slate-500 py-10 justify-center">
          <svg className="w-4 h-4 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
          </svg>
          {t('calendar.loading')}
        </div>

      ) : loadError ? (
        <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 flex items-center justify-between">
          <p className="text-sm text-red-700">{loadError}</p>
          <button onClick={loadEvents} className="text-sm font-medium text-red-700 underline ml-4">
            {t('common.try_again')}
          </button>
        </div>

      ) : view === 'month' ? (
        <MonthView
          events={events}
          currentDate={currentDate}
          onEdit={setModalEvent}
          onDelete={handleDelete}
          t={t}
        />

      ) : view === 'week' ? (
        <WeekView
          events={events}
          currentDate={currentDate}
          onEdit={setModalEvent}
          onDelete={handleDelete}
          t={t}
        />

      ) : (
        <ListView
          events={periodEvents}
          onEdit={setModalEvent}
          onDelete={handleDelete}
          t={t}
        />
      )}

      {/* Event form modal */}
      {modalEvent !== undefined && (
        <EventFormModal
          event={modalEvent}
          onClose={() => setModalEvent(undefined)}
          onSave={handleSave}
        />
      )}

    </div>
  );
}
