import PropTypes from 'prop-types';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

// ─── Timezone options (IANA, grouped by region) ──────────────────────────────
const TIMEZONE_OPTIONS = [
  { group: 'Americas', zones: [
    { value: 'America/New_York',      label: 'Eastern Time (ET) — New York' },
    { value: 'America/Chicago',       label: 'Central Time (CT) — Chicago' },
    { value: 'America/Denver',        label: 'Mountain Time (MT) — Denver' },
    { value: 'America/Los_Angeles',   label: 'Pacific Time (PT) — Los Angeles' },
    { value: 'America/Anchorage',     label: 'Alaska Time — Anchorage' },
    { value: 'Pacific/Honolulu',      label: 'Hawaii Time — Honolulu' },
    { value: 'America/Toronto',       label: 'Eastern — Toronto' },
    { value: 'America/Vancouver',     label: 'Pacific — Vancouver' },
    { value: 'America/Mexico_City',   label: 'Mexico City' },
    { value: 'America/Cancun',        label: 'Cancún' },
    { value: 'America/Bogota',        label: 'Bogotá' },
    { value: 'America/Lima',          label: 'Lima' },
    { value: 'America/Santiago',      label: 'Santiago' },
    { value: 'America/Buenos_Aires',  label: 'Buenos Aires' },
    { value: 'America/Sao_Paulo',     label: 'São Paulo' },
    { value: 'America/Caracas',       label: 'Caracas' },
    { value: 'America/Panama',        label: 'Panama' },
    { value: 'America/Guayaquil',     label: 'Guayaquil' },
    { value: 'America/Santo_Domingo', label: 'Santo Domingo' },
    { value: 'America/Havana',        label: 'Havana' },
  ]},
  { group: 'Europe', zones: [
    { value: 'Europe/London',    label: 'London (GMT/BST)' },
    { value: 'Europe/Paris',     label: 'Paris (CET)' },
    { value: 'Europe/Berlin',    label: 'Berlin (CET)' },
    { value: 'Europe/Madrid',    label: 'Madrid (CET)' },
    { value: 'Europe/Rome',      label: 'Rome (CET)' },
    { value: 'Europe/Amsterdam', label: 'Amsterdam (CET)' },
    { value: 'Europe/Lisbon',    label: 'Lisbon (WET)' },
    { value: 'Europe/Moscow',    label: 'Moscow (MSK)' },
    { value: 'Europe/Istanbul',  label: 'Istanbul (TRT)' },
  ]},
  { group: 'Asia & Pacific', zones: [
    { value: 'Asia/Dubai',       label: 'Dubai (GST)' },
    { value: 'Asia/Kolkata',     label: 'India (IST)' },
    { value: 'Asia/Shanghai',    label: 'Shanghai (CST)' },
    { value: 'Asia/Tokyo',       label: 'Tokyo (JST)' },
    { value: 'Asia/Seoul',       label: 'Seoul (KST)' },
    { value: 'Asia/Singapore',   label: 'Singapore (SGT)' },
    { value: 'Asia/Hong_Kong',   label: 'Hong Kong (HKT)' },
    { value: 'Australia/Sydney', label: 'Sydney (AEST)' },
    { value: 'Pacific/Auckland', label: 'Auckland (NZST)' },
  ]},
  { group: 'Africa', zones: [
    { value: 'Africa/Cairo',        label: 'Cairo (EET)' },
    { value: 'Africa/Lagos',        label: 'Lagos (WAT)' },
    { value: 'Africa/Johannesburg', label: 'Johannesburg (SAST)' },
    { value: 'Africa/Nairobi',      label: 'Nairobi (EAT)' },
    { value: 'Africa/Casablanca',   label: 'Casablanca (WET)' },
  ]},
];

// ─── Color palette ────────────────────────────────────────────────────────────
const COLOR_OPTIONS = [
  { value: '#3B82F6', label: 'Blue' },
  { value: '#10B981', label: 'Green' },
  { value: '#F59E0B', label: 'Amber' },
  { value: '#EF4444', label: 'Red' },
  { value: '#8B5CF6', label: 'Violet' },
  { value: '#EC4899', label: 'Pink' },
  { value: '#14B8A6', label: 'Teal' },
  { value: '#6B7280', label: 'Gray' },
];

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Parse an ISO string as local time by stripping the timezone offset.
 * The backend stores whatever the user typed (no UTC conversion), so we display
 * it back as-is to avoid off-by-one-day shifts in timezones behind UTC.
 */
function parseLocal(iso) {
  if (!iso) return null;
  const local = iso.replace(/([+-]\d{2}:\d{2}|Z)$/, '');
  const d = new Date(local);
  return isNaN(d) ? null : d;
}

