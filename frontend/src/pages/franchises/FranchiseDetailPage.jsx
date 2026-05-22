import PropTypes from 'prop-types';
import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuthStore } from '../../store/authStore';
import { franchisesApi } from '../../api/franchises';
import { invitationsApi } from '../../api/invitations';
import AddAdminModal from './AddAdminModal';
import AddClientModal from './AddClientModal';
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

function formatLastSeen(dateStr) {
  if (!dateStr) return null;
  const date = new Date(dateStr);
  const now = new Date();
  const diffMs = now - date;
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

  if (diffDays === 0) return 'Today';
  if (diffDays === 1) return '1 day ago';
  if (diffDays < 30) return `${diffDays} days ago`;
  if (diffDays < 365) {
    const months = Math.floor(diffDays / 30);
    return months === 1 ? '1 month ago' : `${months} months ago`;
  }
  const years = Math.floor(diffDays / 365);
  return years === 1 ? '1 year ago' : `${years} years ago`;
}

// ─── Badge colors ─────────────────────────────────────────────────────────────

const AREA_COLORS = {
  full_access:     'bg-purple-50 text-purple-700 ring-purple-600/20',
  accounting:      'bg-blue-50 text-blue-700 ring-blue-600/20',
  marketing:       'bg-pink-50 text-pink-700 ring-pink-600/20',
  operations:      'bg-amber-50 text-amber-700 ring-amber-600/20',
  legal:           'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
  human_resources: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
};

const ROLE_COLORS = {
  sb_owner:    'bg-cyan-50 text-cyan-700 ring-cyan-600/20',
  bb_employee: 'bg-violet-50 text-violet-700 ring-violet-600/20',
};

// ─── Member row ───────────────────────────────────────────────────────────────

