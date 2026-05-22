import PropTypes from 'prop-types';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

const AREA_OPTIONS = [
  'full_access',
  'accounting',
  'marketing',
  'operations',
  'legal',
  'human_resources',
];

export default function EditAdminModal({ admin, franchiseId, onClose, onSave }) {
  const { t } = useTranslation('common');

  const [form, setForm] = useState({
    name: admin.name ?? '',
    email: admin.email ?? '',
    phone: admin.phone ?? '',
    job_title: admin.job_title ?? '',
    area: admin.area ?? '',
    password: '',
  });
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [showPasswordSection, setShowPasswordSection] = useState(false);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) setErrors((prev) => ({ ...prev, [name]: '' }));
  }

  function validate() {
    const next = {};
    if (!form.name.trim()) next.name = t('franchise_detail.name_required');
    if (!form.email.trim()) {
      next.email = t('franchise_detail.email_required');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      next.email = t('franchise_detail.email_invalid');
    }
    if (showPasswordSection && form.password && form.password.length < 12) {
      next.password = t('franchise_detail.password_min');
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

    const profilePayload = {
      name: form.name.trim(),
      email: form.email.trim().toLowerCase(),
      phone: form.phone.trim() || null,
      job_title: form.job_title.trim() || null,
      area: form.area || null,
    };

    const passwordPayload = showPasswordSection && form.password
      ? { password: form.password }
      : null;

    setIsSubmitting(true);
    try {
      await onSave(profilePayload, passwordPayload, admin.id, franchiseId);
    } catch (error) {
      const laravelErrors = error?.response?.data?.errors;
      if (laravelErrors) {
        const mapped = {};
        Object.entries(laravelErrors).forEach(([field, msgs]) => {
          mapped[field] = Array.isArray(msgs) ? msgs[0] : msgs;
        });
        setErrors(mapped);
      } else {
        const msgKey = error?.response?.data?.message;
        setApiError(msgKey ? t(msgKey, { defaultValue: msgKey }) : t('common.unexpected_error'));
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div
      className="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
      onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}
    >
      <div className="relative z-50 w-full max-w-lg mx-4 bg-white rounded-2xl shadow-xl overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <h2 className="text-base font-semibold text-slate-800">
            {t('franchise_detail.edit_admin_title')}
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

        <form onSubmit={handleSubmit} noValidate autoComplete="off">
          <div className="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
            {apiError && (
              <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                <p className="text-sm text-red-700">{apiError}</p>
              </div>
            )}

            {/* Name */}
            <div>
              <label htmlFor="ea-name" className="block text-sm font-medium text-slate-700 mb-1">
                {t('franchise_detail.field_name')} <span className="text-red-500">*</span>
              </label>
              <input
                id="ea-name"
                name="name"
                type="text"
                value={form.name}
                onChange={handleChange}
                disabled={isSubmitting}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.name ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
            </div>

            {/* Email */}
            <div>
              <label htmlFor="ea-email" className="block text-sm font-medium text-slate-700 mb-1">
                {t('franchise_detail.field_email')} <span className="text-red-500">*</span>
              </label>
              <input
                id="ea-email"
                name="email"
                type="email"
                value={form.email}
                onChange={handleChange}
                disabled={isSubmitting}
                autoComplete="off"
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.email ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
            </div>

            {/* Phone */}
            <div>
              <label htmlFor="ea-phone" className="block text-sm font-medium text-slate-700 mb-1">
                {t('franchise_detail.field_phone')}
              </label>
              <input
                id="ea-phone"
                name="phone"
                type="text"
                value={form.phone}
                onChange={handleChange}
                disabled={isSubmitting}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition"
              />
            </div>

            {/* Job Title */}
            <div>
              <label htmlFor="ea-job_title" className="block text-sm font-medium text-slate-700 mb-1">
                {t('franchise_detail.field_job_title')}
              </label>
              <input
                id="ea-job_title"
                name="job_title"
                type="text"
                value={form.job_title}
                onChange={handleChange}
                disabled={isSubmitting}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition"
              />
            </div>

            {/* Area */}
            <div>
              <label htmlFor="ea-area" className="block text-sm font-medium text-slate-700 mb-1">
                {t('franchise_detail.field_area')}
              </label>
              <select
                id="ea-area"
                name="area"
                value={form.area}
                onChange={handleChange}
                disabled={isSubmitting}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition bg-white"
              >
                <option value="">{t('franchise_detail.area_none')}</option>
                {AREA_OPTIONS.map((area) => (
                  <option key={area} value={area}>
                    {t(`franchise_detail.area_${area}`)}
                  </option>
                ))}
              </select>
            </div>

            {/* Password Section (collapsible) */}
            <div className="border-t border-slate-200 pt-4">
              <button
                type="button"
                onClick={() => setShowPasswordSection(!showPasswordSection)}
                className="flex items-center gap-2 text-sm font-medium text-slate-700 hover:text-slate-900 transition-colors"
              >
                <svg
                  className={`w-4 h-4 transition-transform ${showPasswordSection ? 'rotate-90' : ''}`}
                  fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"
                >
                  <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
                {t('franchise_detail.password_section')}
              </button>

              {showPasswordSection && (
                <div className="mt-3">
                  <div className="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 mb-3">
                    <p className="text-xs text-amber-700">{t('franchise_detail.password_warning')}</p>
                  </div>
                  <label htmlFor="ea-password" className="block text-sm font-medium text-slate-700 mb-1">
                    {t('franchise_detail.password_label')}
                  </label>
                  <div className="relative">
                    <input
                      id="ea-password"
                      name="password"
                      type={showPassword ? 'text' : 'password'}
                      value={form.password}
                      onChange={handleChange}
                      disabled={isSubmitting}
                      placeholder={t('franchise_detail.password_placeholder')}
                      autoComplete="new-password"
                      className={`w-full rounded-lg border px-3 py-2.5 pr-10 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.password ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"
                    >
                      {showPassword ? (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                      ) : (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                          <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                      )}
                    </button>
                  </div>
                  {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                </div>
              )}
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

EditAdminModal.propTypes = {
  admin: PropTypes.object.isRequired,
  franchiseId: PropTypes.number.isRequired,
  onClose: PropTypes.func.isRequired,
  onSave: PropTypes.func.isRequired,
};
