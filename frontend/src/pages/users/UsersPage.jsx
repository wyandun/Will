import PropTypes from 'prop-types';
import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { invitationsApi } from '../../api/invitations';
import { systemAdminsApi } from '../../api/systemAdmins';
import InviteUserModal from './InviteUserModal';
import SystemAdminFormModal from '../system_admins/SystemAdminFormModal';

// ─── Shared: Role badge ────────────────────────────────────────────────────────

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

RoleBadge.propTypes = { role: PropTypes.string.isRequired };

// ─── Shared: Expiry indicator ──────────────────────────────────────────────────

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

ExpiryBadge.propTypes = { expiresAt: PropTypes.string };

// ─── Shared: Loading spinner ───────────────────────────────────────────────────

function Spinner() {
  return (
    <div className="flex items-center justify-center py-16 text-slate-400 text-sm gap-2">
      <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
      </svg>
    </div>
  );
}

// ─── Shared: Error banner ──────────────────────────────────────────────────────

function ErrorBanner({ message, onRetry }) {
  const { t } = useTranslation('common');
  return (
    <div className="flex flex-col items-center justify-center py-16 gap-3">
      <p className="text-sm text-red-600">{message}</p>
      <button onClick={onRetry} className="text-sm text-slate-600 underline">
        {t('common.try_again')}
      </button>
    </div>
  );
}

ErrorBanner.propTypes = {
  message: PropTypes.string.isRequired,
  onRetry: PropTypes.func.isRequired,
};

// ─── Tab 1: Invitaciones Panel ────────────────────────────────────────────────

