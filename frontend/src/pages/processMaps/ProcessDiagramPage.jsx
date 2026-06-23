import PropTypes from 'prop-types';
import { useState, useEffect, useRef, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  ChevronRight, ArrowLeft, Upload, Play, FileText, ExternalLink,
  Loader2, Trash2, Plus, X, Edit2, Link as LinkIcon,
} from 'lucide-react';
import { processMapsApi } from '../../api/processMaps';
import { usePermissions } from '../../hooks/usePermissions';
import WalkthroughModal from './WalkthroughModal';
import NodeLinkPanel from './NodeLinkPanel';

import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';
import './bpmn-overrides.css';

/** Document types + bilingual labels (matches the document-type picker). */
const DOC_TYPES = [
  { code: 'MP', es: 'Mapa de Procesos', en: 'Process Map' },
  { code: 'CR', es: 'Caracterización', en: 'Characterization' },
  { code: 'MN', es: 'Manual', en: 'Manual' },
  { code: 'AN', es: 'Anexo', en: 'Annex' },
  { code: 'PO', es: 'Política', en: 'Policy' },
  { code: 'PR', es: 'Protocolo', en: 'Protocol' },
  { code: 'IN', es: 'Instructivo', en: 'Instructive' },
  { code: 'FOR', es: 'Formato', en: 'Format' },
  { code: 'REG', es: 'Registro', en: 'Record' },
];
const FILE_ACCEPT = '.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx';

const PRIMARY_BTN =
  'bg-gradient-to-r from-[#1C3755] to-[#2d5a8f] text-white shadow-md hover:shadow-lg hover:brightness-110 active:scale-[0.98] transition-all';

function typeLabel(code, isEs) {
  const t = DOC_TYPES.find((d) => d.code === code);
  return t ? `${code} — ${isEs ? t.es : t.en}` : code;
}

/** Patch Bizagi dataObjects so bpmn-js can render them. */
function fixBizagiBpmn(xml) {
  try {
    const matches = [...xml.matchAll(/<dataObject id="([^"]+)"[^>]*name="([^"]*)"/g)];
    if (!matches.length) return xml;
    let refs = '';
    const idMap = {};
    matches.forEach((m) => {
      const refId = `${m[1]}_ref`;
      idMap[m[1]] = refId;
      refs += `<dataObjectReference id="${refId}" name="${m[2]}" dataObjectRef="${m[1]}"/>`;
    });
    let fixed = xml.includes('<association ')
      ? xml.replace('<association ', refs + '<association ')
      : xml.replace('</process>', refs + '</process>');
    Object.entries(idMap).forEach(([orig, ref]) => {
      fixed = fixed
        .replace(new RegExp(`bpmnElement="${orig}"`, 'g'), `bpmnElement="${ref}"`)
        .replace(new RegExp(`sourceRef="${orig}"`, 'g'), `sourceRef="${ref}"`)
        .replace(new RegExp(`targetRef="${orig}"`, 'g'), `targetRef="${ref}"`);
    });
    return fixed;
  } catch {
    return xml;
  }
}

/** Apply 🔗 gold badges on linked nodes. Skips IDs absent from the registry. */
function applyOverlays(viewer, links) {
  try {
    const overlays = viewer.get('overlays');
    const elementRegistry = viewer.get('elementRegistry');
    overlays.clear();
    Object.keys(links || {}).forEach((nodeId) => {
      if (!elementRegistry.get(nodeId)) return;
      overlays.add(nodeId, {
        position: { top: -8, right: 8 },
        html: '<div style="background:#D5B170;color:#1C3755;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:bold;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,.3);">🔗</div>',
      });
    });
  } catch { /* noop */ }
}

/** Tag linked nodes with `sm-has-link` so CSS shows a pointer cursor. */
function syncLinkMarkers(viewer, links) {
  try {
    const canvas = viewer.get('canvas');
    const registry = viewer.get('elementRegistry');
    registry.getAll().forEach((el) => {
      if (canvas.hasMarker(el.id, 'sm-has-link')) canvas.removeMarker(el.id, 'sm-has-link');
    });
    Object.keys(links || {}).forEach((nid) => {
      if (registry.get(nid)) canvas.addMarker(nid, 'sm-has-link');
    });
  } catch { /* noop */ }
}

/** Highlight a single selected node; clears the previous one. */
function applySelectedMarker(viewer, prevId, nextId) {
  try {
    const canvas = viewer.get('canvas');
    const registry = viewer.get('elementRegistry');
    if (prevId && registry.get(prevId)) canvas.removeMarker(prevId, 'sm-selected');
    if (nextId && registry.get(nextId)) canvas.addMarker(nextId, 'sm-selected');
  } catch { /* noop */ }
}

