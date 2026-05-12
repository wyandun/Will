import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { eventsApi } from '../../api/events';
import EventFormModal from './EventFormModal';

// ─── Type badge colors ────────────────────────────────────────────────────────
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

function formatDate(isoString, allDay) {
  if (!isoString) return '—';
  const d = parseLocal(isoString);
  if (!d) return isoString;
  if (allDay) return d.toLocaleDateString(undefined, { dateStyle: 'medium' });
  return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

// ─── Component ────────────────────────────────────────────────────────────────
export default function CalendarPage() {
  const { t } = useTranslation('common');

  const [events, setEvents]           = useState([]);
  const [loading, setLoading]         = useState(true);
  const [loadError, setLoadError]     = useState('');
  const [modalEvent, setModalEvent]   = useState(undefined); // undefined=closed, null=new, object=edit
  const [deleteError, setDeleteError] = useState('');
  const [successMsg, setSuccessMsg]   = useState('');

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

  // ─────────────────────────────────────────────────────────────────────────────

  return (
    <div className="space-y-6">

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

      {/* Content */}
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
      ) : events.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-24 text-center">
          <div className="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-5">
            <svg className="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <h2 className="text-lg font-semibold text-slate-700">{t('calendar.empty_title')}</h2>
          <p className="mt-1.5 text-sm text-slate-400">{t('calendar.empty_subtitle')}</p>
          <button
            onClick={() => setModalEvent(null)}
            className="mt-5 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
          >
            {t('calendar.new')}
          </button>
        </div>
      ) : (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 border-b border-slate-200">
              <tr>
                <th className="text-left px-5 py-3 font-medium text-slate-600">{t('calendar.col_title')}</th>
                <th className="text-left px-5 py-3 font-medium text-slate-600">{t('calendar.col_date')}</th>
                <th className="text-left px-5 py-3 font-medium text-slate-600 hidden md:table-cell">{t('calendar.col_location')}</th>
                <th className="text-left px-5 py-3 font-medium text-slate-600 hidden lg:table-cell">{t('calendar.col_type')}</th>
                <th className="px-5 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {events.map((ev) => (
                <tr key={ev.id} className="hover:bg-slate-50 transition-colors">
                  {/* Title + color dot */}
                  <td className="px-5 py-3.5">
                    <div className="flex items-center gap-2.5">
                      <span
                        className="w-2.5 h-2.5 rounded-full shrink-0"
                        style={{ backgroundColor: ev.color || '#6B7280' }}
                      />
                      <span className="font-medium text-slate-800">{ev.title}</span>
                      {ev.all_day && (
                        <span className="text-xs px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">
                          {t('calendar.all_day_badge')}
                        </span>
                      )}
                    </div>
                    {ev.description && (
                      <p className="mt-0.5 text-xs text-slate-400 truncate max-w-xs pl-5">{ev.description}</p>
                    )}
                  </td>

                  {/* Date */}
                  <td className="px-5 py-3.5 text-slate-600 whitespace-nowrap">
                    <div>{formatDate(ev.start_at, ev.all_day)}</div>
                    {ev.end_at && ev.end_at !== ev.start_at && (
                      <div className="text-xs text-slate-400">→ {formatDate(ev.end_at, ev.all_day)}</div>
                    )}
                  </td>

                  {/* Location */}
                  <td className="px-5 py-3.5 text-slate-500 hidden md:table-cell">
                    {ev.location || <span className="text-slate-300">—</span>}
                  </td>

                  {/* Type badge */}
                  <td className="px-5 py-3.5 hidden lg:table-cell">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${TYPE_BADGE[ev.type] ?? TYPE_BADGE.casual}`}>
                      {t(`calendar.types.${ev.type ?? 'casual'}`)}
                    </span>
                  </td>

                  {/* Actions */}
                  <td className="px-5 py-3.5">
                    <div className="flex items-center justify-end gap-1">
                      <button
                        onClick={() => setModalEvent(ev)}
                        aria-label={t('common.edit')}
                        className="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                      >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                      </button>
                      <button
                        onClick={() => handleDelete(ev)}
                        aria-label={t('common.delete')}
                        className="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                      >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Modal */}
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
