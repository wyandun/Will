import PropTypes from 'prop-types';
import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  ArrowLeft, ChevronRight, FileSignature, Edit2, Send, Loader2, Trash2,
  CheckCircle2, Circle, Plus, X, ExternalLink, RefreshCw,
} from 'lucide-react';
import { contractsApi } from '../../api/contracts';
import { usePermissions } from '../../hooks/usePermissions';
import ContractStatusBadge from './ContractStatusBadge';

const MANDATORY_SIGNERS = [
  { role: 'elaborated', labelKey: 'contracts.detail.send.role_elaborated' },
  { role: 'reviewed', labelKey: 'contracts.detail.send.role_reviewed' },
  { role: 'approved', labelKey: 'contracts.detail.send.role_approved' },
];

const fmt = (d) => (d ? new Date(d).toLocaleString() : null);
const inputCls =
  'w-full bg-[#F4F6F9] border border-black/10 rounded-xl px-3 py-2 text-sm text-[#1C3755] placeholder-slate-400 outline-none focus:border-[#D5B170] focus:ring-2 focus:ring-[#D5B170]/20 transition';
const labelCls = 'block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5';

/** Send-for-signing modal: template + mandatory/optional signers + ESIGN notice. */
function SendModal({ contract, onClose, onSent }) {
  const { t } = useTranslation('common');
  const [templates, setTemplates] = useState(null); // null = loading
  const [templateId, setTemplateId] = useState('');
  const [signers, setSigners] = useState(
    MANDATORY_SIGNERS.map((s) => ({ name: '', email: '', role: s.role }))
  );
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    contractsApi.templates()
      .then((list) => setTemplates(Array.isArray(list) ? list : []))
      .catch(() => setTemplates([]));
  }, []);

  const setSigner = (i, k, v) => setSigners((arr) => arr.map((s, idx) => (idx === i ? { ...s, [k]: v } : s)));
  const addSigner = () => setSigners((arr) => [...arr, { name: '', email: '', role: '' }]);
  const removeSigner = (i) => setSigners((arr) => arr.filter((_, idx) => idx !== i));

  const signerLabel = (s, i) => {
    const m = MANDATORY_SIGNERS.find((x) => x.role === s.role);
    return m ? t(m.labelKey) : `${t('contracts.detail.send.signers_label')} ${i + 1}`;
  };

  const canSubmit = templateId && signers.every((s) => s.name.trim() && s.email.trim()) && !busy;

  const submit = async () => {
    setBusy(true);
    setError('');
    try {
      await contractsApi.send(contract.id, {
        template_id: Number(templateId),
        signers: signers.map((s) => ({ name: s.name.trim(), email: s.email.trim(), role: s.role || null })),
      });
      onSent();
    } catch (e) {
      setError(e.response?.data?.message || t('contracts.detail.send.error'));
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4" onMouseDown={(e) => { if (e.target === e.currentTarget && !busy) onClose(); }}>
      <div className="bg-white rounded-2xl w-full max-w-lg shadow-2xl max-h-[90vh] flex flex-col">
        <div className="flex items-center justify-between px-6 py-4 border-b border-black/8">
          <h2 className="text-[#1C3755] font-bold text-base">{t('contracts.detail.send.title')}</h2>
          <button onClick={onClose} disabled={busy} className="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:bg-black/5 transition"><X size={15} /></button>
        </div>

        <div className="p-6 space-y-5 overflow-y-auto">
          {/* Template */}
          <div>
            <label className={labelCls}>{t('contracts.detail.send.template_label')}</label>
            {templates === null ? (
              <div className="flex items-center gap-2 text-sm text-slate-400 py-2"><Loader2 size={14} className="animate-spin" /> …</div>
            ) : templates.length === 0 ? (
              <a href={import.meta.env.VITE_DOCUSEAL_URL || 'https://docuseal.com'} target="_blank" rel="noreferrer" className="block text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 hover:bg-amber-100 transition">
                {t('contracts.detail.send.no_templates')}
              </a>
            ) : (
              <select value={templateId} onChange={(e) => setTemplateId(e.target.value)} className={inputCls}>
                <option value="">{t('contracts.detail.send.template_select')}</option>
                {templates.map((tpl) => <option key={tpl.id} value={String(tpl.id)}>{tpl.name}</option>)}
              </select>
            )}
          </div>

          {/* Signers */}
          <div>
            <label className={labelCls}>{t('contracts.detail.send.signers_label')}</label>
            <div className="space-y-2.5">
              {signers.map((s, i) => (
                <div key={i} className="rounded-xl border border-black/8 p-3 bg-[#F8F9FB]">
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-xs font-bold text-[#1C3755]">{signerLabel(s, i)}</span>
                    {i >= MANDATORY_SIGNERS.length && (
                      <button onClick={() => removeSigner(i)} aria-label={t('contracts.detail.send.remove_signer')} className="p-1 rounded text-slate-400 hover:text-red-600 transition"><X size={13} /></button>
                    )}
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <input type="text" value={s.name} onChange={(e) => setSigner(i, 'name', e.target.value)} placeholder={t('contracts.detail.send.signer_name')} className={inputCls} />
                    <input type="email" value={s.email} onChange={(e) => setSigner(i, 'email', e.target.value)} placeholder={t('contracts.detail.send.signer_email')} className={inputCls} />
                  </div>
                </div>
              ))}
            </div>
            <button onClick={addSigner} className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-[#1C3755] hover:text-[#2d5a8f] transition">
              <Plus size={13} /> {t('contracts.detail.send.add_signer')}
            </button>
          </div>

          {/* What happens next */}
          <div className="rounded-xl bg-[#1C3755]/5 border border-[#1C3755]/10 p-4">
            <p className="text-xs font-bold text-[#1C3755] mb-2">{t('contracts.detail.send.what_happens')}</p>
            <ol className="space-y-1 text-xs text-slate-600 list-decimal list-inside">
              <li>{t('contracts.detail.send.what_1')}</li>
              <li>{t('contracts.detail.send.what_2')}</li>
              <li>{t('contracts.detail.send.what_3')}</li>
              <li>{t('contracts.detail.send.what_4')}</li>
            </ol>
          </div>

          {error && <p className="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{error}</p>}
        </div>

        <div className="px-6 py-4 border-t border-black/8 flex items-center justify-end gap-3">
          <button onClick={onClose} disabled={busy} className="px-4 py-2 text-sm text-slate-600 hover:text-[#1C3755] transition">{t('contracts.detail.send.cancel')}</button>
          <button onClick={submit} disabled={!canSubmit} className="bg-gradient-to-r from-[#e8d3a0] to-[#D5B170] text-[#6b500e] font-bold px-5 py-2 rounded-xl text-sm hover:brightness-105 disabled:opacity-50 transition flex items-center gap-2">
            {busy ? <Loader2 size={13} className="animate-spin" /> : <Send size={13} />}
            {busy ? t('contracts.detail.send.sending') : t('contracts.detail.send.submit')}
          </button>
        </div>
      </div>
    </div>
  );
}

SendModal.propTypes = {
  contract: PropTypes.object.isRequired,
  onClose: PropTypes.func.isRequired,
  onSent: PropTypes.func.isRequired,
};

function TimelineStep({ label, date, done, last }) {
  const { t } = useTranslation('common');
  return (
    <div className="flex gap-3">
      <div className="flex flex-col items-center">
        {done ? <CheckCircle2 size={20} className="text-green-600" /> : <Circle size={20} className="text-slate-300" />}
        {!last && <div className={`w-0.5 flex-1 min-h-[18px] ${done ? 'bg-green-200' : 'bg-slate-200'}`} />}
      </div>
      <div className="pb-4">
        <p className={`text-sm font-semibold ${done ? 'text-[#1C3755]' : 'text-slate-400'}`}>{label}</p>
        <p className="text-xs text-slate-400">{fmt(date) || t('contracts.detail.pending')}</p>
      </div>
    </div>
  );
}

TimelineStep.propTypes = {
  label: PropTypes.string.isRequired,
  date: PropTypes.string,
  done: PropTypes.bool,
  last: PropTypes.bool,
};

export default function ContractDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { t } = useTranslation('common');
  const { canWrite } = usePermissions();
  const canManage = canWrite('contracts');

  const [contract, setContract] = useState(null);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({ title: '', description: '', draft_url: '', expires_at: '' });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [showSend, setShowSend] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const data = await contractsApi.get(id);
      setContract(data);
    } catch {
      navigate('/contracts');
    }
    setLoading(false);
  }, [id, navigate]);

  useEffect(() => { load(); }, [load]);

  const startEdit = () => {
    setForm({
      title: contract.title || '',
      description: contract.description || '',
      draft_url: contract.draft_url || '',
      expires_at: contract.expires_at ? contract.expires_at.slice(0, 10) : '',
    });
    setError('');
    setEditing(true);
  };

  const save = async () => {
    setSaving(true);
    setError('');
    try {
      const updated = await contractsApi.update(id, {
        title: form.title.trim(),
        description: form.description.trim() || null,
        draft_url: form.draft_url.trim() || null,
        expires_at: form.expires_at || null,
      });
      setContract(updated);
      setEditing(false);
    } catch (e) {
      setError(e.response?.data?.message || t('contracts.detail.save_error'));
    }
    setSaving(false);
  };

  const doDelete = async () => {
    setBusy(true);
    try {
      await contractsApi.remove(id);
      navigate('/contracts');
    } catch {
      setBusy(false);
      setConfirmDelete(false);
    }
  };

  const doSync = async () => {
    setBusy(true);
    try { setContract(await contractsApi.sync(id)); } catch { /* noop */ }
    setBusy(false);
  };

  if (loading) {
    return <div className="flex items-center justify-center py-24"><Loader2 size={28} className="animate-spin text-slate-300" /></div>;
  }
  if (!contract) return null;

  const status = contract.status;
  const isDraft = status === 'draft';
  const canEditNow = canManage && isDraft;
  const canSend = canManage && isDraft;
  const canDelete = canManage && isDraft;
  const canSync = canManage && status === 'sent';
  const clientLabel = contract.client?.name || contract.company?.name || '—';
  const franchiseLabel = contract.company?.franchise?.name;

  return (
    <div className="min-h-screen bg-[#F4F6F9]">
      {/* Header band + breadcrumb */}
      <div className="bg-gradient-to-r from-[#1C3755] to-[#2d5a8f] px-6 py-4 shadow-md">
        <Link to="/contracts" className="group text-white/70 hover:text-white text-sm flex items-center gap-1.5 w-fit transition">
          <ArrowLeft size={16} className="transition group-hover:-translate-x-0.5" />
          {t('contracts.detail.back')}
        </Link>
        <div className="mt-2 flex items-center gap-2 text-white/60 text-sm flex-wrap">
          <Link to="/contracts" className="hover:text-white transition">{t('contracts.title')}</Link>
          <ChevronRight size={14} />
          <span className="font-semibold text-[#D5B170] truncate max-w-xs">{contract.title}</span>
        </div>
      </div>

      <div className="p-4 md:p-6">
        {/* Header card */}
        <div className="bg-white rounded-2xl border border-black/8 shadow-sm p-4 md:p-5 mb-5 flex items-start justify-between gap-4 flex-wrap">
          <div className="flex items-start gap-3 min-w-0">
            <span className="shrink-0 w-11 h-11 rounded-xl bg-[#1C3755]/10 flex items-center justify-center">
              <FileSignature size={20} className="text-[#1C3755]" />
            </span>
            <div className="min-w-0">
              {editing ? (
                <input type="text" value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))} className={`${inputCls} text-base font-bold`} />
              ) : (
                <h1 className="text-lg font-bold text-[#1C3755] truncate">{contract.title}</h1>
              )}
              <div className="flex items-center gap-2 flex-wrap mt-1.5">
                <ContractStatusBadge status={status} />
                <span className="text-sm text-slate-500">{clientLabel}{franchiseLabel ? ` · ${franchiseLabel}` : ''}</span>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2 shrink-0">
            {editing ? (
              <>
                <button onClick={() => setEditing(false)} disabled={saving} className="px-4 py-2 text-sm text-slate-600 hover:text-[#1C3755] transition">{t('contracts.detail.cancel')}</button>
                <button onClick={save} disabled={saving || !form.title.trim()} className="bg-[#1C3755] text-white font-bold px-5 py-2 rounded-xl text-sm hover:opacity-90 disabled:opacity-50 transition flex items-center gap-2">
                  {saving && <Loader2 size={13} className="animate-spin" />}
                  {saving ? t('contracts.detail.saving') : t('contracts.detail.save')}
                </button>
              </>
            ) : (
              <>
                {canSync && (
                  <button onClick={doSync} disabled={busy} className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold bg-[#F4F6F9] text-slate-600 hover:bg-[#1C3755]/10 hover:text-[#1C3755] border border-black/8 transition disabled:opacity-50">
                    <RefreshCw size={13} className={busy ? 'animate-spin' : ''} /> {t('contracts.detail.sync')}
                  </button>
                )}
                {canEditNow && (
                  <button onClick={startEdit} className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold bg-[#F4F6F9] text-slate-600 hover:bg-[#1C3755]/10 hover:text-[#1C3755] border border-black/8 transition">
                    <Edit2 size={13} /> {t('contracts.detail.edit')}
                  </button>
                )}
                {canSend && (
                  <button onClick={() => setShowSend(true)} className="flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-bold bg-gradient-to-r from-[#e8d3a0] to-[#D5B170] text-[#6b500e] shadow-sm hover:brightness-105 transition">
                    <Send size={13} /> {t('contracts.detail.send_for_signing')}
                  </button>
                )}
              </>
            )}
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5 items-start">
          {/* Left: details */}
          <div className="bg-white rounded-2xl border border-black/8 shadow-sm p-5 space-y-5">
            <h3 className="text-base font-bold text-[#1C3755]">{t('contracts.detail.details_title')}</h3>

            <div>
              <label className={labelCls}>{t('contracts.detail.description_label')}</label>
              {editing ? (
                <textarea rows={4} value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} className={`${inputCls} resize-none`} />
              ) : (
                <p className={`text-sm ${contract.description ? 'text-slate-700' : 'text-slate-400 italic'}`}>{contract.description || t('contracts.detail.no_description')}</p>
              )}
            </div>

            <div>
              <label className={labelCls}>{t('contracts.detail.draft_label')}</label>
              {editing ? (
                <input type="url" value={form.draft_url} onChange={(e) => setForm((f) => ({ ...f, draft_url: e.target.value }))} placeholder="https://..." className={inputCls} />
              ) : contract.draft_url ? (
                <a href={contract.draft_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 hover:text-blue-700 break-all">
                  <ExternalLink size={13} /> {contract.draft_url}
                </a>
              ) : (
                <p className="text-sm text-slate-400 italic">{t('contracts.detail.no_link')}</p>
              )}
            </div>

            <div>
              <label className={labelCls}>{t('contracts.detail.expiration_label')}</label>
              {editing ? (
                <input type="date" value={form.expires_at} onChange={(e) => setForm((f) => ({ ...f, expires_at: e.target.value }))} className={inputCls} />
              ) : (
                <p className={`text-sm ${contract.expires_at ? 'text-slate-700' : 'text-slate-400 italic'}`}>
                  {contract.expires_at ? new Date(contract.expires_at).toLocaleDateString() : t('contracts.detail.no_expiration')}
                </p>
              )}
            </div>

            {(contract.signed_document_url || contract.certificate_url) && (
              <div className="rounded-xl bg-green-50 border border-green-200 p-4 space-y-2">
                <p className="text-xs font-bold text-green-700 uppercase tracking-wider">{t('contracts.detail.signed_docs_title')}</p>
                {contract.signed_document_url && (
                  <a href={contract.signed_document_url} target="_blank" rel="noreferrer" className="flex items-center gap-1.5 text-sm font-semibold text-green-700 hover:text-green-800"><ExternalLink size={13} /> {t('contracts.detail.view_signed')}</a>
                )}
                {contract.certificate_url && (
                  <a href={contract.certificate_url} target="_blank" rel="noreferrer" className="flex items-center gap-1.5 text-sm font-semibold text-green-700 hover:text-green-800"><ExternalLink size={13} /> {t('contracts.detail.view_certificate')}</a>
                )}
              </div>
            )}

            {error && <p className="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{error}</p>}
          </div>

          {/* Right: timeline + signers + delete */}
          <div className="space-y-5">
            <div className="bg-white rounded-2xl border border-black/8 shadow-sm p-5">
              <h3 className="text-base font-bold text-[#1C3755] mb-4">{t('contracts.detail.timeline_title')}</h3>
              <TimelineStep label={t('contracts.detail.step_created')} date={contract.created_at} done />
              <TimelineStep label={t('contracts.detail.step_sent')} date={contract.sent_at} done={!!contract.sent_at} />
              <TimelineStep label={t('contracts.detail.step_signed')} date={contract.signed_at} done={!!contract.signed_at} last />
            </div>

            {Array.isArray(contract.signers) && contract.signers.length > 0 && (
              <div className="bg-white rounded-2xl border border-black/8 shadow-sm p-5">
                <h3 className="text-base font-bold text-[#1C3755] mb-3">{t('contracts.detail.signers_title')}</h3>
                <div className="space-y-2.5">
                  {contract.signers.map((s, i) => (
                    <div key={i} className="flex items-center gap-2.5">
                      {s.status === 'signed' || s.status === 'completed'
                        ? <CheckCircle2 size={16} className="text-green-600 shrink-0" />
                        : <Circle size={16} className="text-slate-300 shrink-0" />}
                      <div className="min-w-0">
                        <p className="text-sm font-medium text-[#1C3755] truncate">{s.name}</p>
                        <p className="text-xs text-slate-400 truncate">{s.email}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {canDelete && (
              <button onClick={() => setConfirmDelete(true)} className="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 transition">
                <Trash2 size={15} /> {t('contracts.detail.delete_btn')}
              </button>
            )}
          </div>
        </div>
      </div>

      {showSend && (
        <SendModal contract={contract} onClose={() => setShowSend(false)} onSent={() => { setShowSend(false); load(); }} />
      )}
      {confirmDelete && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
            <h3 className="text-base font-bold text-slate-800">{t('contracts.detail.delete_btn')}</h3>
            <p className="mt-2 text-sm text-slate-600">{t('contracts.detail.delete_confirm')}</p>
            <div className="mt-5 flex items-center justify-end gap-3">
              <button onClick={() => setConfirmDelete(false)} disabled={busy} className="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">{t('contracts.detail.cancel')}</button>
              <button onClick={doDelete} disabled={busy} className="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 transition flex items-center gap-2">
                {busy && <Loader2 size={13} className="animate-spin" />}
                {t('contracts.detail.delete_btn')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
