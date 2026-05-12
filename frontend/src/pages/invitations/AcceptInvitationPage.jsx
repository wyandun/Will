import PropTypes from 'prop-types';
import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { invitationsApi } from '../../api/invitations';
import { useAuthStore } from '../../store/authStore';

// ─── Password strength indicator ─────────────────────────────────────────────

function PasswordStrength({ password }) {
  const { t } = useTranslation('common');
  if (!password) return null;

  const checks = [
    password.length >= 8,
    /[A-Z]/.test(password),
    /[0-9]/.test(password),
    /[^A-Za-z0-9]/.test(password),
  ];
  const score = checks.filter(Boolean).length;

  const levels = [
    { label: t('invitation.strength_weak'),   color: 'bg-red-500' },
    { label: t('invitation.strength_fair'),   color: 'bg-orange-400' },
    { label: t('invitation.strength_good'),   color: 'bg-yellow-400' },
    { label: t('invitation.strength_strong'), color: 'bg-green-500' },
  ];
  const level = levels[score - 1] ?? levels[0];

  return (
    <div className="mt-1.5 space-y-1">
      <div className="flex gap-1">
        {levels.map((l, i) => (
          <div
            key={i}
            className={`h-1 flex-1 rounded-full transition-colors ${
              i < score ? level.color : 'bg-slate-200'
            }`}
          />
        ))}
      </div>
      <p className="text-xs text-slate-500">{level.label}</p>
    </div>
  );
}

PasswordStrength.propTypes = {
  password: PropTypes.string.isRequired,
};

// ─── Main page ────────────────────────────────────────────────────────────────

export default function AcceptInvitationPage() {
  const { token } = useParams();
  const navigate  = useNavigate();
  const { t }     = useTranslation('common');
  const setAuth   = useAuthStore((s) => s.setAuth);

  // Verification state
  const [verifying, setVerifying]   = useState(true);
  const [tokenError, setTokenError] = useState('');
  const [invite, setInvite]         = useState(null); // { name, email, role }

  // Form state
  const [password, setPassword]         = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [showPass, setShowPass]         = useState(false);
  const [showConf, setShowConf]         = useState(false);

  // Submission state
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState('');

  // ── Verify token on mount ──────────────────────────────────────────────────
  useEffect(() => {
    invitationsApi
      .verifyInvitation(token)
      .then((data) => setInvite(data))
      .catch((err) => {
        const status = err?.response?.status;
        if (status === 410) {
          setTokenError(t('invitation.error_expired'));
        } else {
          setTokenError(t('invitation.error_invalid'));
        }
      })
      .finally(() => setVerifying(false));
  }, [token, t]);

  // ── Submit ─────────────────────────────────────────────────────────────────
  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitError('');

    if (password !== confirmation) {
      setSubmitError(t('invitation.error_password_mismatch'));
      return;
    }

    setSubmitting(true);
    try {
      const authData = await invitationsApi.acceptInvitation(token, {
        password,
        password_confirmation: confirmation,
      });

      // Auto-login
      setAuth({
        user:        authData.user,
        token:       authData.token,
        role:        authData.role,
        permissions: authData.permissions,
      });

      navigate('/', { replace: true });
    } catch (err) {
      const apiErrors = err?.response?.data?.errors;
      if (apiErrors?.password) {
        setSubmitError(apiErrors.password[0]);
      } else {
        setSubmitError(t('common.unexpected_error'));
      }
    } finally {
      setSubmitting(false);
    }
  }

  // ── Render: loading ────────────────────────────────────────────────────────
  if (verifying) {
    return (
      <div className="min-h-screen bg-slate-950 flex items-center justify-center">
        <div className="flex items-center gap-3 text-slate-400">
          <svg className="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
          </svg>
          <span className="text-sm">{t('invitation.verifying')}</span>
        </div>
      </div>
    );
  }

  // ── Render: invalid / expired token ───────────────────────────────────────
  if (tokenError) {
    return (
      <div className="min-h-screen bg-slate-950 flex items-center justify-center px-4">
        <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-8 text-center">
          <div className="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
            <svg className="w-7 h-7 text-red-500" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <h1 className="text-lg font-semibold text-slate-800 mb-2">{t('invitation.error_title')}</h1>
          <p className="text-sm text-slate-500 mb-6">{tokenError}</p>
          <Link
            to="/login"
            className="inline-block rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-700 transition-colors"
          >
            {t('invitation.go_to_login')}
          </Link>
        </div>
      </div>
    );
  }

  // ── Render: activation form ────────────────────────────────────────────────
  return (
    <div className="min-h-screen bg-slate-950 flex items-center justify-center px-4 py-12">
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">

        {/* Header */}
        <div className="bg-slate-900 px-8 py-7">
          <p className="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-1">
            Strategic Mates
          </p>
          <h1 className="text-xl font-bold text-white">{t('invitation.activate_heading')}</h1>
          <p className="mt-1 text-sm text-slate-400">{t('invitation.activate_subheading')}</p>
        </div>

        {/* User info banner */}
        <div className="bg-slate-50 border-b border-slate-100 px-8 py-4">
          <p className="text-sm text-slate-500 mb-0.5">{t('invitation.invited_as')}</p>
          <p className="font-semibold text-slate-800">{invite.name}</p>
          <p className="text-sm text-slate-500">{invite.email}</p>
          {invite.role && (
            <span className="mt-2 inline-block rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">
              {t(`roles.${invite.role}`, { defaultValue: invite.role })}
            </span>
          )}
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="px-8 py-7 space-y-5" autoComplete="off">

          {submitError && (
            <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
              {submitError}
            </div>
          )}

          {/* Password */}
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              {t('invitation.password_label')}
            </label>
            <div className="relative">
              <input
                type={showPass ? 'text' : 'password'}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder={t('invitation.password_placeholder')}
                required
                minLength={8}
                autoComplete="new-password"
                className="w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 pr-10 text-sm text-slate-900 placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
              />
              <button
                type="button"
                onClick={() => setShowPass((v) => !v)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                aria-label={showPass ? t('auth.hide_password') : t('auth.show_password')}
              >
                {showPass ? (
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                  </svg>
                ) : (
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                )}
              </button>
            </div>
            <PasswordStrength password={password} />
          </div>

          {/* Confirm password */}
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">
              {t('invitation.confirm_label')}
            </label>
            <div className="relative">
              <input
                type={showConf ? 'text' : 'password'}
                value={confirmation}
                onChange={(e) => setConfirmation(e.target.value)}
                placeholder={t('invitation.confirm_placeholder')}
                required
                autoComplete="new-password"
                className="w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 pr-10 text-sm text-slate-900 placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
              />
              <button
                type="button"
                onClick={() => setShowConf((v) => !v)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                aria-label={showConf ? t('auth.hide_password') : t('auth.show_password')}
              >
                {showConf ? (
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                  </svg>
                ) : (
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                )}
              </button>
            </div>
          </div>

          <button
            type="submit"
            disabled={submitting}
            className="w-full rounded-lg bg-slate-900 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
          >
            {submitting ? t('invitation.activating') : t('invitation.activate_btn')}
          </button>

          <p className="text-center text-xs text-slate-400">
            {t('invitation.already_have_account')}{' '}
            <Link to="/login" className="text-slate-600 underline hover:text-slate-900">
              {t('invitation.login_link')}
            </Link>
          </p>
        </form>
      </div>
    </div>
  );
}
