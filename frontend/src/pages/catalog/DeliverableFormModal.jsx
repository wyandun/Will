import PropTypes from 'prop-types';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

const EMPTY_FORM = {
  name_es: '',
  parent_id: '',
  estimated_hours: '',
  description_es: '',
  is_monthly: false,
};

/**
 * Modal to create or edit a deliverable.
 * A deliverable must belong to a service (parent_id is required).
 */
export default function DeliverableFormModal({ deliverable, services, onClose, onSave }) {
  const { t, i18n } = useTranslation('common');
  const isEditing = deliverable !== null && deliverable !== undefined;
  const lang = i18n.language === 'es' ? 'es' : 'en';

  const [form, setForm] = useState(EMPTY_FORM);
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (isEditing) {
      setForm({
        name_es: deliverable.name_es ?? '',
        parent_id: deliverable.parent_id != null ? String(deliverable.parent_id) : '',
        estimated_hours:
          deliverable.estimated_hours != null ? String(deliverable.estimated_hours) : '',
        description_es: deliverable.description_es ?? '',
        is_monthly: Boolean(deliverable.is_monthly),
      });
    } else {
      setForm(EMPTY_FORM);
    }
    setErrors({});
    setApiError('');
  }, [deliverable, isEditing]);

  function handleChange(e) {
    const { name, value, type, checked } = e.target;
    setForm((prev) => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  }

  function validate() {
    const next = {};
    if (!form.name_es.trim()) next.name_es = t('catalog.errors.name_required');
    if (!form.parent_id) next.parent_id = t('catalog.errors.service_required');
    const hours = Number(form.estimated_hours);
    if (!form.estimated_hours || Number.isNaN(hours) || hours < 0) {
      next.estimated_hours = t('catalog.errors.hours_required');
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
      level: 'deliverable',
      name_es: form.name_es.trim(),
      name_en: form.name_es.trim(),
      parent_id: Number(form.parent_id),
      estimated_hours: Number(form.estimated_hours),
      description_es: form.description_es.trim(),
      is_monthly: form.is_monthly,
    };

    setIsSubmitting(true);
    try {
      await onSave(payload, isEditing ? deliverable.id : undefined);
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

  return (
    <div
      className="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div className="relative z-50 w-full max-w-lg mx-4 bg-white rounded-2xl shadow-xl max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <h2 className="text-base font-semibold text-slate-800">
            {isEditing
              ? t('catalog.deliverable.edit_title')
              : t('catalog.deliverable.new_title')}
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

        <form onSubmit={handleSubmit} noValidate className="flex flex-col flex-1 overflow-hidden">
          <div className="px-6 py-5 space-y-4 overflow-y-auto">
            {apiError && (
              <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                <p className="text-sm text-red-700">{apiError}</p>
              </div>
            )}

            {/* Name */}
            <div>
              <label htmlFor="del-name-es" className="block text-sm font-medium text-slate-700 mb-1">
                {t('catalog.form.name')} <span className="text-red-500">*</span>
              </label>
              <input
                id="del-name-es"
                name="name_es"
                type="text"
                value={form.name_es}
                onChange={handleChange}
                disabled={isSubmitting}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.name_es ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.name_es && <p className="mt-1 text-xs text-red-600">{errors.name_es}</p>}
            </div>

            {/* Parent service */}
            <div>
              <label htmlFor="del-parent" className="block text-sm font-medium text-slate-700 mb-1">
                {t('catalog.form.phase')} <span className="text-red-500">*</span>
              </label>
              <select
                id="del-parent"
                name="parent_id"
                value={form.parent_id}
                onChange={handleChange}
                disabled={isSubmitting}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition bg-white ${errors.parent_id ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              >
                <option value="">{t('catalog.form.phase_placeholder')}</option>
                {services.map((s) => (
                  <option key={s.id} value={s.id}>
                    {lang === 'es' ? s.name_es : s.name_en}
                  </option>
                ))}
              </select>
              {errors.parent_id && <p className="mt-1 text-xs text-red-600">{errors.parent_id}</p>}
            </div>

            {/* Estimated hours */}
            <div>
              <label htmlFor="del-hours" className="block text-sm font-medium text-slate-700 mb-1">
                {t('catalog.form.estimated_hours')} <span className="text-red-500">*</span>
              </label>
              <input
                id="del-hours"
                name="estimated_hours"
                type="number"
                step="0.25"
                min="0"
                value={form.estimated_hours}
                onChange={handleChange}
                disabled={isSubmitting}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.estimated_hours ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.estimated_hours && (
                <p className="mt-1 text-xs text-red-600">{errors.estimated_hours}</p>
              )}
            </div>

            {/* Description */}
            <div>
              <label htmlFor="del-desc" className="block text-sm font-medium text-slate-700 mb-1">
                {t('catalog.form.description')}
              </label>
              <textarea
                id="del-desc"
                name="description_es"
                value={form.description_es}
                onChange={handleChange}
                disabled={isSubmitting}
                rows={3}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition resize-none"
              />
            </div>

            {/* Monthly toggle */}
            <div className="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
              <div>
                <p className="text-sm font-medium text-slate-700">
                  {t('catalog.form.is_monthly')}
                </p>
                <p className="text-xs text-slate-500">{t('catalog.form.is_monthly_hint')}</p>
              </div>
              <label className="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  name="is_monthly"
                  checked={form.is_monthly}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-slate-300 rounded-full peer peer-checked:bg-blue-600 peer-focus:ring-2 peer-focus:ring-blue-300 transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-transform peer-checked:after:translate-x-5"></div>
              </label>
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
              disabled={isSubmitting}
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

DeliverableFormModal.propTypes = {
  deliverable: PropTypes.shape({
    id: PropTypes.number,
    name_es: PropTypes.string,
    name_en: PropTypes.string,
    parent_id: PropTypes.number,
    estimated_hours: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
    description_es: PropTypes.string,
    is_monthly: PropTypes.bool,
  }),
  services: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.number.isRequired,
      name_es: PropTypes.string,
    })
  ).isRequired,
  onClose: PropTypes.func.isRequired,
  onSave: PropTypes.func.isRequired,
};
