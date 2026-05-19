import PropTypes from 'prop-types';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { TIMEZONE_OPTIONS } from '../../utils/timezones';
import { eventsApi } from '../../api/events';

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

const EMPTY_FORM = {
  title: '',
  description: '',
  location: '',
  start_at: '',
  end_at: '',
  all_day: false,
  timezone: 'America/New_York',
  color: '#3B82F6',
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

export default function EventFormModal({ event, onClose, onSaved }) {
  const { t } = useTranslation('common');
  const isEditing = event !== null;

  const [form, setForm] = useState(EMPTY_FORM);
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

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
      });
    } else {
      setForm(EMPTY_FORM);
    }
    setApiError('');
  }, [event]);

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
          <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-2xl">
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
              {isSubmitting ? t('common.saving') : t('common.save')}
            </button>
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
  }),
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func.isRequired,
};
