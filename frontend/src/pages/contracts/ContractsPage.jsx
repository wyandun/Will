import PropTypes from 'prop-types';
import { useState, useEffect, useMemo, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  FileSignature, Plus, Search, Trash2, ArrowRight, Loader2, Building2, User as UserIcon,
} from 'lucide-react';
import { contractsApi } from '../../api/contracts';
import { usePermissions } from '../../hooks/usePermissions';
import ContractFormModal from './ContractFormModal';
import ContractStatusBadge from './ContractStatusBadge';

function ConfirmDeleteDialog({ title, onCancel, onConfirm, busy }) {
  const { t } = useTranslation('common');
  return (
    <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
        <h3 className="text-base font-bold text-slate-800">{t('contracts.delete_confirm_title')}</h3>
        <p className="mt-2 text-sm text-slate-600">
          {t('contracts.delete_confirm_message', { title })}
        </p>
        <div className="mt-5 flex items-center justify-end gap-3">
          <button onClick={onCancel} disabled={busy} className="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
            {t('contracts.modal.cancel')}
          </button>
          <button onClick={onConfirm} disabled={busy} className="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 transition flex items-center gap-2">
            {busy && <Loader2 size={13} className="animate-spin" />}
            {t('contracts.delete_btn')}
          </button>
        </div>
      </div>
    </div>
  );
}