/** Interactive BPMN canvas (NavigatedViewer); drop zone when empty. */
function BpmnPanel({ xml, canEdit, onUpload, saving, nodeLinks, linkMode, selectedEl, onSelectNode, onOpenLink }) {
  const { t } = useTranslation('common');
  const containerRef = useRef(null);
  const viewerRef = useRef(null);
  const fileRef = useRef(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  // Refs keep eventBus handler free of stale closures (registered once per importXML)
  const linkModeRef = useRef(linkMode);
  const nodeLinksRef = useRef(nodeLinks);
  const selectedElRef = useRef(selectedEl);
  const hoverIdRef = useRef(null);
  const onSelectNodeRef = useRef(onSelectNode);
  const onOpenLinkRef = useRef(onOpenLink);
  useEffect(() => { linkModeRef.current = linkMode; }, [linkMode]);
  useEffect(() => { nodeLinksRef.current = nodeLinks; }, [nodeLinks]);
  useEffect(() => { onSelectNodeRef.current = onSelectNode; }, [onSelectNode]);
  useEffect(() => { onOpenLinkRef.current = onOpenLink; }, [onOpenLink]);

  // Re-apply overlays + link markers when nodeLinks changes (after a save)
  useEffect(() => {
    if (viewerRef.current) {
      applyOverlays(viewerRef.current, nodeLinks);
      syncLinkMarkers(viewerRef.current, nodeLinks);
    }
  }, [nodeLinks]);

  // Toggle link-mode class on the container so CSS shows the crosshair cursor
  useEffect(() => {
    if (containerRef.current) containerRef.current.classList.toggle('sm-linkmode', linkMode);
    // Clear any lingering hover highlight when leaving link mode
    if (!linkMode && hoverIdRef.current && viewerRef.current) {
      try { viewerRef.current.get('canvas').removeMarker(hoverIdRef.current, 'sm-hover'); } catch { /* noop */ }
      hoverIdRef.current = null;
    }
  }, [linkMode]);

  // Highlight the selected node; clear the previously selected one
  useEffect(() => {
    if (viewerRef.current) applySelectedMarker(viewerRef.current, selectedElRef.current, selectedEl);
    selectedElRef.current = selectedEl;
  }, [selectedEl]);

  // The canvas container changes width when the NodeLinkPanel opens/closes
  // (grid ↔ w-full). bpmn-js doesn't auto-resize: re-apply overlays/markers
  // and re-fit the viewport so a just-saved link's badge becomes visible.
  useEffect(() => {
    const v = viewerRef.current;
    if (!v) return undefined;
    const id = setTimeout(() => {
      applyOverlays(v, nodeLinksRef.current);
      syncLinkMarkers(v, nodeLinksRef.current);
      try { v.get('canvas').zoom('fit-viewport', 'auto'); } catch { /* noop */ }
    }, 60);
    return () => clearTimeout(id);
  }, [selectedEl]);

  const render = useCallback(async (data) => {
    if (!containerRef.current || !data) return;
    setLoading(true);
    setError('');
    try {
      if (viewerRef.current) {
        try { viewerRef.current.destroy(); } catch { /* noop */ }
        viewerRef.current = null;
      }
      containerRef.current.innerHTML = '';
      const NavigatedViewer = (await import('bpmn-js/dist/bpmn-navigated-viewer.production.min.js')).default;
      const viewer = new NavigatedViewer({ container: containerRef.current });
      viewerRef.current = viewer;
      await viewer.importXML(fixBizagiBpmn(data));
      applyOverlays(viewer, nodeLinksRef.current);
      syncLinkMarkers(viewer, nodeLinksRef.current);
      applySelectedMarker(viewer, null, selectedElRef.current);
      if (containerRef.current) containerRef.current.classList.toggle('sm-linkmode', linkModeRef.current);
      const eventBus = viewer.get('eventBus');
      eventBus.on('element.click', (event) => {
        const el = event.element;
        if (!el || el.type === 'bpmn:Process' || el.labelTarget) return;
        if (linkModeRef.current) {
          onSelectNodeRef.current?.(el.id);
        } else {
          const link = nodeLinksRef.current?.[el.id];
          if (link) onOpenLinkRef.current?.(link);
        }
      });
      eventBus.on('element.hover', (event) => {
        const el = event.element;
        if (!el || el.type === 'bpmn:Process' || el.labelTarget) return;
        if (!linkModeRef.current) return;
        hoverIdRef.current = el.id;
        try { viewer.get('canvas').addMarker(el.id, 'sm-hover'); } catch { /* noop */ }
      });
      eventBus.on('element.out', (event) => {
        const el = event.element;
        if (!el) return;
        try { viewer.get('canvas').removeMarker(el.id, 'sm-hover'); } catch { /* noop */ }
        if (hoverIdRef.current === el.id) hoverIdRef.current = null;
      });
      const fit = () => { try { viewer.get('canvas').zoom('fit-viewport', 'auto'); } catch { /* noop */ } };
      fit();
      setTimeout(fit, 120);
    } catch {
      setError(t('processMaps.diagram.diagram_error'));
    }
    setLoading(false);
  }, [t]);

  useEffect(() => {
    if (xml) render(xml);
    return () => {
      if (viewerRef.current) {
        try { viewerRef.current.destroy(); } catch { /* noop */ }
        viewerRef.current = null;
      }
    };
  }, [xml, render]);

  const handleFile = async (file) => {
    if (!file) return;
    const text = await file.text();
    onUpload(text);
  };

  if (!xml) {
    return (
      <div
        onDrop={(e) => { e.preventDefault(); if (canEdit) handleFile(e.dataTransfer.files[0]); }}
        onDragOver={(e) => e.preventDefault()}
        className="flex flex-col items-center justify-center min-h-[60vh] bg-[#F4F6F9] rounded-2xl border-2 border-dashed border-black/10 hover:border-[#D5B170] transition"
      >
        <svg className="w-12 h-12 text-[#1C3755]/10 mb-4" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
          <rect x="9" y="2" width="6" height="6" rx="1" /><rect x="3" y="16" width="6" height="6" rx="1" /><rect x="15" y="16" width="6" height="6" rx="1" /><path strokeLinecap="round" d="M12 8v4M6 16v-2a2 2 0 012-2h8a2 2 0 012 2v2" />
        </svg>
        <p className="text-[#1C3755] font-extrabold text-lg mb-1">
          {canEdit ? t('processMaps.diagram.drop_hint') : t('processMaps.diagram.no_diagram')}
        </p>
        {canEdit && (
          <>
            <p className="text-slate-400 text-sm mb-4">{t('processMaps.diagram.browse_hint')}</p>
            <input ref={fileRef} type="file" accept=".bpmn" className="hidden" onChange={(e) => { handleFile(e.target.files[0]); e.target.value = ''; }} />
            <button onClick={() => fileRef.current?.click()} disabled={saving} className={`flex items-center gap-2 font-bold px-5 py-2.5 rounded-xl text-sm disabled:opacity-50 ${PRIMARY_BTN}`}>
              {saving ? <Loader2 size={16} className="animate-spin" /> : <Upload size={16} />}
              {t('processMaps.diagram.browse')}
            </button>
          </>
        )}
      </div>
    );
  }

  return (
    <div>
      <div className="flex justify-end gap-2 mb-2">
        <input ref={fileRef} type="file" accept=".bpmn" className="hidden" onChange={(e) => { handleFile(e.target.files[0]); e.target.value = ''; }} />
        {canEdit && (
          <button
            onClick={() => onSelectNode(linkMode ? null : '__toggle__')}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold border shadow-sm transition-all ${
              linkMode
                ? 'bg-[#D5B170] text-[#1C3755] border-[#D5B170]/60'
                : 'bg-[#F4F6F9] text-slate-600 hover:bg-[#1C3755]/10 hover:text-[#1C3755] border-black/8 hover:shadow'
            }`}
          >
            <LinkIcon size={12} /> {t('processMaps.diagram.link_node')}
          </button>
        )}
        {canEdit && (
          <button onClick={() => fileRef.current?.click()} disabled={saving} className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-[#F4F6F9] text-slate-600 hover:bg-[#1C3755]/10 hover:text-[#1C3755] border border-black/8 shadow-sm hover:shadow disabled:opacity-50 transition-all">
            {saving ? <Loader2 size={12} className="animate-spin" /> : <Upload size={12} />}
            {t('processMaps.diagram.replace_diagram')}
          </button>
        )}
      </div>
      {linkMode && (
        <div className="mb-2 px-3 py-2 rounded-xl bg-[#D5B170]/15 border border-[#D5B170]/40 text-xs font-bold text-[#6b500e]">
          {t('processMaps.diagram.link_mode_hint')}
        </div>
      )}
      <div className="relative rounded-xl overflow-hidden border border-black/8 bg-white shadow-sm" style={{ height: 'calc(100vh - 260px)', minHeight: 380 }}>
        {loading && (
          <div className="absolute inset-0 flex items-center justify-center bg-white/80 z-10">
            <Loader2 size={24} className="animate-spin text-[#1C3755]/40" />
          </div>
        )}
        {error && <div className="absolute inset-0 flex items-center justify-center text-sm text-red-500 z-10">{error}</div>}
        <div ref={containerRef} style={{ width: '100%', height: '100%' }} />
      </div>
    </div>
  );
}

BpmnPanel.propTypes = {
  xml: PropTypes.string,
  canEdit: PropTypes.bool.isRequired,
  onUpload: PropTypes.func.isRequired,
  saving: PropTypes.bool.isRequired,
  nodeLinks: PropTypes.object,
  linkMode: PropTypes.bool,
  selectedEl: PropTypes.string,
  onSelectNode: PropTypes.func,
  onOpenLink: PropTypes.func,
};

export default function ProcessDiagramPage() {
  const { mapId, subId, subSubId } = useParams();
  const navigate = useNavigate();
  const { t, i18n } = useTranslation('common');
  const { canWrite } = usePermissions();
  const canEdit = canWrite('processes');
  const isEs = i18n.language?.startsWith('es');

  const level = subSubId ? 'subsub' : 'sub';
  const id = subSubId || subId;

  const [data, setData] = useState(null);
  const [reviewers, setReviewers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState('flow_es');
  const [saving, setSaving] = useState(false);
  const [walkthrough, setWalkthrough] = useState(false);
  const [editingDoc, setEditingDoc] = useState(null); // null | {} (new) | doc (edit)

  // Link mode
  const [linkMode, setLinkMode] = useState(false);
  const [selectedEl, setSelectedEl] = useState(null);
  const [diagramKey, setDiagramKey] = useState(0); // bump to force a fresh diagram render after a link change
  const subprocessCache = useRef(null);

  const name = (item) => (isEs ? item?.name_es : item?.name_en) || item?.name_en || item?.name_es || '—';

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = level === 'sub'
        ? await processMapsApi.getSubProcess(id)
        : await processMapsApi.getSubSubProcess(id);
      setData(res.data);
      setReviewers(res.reviewers || []);
    } catch {
      navigate(`/processes/${mapId}`);
    }
    setLoading(false);
  }, [id, level, mapId, navigate]);

  useEffect(() => { load(); }, [load]);

  const activeLang = tab === 'flow_en' ? 'en' : 'es';
  const activeXml = tab === 'flow_en' ? data?.bpmn_xml_en : data?.bpmn_xml_es;

  const handleUpload = async (xmlText) => {
    setSaving(true);
    try {
      const res = level === 'sub'
        ? await processMapsApi.uploadSubProcessBpmn(id, activeLang, xmlText)
        : await processMapsApi.uploadSubSubProcessBpmn(id, activeLang, xmlText);
      setData(res.data);
    } catch { /* keep current state */ }
    setSaving(false);
  };

  const nodeLinks = data?.node_links || {};

  const handleSelectNode = (id) => {
    if (id === '__toggle__') {
      setLinkMode((prev) => { if (prev) setSelectedEl(null); return !prev; });
    } else {
      setSelectedEl(id);
    }
  };

  const handleOpenLink = (link) => {
    if (!link) return;
    if (link.type === 'url') {
      window.open(link.value, '_blank', 'noopener,noreferrer');
    } else if (link.type === 'document') {
      const doc = (data?.documents || []).find((d) => d.id === Number(link.value));
      const url = isEs ? (doc?.file_url || doc?.file_url_en) : (doc?.file_url_en || doc?.file_url);
      if (url) window.open(url, '_blank', 'noopener,noreferrer');
    } else if (link.type === 'subprocess') {
      navigate(`/processes/${mapId}/sub/${link.value}`);
    }
  };

  const getSubprocesses = async () => {
    if (subprocessCache.current) return subprocessCache.current;
    const res = await processMapsApi.get(mapId);
    const list = [];
    (res.data?.categories || []).forEach((cat) => {
      (cat.processes || []).forEach((proc) => {
        const macroName = isEs ? (proc.name_es || proc.name_en) : (proc.name_en || proc.name_es);
        (proc.subProcesses || proc.sub_processes || []).forEach((sp) => {
          list.push({ id: sp.id, code: sp.code, name_es: sp.name_es, name_en: sp.name_en, macroName });
        });
      });
    });
    subprocessCache.current = list;
    return list;
  };

  const handleSaveLink = async (nodeId, type, value) => {
    const next = { ...nodeLinks, [nodeId]: { type, value } };
    const res = level === 'sub'
      ? await processMapsApi.updateSubProcessNodeLinks(id, next)
      : await processMapsApi.updateSubSubProcessNodeLinks(id, next);
    setData(res.data);
    setSelectedEl(null);
    setLinkMode(false);
    setDiagramKey((k) => k + 1);
  };

  const handleRemoveLink = async (nodeId) => {
    const next = { ...nodeLinks };
    delete next[nodeId];
    const res = level === 'sub'
      ? await processMapsApi.updateSubProcessNodeLinks(id, next)
      : await processMapsApi.updateSubSubProcessNodeLinks(id, next);
    setData(res.data);
    setSelectedEl(null);
    setLinkMode(false);
    setDiagramKey((k) => k + 1);
  };

  const submitDoc = async (formData, docId) => {
    if (docId) {
      await processMapsApi.updateDocument(docId, formData);
    } else if (level === 'sub') {
      await processMapsApi.addSubProcessDocument(id, formData);
    } else {
      await processMapsApi.addSubSubProcessDocument(id, formData);
    }
    setEditingDoc(null);
    load();
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-24">
        <Loader2 size={28} className="animate-spin text-slate-300" />
      </div>
    );
  }
  if (!data) return null;

  const bc = data.breadcrumb || {};
  const docs = data.documents || [];

  const tabs = [
    { key: 'flow_es', label: t('processMaps.diagram.diagram_es') },
    { key: 'flow_en', label: t('processMaps.diagram.diagram_en') },
    { key: 'docs', label: `${t('processMaps.diagram.documents')} (${docs.length})` },
  ];

  return (
    <div className="min-h-screen bg-[#F4F6F9]">
      {/* Header + breadcrumb */}
      <div className="bg-gradient-to-r from-[#1C3755] to-[#2d5a8f] px-6 py-4 shadow-md">
        <Link to={`/processes/${mapId}`} className="group text-white/70 hover:text-white text-sm flex items-center gap-1.5 w-fit transition">
          <ArrowLeft size={16} className="transition group-hover:-translate-x-0.5" />
          {t('processMaps.diagram.back')}
        </Link>
        <div className="mt-2 flex items-center gap-2 text-white/60 text-sm flex-wrap">
          <Link to="/processes" className="hover:text-white transition">{t('processMaps.title')}</Link>
          <ChevronRight size={14} />
          {bc.map && (<><Link to={`/processes/${mapId}`} className="hover:text-white transition">{isEs ? bc.map.name_es : bc.map.name_en}</Link><ChevronRight size={14} /></>)}
          {bc.macro && (<><span className="text-white/70">{bc.macro.code}</span><ChevronRight size={14} /></>)}
          {bc.process && (<><span className="text-white/70">{bc.process.code}</span><ChevronRight size={14} /></>)}
          <span className="font-semibold text-[#D5B170]">{data.code} — {name(data)}</span>
        </div>
      </div>

      <div className="p-4 md:p-6">
        {/* Tabs + actions */}
        <div className="flex items-center justify-between gap-3 mb-5 flex-wrap">
          <div className="flex gap-1 bg-white border border-black/8 p-1 rounded-xl shadow-sm">
            {tabs.map((tb) => (
              <button
                key={tb.key}
                onClick={() => setTab(tb.key)}
                className={`px-4 py-2 rounded-lg text-sm font-bold transition-all ${tab === tb.key ? `${PRIMARY_BTN}` : 'text-slate-500 hover:text-[#1C3755]'}`}
              >
                {tb.label}
              </button>
            ))}
          </div>
          <div className="flex items-center gap-2">
            {data.manual_url && (
              <a href={data.manual_url} target="_blank" rel="noreferrer" className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold bg-gradient-to-r from-[#e8d3a0] to-[#D5B170] text-[#6b500e] shadow-sm hover:shadow-md hover:brightness-105 transition-all">
                <FileText size={14} /> {t('processMaps.diagram.view_manual')}
              </a>
            )}
            {tab !== 'docs' && activeXml && (
              <button onClick={() => setWalkthrough(true)} className={`flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold ${PRIMARY_BTN}`}>
                <Play size={14} /> {t('processMaps.diagram.walkthrough')}
              </button>
            )}
          </div>
        </div>

        {/* Body */}
        {tab === 'docs' ? (
          <DocumentsTab
            docs={docs}
            canEdit={canEdit}
            onAdd={() => setEditingDoc({})}
            onEdit={(d) => setEditingDoc(d)}
            onDelete={async (docId) => { await processMapsApi.deleteDocument(docId); load(); }}
          />
        ) : (
          <div className={selectedEl ? 'grid grid-cols-[1fr_320px] gap-4 items-start' : 'w-full'}>
            <BpmnPanel
              key={`${tab}-${diagramKey}`}
              xml={activeXml || ''}
              canEdit={canEdit}
              onUpload={handleUpload}
              saving={saving}
              nodeLinks={nodeLinks}
              linkMode={linkMode}
              selectedEl={selectedEl}
              onSelectNode={handleSelectNode}
              onOpenLink={handleOpenLink}
            />
            {selectedEl && (
              <NodeLinkPanel
                nodeId={selectedEl}
                existing={nodeLinks[selectedEl]}
                documents={docs}
                getSubprocesses={getSubprocesses}
                onSave={handleSaveLink}
                onRemove={handleRemoveLink}
                onCancel={() => { setSelectedEl(null); setLinkMode(false); }}
                activeLang={activeLang}
              />
            )}
          </div>
        )}
      </div>

      {walkthrough && (
        <WalkthroughModal
          xml={activeXml}
          onClose={() => setWalkthrough(false)}
          nodeLinks={nodeLinks}
          documents={docs}
          onOpenLink={handleOpenLink}
        />
      )}
      {editingDoc !== null && (
        <DocumentModal
          doc={editingDoc.id ? editingDoc : null}
          parentCode={data.code}
          reviewers={reviewers}
          onClose={() => setEditingDoc(null)}
          onSubmit={submitDoc}
        />
      )}
    </div>
  );
}

function DocumentsTab({ docs, canEdit, onAdd, onEdit, onDelete }) {
  const { t, i18n } = useTranslation('common');
  const isEs = i18n.language?.startsWith('es');
  return (
    <div className="bg-white rounded-2xl border border-black/8 shadow-sm p-4 md:p-6">
      <div className="flex items-center justify-between mb-5">
        <h3 className="text-base font-bold text-[#1C3755]">{t('processMaps.diagram.documents')}</h3>
        {canEdit && (
          <button onClick={onAdd} className={`flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold ${PRIMARY_BTN}`}>
            <Plus size={16} /> {t('processMaps.diagram.new_document')}
          </button>
        )}
      </div>

      {docs.length === 0 ? (
        <p className="text-sm text-slate-400 text-center py-10">{t('processMaps.diagram.no_documents')}</p>
      ) : (
        <div className="space-y-4">
          {docs.map((d) => (
            <div key={d.id} className="rounded-2xl border border-[#D5B170]/40 bg-white shadow-sm hover:shadow-md transition-all overflow-hidden">
              <div className="border-t-4 border-[#D5B170] p-4">
                <div className="flex items-center gap-2 flex-wrap mb-2">
                  <span className="text-[11px] font-bold px-2.5 py-1 rounded-full bg-gradient-to-r from-[#e8d3a0] to-[#D5B170] text-[#6b500e]">{d.type}</span>
                  <span className="text-[11px] font-mono text-slate-500 bg-slate-100 px-2 py-1 rounded-md">{d.code}</span>
                  <span className="text-[11px] font-bold px-2 py-1 rounded-md bg-[#1C3755]/10 text-[#1C3755]">v{Number(d.version).toFixed(1)}</span>
                  <span className="text-[11px] text-slate-400">{typeLabel(d.type, isEs)}</span>
                </div>
                <h4 className="text-[#1C3755] font-bold text-base mb-3">{isEs ? d.title_es : d.title_en}</h4>

                {/* CREATED / REVIEWED / APPROVED */}
                <div className="grid grid-cols-3 rounded-xl bg-[#F8F9FB] border border-black/5 overflow-hidden text-center mb-3">
                  <StatusCell label={t('processMaps.diagram.created')} color="text-[#1C3755]" who={d.created_by?.name} when={d.created_at} />
                  <StatusCell label={t('processMaps.diagram.reviewed')} color="text-blue-600" who={d.reviewed_by?.name} when={d.reviewed_at} border />
                  <StatusCell label={t('processMaps.diagram.approved')} color="text-green-600" who={d.approved_by?.name} when={d.approved_at} border />
                </div>

                <div className="flex items-center gap-2 flex-wrap">
                  {d.file_url && (
                    <a href={d.file_url} target="_blank" rel="noreferrer" className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold border border-black/10 text-slate-600 hover:text-[#1C3755] hover:border-[#1C3755]/30 transition">
                      <ExternalLink size={12} /> ES
                    </a>
                  )}
                  {d.file_url_en && (
                    <a href={d.file_url_en} target="_blank" rel="noreferrer" className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold border border-black/10 text-slate-600 hover:text-[#1C3755] hover:border-[#1C3755]/30 transition">
                      <ExternalLink size={12} /> EN
                    </a>
                  )}
                  {canEdit && (
                    <>
                      <button onClick={() => onEdit(d)} className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold border border-black/10 text-slate-600 hover:text-[#1C3755] hover:border-[#1C3755]/30 transition">
                        <Edit2 size={12} /> {t('processMaps.diagram.edit')}
                      </button>
                      <button onClick={() => onDelete(d.id)} aria-label={t('processMaps.diagram.delete_document')} className="ml-auto p-2 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition">
                        <Trash2 size={14} />
                      </button>
                    </>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

DocumentsTab.propTypes = {
  docs: PropTypes.array.isRequired,
  canEdit: PropTypes.bool.isRequired,
  onAdd: PropTypes.func.isRequired,
  onEdit: PropTypes.func.isRequired,
  onDelete: PropTypes.func.isRequired,
};

function StatusCell({ label, color, who, when, border }) {
  return (
    <div className={`py-3 px-2 ${border ? 'border-l border-black/5' : ''}`}>
      <p className={`text-[10px] font-bold uppercase tracking-wider ${color}`}>{label}</p>
      <p className="text-sm font-semibold text-[#1C3755] mt-1 truncate">{who || '—'}</p>
      <p className="text-[11px] text-slate-400">{when || '—'}</p>
    </div>
  );
}

StatusCell.propTypes = {
  label: PropTypes.string.isRequired,
  color: PropTypes.string.isRequired,
  who: PropTypes.string,
  when: PropTypes.string,
  border: PropTypes.bool,
};

function DocumentModal({ doc, parentCode, reviewers, onClose, onSubmit }) {
  const { t, i18n } = useTranslation('common');
  const isEs = i18n.language?.startsWith('es');
  const isEdit = !!doc;

  const [form, setForm] = useState({
    type: doc?.type || 'FOR',
    title_es: doc?.title_es || '',
    title_en: doc?.title_en || '',
    reviewed_by: doc?.reviewed_by?.id || '',
    approved_by: doc?.approved_by?.id || '',
    valid_from: doc?.valid_from || '',
    notes: doc?.notes || '',
  });
  const [fileEs, setFileEs] = useState(null);
  const [fileEn, setFileEn] = useState(null);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');

  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  const submit = async () => {
    if (!form.title_es.trim() || !form.title_en.trim()) {
      setErr(t('processMaps.detail.err_required'));
      return;
    }
    setBusy(true);
    setErr('');
    try {
      const fd = new FormData();
      fd.append('type', form.type);
      fd.append('title_es', form.title_es.trim());
      fd.append('title_en', form.title_en.trim());
      if (form.reviewed_by) fd.append('reviewed_by', form.reviewed_by);
      if (form.approved_by) fd.append('approved_by', form.approved_by);
      if (form.valid_from) fd.append('valid_from', form.valid_from);
      if (form.notes.trim()) fd.append('notes', form.notes.trim());
      if (fileEs) fd.append('file_es', fileEs);
      if (fileEn) fd.append('file_en', fileEn);
      await onSubmit(fd, doc?.id);
    } catch (e) {
      setErr(e.response?.data?.message || t('processMaps.detail.err_save'));
      setBusy(false);
    }
  };

  const input = 'w-full bg-[#F4F6F9] border border-black/10 rounded-xl px-4 py-2.5 text-sm text-[#1C3755] placeholder-slate-400 outline-none focus:border-[#D5B170] focus:ring-2 focus:ring-[#D5B170]/20 transition';
  const labelCls = 'block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5';

  return (
    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4" onMouseDown={(e) => e.target === e.currentTarget && !busy && onClose()}>
      <div className="bg-white rounded-2xl w-full max-w-lg shadow-2xl max-h-[90vh] flex flex-col">
        <div className="flex items-center justify-between px-6 py-4 border-b border-black/8">
          <h2 className="text-[#1C3755] font-bold text-base">
            {isEdit ? t('processMaps.diagram.edit') : t('processMaps.diagram.new_document')}
          </h2>
          <button onClick={onClose} disabled={busy} className="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:bg-black/5 transition"><X size={15} /></button>
        </div>

        <div className="p-6 space-y-4 overflow-y-auto">
          <p className="text-xs text-slate-400">
            {t('processMaps.diagram.code_hint')} <span className="font-mono font-bold text-[#1C3755]">{parentCode}-{form.type}-01</span>
          </p>

          {/* Document type picker */}
          <div>
            <label className={labelCls}>{t('processMaps.diagram.document_type')}</label>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
              {DOC_TYPES.map((d) => {
                const active = form.type === d.code;
                return (
                  <button
                    key={d.code}
                    type="button"
                    onClick={() => set('type', d.code)}
                    className={`text-left text-xs font-bold rounded-xl px-3 py-2.5 border transition-all ${active ? 'border-[#D5B170] bg-gradient-to-br from-[#f6ecd5] to-[#e8d3a0] text-[#6b500e] shadow-sm' : 'border-black/10 bg-[#F8F9FB] text-slate-500 hover:border-[#1C3755]/30'}`}
                  >
                    <span className="block">{d.code} —</span>
                    <span className="block text-[11px] font-semibold">{isEs ? d.es : d.en}</span>
                  </button>
                );
              })}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className={labelCls}>{t('processMaps.diagram.doc_title')} (ES)</label>
              <input type="text" value={form.title_es} onChange={(e) => set('title_es', e.target.value)} className={input} />
            </div>
            <div>
              <label className={labelCls}>{t('processMaps.diagram.doc_title')} (EN)</label>
              <input type="text" value={form.title_en} onChange={(e) => set('title_en', e.target.value)} className={input} />
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className={labelCls}>{t('processMaps.diagram.reviewed_by')}</label>
              <select value={form.reviewed_by} onChange={(e) => set('reviewed_by', e.target.value)} className={input}>
                <option value="">{t('processMaps.diagram.select')}</option>
                {reviewers.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
              </select>
            </div>
            <div>
              <label className={labelCls}>{t('processMaps.diagram.approved_by')}</label>
              <select value={form.approved_by} onChange={(e) => set('approved_by', e.target.value)} className={input}>
                <option value="">{t('processMaps.diagram.select')}</option>
                {reviewers.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
              </select>
            </div>
          </div>

          <div>
            <label className={labelCls}>{t('processMaps.diagram.valid_from')}</label>
            <input type="date" value={form.valid_from} onChange={(e) => set('valid_from', e.target.value)} className={input} />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <FileField label={t('processMaps.diagram.file_es')} current={doc?.file_name} file={fileEs} onPick={setFileEs} />
            <FileField label={t('processMaps.diagram.file_en')} current={doc?.file_name_en} file={fileEn} onPick={setFileEn} />
          </div>

          <div>
            <label className={labelCls}>{t('processMaps.diagram.notes')}</label>
            <textarea rows={2} value={form.notes} onChange={(e) => set('notes', e.target.value)} className={`${input} resize-none`} />
          </div>

          {err && <p className="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{err}</p>}
        </div>

        <div className="px-6 py-4 border-t border-black/8 flex items-center justify-end gap-3">
          <button onClick={onClose} disabled={busy} className="px-4 py-2 text-sm text-slate-600 hover:text-[#1C3755] transition">{t('processMaps.diagram.cancel')}</button>
          <button onClick={submit} disabled={busy} className={`font-bold px-5 py-2 rounded-xl text-sm disabled:opacity-50 flex items-center gap-2 ${PRIMARY_BTN}`}>
            {busy && <Loader2 size={13} className="animate-spin" />}
            {isEdit ? t('processMaps.diagram.save') : t('processMaps.diagram.add')}
          </button>
        </div>
      </div>
    </div>
  );
}

DocumentModal.propTypes = {
  doc: PropTypes.object,
  parentCode: PropTypes.string.isRequired,
  reviewers: PropTypes.array.isRequired,
  onClose: PropTypes.func.isRequired,
  onSubmit: PropTypes.func.isRequired,
};

function FileField({ label, current, file, onPick }) {
  const { t } = useTranslation('common');
  const ref = useRef(null);
  const labelCls = 'block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5';
  return (
    <div>
      <label className={labelCls}>{label}</label>
      <input ref={ref} type="file" accept={FILE_ACCEPT} className="hidden" onChange={(e) => onPick(e.target.files[0] || null)} />
      <button type="button" onClick={() => ref.current?.click()} className="w-full flex items-center gap-2 bg-[#F4F6F9] border border-black/10 rounded-xl px-3 py-2.5 text-sm text-slate-500 hover:border-[#1C3755]/30 transition">
        <Upload size={14} className="shrink-0 text-[#1C3755]" />
        <span className="truncate">{file?.name || current || t('processMaps.diagram.browse')}</span>
      </button>
    </div>
  );
}

FileField.propTypes = {
  label: PropTypes.string.isRequired,
  current: PropTypes.string,
  file: PropTypes.object,
  onPick: PropTypes.func.isRequired,
};
