import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { dashboardApi } from '../api/dashboard';

// ─── Helpers ─────────────────────────────────────────────────────────────────

function isSameDay(a, b) {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

function getRelativeLabel(dateStr, t, locale) {
  const eventDate = new Date(dateStr);
  const today = new Date();
  const tomorrow = new Date(today);
  tomorrow.setDate(today.getDate() + 1);

  if (isSameDay(eventDate, today)) return t('sidebar.today');
  if (isSameDay(eventDate, tomorrow)) return t('sidebar.tomorrow');
  return eventDate.toLocaleDateString(locale, { weekday: 'long' });
}

// ─── Icon ─────────────────────────────────────────────────────────────────────

function IconCalendar() {
  return (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
    </svg>
  );
}

// ─── Skeleton ────────────────────────────────────────────────────────────────

function SkeletonCard() {
  return (
    <li className="flex items-start gap-3 animate-pulse">
      <div className="flex-shrink-0 w-10 h-12 bg-slate-100 rounded-lg" />
      <div className="flex-1 flex flex-col gap-2 py-1">
        <div className="h-3 bg-slate-100 rounded w-1/3" />
        <div className="h-3 bg-slate-100 rounded w-2/3" />
        <div className="h-2.5 bg-slate-100 rounded w-1/4" />
      </div>
    </li>
  );
}

// ─── Event Card ──────────────────────────────────────────────────────────────

function EventCard({ ev, locale, t }) {
  const eventDate = new Date(ev.start_at);
  const relativeLabel = getRelativeLabel(ev.start_at, t, locale);
  const isToday = relativeLabel === t('sidebar.today');
  const isTomorrow = relativeLabel === t('sidebar.tomorrow');

  const chipBg = isToday
    ? 'bg-amber-50'
    : isTomorrow
    ? 'bg-blue-50'
    : 'bg-slate-50';

  const chipTextMonth = isToday
    ? 'text-amber-600'
    : isTomorrow
    ? 'text-blue-600'
    : 'text-slate-500';

  const chipTextDay = isToday
    ? 'text-amber-800'
    : isTomorrow
    ? 'text-blue-800'
    : 'text-slate-700';

  const timeLabel = ev.all_day
    ? t('sidebar.all_day')
    : eventDate.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });

  return (
    <li className="flex items-start gap-3">
      {/* Color bar */}
      <div
        className="flex-shrink-0 w-1 self-stretch rounded-full"
        style={{ backgroundColor: ev.color ?? '#6B7280' }}
      />
      {/* Date chip */}
      <div className={`flex-shrink-0 w-10 text-center ${chipBg} rounded-lg py-1`}>
        <p className={`text-xs font-semibold uppercase ${chipTextMonth}`}>
          {eventDate.toLocaleDateString(locale, { month: 'short' })}
        </p>
        <p className={`text-base font-bold leading-none ${chipTextDay}`}>
          {eventDate.getDate()}
        </p>
      </div>
      {/* Content */}
      <div className="min-w-0 flex flex-col gap-0.5">
        <p className={`text-xs font-medium capitalize ${isToday ? 'text-amber-600' : isTomorrow ? 'text-blue-600' : 'text-slate-400'}`}>
          {relativeLabel}
        </p>
        <p className="text-sm font-semibold text-slate-700 truncate">{ev.title}</p>
        <p className="text-xs text-slate-400">{timeLabel}</p>
      </div>
    </li>
  );
}

EventCard.propTypes = {
  ev: PropTypes.shape({
    id: PropTypes.number.isRequired,
    title: PropTypes.string.isRequired,
    start_at: PropTypes.string.isRequired,
    end_at: PropTypes.string,
    all_day: PropTypes.bool,
    color: PropTypes.string,
  }).isRequired,
  locale: PropTypes.string.isRequired,
  t: PropTypes.func.isRequired,
};

// ─── UpcomingEventsSidebar ────────────────────────────────────────────────────

export default function UpcomingEventsSidebar() {
  const { t, i18n } = useTranslation('common');
  const navigate = useNavigate();
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(true);
  const locale = i18n.language;

  useEffect(() => {
    dashboardApi.getEvents()
      .then(setEvents)
      .catch(() => setEvents([]))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="bg-white rounded-2xl p-6 border border-slate-100 flex flex-col gap-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-amber-500">
          <IconCalendar />
          <h3 className="text-sm font-semibold text-slate-700">
            {t('sidebar.upcoming_events')}
          </h3>
        </div>
        {!loading && events.length > 0 && (
          <span className="text-xs font-semibold bg-amber-100 text-amber-700 rounded-full px-2 py-0.5">
            {events.length}
          </span>
        )}
      </div>

      {/* Body */}
      {loading ? (
        <ul className="flex flex-col gap-4">
          <SkeletonCard />
          <SkeletonCard />
          <SkeletonCard />
        </ul>
      ) : events.length === 0 ? (
        <p className="text-sm text-slate-400 py-4 text-center">{t('sidebar.no_events')}</p>
      ) : (
        <ul className="flex flex-col gap-4">
          {events.map((ev) => (
            <EventCard key={ev.id} ev={ev} locale={locale} t={t} />
          ))}
        </ul>
      )}

      {/* Footer */}
      <button
        onClick={() => navigate('/calendar')}
        className="mt-auto w-full border border-slate-200 rounded-xl py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
      >
        {t('sidebar.view_all')} →
      </button>
    </div>
  );
}
