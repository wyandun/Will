import PropTypes from 'prop-types';
import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuthStore } from '../../store/authStore';
import { franchisesApi } from '../../api/franchises';
import { invitationsApi } from '../../api/invitations';
import AddAdminModal from './AddAdminModal';
import AddClientModal from './AddClientModal';

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

  const [members, setMembers] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [fetchError, setFetchError] = useState('');
  const [activeTab, setActiveTab] = useState('admins');
  const [isAddAdminOpen, setIsAddAdminOpen] = useState(false);
  const [isAddClientOpen, setIsAddClientOpen] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');

  // ── Load members ──────────────────────────────────────────────────────────

  const loadMembers = useCallback(async () => {
    setIsLoading(true);
    setFetchError('');
    try {
      const data = await franchisesApi.getMembers(id);
      setMembers(data);
    } catch (error) {
      setFetchError(
        error?.response?.data?.message ?? t('franchise_detail.load_error')
      );
    } finally {
      setIsLoading(false);
    }
  }, [id, t]);

  useEffect(() => {
    loadMembers();
  }, [loadMembers]);

  // ── Handlers ──────────────────────────────────────────────────────────────

  async function handleSaveAdmin(payload) {
    await invitationsApi.sendInvitation({ ...payload, role: 'admin_sm', sm_franchise_id: parseInt(id, 10) });
    setIsAddAdminOpen(false);
    setSuccessMessage(t('franchise_detail.admin_invited'));
    setTimeout(() => setSuccessMessage(''), 3000);
    await loadMembers();
  }

  async function handleSaveClient(payload) {
    await invitationsApi.sendInvitation({ ...payload, sm_franchise_id: parseInt(id, 10) });
    setIsAddClientOpen(false);
    setSuccessMessage(t('franchise_detail.client_invited'));
    setTimeout(() => setSuccessMessage(''), 3000);
    await loadMembers();
  }

  // ── Derived data ──────────────────────────────────────────────────────────

  const admins = members?.admins ?? [];
  const clients = members?.clients ?? [];
  const adminsCount = members?.admins_count ?? admins.length;
  const clientsCount = members?.clients_count ?? clients.length;
  const franchiseName = members?.franchise_name ?? '';

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
                onClick={loadMembers}
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
              <div className="flex items-center gap-4">
                <div className={`w-14 h-14 rounded-xl ${getAvatarColor(franchiseName)} flex items-center justify-center shrink-0`}>
                  <span className="text-white text-lg font-bold">{getInitials(franchiseName)}</span>
                </div>
                <div className="flex-1 min-w-0">
                  <h1 className="text-xl font-semibold text-slate-800">{franchiseName}</h1>
                  {members.country && (
                    <p className="text-sm text-slate-500 mt-0.5">{members.country}</p>
                  )}
                </div>
                {members.is_active !== undefined && (
                  <span
                    className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset ${
                      members.is_active !== false
                        ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                        : 'bg-red-50 text-red-700 ring-red-600/20'
                    }`}
                  >
                    {members.is_active !== false ? t('franchises.active') : t('franchises.inactive')}
                  </span>
                )}
              </div>
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
              </div>

              {canAdd && (
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
    </>
  );
}
