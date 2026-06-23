import PropTypes from 'prop-types';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Loader2, X, Link, Trash2 } from 'lucide-react';

const TABS = ['tab_url', 'tab_document', 'tab_subprocess'];

const input =
  'w-full bg-[#F4F6F9] border border-black/10 rounded-xl px-4 py-2.5 text-sm text-[#1C3755] placeholder-slate-400 outline-none focus:border-[#D5B170] focus:ring-2 focus:ring-[#D5B170]/20 transition';

export default function NodeLinkPanel({
  nodeId,
  existing,
  documents,
  getSubprocesses,
  onSave,
  onRemove,
  onCancel,
  activeLang,
}) {
  const { t } = useTranslation('common');

  const typeToTab = (type) => {
    if (type === 'url') return 'tab_url';
    if (type === 'document') return 'tab_document';
    if (type === 'subprocess') return 'tab_subprocess';
    return 'tab_url';
  };

  const [activeTab, setActiveTab] = useState(existing ? typeToTab(existing.type) : 'tab_url');
  const [urlValue, setUrlValue] = useState(existing?.type === 'url' ? existing.value : '');
  const [docValue, setDocValue] = useState(existing?.type === 'document' ? String(existing.value) : '');
  const [subValue, setSubValue] = useState(existing?.type === 'subprocess' ? String(existing.value) : '');
  const [subSearch, setSubSearch] = useState('');
  const [subList, setSubList] = useState(null); // null = not loaded
  const [subLoading, setSubLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const changeTab = (tab) => {
    setActiveTab(tab);
    setError('');
  };

  useEffect(() => {
    if (activeTab !== 'tab_subprocess' || subList !== null) return;
    setSubLoading(true);
    getSubprocesses()
      .then((list) => setSubList(list))
      .catch(() => setSubList([]))
      .finally(() => setSubLoading(false));
  }, [activeTab, subList, getSubprocesses]);

  const currentValue = () => {
    if (activeTab === 'tab_url') return urlValue.trim();
    if (activeTab === 'tab_document') return docValue;
    return subValue;
  };

  const currentType = () => {
    if (activeTab === 'tab_url') return 'url';
    if (activeTab === 'tab_document') return 'document';
    return 'subprocess';
  };

  const canSave = currentValue() !== '';

  const handleSave = async () => {
    const val = currentValue();
    const type = currentType();
    if (!val) return;
    if (type === 'url' && !/^https?:\/\//.test(val)) {
      setError('URL must start with http:// or https://');
      return;
    }
    setSaving(true);
    setError('');
    try {
      await onSave(nodeId, type, type === 'url' ? val : Number(val));
    } catch (e) {
      const msg =
        e?.response?.data?.errors?.node_links?.[0] ||
        e?.response?.data?.message ||
        t('processMaps.diagram.link_save_error');
      setError(msg);
      setSaving(false);
    }
  };

  const filteredSubs = subList?.filter((s) => {
    const q = subSearch.toLowerCase();
    return (
      s.code?.toLowerCase().includes(q) ||
      (activeLang === 'es' ? s.name_es : s.name_en)?.toLowerCase().includes(q) ||
      s.macroName?.toLowerCase().includes(q)
    );
  });

  const labelCls = 'block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5';

  return (
    <div className="bg-white rounded-2xl border border-[#D5B170]/50 shadow-lg p-4 flex flex-col gap-3">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Link size={14} className="text-[#D5B170]" />
          <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
            {t('processMaps.diagram.selected_node')}
          </span>
        </div>
        <button
          onClick={onCancel}
          className="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:bg-black/5 transition"
        >
          <X size={13} />
        </button>
      </div>

      <p className="text-sm font-mono text-[#D5B170] font-bold truncate">{nodeId}</p>

      {/* Tabs */}
      <div className="flex gap-1 bg-[#F4F6F9] p-1 rounded-xl">
        {TABS.map((tab) => (
          <button
            key={tab}
            onClick={() => changeTab(tab)}
            className={`flex-1 px-2 py-1.5 rounded-lg text-xs font-bold transition-all ${
              activeTab === tab
                ? 'bg-white text-[#1C3755] shadow-sm'
                : 'text-slate-400 hover:text-slate-600'
            }`}
          >
            {t(`processMaps.diagram.${tab}`)}
          </button>
        ))}
      </div>

      {/* Tab content */}
      {activeTab === 'tab_url' && (
        <div>
          <label className={labelCls}>{t('processMaps.diagram.tab_url')}</label>
          <input
            type="url"
            value={urlValue}
            onChange={(e) => setUrlValue(e.target.value)}
            placeholder={t('processMaps.diagram.url_placeholder')}
            className={input}
          />
        </div>
      )}

      {activeTab === 'tab_document' && (
        <div>
          <label className={labelCls}>{t('processMaps.diagram.tab_document')}</label>
          {documents.length === 0 ? (
            <p className="text-xs text-slate-400 py-2">{t('processMaps.diagram.no_documents_to_link')}</p>
          ) : (
            <select value={docValue} onChange={(e) => setDocValue(e.target.value)} className={input}>
              <option value="">{t('processMaps.diagram.select_document')}</option>
              {documents.map((d) => (
                <option key={d.id} value={String(d.id)}>
                  {d.code} — {activeLang === 'es' ? d.title_es : d.title_en} (v{Number(d.version).toFixed(1)})
                </option>
              ))}
            </select>
          )}
        </div>
      )}

      {activeTab === 'tab_subprocess' && (
        <div className="flex flex-col gap-2">
          <label className={labelCls}>{t('processMaps.diagram.tab_subprocess')}</label>
          <input
            type="text"
            value={subSearch}
            onChange={(e) => setSubSearch(e.target.value)}
            placeholder={t('processMaps.diagram.search_subprocess')}
            className={input}
          />
          {subLoading ? (
            <div className="flex justify-center py-4">
              <Loader2 size={18} className="animate-spin text-[#1C3755]/40" />
            </div>
          ) : filteredSubs?.length === 0 ? (
            <p className="text-xs text-slate-400 py-2">{t('processMaps.diagram.no_subprocesses')}</p>
          ) : (
            <select
              size={5}
              value={subValue}
              onChange={(e) => setSubValue(e.target.value)}
              className={`${input} h-auto`}
            >
              {(filteredSubs || []).map((s) => (
                <option key={s.id} value={String(s.id)}>
                  {s.code} — {activeLang === 'es' ? s.name_es : s.name_en}
                  {s.macroName ? ` (${s.macroName})` : ''}
                </option>
              ))}
            </select>
          )}
        </div>
      )}

      {error && (
        <p className="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{error}</p>
      )}

      {/* Footer */}
      <div className="flex items-center gap-2 pt-1 border-t border-black/5">
        <button
          onClick={handleSave}
          disabled={!canSave || saving}
          className="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-gradient-to-r from-[#1C3755] to-[#2d5a8f] text-white disabled:opacity-40 transition"
        >
          {saving ? <Loader2 size={12} className="animate-spin" /> : <Link size={12} />}
          {t('processMaps.diagram.save_link')}
        </button>
        {existing && (
          <button
            onClick={() => onRemove(nodeId)}
            disabled={saving}
            className="p-2 rounded-xl text-red-400 hover:text-red-600 hover:bg-red-50 border border-black/8 transition"
            title={t('processMaps.diagram.remove_link')}
          >
            <Trash2 size={13} />
          </button>
        )}
        <button
          onClick={onCancel}
          disabled={saving}
          className="px-3 py-2 text-xs text-slate-500 hover:text-[#1C3755] transition"
        >
          {t('processMaps.diagram.cancel')}
        </button>
      </div>
    </div>
  );
}

NodeLinkPanel.propTypes = {
  nodeId: PropTypes.string.isRequired,
  existing: PropTypes.shape({ type: PropTypes.string, value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]) }),
  documents: PropTypes.array.isRequired,
  getSubprocesses: PropTypes.func.isRequired,
  onSave: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
  onCancel: PropTypes.func.isRequired,
  activeLang: PropTypes.string.isRequired,
};
