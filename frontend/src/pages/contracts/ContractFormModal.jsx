import PropTypes from 'prop-types';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { X, Loader2 } from 'lucide-react';
import { contractsApi } from '../../api/contracts';
import { franchisesApi } from '../../api/franchises';

const inputCls =
  'w-full bg-[#F4F6F9] border border-black/10 rounded-xl px-4 py-2.5 text-sm text-[#1C3755] placeholder-slate-400 outline-none focus:border-[#D5B170] focus:ring-2 focus:ring-[#D5B170]/20 transition';
const labelCls = 'block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5';

export default function ContractFormModal({ onClose, onCreated }) {
  const { t } = useTranslation('common');

  const [franchises, setFranchises] = useState([]);
  const [franchiseId, setFranchiseId] = useState('');
  const [clients, setClients] = useState([]);
  const [clientsLoading, setClientsLoading] = useState(false);

  const [form, setForm] = useState({ client_user_id: '', title: '', description: '', draft_url: '', expires_at: '' });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  // Load franchises; a single-franchise user (admin_sm) auto-selects their own.
  useEffect(() => {
    let active = true;
    franchisesApi.getFranchises().then(({ data }) => {
      if (!active) return;
      setFranchises(data);
      if (data.length === 1) setFranchiseId(String(data[0].id));
    }).catch(() => {});
    return () => { active = false; };
  }, []);

  // Load clients whenever the selected franchise changes.
  useEffect(() => {
    if (!franchiseId) { setClients([]); return; }
    let active = true;
    setClientsLoading(true);
    franchisesApi.getMembers(franchiseId)
      .then((res) => { if (active) setClients(res.clients ?? []); })
      .catch(() => { if (active) setClients([]); })
      .finally(() => { if (active) setClientsLoading(false); });
    return () => { active = false; };
  }, [franchiseId]);

  const onFranchiseChange = (id) => {
    setFranchiseId(id);
    set('client_user_id', '');
  };

  const canSubmit = form.client_user_id && form.title.trim() && !busy;

  const submit = async () => {
    if (!form.title.trim()) { setError(t('contracts.modal.title_required')); return; }
    if (!form.client_user_id) { setError(t('contracts.modal.client_required')); return; }
    setBusy(true);
    setError('');
    try {
      await contractsApi.create({
        client_user_id: Number(form.client_user_id),
        title: form.title.trim(),
        description: form.description.trim() || null,
        draft_url: form.draft_url.trim() || null,
        expires_at: form.expires_at || null,
      });
      onCreated();
    } catch (e) {
      const msg = e.response?.data?.errors
        ? Object.values(e.response.data.errors).flat().join(', ')
        : e.response?.data?.message || t('contracts.create_error');
      setError(msg);
      setBusy(false);
    }
  };

  const showFranchise = franchises.length > 1;

  return (
    <div
      className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4"
      onMouseDown={(e) => { if (e.target === e.currentTarget && !busy) onClose(); }}
    >
      <div className="bg-white rounded-2xl w-full max-w-lg shadow-2xl max-h-[90vh] flex flex-col">
        <div className="flex items-center justify-between px-6 py-4 border-b border-black/8">
          <h2 className="text-[#1C3755] font-bold text-base">{t('contracts.modal.title')}</h2>
          <button onClick={onClose} disabled={busy} className="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:bg-black/5 transition">
            <X size={15} />
          </button>
        </div>

        <div className="p-6 space-y-4 overflow-y-auto">
          {showFranchise && (
            <div>
              <label className={labelCls}>{t('contracts.modal.franchise_label')}</label>
              <select value={franchiseId} onChange={(e) => onFranchiseChange(e.target.value)} className={inputCls}>
                <option value="">{t('contracts.modal.franchise_select')}</option>
                {franchises.map((f) => <option key={f.id} value={String(f.id)}>{f.name}</option>)}
              </select>
            </div>
          )}

          <div>
            <label className={labelCls}>{t('contracts.modal.client_label')}</label>
            <select
              value={form.client_user_id}
              onChange={(e) => set('client_user_id', e.target.value)}
              disabled={!franchiseId || clientsLoading}
              className={`${inputCls} disabled:opacity-60`}
            >
              <option value="">
                {!franchiseId ? t('contracts.modal.client_pick_franchise') : t('contracts.modal.client_select')}
              </option>
              {clients.map((c) => (
                <option key={c.id} value={String(c.id)}>{c.name} — {c.email}</option>
              ))}
            </select>
          </div>

          <div>
            <label className={labelCls}>{t('contracts.modal.title_label')} <span className="text-red-500">*</span></label>
            <input type="text" value={form.title} onChange={(e) => set('title', e.target.value)} placeholder={t('contracts.modal.title_placeholder')} className={inputCls} autoFocus />
          </div>

          <div>
            <label className={labelCls}>{t('contracts.modal.description_label')}</label>
            <textarea rows={3} value={form.description} onChange={(e) => set('description', e.target.value)} placeholder={t('contracts.modal.description_placeholder')} className={`${inputCls} resize-none`} />
          </div>

          <div>
            <label className={labelCls}>{t('contracts.modal.draft_url_label')}</label>
            <input type="url" value={form.draft_url} onChange={(e) => set('draft_url', e.target.value)} placeholder={t('contracts.modal.draft_url_placeholder')} className={inputCls} />
          </div>

          <div>
            <label className={labelCls}>{t('contracts.modal.expiry_label')}</label>
            <input type="date" value={form.expires_at} onChange={(e) => set('expires_at', e.target.value)} className={inputCls} />
          </div>

          {error && <p className="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{error}</p>}
        </div>

        <div className="px-6 py-4 border-t border-black/8 flex items-center justify-end gap-3">
          <button onClick={onClose} disabled={busy} className="px-4 py-2 text-sm text-slate-600 hover:text-[#1C3755] transition">
            {t('contracts.modal.cancel')}
          </button>
          <button onClick={submit} disabled={!canSubmit} className="bg-[#1C3755] text-white font-bold px-5 py-2 rounded-xl text-sm hover:opacity-90 disabled:opacity-50 transition flex items-center gap-2">
            {busy && <Loader2 size={13} className="animate-spin" />}
            {busy ? t('contracts.creating') : t('contracts.modal.submit')}
          </button>
        </div>
      </div>
    </div>
  );
}

ContractFormModal.propTypes = {
  onClose: PropTypes.func.isRequired,
  onCreated: PropTypes.func.isRequired,
};
