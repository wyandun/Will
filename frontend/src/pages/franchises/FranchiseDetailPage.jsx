import PropTypes from 'prop-types';
import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuthStore } from '../../store/authStore';
import { franchisesApi } from '../../api/franchises';
import FranchiseFormModal from './FranchiseFormModal';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getInitials(name) {
  if (!name) return '?';
  return name
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('');
}

function getAvatarColor(name) {
  const colors = [
    'bg-blue-500', 'bg-emerald-500', 'bg-violet-500', 'bg-amber-500',
    'bg-rose-500', 'bg-cyan-500', 'bg-indigo-500', 'bg-teal-500',
  ];
  let hash = 0;
  for (let i = 0; i < (name || '').length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return colors[Math.abs(hash) % colors.length];
}

function PageSpinner() {
  return (
    <div className="flex items-center justify-center py-24">
      <svg className="w-7 h-7 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </div>
  );
}

function InlineSpinner() {
  return (
    <div className="flex items-center justify-center py-10">
      <svg className="w-6 h-6 text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24">
        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </div>
  );
}

// ─── Area options ─────────────────────────────────────────────────────────────

const AREA_VALUES = [
  'full_access',
  'accounting',
  'marketing',
  'operations',
  'legal',
  'human_resources',
];

// ─── Add Admin Modal ──────────────────────────────────────────────────────────

function AddAdminModal({ franchiseId, onClose, onSaved }) {
  const { t } = useTranslation('common');
  const [form, setForm] = useState({
    name: '', email: '', password: '', area: '', phone: '', position: '',
  });
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const [serverError, setServerError] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  function setField(field, value) {
    setForm((prev) => ({ ...prev, [field]: value }));
    setErrors((prev) => ({ ...prev, [field]: '' }));
  }

  function validate() {
    const e = {};
    if (!form.name.trim()) e.name = t('franchise_detail.form.full_name_required');
    if (!form.email.trim()) e.email = t('franchise_detail.form.email_required');
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) e.email = t('franchise_detail.form.email_invalid');
    if (!form.password) e.password = t('franchise_detail.form.password_required');
    else if (form.password.length < 8) e.password = t('franchise_detail.form.password_min');
    if (!form.area) e.area = t('franchise_detail.form.area_required');
    return e;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    const fieldErrors = validate();
    if (Object.keys(fieldErrors).length) { setErrors(fieldErrors); return; }
    setSubmitting(true);
    setServerError('');
    try {
      await franchisesApi.addAdmin(franchiseId, form);
      onSaved();
    } catch (err) {
      setServerError(err?.response?.data?.message ?? t('common.unexpected_error'));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div className="px-6 pt-6 pb-4 border-b border-slate-100">
          <h2 className="text-lg font-semibold text-slate-800">{t('franchise_detail.modal_admin_title')}</h2>
        </div>
        <form onSubmit={handleSubmit} className="px-6 py-5 space-y-4">
          {serverError && (
            <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
              {serverError}
            </div>
          )}

          {/* Name */}
          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
              {t('franchise_detail.form.full_name')} <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={form.name}
              onChange={(e) => setField('name', e.target.value)}
              placeholder={t('franchise_detail.form.full_name_placeholder')}
              className="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
          </div>

          {/* Email */}
          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
              {t('franchise_detail.form.email')} <span className="text-red-500">*</span>
            </label>
            <input
              type="email"
              value={form.email}
              onChange={(e) => setField('email', e.target.value)}
              placeholder={t('franchise_detail.form.email_placeholder')}
              autoComplete="off"
              className="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
          </div>

          {/* Password */}
          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
              {t('franchise_detail.form.password')} <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              <input
                type={showPassword ? 'text' : 'password'}
                value={form.password}
                onChange={(e) => setField('password', e.target.value)}
                placeholder={t('franchise_detail.form.password_placeholder')}
                autoComplete="new-password"
                className="w-full px-3 py-2 pr-10 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <button
                type="button"
                onClick={() => setShowPassword((v) => !v)}
                className="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600"
                tabIndex={-1}
              >
                {showPassword ? (
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                  </svg>
                ) : (
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                )}
              </button>
            </div>
            {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
          </div>

          {/* Area */}
          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
              {t('franchise_detail.form.area')} <span className="text-red-500">*</span>
            </label>
            <select
              value={form.area}
              onChange={(e) => setField('area', e.target.value)}
              className="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">{t('franchise_detail.form.area_select')}</option>
              {AREA_VALUES.map((val) => (
                <option key={val} value={val}>
                  {t(`franchise_detail.areas.${val}`)}
                </option>
              ))}
            </select>
            {errors.area && <p className="mt-1 text-xs text-red-600">{errors.area}</p>}
          </div>

          {/* Phone + Position */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
                {t('franchise_detail.form.phone')}
              </label>
              <input
                type="text"
                value={form.phone}
                onChange={(e) => setField('phone', e.target.value)}
                placeholder={t('franchise_detail.form.phone_placeholder')}
                className="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
                {t('franchise_detail.form.position')}
              </label>
              <input
                type="text"
                value={form.position}
                onChange={(e) => setField('position', e.target.value)}
                placeholder={t('franchise_detail.form.position_placeholder')}
                className="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          {/* Actions */}
          <div className="flex items-center justify-end gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors"
            >
              {t('common.cancel')}
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-60 transition-colors"
            >
              {submitting ? t('common.saving') : t('common.save')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

AddAdminModal.propTypes = {
  franchiseId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func.isRequired,
};

// ─── Add Client Modal ─────────────────────────────────────────────────────────

function AddClientModal({ franchiseId, onClose, onSaved }) {
  const { t } = useTranslation('common');
  const [form, setForm] = useState({
    name: '', email: '', password: '', client_type: 'owner', phone: '', position: '',
  });
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const [serverError, setServerError] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  function setField(field, value) {
    setForm((prev) => ({ ...prev, [field]: value }));
    setErrors((prev) => ({ ...prev, [field]: '' }));
  }

  function validate() {
    const e = {};
    if (!form.name.trim()) e.name = t('franchise_detail.form.full_name_required');
    if (!form.email.trim()) e.email = t('franchise_detail.form.email_required');
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) e.email = t('franchise_detail.form.email_invalid');
    if (!form.password) e.password = t('franchise_detail.form.password_required');
    else if (form.password.length < 8) e.password = t('franchise_detail.form.password_min');
    return e;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    const fieldErrors = validate();
    if (Object.keys(fieldErrors).length) { setErrors(fieldErrors); return; }
    setSubmitting(true);
    setServerError('');
    try {
      await franchisesApi.addClient(franchiseId, form);
      onSaved();
    } catch (err) {
      setServerError(err?.response?.data?.message ?? t('common.unexpected_error'));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div className="px-6 pt-6 pb-4 border-b border-slate-100">
          <h2 className="text-lg font-semibold text-slate-800">{t('franchise_detail.modal_client_title')}</h2>
        </div>
        <form onSubmit={handleSubmit} className="px-6 py-5 space-y-4">
          {serverError && (
            <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
              {serverError}
            </div>
          )}

          {/* Name */}
          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
              {t('franchise_detail.form.full_name')} <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={form.name}
              onChange={(e) => setField('name', e.target.value)}
              placeholder={t('franchise_detail.form.full_name_placeholder')}
              className="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
          </div>

          {/* Email */}
          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
              {t('franchise_detail.form.email')} <span className="text-red-500">*</span>
            </label>
            <input
              type="email"
              value={form.email}
              onChange={(e) => setField('email', e.target.value)}
              placeholder={t('franchise_detail.form.email_placeholder')}
              autoComplete="off"
              className="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
          </div>

          {/* Password */}
          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
              {t('franchise_detail.form.password')} <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              <input
                type={showPassword ? 'text' : 'password'}
                value={form.password}
                onChange={(e) => setField('password', e.target.value)}
                placeholder={t('franchise_detail.form.password_placeholder')}
                autoComplete="new-password"
                className="w-full px-3 py-2 pr-10 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <button
                type="button"
                onClick={() => setShowPassword((v) => !v)}
                className="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600"
                tabIndex={-1}
              >
                {showPassword ? (
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                  </svg>
                ) : (
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                )}
              </button>
            </div>
            {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
          </div>

          {/* Client type toggle */}
          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
              {t('franchise_detail.form.client_type')}
            </label>
            <div className="inline-flex rounded-lg border border-slate-300 overflow-hidden">
              <button
                type="button"
                onClick={() => setField('client_type', 'owner')}
                className={`px-4 py-2 text-sm font-medium transition-colors ${
                  form.client_type === 'owner'
                    ? 'bg-blue-600 text-white'
                    : 'bg-white text-slate-600 hover:bg-slate-50'
                }`}
              >
                {t('franchise_detail.form.client_type_owner')}
              </button>
              <button
                type="button"
                onClick={() => setField('client_type', 'investor')}
                className={`px-4 py-2 text-sm font-medium transition-colors border-l border-slate-300 ${
                  form.client_type === 'investor'
                    ? 'bg-blue-600 text-white'
                    : 'bg-white text-slate-600 hover:bg-slate-50'
                }`}
              >
                {t('franchise_detail.form.client_type_investor')}
              </button>
            </div>
          </div>

          {/* Phone + Position */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
                {t('franchise_detail.form.phone')}
              </label>
              <input
                type="text"
                value={form.phone}
                onChange={(e) => setField('phone', e.target.value)}
                placeholder={t('franchise_detail.form.phone_placeholder')}
                className="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
                {t('franchise_detail.form.position')}
              </label>
              <input
                type="text"
                value={form.position}
                onChange={(e) => setField('position', e.target.value)}
                placeholder={t('franchise_detail.form.position_placeholder')}
                className="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          {/* Actions */}
          <div className="flex items-center justify-end gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors"
            >
              {t('common.cancel')}
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-60 transition-colors"
            >
              {submitting ? t('common.saving') : t('common.save')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

AddClientModal.propTypes = {
  franchiseId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func.isRequired,
};

// ─── Admins Panel ─────────────────────────────────────────────────────────────

function AdminsPanel({ franchiseId, admins, isLoading, fetchError, onReload, isSuperadmin }) {
  const { t } = useTranslation('common');
  const [showModal, setShowModal] = useState(false);
  const [successMsg, setSuccessMsg] = useState('');

  function handleSaved() {
    setShowModal(false);
    setSuccessMsg(t('franchise_detail.admin_created'));
    onReload();
    setTimeout(() => setSuccessMsg(''), 4000);
  }

  return (
    <div className="bg-white rounded-xl border border-slate-200 shadow-sm">
      {/* Header */}
      <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <div>
          <h2 className="text-base font-semibold text-slate-800">{t('franchise_detail.admins_title')}</h2>
          {!isLoading && (
            <p className="text-sm text-slate-500 mt-0.5">
              {admins.length}{' '}
              {admins.length === 1
                ? t('franchise_detail.admins_subtitle_one')
                : t('franchise_detail.admins_subtitle_other')}
            </p>
          )}
        </div>
        {isSuperadmin && (
          <button
            onClick={() => setShowModal(true)}
            className="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            {t('franchise_detail.add_admin')}
          </button>
        )}
      </div>

      <div className="px-6 py-4">
        {successMsg && (
          <div className="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {successMsg}
          </div>
        )}

        {isLoading && <InlineSpinner />}

        {!isLoading && fetchError && (
          <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 flex items-center justify-between">
            <span>{fetchError}</span>
            <button onClick={onReload} className="text-xs underline ml-3">{t('common.try_again')}</button>
          </div>
        )}

        {!isLoading && !fetchError && admins.length === 0 && (
          <div className="flex flex-col items-center justify-center py-10 text-center">
            <svg className="w-10 h-10 text-slate-300 mb-3" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            </svg>
            <p className="text-sm font-medium text-slate-700">{t('franchise_detail.no_admins')}</p>
            <p className="text-sm text-slate-400 mt-1">{t('franchise_detail.no_admins_sub')}</p>
          </div>
        )}

        {!isLoading && !fetchError && admins.length > 0 && (
          <ul className="divide-y divide-slate-100">
            {admins.map((admin) => (
              <li key={admin.id} className="py-3 flex items-center gap-3">
                <div className={`w-9 h-9 rounded-full ${getAvatarColor(admin.name)} flex items-center justify-center shrink-0`}>
                  <span className="text-white text-xs font-bold">{getInitials(admin.name)}</span>
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-slate-800 truncate">{admin.name}</p>
                  <p className="text-xs text-slate-500 truncate">{admin.email}</p>
                </div>
                {admin.area && (
                  <span className="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                    {t(`franchise_detail.areas.${admin.area}`, { defaultValue: admin.area })}
                  </span>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>

      {showModal && (
        <AddAdminModal
          franchiseId={franchiseId}
          onClose={() => setShowModal(false)}
          onSaved={handleSaved}
        />
      )}
    </div>
  );
}

AdminsPanel.propTypes = {
  franchiseId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
  admins: PropTypes.array.isRequired,
  isLoading: PropTypes.bool.isRequired,
  fetchError: PropTypes.string.isRequired,
  onReload: PropTypes.func.isRequired,
  isSuperadmin: PropTypes.bool,
};

// ─── Clients Panel ────────────────────────────────────────────────────────────

function ClientsPanel({ franchiseId, clients, isLoading, fetchError, onReload, isSuperadmin }) {
  const { t } = useTranslation('common');
  const [showModal, setShowModal] = useState(false);
  const [successMsg, setSuccessMsg] = useState('');

  function handleSaved() {
    setShowModal(false);
    setSuccessMsg(t('franchise_detail.client_created'));
    onReload();
    setTimeout(() => setSuccessMsg(''), 4000);
  }

  return (
    <div className="bg-white rounded-xl border border-slate-200 shadow-sm">
      {/* Header */}
      <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <div>
          <h2 className="text-base font-semibold text-slate-800">{t('franchise_detail.clients_title')}</h2>
          {!isLoading && (
            <p className="text-sm text-slate-500 mt-0.5">
              {clients.length}{' '}
              {clients.length === 1
                ? t('franchise_detail.clients_subtitle_one')
                : t('franchise_detail.clients_subtitle_other')}
            </p>
          )}
        </div>
        {isSuperadmin && (
          <button
            onClick={() => setShowModal(true)}
            className="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            {t('franchise_detail.add_client')}
          </button>
        )}
      </div>

      <div className="px-6 py-4">
        {successMsg && (
          <div className="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {successMsg}
          </div>
        )}

        {isLoading && <InlineSpinner />}

        {!isLoading && fetchError && (
          <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 flex items-center justify-between">
            <span>{fetchError}</span>
            <button onClick={onReload} className="text-xs underline ml-3">{t('common.try_again')}</button>
          </div>
        )}

        {!isLoading && !fetchError && clients.length === 0 && (
          <div className="flex flex-col items-center justify-center py-10 text-center">
            <svg className="w-10 h-10 text-slate-300 mb-3" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" />
            </svg>
            <p className="text-sm font-medium text-slate-700">{t('franchise_detail.no_clients')}</p>
            <p className="text-sm text-slate-400 mt-1">{t('franchise_detail.no_clients_sub')}</p>
          </div>
        )}

        {!isLoading && !fetchError && clients.length > 0 && (
          <ul className="divide-y divide-slate-100">
            {clients.map((client) => (
              <li key={client.id} className="py-3 flex items-center gap-3">
                <div className={`w-9 h-9 rounded-full ${getAvatarColor(client.name)} flex items-center justify-center shrink-0`}>
                  <span className="text-white text-xs font-bold">{getInitials(client.name)}</span>
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-slate-800 truncate">{client.name}</p>
                  <p className="text-xs text-slate-500 truncate">{client.email}</p>
                </div>
                {client.role && (
                  <span className={`shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                    client.role === 'bb_employee'
                      ? 'bg-violet-100 text-violet-700'
                      : 'bg-blue-100 text-blue-700'
                  }`}>
                    {t(`roles.${client.role}`, { defaultValue: client.role })}
                  </span>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>

      {showModal && (
        <AddClientModal
          franchiseId={franchiseId}
          onClose={() => setShowModal(false)}
          onSaved={handleSaved}
        />
      )}
    </div>
  );
}

ClientsPanel.propTypes = {
  franchiseId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
  clients: PropTypes.array.isRequired,
  isLoading: PropTypes.bool.isRequired,
  fetchError: PropTypes.string.isRequired,
  onReload: PropTypes.func.isRequired,
  isSuperadmin: PropTypes.bool,
};

// ─── Main page ────────────────────────────────────────────────────────────────

export default function FranchiseDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { t } = useTranslation('common');
  const role = useAuthStore((s) => s.role);
  const isSuperadmin = role === 'superadmin';

  // Franchise data
  const [franchise, setFranchise] = useState(null);
  const [franchiseLoading, setFranchiseLoading] = useState(true);
  const [franchiseError, setFranchiseError] = useState('');

  // Members data (admins + clients) — one shared call
  const [members, setMembers] = useState({ admins: [], clients: [] });
  const [membersLoading, setMembersLoading] = useState(true);
  const [membersError, setMembersError] = useState('');

  // Edit modal
  const [isEditOpen, setIsEditOpen] = useState(false);

  // ── Franchise loader ───────────────────────────────────────────────────────

  const loadFranchise = useCallback(async () => {
    setFranchiseLoading(true);
    setFranchiseError('');
    try {
      const data = await franchisesApi.getFranchise(id);
      setFranchise(data);
    } catch (err) {
      setFranchiseError(err?.response?.data?.message ?? t('franchise_detail.load_error'));
    } finally {
      setFranchiseLoading(false);
    }
  }, [id, t]);

  // ── Members loader ─────────────────────────────────────────────────────────

  const loadMembers = useCallback(async () => {
    setMembersLoading(true);
    setMembersError('');
    try {
      const data = await franchisesApi.getMembers(id);
      setMembers({ admins: data.admins ?? [], clients: data.clients ?? [] });
    } catch (err) {
      setMembersError(err?.response?.data?.message ?? t('franchise_detail.members_load_error'));
    } finally {
      setMembersLoading(false);
    }
  }, [id, t]);

  useEffect(() => {
    loadFranchise();
    loadMembers();
  }, [loadFranchise, loadMembers]);

  // ── Handlers ──────────────────────────────────────────────────────────────

  async function handleToggleStatus() {
    const isActive = franchise.is_active !== false;
    const action = isActive ? 'deactivate' : 'activate';
    if (!window.confirm(t(`franchises.${action}_confirm`, { name: franchise.name }))) return;
    try {
      const updated = await franchisesApi.toggleFranchiseStatus(franchise.id);
      setFranchise((prev) => ({ ...prev, is_active: updated.is_active }));
    } catch (err) {
      window.alert(err?.response?.data?.message ?? t('franchises.toggle_error'));
    }
  }

  async function handleSaveEdit(payload, editId) {
    await franchisesApi.updateFranchise(editId, payload);
    setIsEditOpen(false);
    loadFranchise();
  }

  // ── Loading / error states ─────────────────────────────────────────────────

  if (franchiseLoading) return <PageSpinner />;

  if (franchiseError) {
    return (
      <div className="flex flex-col items-center justify-center py-20 text-center">
        <p className="text-sm text-red-600 mb-3">{franchiseError}</p>
        <button onClick={loadFranchise} className="text-sm text-blue-600 underline mb-2">
          {t('common.try_again')}
        </button>
        <button onClick={() => navigate('/franchises')} className="text-sm text-slate-500 underline">
          {t('common.back')}
        </button>
      </div>
    );
  }

  if (!franchise) return null;

  const isActive = franchise.is_active !== false;

  return (
    <>
      <div className="space-y-6">
        {/* ── Header ── */}
        <div className="flex items-start justify-between gap-4">
          <div>
            <button
              onClick={() => navigate('/franchises')}
              className="text-sm text-slate-500 hover:text-slate-700 inline-flex items-center gap-1 mb-2"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
              </svg>
              {t('common.back')}
            </button>
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-semibold text-slate-800">{franchise.name}</h1>
              <span
                className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                  isActive
                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'
                    : 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
                }`}
              >
                {isActive ? t('franchises.active') : t('franchises.inactive')}
              </span>
            </div>
            <p className="mt-1 text-sm text-slate-500">{t('franchise_detail.subtitle')}</p>
          </div>

          {isSuperadmin && (
            <div className="flex items-center gap-2 shrink-0">
              <button
                onClick={() => setIsEditOpen(true)}
                className="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                </svg>
                {t('franchise_detail.edit')}
              </button>
              <button
                onClick={handleToggleStatus}
                className={`inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                  isActive
                    ? 'text-amber-700 bg-amber-50 hover:bg-amber-100'
                    : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100'
                }`}
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                  {isActive ? (
                    <path strokeLinecap="round" strokeLinejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                  ) : (
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  )}
                </svg>
                {isActive ? t('franchises.deactivate') : t('franchises.activate')}
              </button>
            </div>
          )}
        </div>

        {/* ── Info card ── */}
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
          <div className="flex items-center gap-5 flex-wrap">
            <div
              className={`w-16 h-16 rounded-xl ${getAvatarColor(franchise.name)} flex items-center justify-center shrink-0`}
            >
              <span className="text-white text-xl font-bold">{getInitials(franchise.name)}</span>
            </div>

            <div className="flex-1 min-w-0 space-y-1">
              <h2 className="text-lg font-semibold text-slate-800">{franchise.name}</h2>
              {franchise.country && (
                <p className="text-sm text-slate-500 flex items-center gap-1.5">
                  <svg className="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                  </svg>
                  {franchise.country}
                </p>
              )}
              {franchise.email && (
                <p className="text-sm text-slate-500 flex items-center gap-1.5">
                  <svg className="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                  </svg>
                  {franchise.email}
                </p>
              )}
              {franchise.phone && (
                <p className="text-sm text-slate-500 flex items-center gap-1.5">
                  <svg className="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                  </svg>
                  {franchise.phone}
                </p>
              )}
            </div>

            <div className="shrink-0 flex gap-8 pl-6 border-l border-slate-100">
              <div className="text-center">
                <p className="text-2xl font-bold text-slate-800">{franchise.admins_count ?? 0}</p>
                <p className="text-xs text-slate-500 mt-0.5">{t('franchises.admins')}</p>
              </div>
              <div className="text-center">
                <p className="text-2xl font-bold text-slate-800">{franchise.clients_count ?? 0}</p>
                <p className="text-xs text-slate-500 mt-0.5">{t('franchises.clients')}</p>
              </div>
            </div>
          </div>
        </div>

        {/* ── Panels ── */}
        <AdminsPanel
          franchiseId={id}
          admins={members.admins}
          isLoading={membersLoading}
          fetchError={membersError}
          onReload={loadMembers}
          isSuperadmin={isSuperadmin}
        />
        <ClientsPanel
          franchiseId={id}
          clients={members.clients}
          isLoading={membersLoading}
          fetchError={membersError}
          onReload={loadMembers}
          isSuperadmin={isSuperadmin}
        />
      </div>

      {isEditOpen && (
        <FranchiseFormModal
          franchise={franchise}
          onClose={() => setIsEditOpen(false)}
          onSave={handleSaveEdit}
        />
      )}
    </>
  );
}
