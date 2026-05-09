import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { invitationsApi } from '../../api/invitations';
import InviteUserModal from './InviteUserModal';

// ─── Role badge ───────────────────────────────────────────────────────────────

function RoleBadge({ role }) {
  const { t } = useTranslation('common');
  const colors = {
    superadmin:            'bg-purple-100 text-purple-700',
    system_admin:          'bg-indigo-100 text-indigo-700',
    system_admin_readonly: 'bg-slate-100 text-slate-600',
    admin_sm:              'bg-blue-100 text-blue-700',
    sb_owner:              'bg-green-100 text-green-700',
    sb_employee:           'bg-teal-100 text-teal-700',
    bb_employee:           'bg-orange-100 text-orange-700',
    sub_franchise_owner:   'bg-rose-100 text-rose-700',
    sub_franchise_admin:   'bg-pink-100 text-pink-700',
  };
  const cls = colors[role] ?? 'bg-slate-100 text-slate-600';
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${cls}`}>
      {t(`roles.${role}`, { defaultValue: role })}
    </span>
  );
}

// ─── Expiry indicator ─────────────────────────────────────────────────────────

function ExpiryBadge({ expiresAt }) {
  const { t } = useTranslation('common');
  if (!expiresAt) return <span className="text-slate-400 text-sm">—</span>;

  const ms      = new Date(expiresAt) - Date.now();
  const days    = Math.ceil(ms / 86_400_000);
  const expired = days <= 0;

  return (
    <span className={`text-sm ${expired ? 'text-red-500 font-medium' : days <= 1 ? 'text-orange-500' : 'text-slate-500'}`}>
      {expired
        ? t('invitation.expired_label')
        : t('invitation.expires_in', { count: days })}
    </span>
  );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function InvitationsPage() {
  const { t } = useTranslation('common');

  const [invitations, setInvitations] = useState([]);
  const [loading, setLoading]         = useState(true);
  const [loadError, setLoadError]     = useState('');
  const [showModal, setShowModal]     = useState(false);
  const [actionLoading, setActionLoading] = useState(null); // userId being acted upon
  const [actionError, setActionError] = useState('');
  const [successMsg, setSuccessMsg]   = useState('');

  const flash = (msg) => {
    setSuccessMsg(msg);
    setTimeout(() => setSuccessMsg(''), 3500);
  };

  // ── Fetch ──────────────────────────────────────────────────────────────────
  const fetchInvitations = useCallback(async () => {
    setLoading(true);
    setLoadError('');
    try {
      const { data } = await invitationsApi.getInvitations();
      setInvitations(data);
    } catch {
      setLoadError(t('invitation.load_error'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    fetchInvitations();
  }, [fetchInvitations]);

  // ── Resend ─────────────────────────────────────────────────────────────────
  async function handleResend(user) {
    setActionError('');
    setActionLoading(user.id);
    try {
      await invitationsApi.resendInvitation(user.id);
      flash(t('invitation.resent_success'));
      fetchInvitations();
    } catch {
      setActionError(t('invitation.resend_error'));
    } finally {
      setActionLoading(null);
    }
  }

  // ── Revoke ─────────────────────────────────────────────────────────────────
  async function handleRevoke(user) {
    if (!window.confirm(t('invitation.revoke_confirm', { name: user.name }))) return;
    setActionError('');
    setActionLoading(user.id);
    try {
      await invitationsApi.revokeInvitation(user.id);
      flash(t('invitation.revoked_success'));
      setInvitations((prev) => prev.filter((u) => u.id !== user.id));
    } catch {
      setActionError(t('invitation.revoke_error'));
    } finally {
      setActionLoading(null);
    }
  }

  // ── Render ─────────────────────────────────────────────────────────────────
  return (
    <div className="space-y-6">

      {/* Page header */}
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold text-slate-900">{t('invitation.page_title')}</h1>
          <p className="mt-1 text-sm text-slate-500">{t('invitation.page_subtitle')}</p>
        </div>
        <button
          onClick={() => setShowModal(true)}
          className="flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-700 transition-colors"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {t('invitation.invite_btn')}
        </button>
      </div>

      {/* Feedback banners */}
      {successMsg && (
        <div className="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
          {successMsg}
        </div>
      )}
      {actionError && (
        <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
          {actionError}
        </div>
      )}

      {/* Table */}
      <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center py-16 text-slate-400 text-sm gap-2">
            <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
            {t('common.loading')}
          </div>
        ) : loadError ? (
          <div className="flex flex-col items-center justify-center py-16 gap-3">
            <p className="text-sm text-red-600">{loadError}</p>
            <button onClick={fetchInvitations} className="text-sm text-slate-600 underline">
              {t('common.try_again')}
            </button>
          </div>
        ) : invitations.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-20 text-center px-6">
            <div className="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mb-4">
              <svg className="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
            <p className="text-sm font-medium text-slate-600">{t('invitation.empty_title')}</p>
            <p className="text-sm text-slate-400 mt-1">{t('invitation.empty_subtitle')}</p>
          </div>
        ) : (
          <table className="min-w-full divide-y divide-slate-100">
            <thead className="bg-slate-50">
              <tr>
                {[
                  t('invitation.col_name'),
                  t('invitation.col_email'),
                  t('invitation.col_role'),
                  t('invitation.col_invited_by'),
                  t('invitation.col_expires'),
                  t('invitation.col_actions'),
                ].map((h) => (
                  <th
                    key={h}
                    className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                  >
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 bg-white">
              {invitations.map((user) => {
                const role      = user.roles?.[0]?.name ?? '';
                const isActing  = actionLoading === user.id;

                return (
                  <tr key={user.id} className="hover:bg-slate-50 transition-colors">
                    <td className="px-5 py-4 text-sm font-medium text-slate-800">{user.name}</td>
                    <td className="px-5 py-4 text-sm text-slate-500">{user.email}</td>
                    <td className="px-5 py-4"><RoleBadge role={role} /></td>
                    <td className="px-5 py-4 text-sm text-slate-500">
                      {user.invited_by?.name ?? '—'}
                    </td>
                    <td className="px-5 py-4">
                      <ExpiryBadge expiresAt={user.invitation_expires_at} />
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex items-center gap-3">
                        <button
                          onClick={() => handleResend(user)}
                          disabled={isActing}
                          className="text-sm font-medium text-blue-600 hover:text-blue-800 disabled:opacity-40 transition-colors"
                        >
                          {t('invitation.action_resend')}
                        </button>
                        <button
                          onClick={() => handleRevoke(user)}
                          disabled={isActing}
                          className="text-sm font-medium text-red-500 hover:text-red-700 disabled:opacity-40 transition-colors"
                        >
                          {t('invitation.action_revoke')}
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>

      {/* Count */}
      {!loading && !loadError && invitations.length > 0 && (
        <p className="text-xs text-slate-400 text-right">
          {invitations.length} {invitations.length === 1 ? t('invitation.count_one') : t('invitation.count_other')}
        </p>
      )}

      {/* Modal */}
      <InviteUserModal
        isOpen={showModal}
        onClose={() => setShowModal(false)}
        onSuccess={() => fetchInvitations()}
      />
    </div>
  );
}
