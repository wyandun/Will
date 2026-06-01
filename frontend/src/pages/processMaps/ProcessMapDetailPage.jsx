import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { processMapsApi } from '../../api/processMaps';
import { usePermissions } from '../../hooks/usePermissions';
import {
  ChevronRight,
  ChevronDown,
  Plus,
  Edit2,
  Trash2,
  Network,
  Settings,
  X,
  Loader2,
  ArrowRight,
} from 'lucide-react';

const DIV_COLORS = {
  strategic:   { bg: '#1C3755', text: 'white',   light: '#eef2f7', border: '#1C3755' },
  value_chain: { bg: '#D5B170', text: '#1C3755', light: '#fdf8ee', border: '#D5B170' },
  support:     { bg: '#5C7A5E', text: 'white',   light: '#eef3ef', border: '#5C7A5E' },
};

export default function ProcessMapDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { t, i18n } = useTranslation('common');
  const { canWrite } = usePermissions();
  const canEdit = canWrite('processes');
  const lang = i18n.language;

  const [map, setMap] = useState(null);
  const [loading, setLoading] = useState(true);
  const [expanded, setExpanded] = useState({});

  const [activeModal, setActiveModal] = useState(null);
  const [modalCtx, setModalCtx] = useState({});
  const [form, setForm] = useState({});
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);

  const [deleteTarget, setDeleteTarget] = useState(null);
  const [deleting, setDeleting] = useState(false);

  const getName = (item) =>
    (lang?.startsWith('es') ? item?.name_es : item?.name_en) ||
    item?.name_en ||
    item?.name_es ||
    '—';

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await processMapsApi.get(id);
      const mapData = res.data;
      setMap(mapData);
      const exp = {};
      (mapData.categories || []).forEach((c) => {
        exp[`cat-${c.id}`] = true;
        (c.processes || []).forEach((macro) => {
          exp[`macro-${macro.id}`] = true;
          (macro.sub_processes || []).forEach((proc) => {
            exp[`proc-${proc.id}`] = true;
          });
        });
      });
      setExpanded(exp);
    } catch {
      navigate('/processes');
    }
    setLoading(false);
  }, [id, navigate]);

  useEffect(() => {
    load();
  }, [load]);

  const toggle = (key) =>
    setExpanded((prev) => ({ ...prev, [key]: !prev[key] }));

  const openModal = (type, ctx = {}, initialForm = {}) => {
    setActiveModal(type);
    setModalCtx(ctx);
    setForm(initialForm);
    setFormError('');
  };

  const closeModal = () => {
    setActiveModal(null);
    setModalCtx({});
    setForm({});
    setFormError('');
  };

  const handleSave = async () => {
    setSaving(true);
    setFormError('');
    try {
      if (activeModal === 'editDiv') {
        await processMapsApi.updateCategory(modalCtx.catId, {
          name_es: form.name_es,
          name_en: form.name_en,
        });
      } else if (activeModal === 'addMacro') {
        await processMapsApi.createProcess(modalCtx.catId, {
          code: form.code?.toUpperCase(),
          name_es: form.name_es,
          name_en: form.name_en,
        });
      } else if (activeModal === 'editMacro') {
        await processMapsApi.updateProcess(modalCtx.id, {
          name_es: form.name_es,
          name_en: form.name_en,
        });
      } else if (activeModal === 'addProcess') {
        await processMapsApi.createSubProcess(modalCtx.macroId, {
          name_es: form.name_es,
          name_en: form.name_en,
        });
      } else if (activeModal === 'editProcess') {
        await processMapsApi.updateSubProcess(modalCtx.id, {
          name_es: form.name_es,
          name_en: form.name_en,
        });
      } else if (activeModal === 'addSubProcess') {
        await processMapsApi.createSubSubProcess(modalCtx.processId, {
          name_es: form.name_es,
          name_en: form.name_en,
        });
      } else if (activeModal === 'editSubProcess') {
        await processMapsApi.updateSubSubProcess(modalCtx.id, {
          name_es: form.name_es,
          name_en: form.name_en,
        });
      }
      await load();
      closeModal();
    } catch (e) {
      const msg = e.response?.data?.errors
        ? Object.values(e.response.data.errors).flat().join(', ')
        : e.response?.data?.message || t('processMaps.detail.err_save');
      setFormError(msg);
    }
    setSaving(false);
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      if (deleteTarget.type === 'macro') await processMapsApi.deleteProcess(deleteTarget.id);
      else if (deleteTarget.type === 'process') await processMapsApi.deleteSubProcess(deleteTarget.id);
      else if (deleteTarget.type === 'subprocess') await processMapsApi.deleteSubSubProcess(deleteTarget.id);
      await load();
      setDeleteTarget(null);
    } catch {
      // silent — keep dialog open so user can retry
    }
    setDeleting(false);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Loader2 size={28} className="animate-spin text-slate-300" />
      </div>
    );
  }

  if (!map) return null;

  return (
    <div className="flex flex-col min-h-screen bg-[#F4F6F9]">
      {/* Header */}
      <div className="bg-[#1C3755] px-6 py-4 flex items-center gap-4 shadow">
        <Link
          to="/processes"
          className="text-white/70 hover:text-white text-sm flex items-center gap-1 transition"
        >
          ← {t('processMaps.detail.back')}
        </Link>
        <span className="text-white/40">/</span>
        <h1 className="text-white font-bold text-lg truncate">
          {(lang?.startsWith('es') ? map.name_es : map.name_en) || map.name_en}
        </h1>
      </div>

      {/* Canvas */}
      <div className="flex flex-1 overflow-hidden">
        {/* Left sidebar — CLIENT NEED */}
        <div className="w-8 shrink-0 bg-[#1C3755] flex items-center justify-center">
          <span
            className="text-white text-[10px] font-bold tracking-widest uppercase"
            style={{ writingMode: 'vertical-rl', transform: 'rotate(180deg)' }}
          >
            {t('processMaps.detail.client_need')}
          </span>
        </div>

        {/* Main content */}
        <div className="flex-1 p-4 overflow-y-auto space-y-3">
          {(map.categories || []).map((cat) => {
            const colors = DIV_COLORS[cat.type] || DIV_COLORS.strategic;
            const isExpanded = expanded[`cat-${cat.id}`] !== false;
            const macroCount = cat.processes?.length || 0;

            return (
              <div
                key={cat.id}
                className="rounded-xl overflow-hidden border"
                style={{ borderColor: colors.border }}
              >
                {/* Category header */}
                <div
                  className="flex items-center justify-between px-4 py-3"
                  style={{ background: colors.bg, color: colors.text }}
                >
                  <div className="flex items-center gap-3">
                    <button
                      onClick={() => toggle(`cat-${cat.id}`)}
                      className="hover:opacity-80 transition"
                    >
                      {isExpanded ? <ChevronDown size={16} /> : <ChevronRight size={16} />}
                    </button>
                    <span className="font-bold text-sm uppercase tracking-wide">
                      {getName(cat)}
                    </span>
                    <span className="text-xs opacity-70">
                      (
                      {macroCount}{' '}
                      {t(
                        macroCount === 1
                          ? 'processMaps.detail.macro_singular'
                          : 'processMaps.detail.macro_plural'
                      )}
                      )
                    </span>
                  </div>
                  {canEdit && (
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() =>
                          openModal('editDiv', { catId: cat.id }, { name_es: cat.name_es, name_en: cat.name_en })
                        }
                        title={t('processMaps.detail.edit_division')}
                        className="opacity-70 hover:opacity-100 transition"
                      >
                        <Settings size={15} />
                      </button>
                      <button
                        onClick={() => openModal('addMacro', { catId: cat.id }, {})}
                        className="flex items-center gap-1 text-xs font-semibold opacity-90 hover:opacity-100 transition border rounded-lg px-3 py-1"
                        style={{ borderColor: `${colors.text}40`, color: colors.text }}
                      >
                        <Plus size={12} /> {t('processMaps.detail.add_macro')}
                      </button>
                    </div>
                  )}
                </div>

                {/* Macroprocesses */}
                {isExpanded && (
                  <div className="p-3 space-y-2" style={{ background: colors.light }}>
                    {(cat.processes || []).length === 0 && (
                      <p className="text-xs text-slate-400 text-center py-3">
                        {t('processMaps.detail.no_macros')}
                      </p>
                    )}
                    {(cat.processes || []).map((macro) => {
                      const macroExpanded = expanded[`macro-${macro.id}`] !== false;
                      return (
                        <div
                          key={macro.id}
                          className="bg-white rounded-xl border border-black/8 overflow-hidden shadow-sm"
                        >
                          {/* Macroprocess row */}
                          <div className="group flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition">
                            <button
                              onClick={() => toggle(`macro-${macro.id}`)}
                              className="text-slate-400 hover:text-slate-600 transition shrink-0"
                            >
                              {macroExpanded ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
                            </button>
                            <span
                              className="shrink-0 w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold text-white shadow-sm"
                              style={{ background: colors.bg }}
                            >
                              {macro.code?.substring(0, 3) || '??'}
                            </span>
                            <span className="flex-1 text-sm font-semibold text-[#1C3755] truncate">
                              {getName(macro)}
                            </span>
                            {canEdit && (
                              <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                <button
                                  onClick={() =>
                                    openModal('addProcess', { macroId: macro.id }, {})
                                  }
                                  className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-[#1C3755] transition"
                                  title={t('processMaps.detail.add_process')}
                                >
                                  <Plus size={13} />
                                </button>
                                <button
                                  onClick={() =>
                                    openModal('editMacro', { id: macro.id }, { name_es: macro.name_es, name_en: macro.name_en })
                                  }
                                  className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-[#1C3755] transition"
                                >
                                  <Edit2 size={13} />
                                </button>
                                <button
                                  onClick={() =>
                                    setDeleteTarget({ type: 'macro', id: macro.id, name: getName(macro) })
                                  }
                                  className="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition"
                                >
                                  <Trash2 size={13} />
                                </button>
                              </div>
                            )}
                          </div>

                          {/* Processes (sub_processes) */}
                          {macroExpanded && (
                            <div className="border-t border-black/5">
                              {(macro.sub_processes || []).length === 0 && (
                                <p className="text-xs text-slate-400 text-center py-3 px-4">
                                  {t('processMaps.detail.no_processes')}
                                </p>
                              )}
                              {(macro.sub_processes || []).map((proc) => {
                                const procExpanded = expanded[`proc-${proc.id}`] !== false;
                                return (
                                  <div
                                    key={proc.id}
                                    className="border-b border-black/5 last:border-b-0"
                                  >
                                    <div className="group flex items-center gap-3 px-4 py-2.5 pl-8 hover:bg-slate-50 transition">
                                      <button
                                        onClick={() => toggle(`proc-${proc.id}`)}
                                        className="text-slate-400 hover:text-slate-600 transition shrink-0"
                                      >
                                        {procExpanded ? (
                                          <ChevronDown size={13} />
                                        ) : (
                                          <ChevronRight size={13} />
                                        )}
                                      </button>
                                      <span className="shrink-0 bg-[#F4F6F9] border border-black/10 rounded-md px-2 py-0.5 text-xs font-mono font-bold text-[#1C3755]">
                                        {proc.code}
                                      </span>
                                      <span className="flex-1 text-sm text-slate-700 truncate">
                                        {getName(proc)}
                                      </span>
                                      <div className="flex items-center gap-1 text-slate-400">
                                        {(proc.sub_sub_processes_count || 0) > 0 && (
                                          <span className="text-xs font-bold text-slate-500">
                                            {proc.sub_sub_processes_count}
                                          </span>
                                        )}
                                        <Network
                                          size={14}
                                          className={
                                            proc.has_bpmn ? 'text-[#1C3755]' : 'text-slate-300'
                                          }
                                        />
                                      </div>
                                      {canEdit && (
                                        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                          <button
                                            onClick={() =>
                                              openModal('addSubProcess', { processId: proc.id }, {})
                                            }
                                            className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-[#1C3755] transition"
                                          >
                                            <Plus size={12} />
                                          </button>
                                          <button
                                            onClick={() =>
                                              openModal('editProcess', { id: proc.id }, { name_es: proc.name_es, name_en: proc.name_en })
                                            }
                                            className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-[#1C3755] transition"
                                          >
                                            <Edit2 size={12} />
                                          </button>
                                          <button
                                            onClick={() =>
                                              setDeleteTarget({ type: 'process', id: proc.id, name: getName(proc) })
                                            }
                                            className="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition"
                                          >
                                            <Trash2 size={12} />
                                          </button>
                                        </div>
                                      )}
                                    </div>

                                    {/* Sub-sub-processes */}
                                    {procExpanded && (proc.sub_sub_processes || []).length > 0 && (
                                      <div className="bg-slate-50/60">
                                        {(proc.sub_sub_processes || []).map((sub) => (
                                          <div
                                            key={sub.id}
                                            className="group flex items-center gap-3 px-4 py-2 pl-16 border-b border-black/5 last:border-b-0 hover:bg-white/80 transition"
                                          >
                                            <span className="shrink-0 bg-white border border-black/10 rounded-md px-2 py-0.5 text-xs font-mono text-slate-600">
                                              {sub.code}
                                            </span>
                                            <span className="flex-1 text-xs text-slate-600 truncate">
                                              {getName(sub)}
                                            </span>
                                            <Network
                                              size={13}
                                              className={
                                                sub.has_bpmn ? 'text-[#D5B170]' : 'text-slate-300'
                                              }
                                            />
                                            <button className="opacity-40 hover:opacity-100 text-slate-400 transition">
                                              <ArrowRight size={13} />
                                            </button>
                                            {canEdit && (
                                              <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                                <button
                                                  onClick={() =>
                                                    openModal('editSubProcess', { id: sub.id }, { name_es: sub.name_es, name_en: sub.name_en })
                                                  }
                                                  className="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-[#1C3755] transition"
                                                >
                                                  <Edit2 size={11} />
                                                </button>
                                                <button
                                                  onClick={() =>
                                                    setDeleteTarget({ type: 'subprocess', id: sub.id, name: getName(sub) })
                                                  }
                                                  className="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition"
                                                >
                                                  <Trash2 size={11} />
                                                </button>
                                              </div>
                                            )}
                                          </div>
                                        ))}
                                        {canEdit && (
                                          <button
                                            onClick={() =>
                                              openModal('addSubProcess', { processId: proc.id }, {})
                                            }
                                            className="w-full text-left px-4 py-2 pl-16 text-xs text-slate-400 hover:text-[#1C3755] hover:bg-white/80 transition flex items-center gap-1"
                                          >
                                            <Plus size={11} /> {t('processMaps.detail.add_subprocess')}
                                          </button>
                                        )}
                                      </div>
                                    )}

                                    {/* Empty sub-sub-processes — show add button if expanded */}
                                    {procExpanded &&
                                      canEdit &&
                                      (proc.sub_sub_processes || []).length === 0 && (
                                        <div className="pl-16 py-1">
                                          <button
                                            onClick={() =>
                                              openModal('addSubProcess', { processId: proc.id }, {})
                                            }
                                            className="text-xs text-slate-400 hover:text-[#1C3755] transition flex items-center gap-1"
                                          >
                                            <Plus size={11} /> {t('processMaps.detail.add_subprocess')}
                                          </button>
                                        </div>
                                      )}
                                  </div>
                                );
                              })}
                            </div>
                          )}
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            );
          })}
        </div>

        {/* Right sidebar — CLIENT SATISFACTION */}
        <div className="w-8 shrink-0 bg-[#D5B170] flex items-center justify-center">
          <span
            className="text-[#1C3755] text-[10px] font-bold tracking-widest uppercase"
            style={{ writingMode: 'vertical-rl' }}
          >
            {t('processMaps.detail.client_satisfaction')}
          </span>
        </div>
      </div>

      {/* Unified modal */}
      {activeModal && (
        <div
          className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4"
          onMouseDown={(e) => {
            if (e.target === e.currentTarget && !saving) closeModal();
          }}
        >
          <div className="bg-white rounded-2xl w-full max-w-md shadow-2xl flex flex-col">
            {/* Modal header */}
            <div className="flex items-center justify-between px-6 py-4 border-b border-black/8">
              <h2 className="text-[#1C3755] font-bold text-base">
                {activeModal === 'editDiv' && t('processMaps.detail.edit_division')}
                {activeModal === 'addMacro' && t('processMaps.detail.add_macro')}
                {activeModal === 'editMacro' && t('processMaps.detail.edit_macro')}
                {activeModal === 'addProcess' && t('processMaps.detail.add_process')}
                {activeModal === 'editProcess' && t('processMaps.detail.edit_process')}
                {activeModal === 'addSubProcess' && t('processMaps.detail.add_subprocess')}
                {activeModal === 'editSubProcess' && t('processMaps.detail.edit_subprocess')}
              </h2>
              <button
                onClick={closeModal}
                disabled={saving}
                className="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:bg-black/5 transition"
              >
                <X size={15} />
              </button>
            </div>

            {/* Modal body */}
            <div className="p-6 space-y-4">
              {/* Code field — only for addMacro */}
              {activeModal === 'addMacro' && (
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                    {t('processMaps.detail.field_code')}
                  </label>
                  <input
                    type="text"
                    value={form.code || ''}
                    onChange={(e) =>
                      setForm((f) => ({ ...f, code: e.target.value.toUpperCase() }))
                    }
                    maxLength={4}
                    placeholder="e.g. GTH"
                    className="w-full bg-[#F4F6F9] border border-black/10 rounded-xl px-4 py-2.5 text-sm text-[#1C3755] placeholder-slate-400 outline-none focus:border-[#D5B170] focus:ring-2 focus:ring-[#D5B170]/20 transition font-mono"
                  />
                  <p className="mt-1 text-xs text-slate-400">
                    {t('processMaps.detail.field_code_help')}
                  </p>
                </div>
              )}

              {/* name_es */}
              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                  {t('processMaps.detail.field_name')} (ES)
                </label>
                <input
                  type="text"
                  value={form.name_es || ''}
                  onChange={(e) => setForm((f) => ({ ...f, name_es: e.target.value }))}
                  className="w-full bg-[#F4F6F9] border border-black/10 rounded-xl px-4 py-2.5 text-sm text-[#1C3755] placeholder-slate-400 outline-none focus:border-[#D5B170] focus:ring-2 focus:ring-[#D5B170]/20 transition"
                />
              </div>

              {/* name_en */}
              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                  {t('processMaps.detail.field_name')} (EN)
                </label>
                <input
                  type="text"
                  value={form.name_en || ''}
                  onChange={(e) => setForm((f) => ({ ...f, name_en: e.target.value }))}
                  className="w-full bg-[#F4F6F9] border border-black/10 rounded-xl px-4 py-2.5 text-sm text-[#1C3755] placeholder-slate-400 outline-none focus:border-[#D5B170] focus:ring-2 focus:ring-[#D5B170]/20 transition"
                />
              </div>

              {formError && (
                <p className="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">
                  {formError}
                </p>
              )}
            </div>

            {/* Modal footer */}
            <div className="px-6 py-4 border-t border-black/8 flex items-center justify-end gap-3">
              <button
                onClick={closeModal}
                disabled={saving}
                className="px-4 py-2 text-sm text-slate-600 hover:text-[#1C3755] transition"
              >
                {t('processMaps.detail.cancel')}
              </button>
              <button
                onClick={handleSave}
                disabled={saving}
                className="bg-[#1C3755] text-white font-bold px-5 py-2 rounded-xl text-sm hover:opacity-90 disabled:opacity-50 transition flex items-center gap-2"
              >
                {saving && <Loader2 size={13} className="animate-spin" />}
                {activeModal?.startsWith('add')
                  ? t('processMaps.detail.create')
                  : t('processMaps.detail.save')}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Delete confirmation dialog */}
      {deleteTarget && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl w-full max-w-sm shadow-2xl p-6">
            <h3 className="text-base font-bold text-slate-800">
              {deleteTarget.type === 'macro' && t('processMaps.detail.confirm_delete_macro')}
              {deleteTarget.type === 'process' && t('processMaps.detail.confirm_delete_process')}
              {deleteTarget.type === 'subprocess' &&
                t('processMaps.detail.confirm_delete_subprocess')}
            </h3>
            <p className="mt-2 text-sm text-slate-600">&ldquo;{deleteTarget.name}&rdquo;?</p>
            <div className="mt-5 flex items-center justify-end gap-3">
              <button
                onClick={() => setDeleteTarget(null)}
                disabled={deleting}
                className="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition"
              >
                {t('processMaps.detail.cancel')}
              </button>
              <button
                onClick={handleDelete}
                disabled={deleting}
                className="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 transition flex items-center gap-2"
              >
                {deleting && <Loader2 size={13} className="animate-spin" />}
                {t('processMaps.delete_btn')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
