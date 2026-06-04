import PropTypes from 'prop-types';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

const EMPTY_FORM = {
  franchise_id: '',
  company_id: '',
  name: '',
  description: '',
};

/**
 * Modal for creating a new Process Map.
 *
 * Field strategy:
 *   - The form collects FRANCHISE (for filtering the clients dropdown) and
 *     CLIENT (= company). Only `company_id` is sent to the API; the backend
 *     derives the franchise from the company.
 *   - The single "Map name" field is sent as BOTH name_es and name_en for now
 *     (multilingual editing arrives in a future iteration — see plan).
 *   - `type` is hardcoded to "custom" so it does NOT collide with the
 *     auto-generated "franquiciadora" / "franquiciada" maps.
 */
export default function ProcessMapFormModal({
  franchises,
  companies,
  onClose,
  onSave,
}) {
  const { t } = useTranslation('common');

  const [form, setForm] = useState(EMPTY_FORM);
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Reset on open
  useEffect(() => {
    setForm({ ...EMPTY_FORM });
    setErrors({});
    setApiError('');
  }, []);

  // Filter companies by selected franchise. The franchise dropdown is required
  // before a client can be picked, so when no franchise is selected we show no
  // client options (and the select is disabled).
  const filteredCompanies = useMemo(() => {
    if (!form.franchise_id) return [];
    const fid = String(form.franchise_id);
    return companies.filter((c) => String(c.sm_franchise_id) === fid);
  }, [companies, form.franchise_id]);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm((prev) => {
      // Changing the franchise must clear the previously selected company,
      // since the client list is scoped to the franchise.
      if (name === 'franchise_id') {
        return { ...prev, franchise_id: value, company_id: '' };
      }
      return { ...prev, [name]: value };
    });
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  }

  function validate() {
    const next = {};
    if (!form.franchise_id) next.franchise_id = t('processMaps.modal_franchise_required');
    if (!form.company_id) next.company_id = t('processMaps.modal_client_required');
    if (!form.name.trim()) next.name = t('processMaps.modal_name_required');
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
      company_id: Number(form.company_id),
      type: 'custom',
      name_es: form.name.trim(),
      name_en: form.name.trim(),
    };
    if (form.description.trim()) {
      payload.description = form.description.trim();
    }

    setIsSubmitting(true);
    try {
      await onSave(payload);
    } catch (error) {
      // Laravel 422 validation surfaces here
      if (error?.response?.status === 422 && error?.response?.data?.errors) {
        setErrors(error.response.data.errors);
      }
      const message =
        error?.response?.data?.message ?? t('processMaps.create_error');
      setApiError(message);
    } finally {
      setIsSubmitting(false);
    }
  }

  const inputBase = [
    'w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400',
    'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent',
    'disabled:bg-slate-50 disabled:text-slate-400 transition',
  ].join(' ');

  return (
    <div
      className="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div className="relative z-50 w-full max-w-xl mx-4 bg-white rounded-2xl shadow-xl max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 shrink-0">
          <h2 className="text-base font-semibold text-slate-800">
            {t('processMaps.modal_title')}
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

        <form onSubmit={handleSubmit} noValidate className="flex flex-col flex-1 min-h-0">
          <div className="px-6 py-5 space-y-4 overflow-y-auto flex-1">
            {apiError && (
              <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                <p className="text-sm text-red-700">{apiError}</p>
              </div>
            )}

            {/* Franchise */}
            <div>
              <label htmlFor="pm-franchise" className="block text-sm font-medium text-slate-700 mb-1">
                {t('processMaps.modal_franchise_label')} <span className="text-red-500">{t('common.required')}</span>
              </label>
              <select
                id="pm-franchise"
                name="franchise_id"
                value={form.franchise_id}
                onChange={handleChange}
                disabled={isSubmitting}
                className={`${inputBase} ${errors.franchise_id ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              >
                <option value="">{t('processMaps.modal_franchise_select')}</option>
                {franchises.map((f) => (
                  <option key={f.id} value={String(f.id)}>
                    {f.name}
                  </option>
                ))}
              </select>
              {errors.franchise_id && (
                <p className="mt-1 text-xs text-red-600">{errors.franchise_id}</p>
              )}
            </div>

            {/* Client (Company) */}
            <div>
              <label htmlFor="pm-client" className="block text-sm font-medium text-slate-700 mb-1">
                {t('processMaps.modal_client_label')} <span className="text-red-500">{t('common.required')}</span>
              </label>
              <select
                id="pm-client"
                name="company_id"
                value={form.company_id}
                onChange={handleChange}
                disabled={isSubmitting || !form.franchise_id}
                className={`${inputBase} ${errors.company_id ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              >
                <option value="">
                  {form.franchise_id
                    ? t('processMaps.modal_client_select')
                    : t('processMaps.modal_client_pick_franchise')}
                </option>
                {filteredCompanies.map((c) => (
                  <option key={c.id} value={String(c.id)}>
                    {c.email ? `${c.name} — ${c.email}` : c.name}
                  </option>
                ))}
              </select>
              {errors.company_id && (
                <p className="mt-1 text-xs text-red-600">{errors.company_id}</p>
              )}
            </div>

            {/* Map name */}
            <div>
              <label htmlFor="pm-name" className="block text-sm font-medium text-slate-700 mb-1">
                {t('processMaps.modal_name_label')} <span className="text-red-500">{t('common.required')}</span>
              </label>
              <input
                id="pm-name"
                name="name"
                type="text"
                value={form.name}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('processMaps.modal_name_placeholder')}
                className={`${inputBase} ${errors.name ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.name && (
                <p className="mt-1 text-xs text-red-600">{errors.name}</p>
              )}
            </div>

            {/* Description */}
            <div>
              <label htmlFor="pm-description" className="block text-sm font-medium text-slate-700 mb-1">
                {t('processMaps.modal_description_label')}
              </label>
              <textarea
                id="pm-description"
                name="description"
                rows={3}
                value={form.description}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('processMaps.modal_description_placeholder')}
                className={`${inputBase} border-slate-300 resize-none`}
              />
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
              {t('processMaps.modal_cancel')}
            </button>
            <button
              type="submit"
              disabled={isSubmitting}
              className="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-amber-500 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {isSubmitting ? t('processMaps.creating') : t('processMaps.modal_submit')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

ProcessMapFormModal.propTypes = {
  franchises: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]).isRequired,
      name: PropTypes.string.isRequired,
    })
  ).isRequired,
  companies: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]).isRequired,
      name: PropTypes.string.isRequired,
      sm_franchise_id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
      email: PropTypes.string,
    })
  ).isRequired,
  onClose: PropTypes.func.isRequired,
  onSave: PropTypes.func.isRequired,
};
