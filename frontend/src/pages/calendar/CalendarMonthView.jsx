import PropTypes from 'prop-types';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import DayEventsPopover from './DayEventsPopover';

// ─── Date helpers ─────────────────────────────────────────────────────────────

function toDateKey(isoString) {
  return isoString ? isoString.slice(0, 10) : '';
}

/**
 * Returns an array of 42 cells (6 weeks × 7 days) for the month view.
 * Each cell is { date: Date, currentMonth: boolean }.
 */
function getMonthCells(year, month) {
  const firstDay = new Date(year, month, 1).getDay(); // 0=Sun
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const cells = [];

  // Padding: days from previous month
  const prevMonthDays = new Date(year, month, 0).getDate();
  for (let i = firstDay - 1; i >= 0; i--) {
    cells.push({ date: new Date(year, month - 1, prevMonthDays - i), currentMonth: false });
  }

  // Current month days
  for (let d = 1; d <= daysInMonth; d++) {
    cells.push({ date: new Date(year, month, d), currentMonth: true });
  }

  // Padding: days from next month to complete 42 cells
  let nextDay = 1;
  while (cells.length < 42) {
    cells.push({ date: new Date(year, month + 1, nextDay++), currentMonth: false });
  }

  return cells;
}

function isSameDay(a, b) {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

function isToday(date) {
  return isSameDay(date, new Date());
}

// ─── Component ────────────────────────────────────────────────────────────────

const DAY_HEADERS_EN = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const DAY_HEADERS_ES = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

export default function CalendarMonthView({ year, month, events, onEdit, onCreateWithDate }) {
  const { i18n, t } = useTranslation('common');
  const dayHeaders = i18n.language === 'es' ? DAY_HEADERS_ES : DAY_HEADERS_EN;
  const [popoverDay, setPopoverDay] = useState(null);
  const [popoverAnchor, setPopoverAnchor] = useState(null);

  const cells = getMonthCells(year, month);

  // Build a map: dateKey → events[]
  const eventsByDay = {};
  for (const event of events) {
    const key = toDateKey(event.start_at);
    if (!eventsByDay[key]) eventsByDay[key] = [];
    eventsByDay[key].push(event);
  }

  return (
    <div className="rounded-xl border border-slate-200 overflow-hidden bg-white shadow-sm">
      {/* Day-of-week headers */}
      <div className="grid grid-cols-7 bg-slate-50 border-b border-slate-200">
        {dayHeaders.map((day) => (
          <div key={day} className="py-2 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">
            {day}
          </div>
        ))}
      </div>

      {/* Calendar grid */}
      <div className="grid grid-cols-7 divide-x divide-y divide-slate-100">
        {cells.map(({ date, currentMonth }, idx) => {
          const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
          const dayEvents = eventsByDay[key] ?? [];
          const visibleEvents = dayEvents.slice(0, 3);
          const overflow = dayEvents.length - visibleEvents.length;
          const today = isToday(date);

          return (
            <div
              key={idx}
              className={`min-h-[96px] p-1.5 flex flex-col gap-0.5 cursor-pointer hover:bg-slate-50 transition-colors ${!currentMonth ? 'bg-slate-50/50' : ''}`}
              onClick={() => onCreateWithDate(date)}
            >
              {/* Day number */}
              <div className="flex justify-end">
                <span
                  className={`text-xs font-medium w-6 h-6 flex items-center justify-center rounded-full
                    ${today ? 'bg-blue-600 text-white' : currentMonth ? 'text-slate-700' : 'text-slate-300'}`}
                >
                  {date.getDate()}
                </span>
              </div>

              {/* Event chips */}
              {visibleEvents.map((event) => (
                <button
                  key={event.id}
                  onClick={(e) => { e.stopPropagation(); onEdit(event); }}
                  className="w-full text-left truncate text-xs rounded px-1.5 py-0.5 font-medium text-white leading-tight"
                  style={{ backgroundColor: event.color ?? '#3B82F6' }}
                  title={event.title}
                >
                  {event.all_day ? event.title : `${new Date(event.start_at).toLocaleTimeString(i18n.language, { hour: '2-digit', minute: '2-digit' })} ${event.title}`}
                </button>
              ))}

              {overflow > 0 && (
                <button
                  onClick={(e) => { e.stopPropagation(); setPopoverAnchor(e.currentTarget); setPopoverDay(key); }}
                  className="text-xs text-blue-600 hover:text-blue-800 hover:underline px-1 cursor-pointer font-medium"
                  aria-label={t('calendar.show_all_events', { count: dayEvents.length })}
                >
                  {t('calendar.more_events', { count: overflow })}
                </button>
              )}
            </div>
          );
        })}
      </div>

      {/* Day events popover */}
      {popoverDay && (
        <DayEventsPopover
          date={cells.find((c) => {
            const k = `${c.date.getFullYear()}-${String(c.date.getMonth() + 1).padStart(2, '0')}-${String(c.date.getDate()).padStart(2, '0')}`;
            return k === popoverDay;
          })?.date}
          events={eventsByDay[popoverDay] ?? []}
          onEdit={(event) => { setPopoverDay(null); onEdit(event); }}
          onCreateWithDate={(d) => { setPopoverDay(null); onCreateWithDate(d); }}
          onClose={() => setPopoverDay(null)}
          anchorEl={popoverAnchor}
        />
      )}
    </div>
  );
}

CalendarMonthView.propTypes = {
  year: PropTypes.number.isRequired,
  month: PropTypes.number.isRequired,
  events: PropTypes.array.isRequired,
  onEdit: PropTypes.func.isRequired,
  onCreateWithDate: PropTypes.func.isRequired,
};
