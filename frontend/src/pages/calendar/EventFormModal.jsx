import PropTypes from 'prop-types';
import { useState, useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { TIMEZONE_OPTIONS } from '../../utils/timezones';
import { eventsApi } from '../../api/events';
import { usersApi } from '../../api/users';

const EVENT_COLORS = [
  { value: '#EF4444', labelKey: 'calendar.colors.red' },
  { value: '#F97316', labelKey: 'calendar.colors.orange' },
  { value: '#EAB308', labelKey: 'calendar.colors.yellow' },
  { value: '#10B981', labelKey: 'calendar.colors.green' },
  { value: '#3B82F6', labelKey: 'calendar.colors.blue' },
  { value: '#8B5CF6', labelKey: 'calendar.colors.purple' },
  { value: '#EC4899', labelKey: 'calendar.colors.pink' },
  { value: '#6366F1', labelKey: 'calendar.colors.indigo' },
  { value: '#14B8A6', labelKey: 'calendar.colors.teal' },
  { value: '#6B7280', labelKey: 'calendar.colors.gray' },
];

// UI presets → RFC 5545 RRULE strings. Empty string = non-recurring.
const REPEAT_PRESETS = [
  { value: '',                       labelKey: 'calendar.repeat_none' },
  { value: 'FREQ=DAILY',             labelKey: 'calendar.repeat_daily' },
  { value: 'FREQ=WEEKLY',            labelKey: 'calendar.repeat_weekly' },
  { value: 'FREQ=MONTHLY',           labelKey: 'calendar.repeat_monthly' },
  { value: 'FREQ=YEARLY',            labelKey: 'calendar.repeat_yearly' },
];

const REMINDER_PRESETS = [
  { value: '',     labelKey: 'calendar.reminder_none' },
  { value: '5',    labelKey: 'calendar.reminder_5min' },
  { value: '10',   labelKey: 'calendar.reminder_10min' },
  { value: '15',   labelKey: 'calendar.reminder_15min' },
  { value: '30',   labelKey: 'calendar.reminder_30min' },
  { value: '60',   labelKey: 'calendar.reminder_1hour' },
  { value: '1440', labelKey: 'calendar.reminder_1day' },
];

const VISIBILITY_OPTIONS = [
  { value: 'private',   labelKey: 'calendar.visibility_private' },
  { value: 'franchise', labelKey: 'calendar.visibility_franchise' },
  { value: 'public',    labelKey: 'calendar.visibility_public' },
];

const EMPTY_FORM = {
  title: '',
  description: '',
  location: '',
  start_at: '',
  end_at: '',
  all_day: false,
  timezone: 'America/New_York',
  color: '#3B82F6',
  rrule: '',
  reminder_minutes: '',
  visibility: 'private',
};

function toLocalDatetime(isoString) {
  if (!isoString) return '';
  const d = new Date(isoString);
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function toLocalDate(isoString) {
  if (!isoString) return '';
  return isoString.slice(0, 10);
}

export default function EventFormModal({ event, initialDate, onClose, onSaved, onDelete }) {
  const { t } = useTranslation('common');
  const isEditing = Boolean(event?.id);

  const [form, setForm] = useState(EMPTY_FORM);
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

  // Guests state — independent of form because it's a controlled list.
  const [guests, setGuests] = useState([]);
  const [guestQuery, setGuestQuery] = useState('');
  const [guestResults, setGuestResults] = useState([]);
  const [showGuestResults, setShowGuestResults] = useState(false);
  const searchTimer = useRef(null);

  useEffect(() => {
    if (event) {
      setForm({
        title: event.title ?? '',
        description: event.description ?? '',
        location: event.location ?? '',
        start_at: event.all_day ? toLocalDate(event.start_at) : toLocalDatetime(event.start_at),
        end_at: event.all_day ? toLocalDate(event.end_at) : toLocalDatetime(event.end_at),
        all_day: event.all_day ?? false,
        timezone: event.timezone ?? 'America/New_York',
        color: event.color ?? '#3B82F6',
        rrule: event.rrule ?? '',
        reminder_minutes: event.reminder_minutes != null ? String(event.reminder_minutes) : '',
        visibility: event.visibility ?? 'private',
      });
      setGuests(Array.isArray(event.attendees) ? event.attendees : []);
    } else if (initialDate) {
      const pad = (n) => String(n).padStart(2, '0');
      const d = initialDate;
      const hasTime = d.getHours() !== 0 || d.getMinutes() !== 0;
      const dateOnly = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
      const start = hasTime ? `${dateOnly}T${pad(d.getHours())}:${pad(d.getMinutes())}` : dateOnly;
      const endD = new Date(d.getTime() + 60 * 60 * 1000); // +1h
      const endDateOnly = `${endD.getFullYear()}-${pad(endD.getMonth() + 1)}-${pad(endD.getDate())}`;
      const end = hasTime ? `${endDateOnly}T${pad(endD.getHours())}:${pad(endD.getMinutes())}` : dateOnly;
      setForm({ ...EMPTY_FORM, start_at: start, end_at: end, all_day: !hasTime });
      setGuests([]);
    } else {
      setForm(EMPTY_FORM);
      setGuests([]);
    }
    setApiError('');
  }, [event, initialDate]);

  function handleChange(e) {
    const { name, value, type, checked } = e.target;
    setForm((prev) => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
  }

  function handleAllDayToggle(e) {
    const checked = e.target.checked;
    setForm((prev) => {
      const next = { ...prev, all_day: checked };
      if (checked) {
        if (prev.start_at) next.start_at = prev.start_at.slice(0, 10);
        if (prev.end_at) next.end_at = prev.end_at.slice(0, 10);
      }
      return next;
    });
  }

  // Debounced user search for the Add Guests picker.
  function handleGuestQueryChange(e) {
    const value = e.target.value;
    setGuestQuery(value);
    setShowGuestResults(true);

    if (searchTimer.current) clearTimeout(searchTimer.current);

    if (!value.trim()) {
      setGuestResults([]);
      return;
    }

    searchTimer.current = setTimeout(async () => {
      try {
        const results = await usersApi.search(value.trim());
        // Filter out already-added guests.
        const guestIds = new Set(guests.map((g) => g.id));
        setGuestResults(results.filter((u) => !guestIds.has(u.id)));
      } catch {
        setGuestResults([]);
      }
    }, 300);
  }

  function addGuest(user) {
    setGuests((prev) => (prev.some((g) => g.id === user.id) ? prev : [...prev, user]));
    setGuestQuery('');
    setGuestResults([]);
    setShowGuestResults(false);
  }

  function removeGuest(userId) {
    setGuests((prev) => prev.filter((g) => g.id !== userId));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setApiError('');

    if (!form.title.trim()) return;

    const payload = {
      title: form.title.trim(),
      description: form.description.trim() || null,
      location: form.location.trim() || null,
      start_at: form.start_at,
      end_at: form.end_at,
      all_day: form.all_day,
      timezone: form.timezone,
      color: form.color,
      visibility: form.visibility,
      rrule: form.rrule || null,
      reminder_minutes: form.reminder_minutes === '' ? null : parseInt(form.reminder_minutes, 10),
      attendee_ids: guests.map((g) => g.id),
    };

    setIsSubmitting(true);
    try {
      if (isEditing) {
        await eventsApi.updateEvent(event.id, payload);
      } else {
        await eventsApi.createEvent(payload);
      }
      onSaved();
    } catch (error) {
      const msg = error?.response?.data?.message;
      setApiError(msg ? t(msg, { defaultValue: msg }) : t('common.unexpected_error'));
    } finally {
      setIsSubmitting(false);
    }
  }

  const isSaveDisabled = isSubmitting || !form.title.trim() || !form.start_at || !form.end_at || !form.timezone;

  return (
    <div
      className="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
      onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}
    >
      <div className="relative z-50 w-full max-w-lg mx-4 bg-white rounded-2xl shadow-xl max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <h2 className="text-base font-semibold text-slate-800">
            {isEditing ? t('calendar.edit_event') : t('calendar.new_event')}
          </h2>
          <button
            type="button"
            onClick={onClose}
            aria-label={t('common.close')}
            className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form onSubmit={handleSubmit} noValidate className="flex flex-col overflow-hidden">
          <div className="px-6 py-5 space-y-4 overflow-y-auto">
            {apiError && (
              <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                <p className="text-sm text-red-700">{apiError}</p>
              </div>
            )}

            {/* Title */}
            <div>
              <label htmlFor="ev-title" className="block text-sm font-medium text-slate-700 mb-1">
                {t('calendar.fields.title')} <span className="text-red-500">{t('common.required')}</span>
              </label>
              <input
                id="ev-title"
                name="title"
                type="text"
                value={form.title}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('calendar.fields.title_placeholder')}
                maxLength={255}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition"
              />
            </div>

            {/* Description */}
            <div>
              <label htmlFor="ev-desc" className="block text-sm font-medium text-slate-700 mb-1">
                {t('calendar.fields.description')}
              </label>
              <textarea
                id="ev-desc"
                name="description"
                rows={3}
                value={form.description}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('calendar.fields.description_placeholder')}
                maxLength={5000}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition resize-none"
              />
            </div>

            {/* Location */}
            <div>
              <label htmlFor="ev-location" className="block text-sm font-medium text-slate-700 mb-1">
                {t('calendar.fields.location')}
              </label>
              <input
                id="ev-location"
                name="location"
                type="text"
                value={form.location}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('calendar.fields.location_placeholder')}
                maxLength={255}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition"
              />
            </div>

            {/* All Day Toggle */}
            <div className="flex items-center gap-2">
              <input
                id="ev-allday"
                name="all_day"
                type="checkbox"
                checked={form.all_day}
                onChange={handleAllDayToggle}
                disabled={isSubmitting}
                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
              />
              <label htmlFor="ev-allday" className="text-sm font-medium text-slate-700">
                {t('calendar.fields.all_day')}
              </label>
            </div>

            {/* Start & End */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="ev-start" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('calendar.fields.start_at')} <span className="text-red-500">{t('common.required')}</span>
                </label>
                <input
                  id="ev-start"
                  name="start_at"
                  type={form.all_day ? 'date' : 'datetime-local'}
                  value={form.start_at}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition"
                />
              </div>
              <div>
                <label htmlFor="ev-end" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('calendar.fields.end_at')} <span className="text-red-500">{t('common.required')}</span>
                </label>
                <input
                  id="ev-end"
                  name="end_at"
                  type={form.all_day ? 'date' : 'datetime-local'}
                  value={form.end_at}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition"
                />
              </div>
            </div>

            {/* Timezone */}
            <div>
              <label htmlFor="ev-timezone" className="block text-sm font-medium text-slate-700 mb-1">
                {t('calendar.fields.timezone')} <span className="text-red-500">{t('common.required')}</span>
              </label>
              <select
                id="ev-timezone"
                name="timezone"
                value={form.timezone}
                onChange={handleChange}
                disabled={isSubmitting}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition appearance-none bg-white"
              >
                <option value="">{t('calendar.fields.timezone_placeholder')}</option>
                {TIMEZONE_OPTIONS.map((group) => (
                  <optgroup key={group.group} label={group.group}>
                    {group.zones.map((tz) => (
                      <option key={tz.value} value={tz.value}>{tz.label}</option>
                    ))}
                  </optgroup>
                ))}
              </select>
            </div>

            {/* Repeat / Reminder / Privacy */}
            <div className="grid grid-cols-3 gap-3">
              <div>
                <label htmlFor="ev-rrule" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('calendar.repeat')}
                </label>
                <select
                  id="ev-rrule"
                  name="rrule"
                  value={form.rrule}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition appearance-none bg-white"
                >
                  {REPEAT_PRESETS.map((opt) => (
                    <option key={opt.value} value={opt.value}>{t(opt.labelKey)}</option>
                  ))}
                </select>
              </div>
              <div>
                <label htmlFor="ev-reminder" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('calendar.reminder')}
                </label>
                <select
                  id="ev-reminder"
                  name="reminder_minutes"
                  value={form.reminder_minutes}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition appearance-none bg-white"
                >
                  {REMINDER_PRESETS.map((opt) => (
                    <option key={opt.value} value={opt.value}>{t(opt.labelKey)}</option>
                  ))}
                </select>
              </div>
              <div>
                <label htmlFor="ev-visibility" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('calendar.privacy')}
                </label>
                <select
                  id="ev-visibility"
                  name="visibility"
                  value={form.visibility}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition appearance-none bg-white"
                >
                  {VISIBILITY_OPTIONS.map((opt) => (
                    <option key={opt.value} value={opt.value}>{t(opt.labelKey)}</option>
                  ))}
                </select>
              </div>
            </div>

            {/* Add Guests */}
            <div>
              <label htmlFor="ev-guests" className="block text-sm font-medium text-slate-700 mb-1">
                {t('calendar.add_guests')}
              </label>
              <div className="relative">
                <input
                  id="ev-guests"
                  type="text"
                  value={guestQuery}
                  onChange={handleGuestQueryChange}
                  onFocus={() => setShowGuestResults(true)}
                  onBlur={() => setTimeout(() => setShowGuestResults(false), 150)}
                  disabled={isSubmitting}
                  placeholder={t('calendar.search_users_placeholder')}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition"
                />
                {showGuestResults && guestQuery.trim() && (
                  <div className="absolute z-10 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg max-h-56 overflow-y-auto">
                    {guestResults.length === 0 ? (
                      <p className="px-3 py-2 text-xs text-slate-500">{t('calendar.no_users_found')}</p>
                    ) : (
                      guestResults.map((u) => (
                        <button
                          key={u.id}
                          type="button"
                          onMouseDown={(e) => e.preventDefault()}
                          onClick={() => addGuest(u)}
                          className="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-slate-50 transition-colors"
                        >
                          {u.avatar_url ? (
                            <img src={u.avatar_url} alt="" className="w-6 h-6 rounded-full" />
                          ) : (
                            <span className="w-6 h-6 rounded-full bg-slate-200 inline-flex items-center justify-center text-xs text-slate-600">
                              {u.name?.[0]?.toUpperCase() ?? '?'}
                            </span>
                          )}
                          <span className="flex-1 min-w-0">
                            <span className="block text-sm text-slate-800 truncate">{u.name}</span>
                            <span className="block text-xs text-slate-500 truncate">{u.email}</span>
                          </span>
                        </button>
                      ))
                    )}
                  </div>
                )}
              </div>

              {guests.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {guests.map((g) => (
                    <span
                      key={g.id}
                      className="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-blue-50 border border-blue-200 text-xs text-blue-800"
                    >
                      {g.name}
                      <button
                        type="button"
                        onClick={() => removeGuest(g.id)}
                        disabled={isSubmitting}
                        aria-label={t('common.remove')}
                        className="text-blue-500 hover:text-blue-700 disabled:opacity-50"
                      >
                        <svg className="w-3 h-3" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </span>
                  ))}
                </div>
              )}
            </div>

            {/* Color */}
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-2">
                {t('calendar.fields.color')}
              </label>
              <div className="flex flex-wrap gap-2">
                {EVENT_COLORS.map((c) => (
                  <button
                    key={c.value}
                    type="button"
                    title={t(c.labelKey)}
                    onClick={() => setForm((prev) => ({ ...prev, color: c.value }))}
                    disabled={isSubmitting}
                    className={`w-8 h-8 rounded-full border-2 transition-all ${
                      form.color === c.value
                        ? 'border-slate-800 scale-110 ring-2 ring-offset-1 ring-slate-400'
                        : 'border-transparent hover:scale-105'
                    }`}
                    style={{ backgroundColor: c.value }}
                  />
                ))}
              </div>
            </div>
          </div>

          {/* Footer */}
          <div className="flex items-center justify-between px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-2xl">
            {/* Left: Delete (edit mode only) */}
            <div>
              {isEditing && onDelete && !showDeleteConfirm && (
                <button
                  type="button"
                  onClick={() => setShowDeleteConfirm(true)}
                  disabled={isSubmitting}
                  className="px-4 py-2 rounded-lg text-sm font-medium text-red-600 bg-white border border-red-300 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  {t('common.delete')}
                </button>
              )}
              {showDeleteConfirm && (
                <div className="flex items-center gap-2">
                  <span className="text-xs text-red-600">{t('calendar.delete_confirm')}</span>
                  <button
                    type="button"
                    onClick={() => setShowDeleteConfirm(false)}
                    className="px-3 py-1 rounded text-xs font-medium text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 transition-colors"
                  >
                    {t('common.cancel')}
                  </button>
                  <button
                    type="button"
                    onClick={() => onDelete(event.id)}
                    className="px-3 py-1 rounded text-xs font-medium text-white bg-red-600 hover:bg-red-700 transition-colors"
                  >
                    {t('common.delete')}
                  </button>
                </div>
              )}
            </div>

            {/* Right: Cancel + Save */}
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={onClose}
                disabled={isSubmitting}
                className="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {t('common.cancel')}
              </button>
              <button
                type="submit"
                disabled={isSaveDisabled}
                className="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {isSubmitting
                  ? t('common.saving')
                  : (isEditing ? t('calendar.save_changes') : t('calendar.create_event'))}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
}

EventFormModal.propTypes = {
  event: PropTypes.shape({
    id: PropTypes.number,
    title: PropTypes.string,
    description: PropTypes.string,
    location: PropTypes.string,
    start_at: PropTypes.string,
    end_at: PropTypes.string,
    all_day: PropTypes.bool,
    timezone: PropTypes.string,
    color: PropTypes.string,
    rrule: PropTypes.string,
    reminder_minutes: PropTypes.number,
    visibility: PropTypes.string,
    attendees: PropTypes.arrayOf(PropTypes.shape({
      id: PropTypes.number.isRequired,
      name: PropTypes.string.isRequired,
      email: PropTypes.string,
      avatar_url: PropTypes.string,
    })),
  }),
  initialDate: PropTypes.instanceOf(Date),
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func.isRequired,
  onDelete: PropTypes.func,
};
