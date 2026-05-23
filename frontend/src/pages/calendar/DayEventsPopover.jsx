import PropTypes from 'prop-types';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';

export default function DayEventsPopover({ date, events, onEdit, onCreateWithDate, onClose, anchorEl }) {
  const { t, i18n } = useTranslation('common');
  const popoverRef = useRef(null);
  const locale = i18n.language;

  // Position the popover relative to the anchor element
  useEffect(() => {
    if (!anchorEl || !popoverRef.current) return;

    const anchor = anchorEl.getBoundingClientRect();
    const popover = popoverRef.current;
    const viewportH = window.innerHeight;
    const viewportW = window.innerWidth;

    // Default: below the anchor
    let top = anchor.bottom + 4;
    let left = anchor.left;

    // Flip above if not enough space below
    const popoverHeight = popover.offsetHeight;
    if (top + popoverHeight > viewportH - 16) {
      top = anchor.top - popoverHeight - 4;
    }

    // Keep within horizontal viewport bounds
    const popoverWidth = popover.offsetWidth;
    if (left + popoverWidth > viewportW - 16) {
      left = viewportW - popoverWidth - 16;
    }
    if (left < 16) left = 16;

    popover.style.top = `${top}px`;
    popover.style.left = `${left}px`;
  }, [anchorEl]);

  // Close on Escape
  useEffect(() => {
    function handleKeyDown(e) {
      if (e.key === 'Escape') onClose();
    }
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [onClose]);

  const dateLabel = date
    ? date.toLocaleDateString(locale, { weekday: 'long', month: 'long', day: 'numeric' })
    : '';

  return (
    <>
      {/* Backdrop */}
      <div className="fixed inset-0 z-40" onClick={onClose} />

      {/* Popover */}
      <div
        ref={popoverRef}
        role="dialog"
        aria-modal="true"
        aria-label={t('calendar.all_events_for', { date: dateLabel })}
        className="fixed z-50 w-72 bg-white rounded-xl shadow-xl border border-slate-200 flex flex-col overflow-hidden"
      >
        {/* Header */}
        <div className="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-slate-50">
          <h3 className="text-sm font-semibold text-slate-800 capitalize">{dateLabel}</h3>
          <button
            onClick={onClose}
            className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
            aria-label={t('common.close')}
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Event list */}
        <ul className="flex flex-col gap-1 p-2 max-h-[280px] overflow-y-auto">
          {events.map((event) => {
            const eventDate = new Date(event.start_at);
            const timeLabel = event.all_day
              ? t('calendar.all_day')
              : eventDate.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });

            return (
              <li key={event.id}>
                <button
                  onClick={() => onEdit(event)}
                  className="w-full flex items-center gap-2 px-2 py-1.5 rounded-lg text-left hover:bg-slate-50 transition-colors group"
                >
                  <div
                    className="w-1.5 h-1.5 rounded-full flex-shrink-0"
                    style={{ backgroundColor: event.color ?? '#3B82F6' }}
                  />
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-slate-700 truncate group-hover:text-slate-900">
                      {event.title}
                    </p>
                    <p className="text-xs text-slate-400">{timeLabel}</p>
                  </div>
                </button>
              </li>
            );
          })}
        </ul>

        {/* Footer — new event */}
        <div className="border-t border-slate-100 p-2">
          <button
            onClick={() => onCreateWithDate(date)}
            className="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-blue-600 hover:bg-blue-50 transition-colors"
          >
            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            {t('calendar.new_event_on')}
          </button>
        </div>
      </div>
    </>
  );
}

DayEventsPopover.propTypes = {
  date: PropTypes.instanceOf(Date),
  events: PropTypes.array.isRequired,
  onEdit: PropTypes.func.isRequired,
  onCreateWithDate: PropTypes.func.isRequired,
  onClose: PropTypes.func.isRequired,
  anchorEl: PropTypes.object,
};