export default function ContractsPage() {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const { canWrite } = usePermissions();
  const canManage = canWrite('contracts');

  const [contracts, setContracts] = useState([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [franchiseFilter, setFranchiseFilter] = useState('all');

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [toDelete, setToDelete] = useState(null);
  const [deleting, setDeleting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await contractsApi.list();
      const rows = res.data ?? [];
      setContracts(rows);
      setTotal(res.meta?.total ?? rows.length);
    } catch {
      setError(t('contracts.load_error'));
    }
    setLoading(false);
  }, [t]);

  useEffect(() => { load(); }, [load]);

  // Distinct franchises across loaded contracts (drives the franchise filter visibility)
  const franchises = useMemo(() => {
    const map = new Map();
    contracts.forEach((c) => {
      const f = c.company?.franchise;
      if (f && !map.has(f.id)) map.set(f.id, f);
    });
    return [...map.values()];
  }, [contracts]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return contracts.filter((c) => {
      if (statusFilter !== 'all' && c.status !== statusFilter) return false;
      if (franchiseFilter !== 'all' && String(c.company?.franchise?.id) !== franchiseFilter) return false;
      if (!q) return true;
      const hay = [c.title, c.company?.name, c.client?.name, c.client?.email]
        .filter(Boolean).join(' ').toLowerCase();
      return hay.includes(q);
    });
  }, [contracts, search, statusFilter, franchiseFilter]);

  const handleDelete = async () => {
    if (!toDelete) return;
    setDeleting(true);
    try {
      await contractsApi.remove(toDelete.id);
      setToDelete(null);
      await load();
    } catch {
      // keep dialog open so the user can retry
    }
    setDeleting(false);
  };

  const clearFilters = () => { setSearch(''); setStatusFilter('all'); setFranchiseFilter('all'); };
  const hasFilters = search.trim() || statusFilter !== 'all' || franchiseFilter !== 'all';

  return (
    <div className="p-4 md:p-6 space-y-4">
      {/* Header */}
      <div className="rounded-xl bg-gradient-to-r from-[#1e3a5f] via-[#1C3755] to-[#2d5a8f] text-white px-6 py-5 flex items-center justify-between shadow-md">
        <div>
          <h1 className="text-2xl font-semibold">{t('contracts.title')}</h1>
          <p className="mt-1 text-sm text-slate-200">
            {t('contracts.count', { shown: filtered.length, total })}
          </p>
        </div>
        {canManage && (
          <button
            onClick={() => setIsModalOpen(true)}
            className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-900 bg-amber-400 hover:bg-amber-500 shadow-sm transition"
          >
            <Plus size={16} /> {t('contracts.new_contract')}
          </button>
        )}
      </div>

      {!canManage && (
        <div className="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-2.5">
          {t('contracts.view_only')}
        </div>
      )}

      {/* Filters */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-3 flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t('contracts.search_placeholder')}
            className="w-full pl-9 pr-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="w-full sm:w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="all">{t('contracts.all_statuses')}</option>
          <option value="draft">{t('contracts.status.draft')}</option>
          <option value="sent">{t('contracts.status.sent')}</option>
          <option value="signed">{t('contracts.status.signed')}</option>
        </select>
        {franchises.length > 1 && (
          <select
            value={franchiseFilter}
            onChange={(e) => setFranchiseFilter(e.target.value)}
            className="w-full sm:w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="all">{t('contracts.modal.franchise_label')}</option>
            {franchises.map((f) => <option key={f.id} value={String(f.id)}>{f.name}</option>)}
          </select>
        )}
      </div>

      {/* Body */}
      {loading ? (
        <div className="flex items-center justify-center py-24">
          <Loader2 size={28} className="animate-spin text-slate-300" />
        </div>
      ) : error ? (
        <div className="rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 flex items-center justify-between">
          {error}
          <button onClick={load} className="font-semibold underline">↻</button>
        </div>
      ) : filtered.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <div className="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-4">
            <FileSignature className="w-7 h-7 text-slate-400" />
          </div>
          <p className="text-slate-600 font-medium">
            {hasFilters ? t('contracts.empty_filtered') : t('contracts.empty_title')}
          </p>
          {hasFilters && (
            <button onClick={clearFilters} className="mt-3 text-sm font-semibold text-blue-600 hover:text-blue-700">
              {t('contracts.clear_filters')}
            </button>
          )}
        </div>
      ) : (
        <div className="space-y-2.5">
          {filtered.map((c) => (
            <div
              key={c.id}
              onClick={() => navigate(`/contracts/${c.id}`)}
              className="group relative flex items-center gap-3 bg-white rounded-xl border border-slate-200 hover:border-[#D5B170] hover:shadow-md transition-all cursor-pointer p-4"
            >
              <span className="shrink-0 w-10 h-10 rounded-lg bg-[#1C3755]/10 flex items-center justify-center">
                <FileSignature size={18} className="text-[#1C3755]" />
              </span>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="font-semibold text-[#1C3755] truncate">{c.title}</span>
                  <ContractStatusBadge status={c.status} />
                </div>
                {c.description && (
                  <p className="text-sm text-slate-500 line-clamp-1 mt-0.5">{c.description}</p>
                )}
                <div className="flex items-center gap-2 flex-wrap mt-1.5">
                  {c.company?.franchise?.name && (
                    <span className="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-md bg-slate-100 text-slate-600">
                      <Building2 size={11} /> {c.company.franchise.name}
                    </span>
                  )}
                  {(c.client?.name || c.company?.name) && (
                    <span className="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-md bg-[#D5B170]/15 text-[#6b500e]">
                      <UserIcon size={11} /> {c.client?.name || c.company?.name}
                    </span>
                  )}
                </div>
              </div>
              {canManage && c.status === 'draft' && (
                <button
                  onClick={(e) => { e.preventDefault(); e.stopPropagation(); setToDelete(c); }}
                  aria-label={t('contracts.delete_btn')}
                  className="shrink-0 p-1.5 rounded-lg text-red-500 bg-red-50 hover:bg-red-100 opacity-0 group-hover:opacity-100 transition-opacity"
                >
                  <Trash2 size={15} />
                </button>
              )}
              <ArrowRight size={18} className="shrink-0 text-slate-300 group-hover:text-[#1C3755] transition-colors" />
            </div>
          ))}
        </div>
      )}

      {isModalOpen && (
        <ContractFormModal
          onClose={() => setIsModalOpen(false)}
          onCreated={() => { setIsModalOpen(false); load(); }}
        />
      )}
      {toDelete && (
        <ConfirmDeleteDialog
          title={toDelete.title}
          busy={deleting}
          onCancel={() => setToDelete(null)}
          onConfirm={handleDelete}
        />
      )}
    </div>
  );
}

ConfirmDeleteDialog.propTypes = {
  title: PropTypes.string.isRequired,
  onCancel: PropTypes.func.isRequired,
  onConfirm: PropTypes.func.isRequired,
  busy: PropTypes.bool,
};