function InvitationsPanel() {
  const { t } = useTranslation('common');

  const [invitations, setInvitations]     = useState([]);
  const [loading, setLoading]             = useState(true);
  const [loadError, setLoadError]         = useState('');
  const [showModal, setShowModal]         = useState(false);
  const [actionLoading, setActionLoading] = useState(null);
  const [actionError, setActionError]     = useState('');
  const [successMsg, setSuccessMsg]       = useState('');

  const flash = (msg) => {
    setSuccessMsg(msg);
    setTimeout(() => setSuccessMsg(''), 3500);
  };

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

  useEffect(() => { fetchInvitations(); }, [fetchInvitations]);

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

  return (
    <div className="space-y-5">

      {/* Sub-header */}
      <div className="flex items-start justify-between gap-4">
        <p className="text-sm text-slate-500">{t('invitation.page_subtitle')}</p>
        <button
          onClick={() => setShowModal(true)}
          className="shrink-0 flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-700 transition-colors"
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
          <Spinner />
        ) : loadError ? (
          <ErrorBanner message={loadError} onRetry={fetchInvitations} />
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
                  <th key={h} className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 bg-white">
              {invitations.map((user) => {
                const role     = user.roles?.[0]?.name ?? '';
                const isActing = actionLoading === user.id;
                return (
                  <tr key={user.id} className="hover:bg-slate-50 transition-colors">
                    <td className="px-5 py-4 text-sm font-medium text-slate-800">{user.name}</td>
                    <td className="px-5 py-4 text-sm text-slate-500">{user.email}</td>
                    <td className="px-5 py-4"><RoleBadge role={role} /></td>
                    <td className="px-5 py-4 text-sm text-slate-500">{user.invited_by?.name ?? '—'}</td>
                    <td className="px-5 py-4"><ExpiryBadge expiresAt={user.invitation_expires_at} /></td>
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
          {invitations.length}{' '}
          {invitations.length === 1 ? t('invitation.count_one') : t('invitation.count_other')}
        </p>
      )}

      <InviteUserModal
        isOpen={showModal}
        onClose={() => setShowModal(false)}
        onSuccess={() => fetchInvitations()}
      />
    </div>
  );
}

// ─── Tab 2: Administradores del Sistema Panel ──────────────────────────────────

function SystemAdminsPanel() {
  const { t } = useTranslation('common');

  const [admins, setAdmins]         = useState([]);
  const [isLoading, setIsLoading]   = useState(true);
  const [fetchError, setFetchError] = useState('');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingAdmin, setEditingAdmin] = useState(null);
  const [successMsg, setSuccessMsg] = useState('');

  const flash = (msg) => {
    setSuccessMsg(msg);
    setTimeout(() => setSuccessMsg(''), 3500);
  };

  const loadAdmins = useCallback(async () => {
    setIsLoading(true);
    setFetchError('');
    try {
      const { data } = await systemAdminsApi.getSystemAdmins();
      setAdmins(Array.isArray(data) ? data : []);
    } catch (error) {
      setFetchError(error?.response?.data?.message ?? t('system_admins.load_error'));
    } finally {
      setIsLoading(false);
    }
  }, [t]);

  useEffect(() => { loadAdmins(); }, [loadAdmins]);

  function openCreateModal() {
    setEditingAdmin(null);
    setIsModalOpen(true);
  }

  function openEditModal(admin) {
    setEditingAdmin(admin);
    setIsModalOpen(true);
  }

  function closeModal() {
    setIsModalOpen(false);
    setEditingAdmin(null);
  }

  async function handleSave(payload, id) {
    if (id) {
      await systemAdminsApi.updateSystemAdmin(id, payload);
      flash(t('system_admin.updated_success'));
    } else {
      await systemAdminsApi.createSystemAdmin(payload);
      flash(t('system_admin.created_success'));
    }
    closeModal();
    await loadAdmins();
  }

  async function handleDelete(admin) {
    if (!window.confirm(t('system_admins.delete_confirm', { name: admin.name }))) return;
    try {
      await systemAdminsApi.deleteSystemAdmin(admin.id);
      flash(t('system_admin.deleted_success'));
      await loadAdmins();
    } catch (error) {
      window.alert(error?.response?.data?.message ?? t('common.unexpected_error'));
    }
  }

  return (
    <div className="space-y-5">

      {/* Sub-header */}
      <div className="flex items-start justify-between gap-4">
        <p className="text-sm text-slate-500">{t('system_admins.subtitle')}</p>
        <button
          onClick={openCreateModal}
          className="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          {t('system_admins.new')}
        </button>
      </div>

      {/* Success banner */}
      {successMsg && (
        <div className="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
          {successMsg}
        </div>
      )}

      {/* List */}
      <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
        {isLoading ? (
          <Spinner />
        ) : fetchError ? (
          <ErrorBanner message={fetchError} onRetry={loadAdmins} />
        ) : admins.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-20 text-center px-6">
            <div className="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mb-4">
              <svg className="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
              </svg>
            </div>
            <p className="text-sm text-slate-500">{t('system_admins.empty')}</p>
          </div>
        ) : (
          <ul className="divide-y divide-slate-100">
            {admins.map((admin) => (
              <li
                key={admin.id}
                className="p-4 flex flex-col sm:flex-row sm:items-center justify-between hover:bg-slate-50 transition-colors gap-4"
              >
                <div className="min-w-0">
                  <h3 className="font-semibold text-slate-800 flex items-center gap-2 flex-wrap">
                    {admin.name}
                    <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium border ${admin.roles?.[0]?.name === 'system_admin' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                      {admin.roles?.map((r) => t(`roles.${r.name}`, { defaultValue: r.name })).join(', ')}
                    </span>
                  </h3>
                  <p className="text-sm text-slate-500 mt-0.5">{admin.email}</p>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                  {/* Edit */}
                  <button
                    onClick={() => openEditModal(admin)}
                    className="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                    title={t('common.edit')}
                  >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                    </svg>
                  </button>
                  {/* Delete */}
                  <button
                    onClick={() => handleDelete(admin)}
                    className="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                    title={t('common.delete')}
                  >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                  </button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>

      {/* Count */}
      {!isLoading && !fetchError && admins.length > 0 && (
        <p className="text-xs text-slate-400 text-right">
          {admins.length} {t('users_page.admins_count', { count: admins.length })}
        </p>
      )}

      {isModalOpen && (
        <SystemAdminFormModal
          initialData={editingAdmin}
          onClose={closeModal}
          onSave={handleSave}
        />
      )}
    </div>
  );
}

// ─── Tab definitions ──────────────────────────────────────────────────────────

const TABS = [
  { key: 'invitations',   labelKey: 'users_page.tab_invitations' },
  { key: 'system_admins', labelKey: 'users_page.tab_system_admins' },
];

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function UsersPage() {
  const { t } = useTranslation('common');
  const [activeTab, setActiveTab] = useState('invitations');

  return (
    <div className="space-y-6">

      {/* Page header */}
      <div>
        <h1 className="text-xl font-semibold text-slate-900">{t('users_page.title')}</h1>
        <p className="mt-1 text-sm text-slate-500">{t('users_page.subtitle')}</p>
      </div>

      {/* Tabs */}
      <div className="border-b border-slate-200">
        <nav className="-mb-px flex gap-6" aria-label="Tabs">
          {TABS.map((tab) => {
            const isActive = activeTab === tab.key;
            return (
              <button
                key={tab.key}
                onClick={() => setActiveTab(tab.key)}
                className={`whitespace-nowrap pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
                  isActive
                    ? 'border-slate-900 text-slate-900'
                    : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                }`}
              >
                {t(tab.labelKey)}
              </button>
            );
          })}
        </nav>
      </div>

      {/* Tab content */}
      {activeTab === 'invitations'   && <InvitationsPanel />}
      {activeTab === 'system_admins' && <SystemAdminsPanel />}

    </div>
  );
}
