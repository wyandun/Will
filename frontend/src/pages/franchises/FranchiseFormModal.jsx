import PropTypes from 'prop-types';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

const EMPTY_FORM = {
  name: '',
  region: '',
  address: '',
  phone: '',
};

/**
 * Modal for creating and editing a franchise.
 *
 * Props:
 *   franchise  — null for create mode, franchise object for edit mode
 *   onClose    — called when the modal should be dismissed (no changes)
 *   onSave     — async fn(formData, id?) — called with cleaned payload on submit
 */
export default function FranchiseFormModal({ franchise, onClose, onSave }) {
  const { t } = useTranslation('common');
  const isEditing = franchise !== null;

  const [form, setForm] = useState(EMPTY_FORM);
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Pre-fill form when editing
  useEffect(() => {
    if (franchise) {
      setForm({
        name: franchise.name ?? '',
        region: franchise.region ?? '',
        address: franchise.address ?? '',
        phone: franchise.phone ?? '',
      });
    } else {
      setForm(EMPTY_FORM);
    }
    setErrors({});
    setApiError('');
  }, [franchise]);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    // Clear field-level error on change
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  }

  function validate() {
    const next = {};
    if (!form.name.trim()) next.name = t('franchises.form.name_required');
    if (form.phone.length > 30) next.phone = t('franchises.form.phone_max');
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

    // Build clean payload — type is only sent on create (immutable after creation)
    const payload = { name: form.name.trim() };
    if (!isEditing) payload.type = 'sm';
    if (form.region.trim()) payload.region = form.region.trim();
    if (form.address.trim()) payload.address = form.address.trim();
    if (form.phone.trim()) payload.phone = form.phone.trim();

    setIsSubmitting(true);
    try {
      await onSave(payload, isEditing ? franchise.id : undefined);
    } catch (error) {
      const message =
        error?.response?.data?.message ?? t('common.unexpected_error');
      setApiError(message);
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    /* Backdrop */
    <div
      className="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
      onMouseDown={(e) => {
        // Close when clicking directly on the backdrop
        if (e.target === e.currentTarget) onClose();
      }}
    >
      {/* Panel */}
      <div className="relative z-50 w-full max-w-lg mx-4 bg-white rounded-2xl shadow-xl">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <h2 className="text-base font-semibold text-slate-800">
            {isEditing ? t('franchises.edit_title') : t('franchises.new_title')}
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
        <form onSubmit={handleSubmit} noValidate>
          <div className="px-6 py-5 space-y-4">
            {/* API-level error */}
            {apiError && (
              <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                <p className="text-sm text-red-700">{apiError}</p>
              </div>
            )}

            {/* Name */}
            <div>
              <label htmlFor="fm-name" className="block text-sm font-medium text-slate-700 mb-1">
                {t('franchises.form.name')} <span className="text-red-500">{t('common.required')}</span>
              </label>
              <input
                id="fm-name"
                name="name"
                type="text"
                value={form.name}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('franchises.form.name_placeholder')}
                className={[
                  'w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400',
                  'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent',
                  'disabled:bg-slate-50 disabled:text-slate-400 transition',
                  errors.name ? 'border-red-400 bg-red-50' : 'border-slate-300',
                ].join(' ')}
              />
              {errors.name && (
                <p className="mt-1 text-xs text-red-600">{errors.name}</p>
              )}
            </div>

            {/* Region */}
            <div>
              <label htmlFor="fm-region" className="block text-sm font-medium text-slate-700 mb-1">
                {t('franchises.form.region')}
              </label>
              <input
                id="fm-region"
                name="region"
                type="text"
                value={form.region}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('franchises.form.region_placeholder')}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition"
              />
            </div>

            {/* Phone */}
            <div>
              <label htmlFor="fm-phone" className="block text-sm font-medium text-slate-700 mb-1">
                {t('franchises.form.phone')}
              </label>
              <input
                id="fm-phone"
                name="phone"
                type="tel"
                value={form.phone}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('franchises.form.phone_placeholder')}
                maxLength={30}
                className={[
                  'w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400',
                  'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent',
                  'disabled:bg-slate-50 disabled:text-slate-400 transition',
                  errors.phone ? 'border-red-400 bg-red-50' : 'border-slate-300',
                ].join(' ')}
              />
              {errors.phone && (
                <p className="mt-1 text-xs text-red-600">{errors.phone}</p>
              )}
            </div>

            {/* Address */}
            <div>
              <label htmlFor="fm-address" className="block text-sm font-medium text-slate-700 mb-1">
                {t('franchises.form.address')}
              </label>
              <input
                id="fm-address"
                name="address"
                type="text"
                value={form.address}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('franchises.form.address_placeholder')}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition"
              />
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
              {isSubmitting
                ? isEditing ? t('common.saving') : t('franchises.creating')
                : isEditing ? t('common.save') : t('franchises.create')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

FranchiseFormModal.propTypes = {
  franchise: PropTypes.shape({
    id: PropTypes.number,
    name: PropTypes.string,
    region: PropTypes.string,
    address: PropTypes.string,
    phone: PropTypes.string,
  }),
  onClose: PropTypes.func.isRequired,
  onSave: PropTypes.func.isRequired,
};