function MemberRow({ member, badgeLabel, badgeColor, t }) {
  const lastSeenLabel = member.last_seen_at
    ? formatLastSeen(member.last_seen_at)
    : null;

  return (
    <li className="flex items-center gap-4 py-3 px-1">
      {/* Avatar */}
      <div
        className={`w-10 h-10 rounded-full ${getAvatarColor(member.name)} flex items-center justify-center shrink-0`}
      >
        <span className="text-white text-sm font-semibold">{getInitials(member.name)}</span>
      </div>

      {/* Name + email */}
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-slate-800 truncate">{member.name}</p>
        <p className="text-xs text-slate-400 truncate">{member.email}</p>
      </div>

      {/* Badge */}
      {badgeLabel && (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset shrink-0 ${badgeColor ?? 'bg-slate-50 text-slate-600 ring-slate-600/20'}`}>
          {badgeLabel}
        </span>
      )}

      {/* Job title */}
      {member.job_title && (
        <span className="hidden sm:block text-xs text-slate-400 shrink-0 max-w-[120px] truncate">
          {member.job_title}
        </span>
      )}

      {/* Last seen */}
      <div className="shrink-0 text-right">
        {lastSeenLabel ? (
          <span className="text-xs text-slate-400">{lastSeenLabel}</span>
        ) : (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20">
            {t('franchise_detail.pending')}
          </span>
        )}
      </div>
    </li>
  );
}

MemberRow.propTypes = {
  member: PropTypes.shape({
    name: PropTypes.string,
    email: PropTypes.string,
    job_title: PropTypes.string,
    last_seen_at: PropTypes.string,
  }).isRequired,
  badgeLabel: PropTypes.string,
  badgeColor: PropTypes.string,
  t: PropTypes.func.isRequired,
};

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function FranchiseDetailPage() {
  const { t } = useTranslation('common');
  const { id } = useParams();
  const navigate = useNavigate();
  const role = useAuthStore((s) => s.role);
  const user = useAuthStore((s) => s.user);

  const isSuperadmin = role === 'superadmin' || role === 'system_admin';
  const isAdminSm = role === 'admin_sm';
  const canAdd = isSuperadmin || (isAdminSm && user?.sm_franchise_id === parseInt(id, 10));
  const canManage = isSuperadmin || (isAdminSm && user?.sm_franchise_id === parseInt(id, 10));

  const [franchise, setFranchise] = useState(null);
  const [members, setMembers] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [fetchError, setFetchError] = useState('');
  const [activeTab, setActiveTab] = useState('admins');
  const [isAddAdminOpen, setIsAddAdminOpen] = useState(false);
  const [isAddClientOpen, setIsAddClientOpen] = useState(false);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');

  // Pending invitations state
  const [activationUrls, setActivationUrls] = useState({});
  const [resendingId, setResendingId] = useState(null);
  const [revokingId, setRevokingId] = useState(null);
  const [copiedId, setCopiedId] = useState(null);

  // ── Load franchise + members ───────────────────────────────────────────────

  const loadData = useCallback(async () => {
    setIsLoading(true);
    setFetchError('');
    try {
      const [franchiseData, membersData] = await Promise.all([
        franchisesApi.getFranchise(id),
        franchisesApi.getMembers(id),
      ]);
      setFranchise(franchiseData);
      setMembers(membersData);
    } catch (error) {
      setFetchError(
        error?.response?.data?.message ?? t('franchise_detail.load_error')
      );
    } finally {
      setIsLoading(false);
    }
  }, [id, t]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // ── Handlers ──────────────────────────────────────────────────────────────

  async function handleSave(payload, franchiseId) {
    await franchisesApi.updateFranchise(franchiseId, payload);
    setIsModalOpen(false);
    await loadData();
  }

  async function handleToggleStatus() {
    if (!franchise) return;
    const action = franchise.is_active ? 'deactivate' : 'activate';
    if (!window.confirm(t(`franchises.${action}_confirm`, { name: franchise.name }))) return;
    try {
      const updated = await franchisesApi.toggleFranchiseStatus(franchise.id);
      setFranchise(updated);
    } catch (error) {
      const msgKey = error?.response?.data?.message;
      const message = msgKey ? t(msgKey, { defaultValue: msgKey }) : t('common.unexpected_error');
      window.alert(message);
    }
  }

  async function handleSaveAdmin(payload) {
    const result = await invitationsApi.sendInvitation({ ...payload, role: 'admin_sm', sm_franchise_id: parseInt(id, 10) });
    if (result?.data?.activation_url && result?.data?.user?.id) {
      setActivationUrls((prev) => ({ ...prev, [result.data.user.id]: result.data.activation_url }));
    }
    setIsAddAdminOpen(false);
    setSuccessMessage(t('franchise_detail.admin_invited'));
    setTimeout(() => setSuccessMessage(''), 4000);
    await loadData();
  }

  async function handleSaveClient(payload) {
    const result = await invitationsApi.sendInvitation({ ...payload, sm_franchise_id: parseInt(id, 10) });
    if (result?.data?.activation_url && result?.data?.user?.id) {
      setActivationUrls((prev) => ({ ...prev, [result.data.user.id]: result.data.activation_url }));
    }
    setIsAddClientOpen(false);
    setSuccessMessage(t('franchise_detail.client_invited'));
    setTimeout(() => setSuccessMessage(''), 4000);
    await loadData();
  }

  async function handleResend(member) {
    setResendingId(member.id);
    try {
      const result = await invitationsApi.resendInvitation(member.id);
      if (result?.data?.activation_url) {
        setActivationUrls((prev) => ({ ...prev, [member.id]: result.data.activation_url }));
      }
      setSuccessMessage(t('invitation.resent_success'));
      setTimeout(() => setSuccessMessage(''), 4000);
    } catch {
      window.alert(t('invitation.resend_error'));
    } finally {
      setResendingId(null);
    }
  }

  async function handleRevoke(member) {
    if (!window.confirm(t('invitation.revoke_confirm', { name: member.name }))) return;
    setRevokingId(member.id);
    try {
      await invitationsApi.revokeInvitation(member.id);
      setActivationUrls((prev) => {
        const next = { ...prev };
        delete next[member.id];
        return next;
      });
      setSuccessMessage(t('invitation.revoked_success'));
      setTimeout(() => setSuccessMessage(''), 4000);
      await loadData();
    } catch {
      window.alert(t('invitation.revoke_error'));
    } finally {
      setRevokingId(null);
    }
  }

  function handleCopyUrl(memberId, url) {
    navigator.clipboard.writeText(url).then(() => {
      setCopiedId(memberId);
      setTimeout(() => setCopiedId(null), 2000);
    });
  }

  // ── Derived data ──────────────────────────────────────────────────────────

  const admins = members?.admins ?? [];
  const clients = members?.clients ?? [];
  const adminsCount = members?.admins_count ?? admins.length;
  const clientsCount = members?.clients_count ?? clients.length;
  const franchiseName = franchise?.name ?? members?.franchise_name ?? '';
  const isActive = franchise?.is_active ?? members?.is_active;

  const pendingAdmins = admins
    .filter((a) => !a.invitation_accepted_at)
    .map((a) => ({ ...a, memberType: 'admin' }));
  const pendingClients = clients
    .filter((c) => !c.invitation_accepted_at)
    .map((c) => ({ ...c, memberType: 'client' }));
  const pendingMembers = [...pendingAdmins, ...pendingClients];
  const pendingCount = pendingMembers.length;

  // ── Render ────────────────────────────────────────────────────────────────

  return (
    <>
      <div className="space-y-5">
        {/* Back button */}
        <button
          onClick={() => navigate('/franchises')}
          className="text-sm text-slate-500 hover:text-slate-700 inline-flex items-center gap-1"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
          </svg>
          {t('common.back')}
        </button>

        {/* Loading */}
        {isLoading && (
          <div className="flex items-center justify-center py-20 gap-3">
            <svg className="w-6 h-6 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p className="text-sm text-slate-500">{t('franchise_detail.loading')}</p>
          </div>
        )}

        {/* Error */}
        {!isLoading && fetchError && (
          <div className="rounded-xl bg-red-50 border border-red-200 px-5 py-4 flex items-start gap-3">
            <svg className="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div>
              <p className="text-sm font-medium text-red-700">{fetchError}</p>
              <button
                onClick={loadData}
                className="mt-1 text-xs text-red-600 underline hover:text-red-800"
              >
                {t('common.try_again')}
              </button>
            </div>
          </div>
        )}

        {/* Main content */}
        {!isLoading && !fetchError && members && (
          <>
            {/* Franchise header */}
            <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
              <div className="flex items-start gap-4">
                <div className={`w-14 h-14 rounded-xl ${getAvatarColor(franchiseName)} flex items-center justify-center shrink-0`}>
                  <span className="text-white text-lg font-bold">{getInitials(franchiseName)}</span>
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-3 flex-wrap">
                    <h1 className="text-xl font-semibold text-slate-800">{franchiseName}</h1>
                    {isActive !== undefined && (
                      <span
                        className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset ${
                          isActive !== false
                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                            : 'bg-red-50 text-red-700 ring-red-600/20'
                        }`}
                      >
                        {isActive !== false ? t('franchises.active') : t('franchises.inactive')}
                      </span>
                    )}
                  </div>

                  {/* Contact info row */}
                  {(franchise?.country || franchise?.email || franchise?.phone || franchise?.address) && (
                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                      {franchise?.country && (
                        <span className="text-sm text-slate-500 flex items-center gap-1">
                          <svg className="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253" />
                          </svg>
                          {franchise.country}
                        </span>
                      )}
                      {franchise?.email && (
                        <span className="text-sm text-slate-500 flex items-center gap-1">
                          <svg className="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                          </svg>
                          {franchise.email}
                        </span>
                      )}
                      {franchise?.phone && (
                        <span className="text-sm text-slate-500 flex items-center gap-1">
                          <svg className="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                          </svg>
                          {franchise.phone}
                        </span>
                      )}
                      {franchise?.address && (
                        <span className="text-sm text-slate-500 flex items-center gap-1">
                          <svg className="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                          </svg>
                          {franchise.address}
                        </span>
                      )}
                    </div>
                  )}
                </div>
              </div>

              {/* Action buttons — only for users who can manage this franchise */}
              {canManage && franchise && (
                <div className="mt-4 pt-3 border-t border-slate-100 flex items-center gap-2">
                  <button
                    onClick={() => setIsModalOpen(true)}
                    className="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors"
                  >
                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                    </svg>
                    {t('common.edit')}
                  </button>
                  <button
                    onClick={handleToggleStatus}
                    className={`flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors ${
                      isActive !== false
                        ? 'text-amber-700 bg-amber-50 hover:bg-amber-100'
                        : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100'
                    }`}
                  >
                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                      {isActive !== false ? (
                        <path strokeLinecap="round" strokeLinejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                      ) : (
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      )}
                    </svg>
                    {isActive !== false ? t('franchises.deactivate') : t('franchises.activate')}
                  </button>
                </div>
              )}
            </div>

            {/* Success toast */}
            {successMessage && (
              <div className="rounded-xl bg-emerald-50 border border-emerald-200 px-5 py-3 flex items-center gap-2">
                <svg className="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p className="text-sm font-medium text-emerald-700">{successMessage}</p>
              </div>
            )}

            {/* Tabs + Add button */}
            <div className="flex items-center justify-between">
              <div className="flex gap-1 bg-slate-100 rounded-lg p-1">
                <button
                  onClick={() => setActiveTab('admins')}
                  className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                    activeTab === 'admins'
                      ? 'bg-white text-slate-800 shadow-sm'
                      : 'text-slate-500 hover:text-slate-700'
                  }`}
                >
                  {t('franchise_detail.tab_admins')}
                  <span className={`ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-semibold ${
                    activeTab === 'admins' ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-500'
                  }`}>
                    {adminsCount}
                  </span>
                </button>
                <button
                  onClick={() => setActiveTab('clients')}
                  className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                    activeTab === 'clients'
                      ? 'bg-white text-slate-800 shadow-sm'
                      : 'text-slate-500 hover:text-slate-700'
                  }`}
                >
                  {t('franchise_detail.tab_clients')}
                  <span className={`ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-semibold ${
                    activeTab === 'clients' ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-500'
                  }`}>
                    {clientsCount}
                  </span>
                </button>
                <button
                  onClick={() => setActiveTab('pending')}
                  className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                    activeTab === 'pending'
                      ? 'bg-white text-slate-800 shadow-sm'
                      : 'text-slate-500 hover:text-slate-700'
                  }`}
                >
                  {t('franchise_detail.tab_pending')}
                  {pendingCount > 0 && (
                    <span className={`ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-semibold ${
                      activeTab === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-500'
                    }`}>
                      {pendingCount}
                    </span>
                  )}
                </button>
              </div>

              {canAdd && activeTab !== 'pending' && (
                <button
                  onClick={() => activeTab === 'admins' ? setIsAddAdminOpen(true) : setIsAddClientOpen(true)}
                  className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                  </svg>
                  {activeTab === 'admins' ? t('franchise_detail.add_admin') : t('franchise_detail.add_client')}
                </button>
              )}
            </div>

            {/* Tab content */}
            <div className="bg-white rounded-xl border border-slate-200 shadow-sm">
              {activeTab === 'admins' && (
                <>
                  {admins.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                      <svg className="w-10 h-10 text-slate-300 mb-3" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                      </svg>
                      <p className="text-sm text-slate-500">{t('franchise_detail.no_admins')}</p>
                    </div>
                  ) : (
                    <ul className="divide-y divide-slate-100 px-5">
                      {admins.map((admin) => (
                        <MemberRow
                          key={admin.id}
                          member={admin}
                          badgeLabel={admin.area ? t(`franchise_detail.area_${admin.area}`) : null}
                          badgeColor={admin.area ? `${AREA_COLORS[admin.area]} ring-1 ring-inset` : null}
                          t={t}
                        />
                      ))}
                    </ul>
                  )}
                </>
              )}

              {activeTab === 'clients' && (
                <>
                  {clients.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                      <svg className="w-10 h-10 text-slate-300 mb-3" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 0h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                      </svg>
                      <p className="text-sm text-slate-500">{t('franchise_detail.no_clients')}</p>
                    </div>
                  ) : (
                    <ul className="divide-y divide-slate-100 px-5">
                      {clients.map((client) => {
                        const roleKey = client.role === 'sb_owner' ? 'sb_owner' : 'bb_employee';
                        return (
                          <MemberRow
                            key={client.id}
                            member={client}
                            badgeLabel={t(`roles.${roleKey}`)}
                            badgeColor={`${ROLE_COLORS[roleKey] ?? 'bg-slate-50 text-slate-600 ring-slate-600/20'} ring-1 ring-inset`}
                            t={t}
                          />
                        );
                      })}
                    </ul>
                  )}
                </>
              )}

              {activeTab === 'pending' && (
                <>
                  {pendingMembers.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                      <svg className="w-10 h-10 text-slate-300 mb-3" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 001.183 1.981l6.478 3.488m8.839 2.51l-4.66-2.51m0 0l-1.023-.55a2.25 2.25 0 00-2.134 0l-1.022.55m0 0l-4.661 2.51m16.5 1.615a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V8.844a2.25 2.25 0 011.183-1.981l7.5-4.039a2.25 2.25 0 012.134 0l7.5 4.039a2.25 2.25 0 011.183 1.98V19.5z" />
                      </svg>
                      <p className="text-sm text-slate-500">{t('franchise_detail.no_pending')}</p>
                    </div>
                  ) : (
                    <ul className="divide-y divide-slate-100">
                      {pendingMembers.map((member) => (
                        <li key={member.id} className="px-5 py-4">
                          <div className="flex items-start gap-4">
                            {/* Avatar */}
                            <div className={`w-10 h-10 rounded-full ${getAvatarColor(member.name)} flex items-center justify-center shrink-0`}>
                              <span className="text-white text-sm font-semibold">{getInitials(member.name)}</span>
                            </div>

                            {/* Info */}
                            <div className="flex-1 min-w-0">
                              <div className="flex items-center gap-2 flex-wrap">
                                <p className="text-sm font-medium text-slate-800">{member.name}</p>
                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset bg-amber-50 text-amber-700 ring-amber-600/20">
                                  {t('franchise_detail.pending')}
                                </span>
                                {member.memberType === 'admin' ? (
                                  <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset bg-blue-50 text-blue-700 ring-blue-600/20">
                                    Admin SM
                                  </span>
                                ) : (
                                  <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset ${ROLE_COLORS[member.role] ?? 'bg-slate-50 text-slate-600 ring-slate-600/20'}`}>
                                    {t(`roles.${member.role}`, { defaultValue: member.role })}
                                  </span>
                                )}
                              </div>
                              <p className="text-xs text-slate-400 mt-0.5">{member.email}</p>
                              {member.created_at && (
                                <p className="text-xs text-slate-400 mt-0.5">
                                  {t('franchise_detail.invited_label')} {formatLastSeen(member.created_at)}
                                </p>
                              )}

                              {/* Activation URL (dev mode only — shown after invite/resend) */}
                              {activationUrls[member.id] && (
                                <div className="mt-2 flex items-center gap-2 rounded-lg bg-blue-50 border border-blue-100 px-3 py-2">
                                  <svg className="w-3.5 h-3.5 text-blue-500 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                  </svg>
                                  <p className="text-xs text-blue-700 flex-1 truncate font-mono">{activationUrls[member.id]}</p>
                                  <button
                                    onClick={() => handleCopyUrl(member.id, activationUrls[member.id])}
                                    className="shrink-0 text-xs font-medium text-blue-700 hover:text-blue-900 transition-colors"
                                  >
                                    {copiedId === member.id ? t('invitation.copied') : t('invitation.copy')}
                                  </button>
                                </div>
                              )}
                            </div>

                            {/* Actions — only for users who can manage invitations */}
                            {canAdd && (
                              <div className="flex items-center gap-2 shrink-0">
                                <button
                                  onClick={() => handleResend(member)}
                                  disabled={resendingId === member.id || revokingId === member.id}
                                  className="px-3 py-1.5 rounded-lg text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                  {resendingId === member.id ? '…' : t('invitation.action_resend')}
                                </button>
                                <button
                                  onClick={() => handleRevoke(member)}
                                  disabled={revokingId === member.id || resendingId === member.id}
                                  className="px-3 py-1.5 rounded-lg text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                  {revokingId === member.id ? '…' : t('invitation.action_revoke')}
                                </button>
                              </div>
                            )}
                          </div>
                        </li>
                      ))}
                    </ul>
                  )}
                </>
              )}
            </div>
          </>
        )}
      </div>

      {/* Modals */}
      {isAddAdminOpen && (
        <AddAdminModal
          onClose={() => setIsAddAdminOpen(false)}
          onSave={handleSaveAdmin}
        />
      )}
      {isAddClientOpen && (
        <AddClientModal
          onClose={() => setIsAddClientOpen(false)}
          onSave={handleSaveClient}
        />
      )}
      {isModalOpen && franchise && (
        <FranchiseFormModal
          franchise={franchise}
          onClose={() => setIsModalOpen(false)}
          onSave={handleSave}
        />
      )}
    </>
  );
}
