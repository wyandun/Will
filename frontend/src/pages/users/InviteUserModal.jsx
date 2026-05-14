import { useState } from 'react';
import PropTypes from 'prop-types';
import { useTranslation } from 'react-i18next';
import { invitationsApi } from '../../api/invitations';

const INVITABLE_ROLES = [
  'admin_sm',
  'sb_owner',
  'sb_employee',
  'bb_employee',
  'sub_franchise_owner',
  'sub_franchise_admin',
  'system_admin',
  'system_admin_readonly',
];

const INITIAL_FORM = { name: '', email: '', role: 'admin_sm' };

export default function InviteUserModal({ isOpen, onClose, onSuccess }) {
  const { t } = useTranslation('common');

  const [form, setForm]         = useState(INITIAL_FORM);
  const [errors, setErrors]     = useState({});
  const [submitting, setSubmitting] = useState(false);
  const [apiError, setApiError]  = useState('');

  // Shown in dev when email is not configured
  const [devLink, setDevLink]   = useState('');
  const [copied, setCopied]     = useState(false);

  function handleClose() {
    setForm(INITIAL_FORM);
    setErrors({});
    setApiError('');
    setDevLink('');
    setCopied(false);
    onClose();
  }

  function validate() {
    const errs = {};
    if (!form.name.trim()) errs.name = t('invitation.form.name_required');
    if (!form.email.trim()) {
      errs.email = t('invitation.form.email_required');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      errs.email = t('invitation.form.email_invalid');
    }
    if (!form.role) errs.role = t('invitation.form.role_required');
    return errs;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setApiError('');
    setDevLink('');

    const errs = validate();
    if (Object.keys(errs).length) {
      setErrors(errs);
      return;
    }
    setErrors({});
    setSubmitting(true);

    try {
      const res = await invitationsApi.sendInvitation(form);

      // In dev/staging the backend returns the activation URL so the admin
      // can share it manually (no mail service needed).
      if (res.data?.activation_url) {
        setDevLink(res.data.activation_url);
      } else {
        onSuccess(res);
        handleClose();
      }
    } catch (err) {
      const apiErrors = err?.response?.data?.errors;
      if (apiErrors) {
        // Map backend validation errors to fields
        const mapped = {};
        Object.entries(apiErrors).forEach(([key, msgs]) => {
          mapped[key] = msgs[0];
        });
        setErrors(mapped);
      } else {
        setApiError(
          err?.response?.data?.message || t('common.unexpected_error'),
        );
      }
    } finally {
      setSubmitting(false);
    }
  }

  async function copyLink() {
    try {
      await navigator.clipboard.writeText(devLink);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // clipboard API not available in some contexts
    }
  }

  if (!isOpen) return null;

  // ── Dev-mode success: show activation link ─────────────────────────────────
  if (devLink) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
        <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
          <div className="bg-green-50 border-b border-green-100 px-6 py-5">
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <div>
                <h2 className="text-base font-semibold text-slate-800">{t('invitation.sent_title')}</h2>
                <p className="text-sm text-slate-500">{t('invitation.dev_link_hint')}</p>
              </div>
            </div>
          </div>

          <div className="px-6 py-5 space-y-4">
            <div>
              <label className="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">
                {t('invitation.activation_link')}
              </label>
              <div className="flex gap-2">
                <input
                  readOnly
                  value={devLink}
                  className="flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700 font-mono focus:outline-none"
                />
                <button
                  onClick={copyLink}
                  className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors"
                >
                  {copied ? t('invitation.copied') : t('invitation.copy')}
                </button>
              </div>
            </div>

            <p className="text-xs text-slate-400">{t('invitation.dev_note')}</p>

            <div className="flex justify-end gap-3 pt-1">
              <button
                onClick={() => { setDevLink(''); setForm(INITIAL_FORM); }}
                className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
              >
                {t('invitation.invite_another')}
              </button>
              <button
                onClick={() => { onSuccess(); handleClose(); }}
                className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 transition-colors"
              >
                {t('common.close')}
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // ── Invitation form ────────────────────────────────────────────────────────
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

        {/* Header */}
        <div className="flex items-center justify-between px-6 py-5 border-b border-slate-100">
          <div>
            <h2 className="text-base font-semibold text-slate-800">{t('invitation.modal_title')}</h2>
            <p className="text-sm text-slate-500 mt-0.5">{t('invitation.modal_subtitle')}</p>
          </div>
          <button
            onClick={handleClose}
            className="text-slate-400 hover:text-slate-600 transition-colors"
            aria-label={t('common.close')}
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Info banner */}
        <div className="mx-6 mt-5 rounded-lg bg-blue-50 border border-blue-100 px-4 py-3 text-sm text-blue-700">
          {t('invitation.mail_info')}
        </div>

        <form onSubmit={handleSubmit} className="px-6 pb-6 pt-5 space-y-4">

          {apiError && (
            <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
              {apiError}
            </div>
          )}

          {/* Name */}
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              {t('invitation.form.name')}
              <span className="text-red-400 ml-0.5">{t('common.required')}</span>
            </label>
            <input
              type="text"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              placeholder={t('invitation.form.name_placeholder')}
              className={`w-full rounded-lg border px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200 ${
                errors.name ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-white focus:border-slate-400'
              }`}
            />
            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
          </div>

          {/* Email */}
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              {t('invitation.form.email')}
              <span className="text-red-400 ml-0.5">{t('common.required')}</span>
            </label>
            <input
              type="email"
              value={form.email}
              onChange={(e) => setForm((f) => ({ ...f, email: e.target.value.toLowerCase() }))}
              placeholder={t('invitation.form.email_placeholder')}
              className={`w-full rounded-lg border px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200 ${
                errors.email ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-white focus:border-slate-400'
              }`}
            />
            {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
          </div>

          {/* Role */}
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              {t('invitation.form.role')}
              <span className="text-red-400 ml-0.5">{t('common.required')}</span>
            </label>
            <select
              value={form.role}
              onChange={(e) => setForm((f) => ({ ...f, role: e.target.value }))}
              className={`w-full rounded-lg border px-3.5 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-200 ${
                errors.role ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-white focus:border-slate-400'
              }`}
            >
              {INVITABLE_ROLES.map((r) => (
                <option key={r} value={r}>
                  {t(`roles.${r}`, { defaultValue: r })}
                </option>
              ))}
            </select>
            {errors.role && <p className="mt-1 text-xs text-red-600">{errors.role}</p>}
          </div>

          {/* Actions */}
          <div className="flex justify-end gap-3 pt-2">
            <button
              type="button"
              onClick={handleClose}
              className="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
            >
              {t('common.cancel')}
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
            >
              {submitting ? t('invitation.sending') : t('invitation.send_btn')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

InviteUserModal.propTypes = {
  isOpen:    PropTypes.bool.isRequired,
  onClose:   PropTypes.func.isRequired,
  onSuccess: PropTypes.func.isRequired,
};
