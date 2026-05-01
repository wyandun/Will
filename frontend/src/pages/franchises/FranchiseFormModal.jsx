import PropTypes from 'prop-types';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

const EMPTY_FORM = {
  name: '',
  email: '',
  phone: '',
  country: '',
  timezone: '',
  address: '',
};

export default function FranchiseFormModal({ franchise, onClose, onSave }) {
  const { t } = useTranslation('common');
  const isEditing = franchise !== null;

  const [form, setForm] = useState(EMPTY_FORM);
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Sincronizar el formulario cuando se abre para editar o crear
  useEffect(() => {
    if (franchise) {
      setForm({
        name: franchise.name ?? '',
        email: franchise.email ?? '',
        phone: franchise.phone ?? '',
        country: franchise.country ?? '',
        timezone: franchise.timezone ?? '',
        address: franchise.address ?? '',
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
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  }

  function validate() {
    const next = {};
    if (!form.name.trim()) next.name = t('franchises.form.name_required');
    if (form.phone.length > 30) next.phone = t('franchises.form.phone_max');
    if (form.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
      next.email = t('franchises.form.email_invalid');
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

    // Construir payload con todos los campos (en edición se envían todos para permitir blanquear)
    const payload = {
      name: form.name.trim(),
    };

    if (!isEditing) {
      payload.type = 'sm';
    }

    // Siempre incluir campos opcionales (vacíos o no) en edición para permitir limpiarlos
    if (isEditing) {
      payload.email = form.email.trim();
      payload.phone = form.phone.trim();
      payload.country = form.country.trim();
      payload.timezone = form.timezone.trim();
      payload.address = form.address.trim();
    } else {
      // En creación solo enviar si tienen contenido
      if (form.email.trim()) payload.email = form.email.trim();
      if (form.phone.trim()) payload.phone = form.phone.trim();
      if (form.country.trim()) payload.country = form.country.trim();
      if (form.timezone.trim()) payload.timezone = form.timezone.trim();
      if (form.address.trim()) payload.address = form.address.trim();
    }

    setIsSubmitting(true);
    try {
      await onSave(payload, isEditing ? franchise.id : undefined);
    } catch (error) {
      const message = error?.response?.data?.message ?? t('common.unexpected_error');
      setApiError(message);
    } finally {
      setIsSubmitting(false);
    }
  }

  const isSaveDisabled = isSubmitting || !form.name.trim();

  return (
    <div
      className="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
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

        <form onSubmit={handleSubmit} noValidate>
          <div className="px-6 py-5 space-y-4">
            {apiError && (
              <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                <p className="text-sm text-red-700">{apiError}</p>
              </div>
            )}

            {/* Name Field */}
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
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.name ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
            </div>

            {/* Email Field */}
            <div>
              <label htmlFor="fm-email" className="block text-sm font-medium text-slate-700 mb-1">
                {t('franchises.form.email')}
              </label>
              <input
                id="fm-email"
                name="email"
                type="email"
                value={form.email}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('franchises.form.email_placeholder')}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.email ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
            </div>

            {/* Country & Timezone Fields */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="fm-country" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('franchises.form.country')}
                </label>
                <input
                  id="fm-country"
                  name="country"
                  type="text"
                  value={form.country}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  placeholder={t('franchises.form.country_placeholder')}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition"
                />
              </div>
              <div>
                <label htmlFor="fm-timezone" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('franchises.form.timezone')}
                </label>
                <input
                  id="fm-timezone"
                  name="timezone"
                  type="text"
                  value={form.timezone}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  placeholder={t('franchises.form.timezone_placeholder')}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition"
                />
              </div>
            </div>

            {/* Phone Field */}
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
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.phone ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.phone && <p className="mt-1 text-xs text-red-600">{errors.phone}</p>}
            </div>

            {/* Address Field */}
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

          {/* Footer Buttons */}
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
    email: PropTypes.string,
    country: PropTypes.string,
    timezone: PropTypes.string,
    address: PropTypes.string,
    phone: PropTypes.string,
  }),
  onClose: PropTypes.func.isRequired,
  onSave: PropTypes.func.isRequired,
};