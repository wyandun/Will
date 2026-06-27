import PropTypes from 'prop-types';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { companiesApi } from '../../api/companies';

const CATALOG_TYPES = ['bundle', 'service', 'deliverable'];

const EMPTY_FORM = {
  franchise_id: '',
  company_id: '',
  type: 'bundle',
  catalog_item_id: '',
  start_date: '',
  notes: '',
};

/**
 * Modal to assign a catalog bundle, service, or deliverable to a client company.
 * On submit, creates a project and auto-generates its deliverable schedule.
 *
 * Props:
 *   franchises   — list of all franchises (id, name)
 *   catalogTree  — { bundles, services } from GET /catalog-items/tree
 *   onClose      — called when user cancels or closes the modal
 *   onSave       — async fn(data) called with validated payload; should call the API
 */
export default function AssignServiceModal({ franchises, catalogTree, onClose, onSave }) {
  const { t, i18n } = useTranslation('common');
  const lang = i18n.language === 'es' ? 'es' : 'en';

  const [form, setForm] = useState(EMPTY_FORM);
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [companies, setCompanies] = useState([]);
  const [loadingCompanies, setLoadingCompanies] = useState(false);

  // Reset company when franchise changes and load companies for that franchise
  useEffect(() => {
    if (!form.franchise_id) {
      setCompanies([]);
      return;
    }

    setLoadingCompanies(true);
    setForm((prev) => ({ ...prev, company_id: '' }));

    companiesApi
      .getCompaniesByFranchise(Number(form.franchise_id))
      .then((data) => setCompanies(Array.isArray(data) ? data : []))
      .catch(() => setCompanies([]))
      .finally(() => setLoadingCompanies(false));
  }, [form.franchise_id]);

  // Reset catalog_item_id when type changes
  useEffect(() => {
    setForm((prev) => ({ ...prev, catalog_item_id: '' }));
  }, [form.type]);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  }

  function setType(type) {
    setForm((prev) => ({ ...prev, type }));
    if (errors.type) {
      setErrors((prev) => ({ ...prev, type: '' }));
    }
  }

  function validate() {
    const next = {};
    if (!form.franchise_id) next.franchise_id = t('tracking.errors.franchise_required');
    if (!form.company_id) next.company_id = t('tracking.errors.company_required');
    if (!form.catalog_item_id) next.catalog_item_id = t('tracking.errors.catalog_item_required');
    if (!form.start_date) next.start_date = t('tracking.errors.start_date_required');
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
      franchise_id: Number(form.franchise_id),
      company_id: Number(form.company_id),
      catalog_item_id: Number(form.catalog_item_id),
      type: form.type,
      start_date: form.start_date,
      notes: form.notes.trim() || null,
    };

    setIsSubmitting(true);
    try {
      await onSave(payload);
    } catch (error) {
      const msg = error?.response?.data?.message;
      setApiError(msg ? t(msg, { defaultValue: msg }) : t('common.unexpected_error'));
    } finally {
      setIsSubmitting(false);
    }
  }

  // Catalog options based on selected type
  const catalogOptions = (() => {
    switch (form.type) {
      case 'bundle':
        return (catalogTree?.bundles ?? []).map((b) => ({
          id: b.id,
          label: lang === 'es' ? b.name_es : b.name_en,
        }));
      case 'service':
        return (catalogTree?.services ?? []).map((s) => ({
          id: s.id,
          label: lang === 'es' ? s.name_es : s.name_en,
        }));
      case 'deliverable':
        return (catalogTree?.services ?? []).flatMap((s) =>
          (s.children ?? []).map((d) => ({
            id: d.id,
            label: `${lang === 'es' ? s.name_es : s.name_en} — ${lang === 'es' ? d.name_es : d.name_en}`,
          }))
        );
      default:
        return [];
    }
  })();

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
            {t('tracking.assign_modal.title')}
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

        {/* Form */}
        <form onSubmit={handleSubmit} noValidate className="flex flex-col flex-1 overflow-hidden">
          <div className="px-6 py-5 space-y-4 overflow-y-auto">
            {apiError && (
              <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                <p className="text-sm text-red-700">{apiError}</p>
              </div>
            )}

            {/* FRANCHISE */}
            <div>
              <label htmlFor="assign-franchise" className="block text-sm font-medium text-slate-700 mb-1">
                {t('tracking.assign_modal.franchise')} <span className="text-red-500">*</span>
              </label>
              <select
                id="assign-franchise"
                name="franchise_id"
                value={form.franchise_id}
                onChange={handleChange}
                disabled={isSubmitting}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.franchise_id ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              >
                <option value="">{t('tracking.assign_modal.select_franchise')}</option>
                {franchises.map((f) => (
                  <option key={f.id} value={f.id}>{f.name}</option>
                ))}
              </select>
              {errors.franchise_id && (
                <p className="mt-1 text-xs text-red-600">{errors.franchise_id}</p>
              )}
            </div>

            {/* CLIENT / COMPANY */}
            <div>
              <label htmlFor="assign-company" className="block text-sm font-medium text-slate-700 mb-1">
                {t('tracking.assign_modal.client')} <span className="text-red-500">*</span>
              </label>
              <select
                id="assign-company"
                name="company_id"
                value={form.company_id}
                onChange={handleChange}
                disabled={isSubmitting || !form.franchise_id || loadingCompanies}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.company_id ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              >
                <option value="">
                  {loadingCompanies
                    ? t('common.loading')
                    : !form.franchise_id
                      ? t('tracking.assign_modal.select_franchise_first')
                      : t('tracking.assign_modal.select_client')}
                </option>
                {companies.map((c) => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>
              {errors.company_id && (
                <p className="mt-1 text-xs text-red-600">{errors.company_id}</p>
              )}
            </div>

            {/* TYPE segmented control */}
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-2">
                {t('tracking.assign_modal.type')} <span className="text-red-500">*</span>
              </label>
              <div className="inline-flex rounded-lg border border-slate-300 bg-slate-50 p-1 w-full">
                {CATALOG_TYPES.map((type) => {
                  const active = form.type === type;
                  return (
                    <button
                      key={type}
                      type="button"
                      onClick={() => setType(type)}
                      disabled={isSubmitting}
                      className={`flex-1 px-3 py-1.5 rounded-md text-sm font-medium transition-colors capitalize ${active
                        ? 'bg-white text-slate-800 shadow-sm'
                        : 'text-slate-500 hover:text-slate-700'
                      }`}
                    >
                      {t(`tracking.catalog_type.${type}`, { defaultValue: type })}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Dynamic catalog item dropdown */}
            <div>
              <label htmlFor="assign-catalog-item" className="block text-sm font-medium text-slate-700 mb-1">
                {t(`tracking.assign_modal.catalog_item_label.${form.type}`, {
                  defaultValue: t('tracking.assign_modal.catalog_item'),
                })}{' '}
                <span className="text-red-500">*</span>
              </label>
              <select
                id="assign-catalog-item"
                name="catalog_item_id"
                value={form.catalog_item_id}
                onChange={handleChange}
                disabled={isSubmitting}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.catalog_item_id ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              >
                <option value="">{t('tracking.assign_modal.select_item')}</option>
                {catalogOptions.map((opt) => (
                  <option key={opt.id} value={opt.id}>{opt.label}</option>
                ))}
              </select>
              {errors.catalog_item_id && (
                <p className="mt-1 text-xs text-red-600">{errors.catalog_item_id}</p>
              )}
              {catalogOptions.length === 0 && (
                <p className="mt-1 text-xs text-amber-600">
                  {t('tracking.assign_modal.no_items_in_catalog')}
                </p>
              )}
            </div>

            {/* START DATE */}
            <div>
              <label htmlFor="assign-start-date" className="block text-sm font-medium text-slate-700 mb-1">
                {t('tracking.assign_modal.start_date')} <span className="text-red-500">*</span>
              </label>
              <input
                id="assign-start-date"
                name="start_date"
                type="date"
                value={form.start_date}
                onChange={handleChange}
                disabled={isSubmitting}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.start_date ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.start_date && (
                <p className="mt-1 text-xs text-red-600">{errors.start_date}</p>
              )}
            </div>

            {/* NOTES */}
            <div>
              <label htmlFor="assign-notes" className="block text-sm font-medium text-slate-700 mb-1">
                {t('tracking.assign_modal.notes')}
              </label>
              <textarea
                id="assign-notes"
                name="notes"
                value={form.notes}
                onChange={handleChange}
                disabled={isSubmitting}
                rows={3}
                placeholder={t('tracking.assign_modal.notes_placeholder')}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition resize-none"
              />
            </div>
          </div>

          {/* Footer buttons */}
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
              {isSubmitting
                ? t('common.saving')
                : t('tracking.assign_modal.submit')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

AssignServiceModal.propTypes = {
  /** List of franchises to populate the franchise dropdown */
  franchises: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.number.isRequired,
      name: PropTypes.string.isRequired,
    })
  ).isRequired,
  /** Catalog tree from GET /catalog-items/tree */
  catalogTree: PropTypes.shape({
    bundles: PropTypes.array,
    services: PropTypes.array,
  }).isRequired,
  onClose: PropTypes.func.isRequired,
  /** async fn(payload) — should call projectsApi.createProject and close on success */
  onSave: PropTypes.func.isRequired,
};
