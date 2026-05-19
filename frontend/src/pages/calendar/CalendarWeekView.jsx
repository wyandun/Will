import PropTypes from 'prop-types';
import { useTranslation } from 'react-i18next';

// ─── Constants ────────────────────────────────────────────────────────────────

const HOUR_HEIGHT = 56; // px per hour
const HOURS = Array.from({ length: 24 }, (_, i) => i);

// ─── Date helpers ─────────────────────────────────────────────────────────────

/** Returns the 7 days of the week containing `date`, starting Sunday. */
function getWeekDays(date) {
  const start = new Date(date);
  start.setDate(start.getDate() - start.getDay()); // 0 = Sunday
  start.setHours(0, 0, 0, 0);
  return Array.from({ length: 7 }, (_, i) => {
    const d = new Date(start);
    d.setDate(start.getDate() + i);
    return d;
  });
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

function formatHour(h, locale) {
  const d = new Date();
  d.setHours(h, 0, 0, 0);
  return d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit', hour12: false });
}

function formatDayLabel(date, locale) {
  return date.toLocaleDateString(locale, { weekday: 'short', day: 'numeric' });
}

/**
 * For a timed event, compute top offset (px) and height (px) based on HOUR_HEIGHT.
 */
function getEventStyle(event) {
  const start = new Date(event.start_at);
  const end = new Date(event.end_at);
  const startMinutes = start.getHours() * 60 + start.getMinutes();
  const endMinutes = end.getHours() * 60 + end.getMinutes();
  const durationMinutes = Math.max(30, endMinutes - startMinutes); // min 30min display
  return {
    top: `${(startMinutes / 60) * HOUR_HEIGHT}px`,
    height: `${(durationMinutes / 60) * HOUR_HEIGHT}px`,
  };
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function CalendarWeekView({ currentDate, events, onEdit, onCreateWithDate }) {
  const { t, i18n } = useTranslation('common');
  const locale = i18n.language;
  const weekDays = getWeekDays(currentDate);

  // Separate all-day events from timed events
  const allDayEvents = events.filter((e) => e.all_day);
  const timedEvents = events.filter((e) => !e.all_day);

  // Build map: dateKey → timed events[]
  function toDateKey(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }

  const timedByDay = {};
  for (const event of timedEvents) {
    const key = event.start_at.slice(0, 10);
    if (!timedByDay[key]) timedByDay[key] = [];
    timedByDay[key].push(event);
  }

  const allDayByDay = {};
  for (const event of allDayEvents) {
    const key = event.start_at.slice(0, 10);
    if (!allDayByDay[key]) allDayByDay[key] = [];
    allDayByDay[key].push(event);
  }

  return (
    <div className="rounded-xl border border-slate-200 overflow-hidden bg-white shadow-sm">
      {/* Header row: time gutter + day labels */}
      <div className="grid border-b border-slate-200 bg-slate-50" style={{ gridTemplateColumns: '3rem repeat(7, 1fr)' }}>
        {/* Gutter */}
        <div className="border-r border-slate-200" />
        {weekDays.map((day, i) => (
          <div
            key={i}
            className={`py-2 px-1 text-center border-r border-slate-100 last:border-r-0 ${isToday(day) ? 'bg-blue-50' : ''}`}
          >
            <div className={`text-xs font-semibold uppercase tracking-wide ${isToday(day) ? 'text-blue-600' : 'text-slate-500'}`}>
              {formatDayLabel(day, locale)}
            </div>
          </div>
        ))}
      </div>

      {/* All-day events row */}
      {allDayEvents.length > 0 && (
        <div
          className="grid border-b border-slate-200 bg-slate-50/50"
          style={{ gridTemplateColumns: '3rem repeat(7, 1fr)' }}
        >
          <div className="border-r border-slate-200 flex items-center justify-center">
            <span className="text-[10px] text-slate-400 font-medium uppercase rotate-[-90deg] whitespace-nowrap">
              {t('calendar.all_day')}
            </span>
          </div>
          {weekDays.map((day, i) => {
            const key = toDateKey(day);
            const dayAllDay = allDayByDay[key] ?? [];
            return (
              <div key={i} className="p-1 border-r border-slate-100 last:border-r-0 min-h-[28px] flex flex-col gap-0.5">
                {dayAllDay.map((event) => (
                  <button
                    key={event.id}
                    onClick={() => onEdit(event)}
                    className="w-full text-left truncate text-xs rounded px-1.5 py-0.5 font-medium text-white"
                    style={{ backgroundColor: event.color ?? '#3B82F6' }}
                    title={event.title}
                  >
                    {event.title}
                  </button>
                ))}
              </div>
            );
          })}
        </div>
      )}

      {/* Scrollable time grid */}
      <div className="overflow-y-auto" style={{ maxHeight: '560px' }}>
        <div className="relative" style={{ gridTemplateColumns: '3rem repeat(7, 1fr)' }}>
          {/* Grid lines + hour labels */}
          <div className="grid" style={{ gridTemplateColumns: '3rem repeat(7, 1fr)' }}>
            {HOURS.map((hour) => (
              <div
                key={hour}
                className="contents"
              >
                {/* Hour label */}
                <div
                  className="border-r border-b border-slate-100 flex items-start justify-end pr-1 pt-0.5"
                  style={{ height: `${HOUR_HEIGHT}px` }}
                >
                  <span className="text-[10px] text-slate-400 font-medium">{formatHour(hour, locale)}</span>
                </div>
                {/* Day columns for this hour */}
                {weekDays.map((day, dayIdx) => (
                  <div
                    key={dayIdx}
                    className={`border-r border-b border-slate-100 last:border-r-0 cursor-pointer hover:bg-slate-50 transition-colors ${isToday(day) ? 'bg-blue-50/30' : ''}`}
                    style={{ height: `${HOUR_HEIGHT}px` }}
                    onClick={() => {
                      const d = new Date(day);
                      d.setHours(hour, 0, 0, 0);
                      onCreateWithDate(d);
                    }}
                  />
                ))}
              </div>
            ))}
          </div>

          {/* Timed events overlay — absolutely positioned over the grid */}
          <div
            className="absolute inset-0 pointer-events-none"
            style={{ left: '3rem' }}
          >
            <div
              className="relative h-full grid"
              style={{
                gridTemplateColumns: `repeat(7, 1fr)`,
                height: `${HOUR_HEIGHT * 24}px`,
              }}
            >
              {weekDays.map((day, dayIdx) => {
                const key = toDateKey(day);
                const dayTimed = timedByDay[key] ?? [];
                return (
                  <div key={dayIdx} className="relative">
                    {dayTimed.map((event) => {
                      const style = getEventStyle(event);
                      return (
                        <button
                          key={event.id}
                          onClick={() => onEdit(event)}
                          className="absolute left-0.5 right-0.5 rounded text-white text-xs px-1.5 py-0.5 overflow-hidden text-left pointer-events-auto hover:brightness-90 transition-all shadow-sm"
                          style={{
                            backgroundColor: event.color ?? '#3B82F6',
                            top: style.top,
                            height: style.height,
                            minHeight: '20px',
                          }}
                          title={event.title}
                        >
                          <span className="font-medium truncate block leading-tight">{event.title}</span>
                          <span className="text-white/80 text-[10px] leading-none">
                            {new Date(event.start_at).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit', hour12: false })}
                          </span>
                        </button>
                      );
                    })}
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

CalendarWeekView.propTypes = {
  currentDate: PropTypes.instanceOf(Date).isRequired,
  events: PropTypes.array.isRequired,
  onEdit: PropTypes.func.isRequired,
  onCreateWithDate: PropTypes.func.isRequired,
};
