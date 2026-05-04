import PropTypes from 'prop-types';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useAuthStore } from '../../store/authStore';
import { franchisesApi } from '../../api/franchises';

const EMPTY_FORM = {
  name: '',
  sm_franchise_id: '',
  industry: '',
  phone: '',
  email: '',
  city: '',
  state: '',
  country: '',
  address: '',
  notes: '',
};

/**
 * Modal for creating and editing a company.
 *
 * Props:
 *   company    — null for create mode, company object for edit mode
 *   onClose    — called when the modal should be dismissed (no changes)
 *   onSave     — async fn(formData, id?) — called with cleaned payload on submit
 */
CompanyFormModal.propTypes = {
  company: PropTypes.shape({
    id: PropTypes.number,
    name: PropTypes.string,
    sm_franchise_id: PropTypes.number,
    industry: PropTypes.string,
    phone: PropTypes.string,
    email: PropTypes.string,
    city: PropTypes.string,
    state: PropTypes.string,
    country: PropTypes.string,
    address: PropTypes.string,
    notes: PropTypes.string,
  }),
  onClose: PropTypes.func.isRequired,
  onSave: PropTypes.func.isRequired,
};

export default function CompanyFormModal({ company, onClose, onSave }) {
  const { t } = useTranslation('common');
  const isEditing = company !== null;
  const role = useAuthStore((s) => s.role);
  const isAdminSm = role === 'admin_sm';

  const [form, setForm] = useState(EMPTY_FORM);
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [franchises, setFranchises] = useState([]);
  const [franchisesLoading, setFranchisesLoading] = useState(true);

  // Load franchises for the dropdown
  useEffect(() => {
    setFranchisesLoading(true);
    franchisesApi
      .getFranchises()
      .then(({ data }) => setFranchises(Array.isArray(data) ? data : []))
      .catch(() => setFranchises([]))
      .finally(() => setFranchisesLoading(false));
  }, []);

  // Pre-fill form when editing or reset form for create mode.
  // Note: admin_sm franchise auto-selection is handled in the effect below,
  // after the franchises list finishes loading.
  useEffect(() => {
    if (company) {
      setForm({
        name: company.name ?? '',
        sm_franchise_id: company.sm_franchise_id ? String(company.sm_franchise_id) : '',
        industry: company.industry ?? '',
        phone: company.phone ?? '',
        email: company.email ?? '',
        city: company.city ?? '',
        state: company.state ?? '',
        country: company.country ?? '',
        address: company.address ?? '',
        notes: company.notes ?? '',
      });
    } else {
      setForm({ ...EMPTY_FORM });
    }
    setErrors({});
    setApiError('');
  }, [company]);

  // After franchises load, auto-select the only available franchise for admin_sm
  // in create mode. Edit mode already has sm_franchise_id from the company object.
  useEffect(() => {
    if (franchisesLoading) return;
    if (company) return; // edit mode — do not override the company's franchise
    if (!isAdminSm) return; // superadmin picks manually
    if (franchises.length === 1) {
      setForm((prev) => ({ ...prev, sm_franchise_id: String(franchises[0].id) }));
    }
  }, [franchisesLoading, franchises, company, isAdminSm]);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  }

  function validate() {
    const next = {};
    if (!form.name.trim()) next.name = t('companies.form.name_required');
    if (!form.sm_franchise_id) next.sm_franchise_id = t('companies.form.franchise_required');
    if (form.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      next.email = t('companies.form.email_invalid');
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

    // Build payload. When editing, always include optional fields so the
    // backend (which uses `sometimes` rules) can clear them when the user
    // removes a value. When creating, omit empty fields entirely.
    const payload = {
      name: form.name.trim(),
      sm_franchise_id: Number(form.sm_franchise_id),
    };

    if (isEditing) {
      payload.industry = form.industry.trim() || null;
      payload.phone    = form.phone.trim()    || null;
      payload.email    = form.email.trim()    || null;
      payload.city     = form.city.trim()     || null;
      payload.state    = form.state.trim()    || null;
      payload.country  = form.country.trim()  || null;
      payload.address  = form.address.trim()  || null;
      payload.notes    = form.notes.trim()    || null;
    } else {
      if (form.industry.trim()) payload.industry = form.industry.trim();
      if (form.phone.trim())    payload.phone    = form.phone.trim();
      if (form.email.trim())    payload.email    = form.email.trim();
      if (form.city.trim())     payload.city     = form.city.trim();
      if (form.state.trim())    payload.state    = form.state.trim();
      if (form.country.trim())  payload.country  = form.country.trim();
      if (form.address.trim())  payload.address  = form.address.trim();
      if (form.notes.trim())    payload.notes    = form.notes.trim();
    }

    setIsSubmitting(true);
    try {
      await onSave(payload, isEditing ? company.id : undefined);
    } catch (error) {
      const message =
        error?.response?.data?.message ?? t('common.unexpected_error');
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
    /* Backdrop */
    <div
      className="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      {/* Panel */}
      <div className="relative z-50 w-full max-w-2xl mx-4 bg-white rounded-2xl shadow-xl max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 shrink-0">
          <h2 className="text-base font-semibold text-slate-800">
            {isEditing ? t('companies.edit_title') : t('companies.new_title')}
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
        <form onSubmit={handleSubmit} noValidate className="flex flex-col flex-1 min-h-0">
          <div className="px-6 py-5 space-y-4 overflow-y-auto flex-1">
            {/* API-level error */}
            {apiError && (
              <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                <p className="text-sm text-red-700">{apiError}</p>
              </div>
            )}

            {/* Name */}
            <div>
              <label htmlFor="cf-name" className="block text-sm font-medium text-slate-700 mb-1">
                {t('companies.form.name')} <span className="text-red-500">{t('common.required')}</span>
              </label>
              <input
                id="cf-name"
                name="name"
                type="text"
                value={form.name}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('companies.form.name_placeholder')}
                className={`${inputBase} ${errors.name ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.name && (
                <p className="mt-1 text-xs text-red-600">{errors.name}</p>
              )}
            </div>

            {/* Franchise */}
            <div>
              <label htmlFor="cf-franchise" className="block text-sm font-medium text-slate-700 mb-1">
                {t('companies.form.franchise')} <span className="text-red-500">{t('common.required')}</span>
              </label>
              <select
                id="cf-franchise"
                name="sm_franchise_id"
                value={form.sm_franchise_id}
                onChange={handleChange}
                disabled={isSubmitting || isAdminSm || franchisesLoading}
                className={`${inputBase} ${errors.sm_franchise_id ? 'border-red-400 bg-red-50' : 'border-slate-300'} ${isAdminSm ? 'cursor-not-allowed' : ''}`}
              >
                <option value="">
                  {franchisesLoading ? t('companies.form.franchise_loading') : t('companies.form.franchise_select')}
                </option>
                {franchises.map((f) => (
                  <option key={f.id} value={String(f.id)}>
                    {f.name}
                  </option>
                ))}
              </select>
              {errors.sm_franchise_id && (
                <p className="mt-1 text-xs text-red-600">{errors.sm_franchise_id}</p>
              )}
            </div>

            {/* Industry + Phone — 2 columns */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="cf-industry" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('companies.form.industry')}
                </label>
                <input
                  id="cf-industry"
                  name="industry"
                  type="text"
                  value={form.industry}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  placeholder={t('companies.form.industry_placeholder')}
                  className={`${inputBase} border-slate-300`}
                />
              </div>
              <div>
                <label htmlFor="cf-phone" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('companies.form.phone')}
                </label>
                <input
                  id="cf-phone"
                  name="phone"
                  type="tel"
                  value={form.phone}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  placeholder={t('companies.form.phone_placeholder')}
                  className={`${inputBase} border-slate-300`}
                />
              </div>
            </div>

            {/* Email */}
            <div>
              <label htmlFor="cf-email" className="block text-sm font-medium text-slate-700 mb-1">
                {t('companies.form.email')}
              </label>
              <input
                id="cf-email"
                name="email"
                type="email"
                value={form.email}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('companies.form.email_placeholder')}
                className={`${inputBase} ${errors.email ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.email && (
                <p className="mt-1 text-xs text-red-600">{errors.email}</p>
              )}
            </div>

            {/* City + State — 2 columns */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="cf-city" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('companies.form.city')}
                </label>
                <input
                  id="cf-city"
                  name="city"
                  type="text"
                  value={form.city}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  placeholder={t('companies.form.city_placeholder')}
                  className={`${inputBase} border-slate-300`}
                />
              </div>
              <div>
                <label htmlFor="cf-state" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('companies.form.state')}
                </label>
                <input
                  id="cf-state"
                  name="state"
                  type="text"
                  value={form.state}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  placeholder={t('companies.form.state_placeholder')}
                  className={`${inputBase} border-slate-300`}
                />
              </div>
            </div>

            {/* Country */}
            <div>
              <label htmlFor="cf-country" className="block text-sm font-medium text-slate-700 mb-1">
                {t('companies.form.country')}
              </label>
              <input
                id="cf-country"
                name="country"
                type="text"
                value={form.country}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('companies.form.country_placeholder')}
                className={`${inputBase} border-slate-300`}
              />
            </div>

            {/* Address */}
            <div>
              <label htmlFor="cf-address" className="block text-sm font-medium text-slate-700 mb-1">
                {t('companies.form.address')}
              </label>
              <input
                id="cf-address"
                name="address"
                type="text"
                value={form.address}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('companies.form.address_placeholder')}
                className={`${inputBase} border-slate-300`}
              />
            </div>

            {/* Notes */}
            <div>
              <label htmlFor="cf-notes" className="block text-sm font-medium text-slate-700 mb-1">
                {t('companies.form.notes')}
              </label>
              <textarea
                id="cf-notes"
                name="notes"
                rows={3}
                value={form.notes}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('companies.form.notes_placeholder')}
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
              {t('common.cancel')}
            </button>
            <button
              type="submit"
              disabled={isSubmitting}
              className="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {isSubmitting
                ? isEditing ? t('common.saving') : t('companies.creating')
                : isEditing ? t('common.save') : t('companies.create')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