/** Format an ISO string to the value expected by datetime-local input */
function toDatetimeLocal(iso) {
  const d = parseLocal(iso);
  if (!d) return '';
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** Format an ISO string to "YYYY-MM-DD" for date inputs */
function toDateOnly(iso) {
  const d = parseLocal(iso);
  if (!d) return '';
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

const EMPTY_FORM = {
  title: '',
  description: '',
  location: '',
  start_at: '',
  end_at: '',
  timezone: '',
  all_day: false,
  color: '#3B82F6',
  visibility: 'private',
  type: 'casual',
};

// ─── Component ────────────────────────────────────────────────────────────────

export default function EventFormModal({ event, onClose, onSave }) {
  const { t } = useTranslation('common');
  const isEditing = event !== null;

  const [form, setForm] = useState(EMPTY_FORM);
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Sync form when opened for create or edit
  useEffect(() => {
    if (event) {
      setForm({
        title:       event.title ?? '',
        description: event.description ?? '',
        location:    event.location ?? '',
        start_at:    event.all_day ? toDateOnly(event.start_at) : toDatetimeLocal(event.start_at),
        end_at:      event.all_day ? toDateOnly(event.end_at)   : toDatetimeLocal(event.end_at),
        timezone:    event.timezone ?? '',
        all_day:     event.all_day ?? false,
        color:       event.color ?? '#3B82F6',
        visibility:  event.visibility ?? 'private',
        type:        event.type ?? 'casual',
      });
    } else {
      setForm(EMPTY_FORM);
    }
    setErrors({});
    setApiError('');
  }, [event]);

  function handleChange(e) {
    const { name, value, type: inputType, checked } = e.target;
    const newValue = inputType === 'checkbox' ? checked : value;
    setForm((prev) => {
      const next = { ...prev, [name]: newValue };
      // When toggling All Day, clear the time portions kept in state
      if (name === 'all_day') {
        next.start_at = '';
        next.end_at   = '';
      }
      return next;
    });
    if (errors[name]) setErrors((prev) => ({ ...prev, [name]: '' }));
  }

  function handleColorSelect(hex) {
    setForm((prev) => ({ ...prev, color: hex }));
  }

  function validate() {
    const next = {};
    if (!form.title.trim()) {
      next.title = t('calendar.form.title_required');
    }
    if (!form.start_at) {
      next.start_at = t('common.required');
    }
    if (!form.end_at) {
      next.end_at = t('common.required');
    }
    if (form.start_at && form.end_at && form.end_at < form.start_at) {
      next.end_at = t('calendar.form.end_before_start');
    }
    return next;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setApiError('');

    const fieldErrors = validate();
    if (Object.keys(fieldErrors).length > 0) {
      setErrors(fieldErrors);
      return;
    }

    const payload = {
      title:       form.title.trim(),
      description: form.description.trim() || null,
      location:    form.location.trim() || null,
      start_at:    form.start_at,
      end_at:      form.end_at,
      timezone:    form.timezone || null,
      all_day:     form.all_day,
      color:       form.color || null,
      visibility:  form.visibility,
      type:        form.type,
    };

    setIsSubmitting(true);
    try {
      await onSave(payload, isEditing ? event.id : undefined);
    } catch (error) {
      const msgKey = error?.response?.data?.message;
      const message = msgKey
        ? t(msgKey, { defaultValue: msgKey })
        : t('common.unexpected_error');
      setApiError(message);
    } finally {
      setIsSubmitting(false);
    }
  }

  const isSaveDisabled = isSubmitting || !form.title.trim();
  const inputClass = (field) =>
    `w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors[field] ? 'border-red-400 bg-red-50' : 'border-slate-300'}`;

  return (
    <div
      className="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
      onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}
    >
      <div className="relative z-50 w-full max-w-lg mx-4 bg-white rounded-2xl shadow-xl max-h-[90vh] flex flex-col">

        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 shrink-0">
          <h2 className="text-base font-semibold text-slate-800">
            {isEditing ? t('calendar.edit_title') : t('calendar.new_title')}
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

        {/* Scrollable form body */}
        <form onSubmit={handleSubmit} noValidate className="flex flex-col flex-1 min-h-0">
          <div className="px-6 py-5 space-y-4 overflow-y-auto flex-1">

            {apiError && (
              <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                <p className="text-sm text-red-700">{apiError}</p>
              </div>
            )}

            {/* Title */}
            <div>
              <label htmlFor="ev-title" className="block text-sm font-medium text-slate-700 mb-1">
                {t('calendar.form.title')} <span className="text-red-500">{t('common.required')}</span>
              </label>
              <input
                id="ev-title"
                name="title"
                type="text"
                value={form.title}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('calendar.form.title_placeholder')}
                className={inputClass('title')}
              />
              {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title}</p>}
            </div>

            {/* Description */}
            <div>
              <label htmlFor="ev-description" className="block text-sm font-medium text-slate-700 mb-1">
                {t('calendar.form.description')}
              </label>
              <textarea
                id="ev-description"
                name="description"
                rows={3}
                value={form.description}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('calendar.form.description_placeholder')}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition resize-none"
              />
            </div>

            {/* Location */}
            <div>
              <label htmlFor="ev-location" className="block text-sm font-medium text-slate-700 mb-1">
                {t('calendar.form.location')}
              </label>
              <input
                id="ev-location"
                name="location"
                type="text"
                value={form.location}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('calendar.form.location_placeholder')}
                className={inputClass('location')}
              />
            </div>

            {/* All Day toggle */}
            <div className="flex items-center gap-3">
              <button
                type="button"
                role="switch"
                aria-checked={form.all_day}
                onClick={() =>
                  setForm((prev) => ({ ...prev, all_day: !prev.all_day, start_at: '', end_at: '' }))
                }
                disabled={isSubmitting}
                className={`relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 ${form.all_day ? 'bg-blue-600' : 'bg-slate-300'}`}
              >
                <span
                  className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${form.all_day ? 'translate-x-4' : 'translate-x-0.5'}`}
                />
              </button>
              <span className="text-sm font-medium text-slate-700">
                {t('calendar.form.all_day')}
              </span>
            </div>

            {/* From / To */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="ev-start" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('calendar.form.start_at')} <span className="text-red-500">{t('common.required')}</span>
                </label>
                <input
                  id="ev-start"
                  name="start_at"
                  type={form.all_day ? 'date' : 'datetime-local'}
                  value={form.start_at}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className={inputClass('start_at')}
                />
                {errors.start_at && <p className="mt-1 text-xs text-red-600">{errors.start_at}</p>}
              </div>
              <div>
                <label htmlFor="ev-end" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('calendar.form.end_at')} <span className="text-red-500">{t('common.required')}</span>
                </label>
                <input
                  id="ev-end"
                  name="end_at"
                  type={form.all_day ? 'date' : 'datetime-local'}
                  value={form.end_at}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  min={form.start_at || undefined}
                  className={inputClass('end_at')}
                />
                {errors.end_at && <p className="mt-1 text-xs text-red-600">{errors.end_at}</p>}
              </div>
            </div>

            {/* Timezone */}
            <div>
              <label htmlFor="ev-timezone" className="block text-sm font-medium text-slate-700 mb-1">
                {t('calendar.form.timezone')}
              </label>
              <select
                id="ev-timezone"
                name="timezone"
                value={form.timezone}
                onChange={handleChange}
                disabled={isSubmitting}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition appearance-none bg-white"
              >
                <option value="">{t('calendar.form.timezone_placeholder')}</option>
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
                {t('calendar.form.color')}
              </label>
              <div className="flex items-center gap-2 flex-wrap">
                {COLOR_OPTIONS.map((c) => (
                  <button
                    key={c.value}
                    type="button"
                    aria-label={c.label}
                    disabled={isSubmitting}
                    onClick={() => handleColorSelect(c.value)}
                    style={{ backgroundColor: c.value }}
                    className={`w-7 h-7 rounded-full transition-transform focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 ${form.color === c.value ? 'ring-2 ring-offset-2 ring-slate-600 scale-110' : 'hover:scale-110'}`}
                  />
                ))}
                {/* Custom hex input */}
                <div className="flex items-center gap-1.5 ml-1">
                  <div
                    className="w-6 h-6 rounded-full border border-slate-300"
                    style={{ backgroundColor: form.color || 'transparent' }}
                  />
                  <input
                    type="text"
                    value={form.color}
                    onChange={(e) => setForm((prev) => ({ ...prev, color: e.target.value }))}
                    disabled={isSubmitting}
                    maxLength={10}
                    placeholder="#3B82F6"
                    className="w-24 rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50"
                  />
                </div>
              </div>
            </div>

            {/* Type + Visibility */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="ev-type" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('calendar.form.type')}
                </label>
                <select
                  id="ev-type"
                  name="type"
                  value={form.type}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition appearance-none bg-white"
                >
                  {['casual', 'meeting', 'deadline', 'reminder', 'training'].map((type) => (
                    <option key={type} value={type}>{t(`calendar.types.${type}`)}</option>
                  ))}
                </select>
              </div>
              <div>
                <label htmlFor="ev-visibility" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('calendar.form.visibility')}
                </label>
                <select
                  id="ev-visibility"
                  name="visibility"
                  value={form.visibility}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition appearance-none bg-white"
                >
                  {['private', 'franchise', 'public'].map((v) => (
                    <option key={v} value={v}>{t(`calendar.visibility.${v}`)}</option>
                  ))}
                </select>
              </div>
            </div>

          </div>

          {/* Footer */}
          <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-2xl shrink-0">
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
                ? isEditing ? t('common.saving') : t('calendar.creating')
                : isEditing ? t('common.save')   : t('calendar.create')}
            </button>
          </div>
        </form>

      </div>
    </div>
  );
}

EventFormModal.propTypes = {
  event:   PropTypes.shape({
    id:          PropTypes.number,
    title:       PropTypes.string,
    description: PropTypes.string,
    location:    PropTypes.string,
    start_at:    PropTypes.string,
    end_at:      PropTypes.string,
    timezone:    PropTypes.string,
    all_day:     PropTypes.bool,
    color:       PropTypes.string,
    visibility:  PropTypes.string,
    type:        PropTypes.string,
  }),
  onClose: PropTypes.func.isRequired,
  onSave:  PropTypes.func.isRequired,
};
