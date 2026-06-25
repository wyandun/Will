import PropTypes from 'prop-types';
import { useState, useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const SERVICE_TYPES = ['individual', 'package', 'retainer'];

const EMPTY_FORM = {
  name_es: '',
  service_type: 'individual',
  description_es: '',
  deliverable_ids: [],
};

/**
 * Modal to create or edit a service.
 * Deliverables can be assigned (and reassigned away from their current service).
 */
export default function ServiceFormModal({ service, services, onClose, onSave }) {
  const { t, i18n } = useTranslation('common');
  const isEditing = service !== null && service !== undefined;
  const lang = i18n.language === 'es' ? 'es' : 'en';

  const [form, setForm] = useState(EMPTY_FORM);
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Group all deliverables by their current service for the picker
  const deliverablesByService = useMemo(() => {
    return services.map((s) => ({
      service: s,
      deliverables: Array.isArray(s.children) ? s.children : [],
    }));
  }, [services]);

  useEffect(() => {
    if (isEditing) {
      const currentDeliverableIds = Array.isArray(service.children)
        ? service.children.map((c) => c.id)
        : [];
      setForm({
        name_es: service.name_es ?? '',
        service_type: service.service_type ?? 'individual',
        description_es: service.description_es ?? '',
        deliverable_ids: currentDeliverableIds,
      });
    } else {
      setForm(EMPTY_FORM);
    }
    setErrors({});
    setApiError('');
  }, [service, isEditing]);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  }

  function toggleDeliverable(id) {
    setForm((prev) => {
      const exists = prev.deliverable_ids.includes(id);
      return {
        ...prev,
        deliverable_ids: exists
          ? prev.deliverable_ids.filter((x) => x !== id)
          : [...prev.deliverable_ids, id],
      };
    });
  }

  function validate() {
    const next = {};
    if (!form.name_es.trim()) next.name_es = t('catalog.errors.name_required');
    if (!SERVICE_TYPES.includes(form.service_type)) {
      next.service_type = t('catalog.errors.type_required');
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
      level: 'service',
      name_es: form.name_es.trim(),
      name_en: form.name_es.trim(),
      service_type: form.service_type,
      description_es: form.description_es.trim(),
      deliverable_ids: form.deliverable_ids,
    };

    setIsSubmitting(true);
    try {
      await onSave(payload, isEditing ? service.id : undefined);
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
      <div className="relative z-50 w-full max-w-2xl mx-4 bg-white rounded-2xl shadow-xl max-h-[90vh] flex flex-col">
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <h2 className="text-base font-semibold text-slate-800">
            {isEditing
              ? t('catalog.service.edit_title')
              : t('catalog.service.new_title')}
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

            <div>
              <label htmlFor="srv-name-es" className="block text-sm font-medium text-slate-700 mb-1">
                {t('catalog.form.name')} <span className="text-red-500">*</span>
              </label>
              <input
                id="srv-name-es"
                name="name_es"
                type="text"
                value={form.name_es}
                onChange={handleChange}
                disabled={isSubmitting}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.name_es ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.name_es && <p className="mt-1 text-xs text-red-600">{errors.name_es}</p>}
            </div>

            {/* Service type toggle */}
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-2">
                {t('catalog.form.service_type')} <span className="text-red-500">*</span>
              </label>
              <div className="inline-flex rounded-lg border border-slate-300 bg-slate-50 p-1">
                {SERVICE_TYPES.map((type) => {
                  const active = form.service_type === type;
                  return (
                    <button
                      key={type}
                      type="button"
                      onClick={() => setForm((prev) => ({ ...prev, service_type: type }))}
                      disabled={isSubmitting}
                      className={`px-4 py-1.5 rounded-md text-sm font-medium transition-colors ${active
                          ? 'bg-white text-slate-800 shadow-sm'
                          : 'text-slate-500 hover:text-slate-700'
                        }`}
                    >
                      {t(`catalog.service_type.${type}`)}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Description */}
            <div>
              <label htmlFor="srv-desc" className="block text-sm font-medium text-slate-700 mb-1">
                {t('catalog.form.description')}
              </label>
              <textarea
                id="srv-desc"
                name="description_es"
                value={form.description_es}
                onChange={handleChange}
                disabled={isSubmitting}
                rows={3}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition resize-none"
              />
            </div>

            {/* Deliverables list */}
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-2">
                {t('catalog.form.deliverables')}{' '}
                <span className="text-xs font-normal text-slate-500">
                  ({form.deliverable_ids.length} {t('catalog.selected')})
                </span>
              </label>
              <p className="text-xs text-slate-500 mb-2">
                {t('catalog.form.deliverables_hint')}
              </p>
              <div className="rounded-lg border border-slate-200 bg-white max-h-72 overflow-y-auto divide-y divide-slate-100">
                {deliverablesByService.length === 0 && (
                  <p className="px-4 py-6 text-center text-sm text-slate-400">
                    {t('catalog.no_deliverables')}
                  </p>
                )}
                {deliverablesByService.map(({ service: parentService, deliverables }) => (
                  deliverables.length > 0 && (
                    <div key={parentService.id} className="px-4 py-3">
                      <p className="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">
                        {lang === 'es' ? parentService.name_es : parentService.name_en}
                      </p>
                      <div className="space-y-1.5">
                        {deliverables.map((d) => {
                          const checked = form.deliverable_ids.includes(d.id);
                          return (
                            <label
                              key={d.id}
                              className="flex items-start gap-2 px-2 py-1.5 rounded hover:bg-slate-50 cursor-pointer"
                            >
                              <input
                                type="checkbox"
                                checked={checked}
                                onChange={() => toggleDeliverable(d.id)}
                                disabled={isSubmitting}
                                className="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                              />
                              <div className="flex-1 min-w-0">
                                <p className="text-sm text-slate-800 truncate">
                                  {lang === 'es' ? d.name_es : d.name_en}
                                </p>
                              </div>
                              <span className="text-xs text-slate-500">
                                {d.estimated_hours ?? 0}h
                              </span>
                            </label>
                          );
                        })}
                      </div>
                    </div>
                  )
                ))}
              </div>
            </div>
          </div>

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

ServiceFormModal.propTypes = {
  service: PropTypes.shape({
    id: PropTypes.number,
    name_es: PropTypes.string,
    name_en: PropTypes.string,
    service_type: PropTypes.string,
    description_es: PropTypes.string,
    children: PropTypes.array,
  }),
  services: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.number.isRequired,
      name_es: PropTypes.string,
      name_en: PropTypes.string,
      children: PropTypes.array,
    })
  ).isRequired,
  onClose: PropTypes.func.isRequired,
  onSave: PropTypes.func.isRequired,
};
