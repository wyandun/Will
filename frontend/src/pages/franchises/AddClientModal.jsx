import PropTypes from 'prop-types';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { franchisesApi } from '../../api/franchises';

const REQUIRES_SUB_FRANCHISE = ['sub_franchise_owner', 'sub_franchise_admin'];

const CLIENT_ROLE_OPTIONS = [
  { value: 'sb_owner',             labelKey: 'roles.sb_owner' },
  { value: 'bb_employee',          labelKey: 'roles.bb_employee' },
  { value: 'sub_franchise_owner',  labelKey: 'roles.sub_franchise_owner' },
  { value: 'sub_franchise_admin',  labelKey: 'roles.sub_franchise_admin' },
];

export default function AddClientModal({ onClose, onSave, franchiseId }) {
  const { t } = useTranslation('common');
  const [form, setForm] = useState({ name: '', email: '', role: '', sub_franchise_id: '' });
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [subFranchises, setSubFranchises] = useState([]);
  const [loadingSubFranchises, setLoadingSubFranchises] = useState(false);

  const needsSubFranchise = REQUIRES_SUB_FRANCHISE.includes(form.role);

  useEffect(() => {
    if (!needsSubFranchise || !franchiseId) {
      setSubFranchises([]);
      return;
    }
    setLoadingSubFranchises(true);
    franchisesApi.getSubFranchisesForFranchise(franchiseId)
      .then(setSubFranchises)
      .catch(() => setSubFranchises([]))
      .finally(() => setLoadingSubFranchises(false));
  }, [needsSubFranchise, franchiseId]);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm((prev) => ({
      ...prev,
      [name]: value,
      // Reset sub_franchise_id when role changes to one that doesn't need it
      ...(name === 'role' && !REQUIRES_SUB_FRANCHISE.includes(value) ? { sub_franchise_id: '' } : {}),
    }));
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
    if (!form.role) next.role = t('franchise_detail.role_required');
    if (needsSubFranchise && !form.sub_franchise_id) {
      next.sub_franchise_id = t('franchise_detail.sub_franchise_required');
    }
    return next;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setApiError('');
    const fieldErrors = validate();
    if (Object.keys(fieldErrors).length > 0) { setErrors(fieldErrors); return; }

    const payload = {
      name: form.name.trim(),
      email: form.email.trim().toLowerCase(),
      role: form.role,
    };
    if (needsSubFranchise && form.sub_franchise_id) {
      payload.sub_franchise_id = parseInt(form.sub_franchise_id, 10);
    }

    setIsSubmitting(true);
    try {
      await onSave(payload);
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
      <div className="relative z-50 w-full max-w-lg mx-4 bg-white rounded-2xl shadow-xl">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <h2 className="text-base font-semibold text-slate-800">{t('franchise_detail.client_modal_title')}</h2>
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

            {/* Name */}
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">
                {t('franchise_detail.field_name')} <span className="text-red-500">*</span>
              </label>
              <input
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
              <label className="block text-sm font-medium text-slate-700 mb-1">
                {t('franchise_detail.field_email')} <span className="text-red-500">*</span>
              </label>
              <input
                name="email"
                type="email"
                value={form.email}
                onChange={handleChange}
                disabled={isSubmitting}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.email ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
            </div>

            {/* Role */}
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">
                {t('franchise_detail.field_role')} <span className="text-red-500">*</span>
              </label>
              <select
                name="role"
                value={form.role}
                onChange={handleChange}
                disabled={isSubmitting}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition appearance-none bg-white ${errors.role ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              >
                <option value="">—</option>
                {CLIENT_ROLE_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>{t(opt.labelKey)}</option>
                ))}
              </select>
              {errors.role && <p className="mt-1 text-xs text-red-600">{errors.role}</p>}
            </div>

            {/* Sub-franchise selector — shown when role requires it */}
            {needsSubFranchise && (
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">
                  {t('franchise_detail.field_sub_franchise')} <span className="text-red-500">*</span>
                </label>
                <select
                  name="sub_franchise_id"
                  value={form.sub_franchise_id}
                  onChange={handleChange}
                  disabled={isSubmitting || loadingSubFranchises}
                  className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition appearance-none bg-white ${errors.sub_franchise_id ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
                >
                  <option value="">
                    {loadingSubFranchises ? t('common.loading') : '—'}
                  </option>
                  {subFranchises.map((sf) => (
                    <option key={sf.id} value={sf.id}>{sf.name}</option>
                  ))}
                </select>
                {errors.sub_franchise_id && <p className="mt-1 text-xs text-red-600">{errors.sub_franchise_id}</p>}
                {!loadingSubFranchises && subFranchises.length === 0 && (
                  <p className="mt-1 text-xs text-amber-600">{t('franchise_detail.no_sub_franchises')}</p>
                )}
              </div>
            )}
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
              {isSubmitting ? t('franchise_detail.sending_invitation') : t('franchise_detail.send_invitation')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

AddClientModal.propTypes = {
  onClose: PropTypes.func.isRequired,
  onSave: PropTypes.func.isRequired,
  franchiseId: PropTypes.number,
};
