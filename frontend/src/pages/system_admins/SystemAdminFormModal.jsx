import PropTypes from 'prop-types';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

const SYSTEM_ADMIN_FIELDS = ['name', 'email', 'password', 'role'];
const EMPTY_FORM = Object.fromEntries(SYSTEM_ADMIN_FIELDS.map(f => [f, '']));

export default function SystemAdminFormModal({ onClose, onSave, initialData }) {
  const { t } = useTranslation('common');
  
  const isEditing = Boolean(initialData);

  const [form, setForm] = useState(
    initialData
      ? {
          name: initialData.name,
          email: initialData.email,
          password: '',
          role: initialData.roles?.[0]?.name ?? 'system_admin',
        }
      : { ...EMPTY_FORM, role: 'system_admin' }
  );
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  }

  function validate() {
    const next = {};
    if (!form.name.trim()) next.name = t('system_admins.form.name_required');
    if (!form.email.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      next.email = t('system_admins.form.email_invalid');
    }
    if (!isEditing && (!form.password || form.password.length < 12)) {
      next.password = t('system_admins.form.password_min');
    } else if (isEditing && form.password && form.password.length < 12) {
      next.password = t('system_admins.form.password_min');
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
      name: form.name.trim(),
      email: form.email.trim(),
      password: form.password ? form.password : undefined,
      role: form.role,
    };

    setIsSubmitting(true);
    try {
      await onSave(payload, initialData?.id);
    } catch (error) {
      const messageKey = error?.response?.data?.message;
      const message = messageKey ? t(messageKey, messageKey) : t('common.unexpected_error');
      setApiError(message);
    } finally {
      setIsSubmitting(false);
    }
  }

  const isSaveDisabled = isSubmitting;

  return (
    <div
      className="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div className="relative z-50 w-full max-w-lg mx-4 bg-white rounded-2xl shadow-xl overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <h2 className="text-base font-semibold text-slate-800">
            {isEditing ? t('system_admins.edit_title') : t('system_admins.new_title')}
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

        {/* Security Warning */}
        <div className="bg-amber-50 border-b border-amber-200 px-6 py-4 flex items-start gap-3">
          <svg className="w-6 h-6 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <div className="text-sm text-amber-800">
            <p className="font-semibold">{t('system_admins.warning_title')}</p>
            <p className="mt-1 opacity-90">{t('system_admins.warning_desc')}</p>
          </div>
        </div>

        <form onSubmit={handleSubmit} noValidate autoComplete="off">
          <div className="px-6 py-5 space-y-4">
            {apiError && (
              <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                <p className="text-sm text-red-700">{apiError}</p>
              </div>
            )}

            {/* Role Field */}
            <div>
              <label htmlFor="fm-role" className="block text-sm font-medium text-slate-700 mb-1">
                {t('system_admins.form.role')} <span className="text-red-500">{t('common.required')}</span>
              </label>
              <select
                id="fm-role"
                name="role"
                value={form.role}
                onChange={handleChange}
                disabled={isSubmitting}
                className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition bg-white"
              >
                <option value="system_admin">{t('roles.system_admin')}</option>
                <option value="system_admin_readonly">{t('roles.system_admin_readonly')}</option>
              </select>
            </div>

            {/* Name Field */}
            <div>
              <label htmlFor="fm-name" className="block text-sm font-medium text-slate-700 mb-1">
                {t('system_admins.form.name')} <span className="text-red-500">{t('common.required')}</span>
              </label>
              <input
                id="fm-name"
                name="name"
                type="text"
                value={form.name}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('system_admins.form.name_placeholder')}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.name ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
            </div>

            {/* Email Field */}
            <div>
              <label htmlFor="fm-email" className="block text-sm font-medium text-slate-700 mb-1">
                {t('system_admins.form.email')} <span className="text-red-500">{t('common.required')}</span>
              </label>
              <input
                id="fm-email"
                name="email"
                type="email"
                value={form.email}
                onChange={handleChange}
                disabled={isSubmitting}
                placeholder={t('system_admins.form.email_placeholder')}
                autoComplete="off"
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.email ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
            </div>

            {/* Password Field */}
            <div>
              <label htmlFor="fm-password" className="block text-sm font-medium text-slate-700 mb-1">
                {t('system_admins.form.password')} {!isEditing && <span className="text-red-500">{t('common.required')}</span>}
              </label>
              <div className="relative">
                <input
                  id="fm-password"
                  name="password"
                  type={showPassword ? "text" : "password"}
                  value={form.password}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  placeholder={isEditing ? t('system_admins.form.password_optional') : t('system_admins.form.password_placeholder')}
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
                ? t(isEditing ? 'common.saving' : 'system_admins.creating') 
                : t(isEditing ? 'common.save' : 'system_admins.create')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

SystemAdminFormModal.propTypes = {
  onClose: PropTypes.func.isRequired,
  onSave: PropTypes.func.isRequired,
  initialData: PropTypes.object,
};
