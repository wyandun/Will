import PropTypes from 'prop-types';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

const CLIENT_ROLE_OPTIONS = [
  { value: 'sb_owner',    labelKey: 'roles.sb_owner' },
  { value: 'bb_employee', labelKey: 'roles.bb_employee' },
];

export default function AddClientModal({ onClose, onSave, defaultRole, sbOwners = [] }) {
  const { t } = useTranslation('common');
  const [form, setForm] = useState({
    name: '',
    email: '',
    role: defaultRole || '',
    job_title: '',
    company_name: '',
    company_tax_id: '',
    company_phone: '',
    sb_owner_id: '',
  });
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const effectiveRole = form.role;
  const isSbOwner = effectiveRole === 'sb_owner';
  const isInvestor = effectiveRole === 'bb_employee';

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
    if (!form.role) next.role = t('franchise_detail.role_required');

    if (isSbOwner && !form.company_name.trim()) {
      next.company_name = t('franchise_detail.company_name_required');
    }
    if (isInvestor && !form.sb_owner_id) {
      next.sb_owner_id = t('franchise_detail.sb_owner_required');
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
    if (form.job_title.trim()) payload.job_title = form.job_title.trim();

    if (isSbOwner) {
      payload.company_name = form.company_name.trim();
      if (form.company_tax_id.trim()) payload.company_tax_id = form.company_tax_id.trim();
      if (form.company_phone.trim()) payload.company_phone = form.company_phone.trim();
    }
    if (isInvestor) {
      payload.sb_owner_id = parseInt(form.sb_owner_id, 10);
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

  const inputClass = (hasError) =>
    `w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${hasError ? 'border-red-400 bg-red-50' : 'border-slate-300'}`;

  return (
    <div
      className="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
      onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}
    >
      <div className="relative z-50 w-full max-w-lg mx-4 bg-white rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto">
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
                className={inputClass(errors.name)}
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
                className={inputClass(errors.email)}
              />
              {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
            </div>

            {/* Role — hidden when defaultRole is provided (tab determines role) */}
            {!defaultRole && (
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">
                  {t('franchise_detail.field_role')} <span className="text-red-500">*</span>
                </label>
                <select
                  name="role"
                  value={form.role}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className={`${inputClass(errors.role)} appearance-none bg-white`}
                >
                  <option value="">—</option>
                  {CLIENT_ROLE_OPTIONS.map((opt) => (
                    <option key={opt.value} value={opt.value}>{t(opt.labelKey)}</option>
                  ))}
                </select>
                {errors.role && <p className="mt-1 text-xs text-red-600">{errors.role}</p>}
              </div>
            )}

            {/* Position / job title (both roles) */}
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">
                {t('franchise_detail.field_position')}
              </label>
              <input
                name="job_title"
                type="text"
                value={form.job_title}
                onChange={handleChange}
                disabled={isSubmitting}
                className={inputClass(errors.job_title)}
              />
              {errors.job_title && <p className="mt-1 text-xs text-red-600">{errors.job_title}</p>}
            </div>

            {/* SB Owner: company fields */}
            {isSbOwner && (
              <div className="space-y-4 pt-2 border-t border-slate-100">
                <h3 className="text-sm font-semibold text-slate-700">{t('franchise_detail.company_section')}</h3>

                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">
                    {t('franchise_detail.field_company_name')} <span className="text-red-500">*</span>
                  </label>
                  <input
                    name="company_name"
                    type="text"
                    value={form.company_name}
                    onChange={handleChange}
                    disabled={isSubmitting}
                    className={inputClass(errors.company_name)}
                  />
                  {errors.company_name && <p className="mt-1 text-xs text-red-600">{errors.company_name}</p>}
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">
                    {t('franchise_detail.field_company_tax_id')}
                  </label>
                  <input
                    name="company_tax_id"
                    type="text"
                    value={form.company_tax_id}
                    onChange={handleChange}
                    disabled={isSubmitting}
                    className={inputClass(errors.company_tax_id)}
                  />
                  {errors.company_tax_id && <p className="mt-1 text-xs text-red-600">{errors.company_tax_id}</p>}
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">
                    {t('franchise_detail.field_company_phone')}
                  </label>
                  <input
                    name="company_phone"
                    type="text"
                    value={form.company_phone}
                    onChange={handleChange}
                    disabled={isSubmitting}
                    className={inputClass(errors.company_phone)}
                  />
                  {errors.company_phone && <p className="mt-1 text-xs text-red-600">{errors.company_phone}</p>}
                </div>
              </div>
            )}

            {/* Investor: pick SB Owner */}
            {isInvestor && (
              <div className="pt-2 border-t border-slate-100">
                <label className="block text-sm font-medium text-slate-700 mb-1">
                  {t('franchise_detail.field_sb_owner')} <span className="text-red-500">*</span>
                </label>
                {sbOwners.length === 0 ? (
                  <p className="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    {t('franchise_detail.no_sb_owners_help')}
                  </p>
                ) : (
                  <select
                    name="sb_owner_id"
                    value={form.sb_owner_id}
                    onChange={handleChange}
                    disabled={isSubmitting}
                    className={`${inputClass(errors.sb_owner_id)} appearance-none bg-white`}
                  >
                    <option value="">{t('franchise_detail.select_sb_owner')}</option>
                    {sbOwners.map((owner) => (
                      <option key={owner.id} value={owner.id}>
                        {owner.name}{owner.company?.name ? ` — ${owner.company.name}` : ''}
                      </option>
                    ))}
                  </select>
                )}
                {errors.sb_owner_id && <p className="mt-1 text-xs text-red-600">{errors.sb_owner_id}</p>}
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
              disabled={isSubmitting || (isInvestor && sbOwners.length === 0)}
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
  defaultRole: PropTypes.oneOf(['sb_owner', 'bb_employee']),
  sbOwners: PropTypes.arrayOf(PropTypes.shape({
    id: PropTypes.number.isRequired,
    name: PropTypes.string.isRequired,
    company: PropTypes.shape({ name: PropTypes.string }),
  })),
};
