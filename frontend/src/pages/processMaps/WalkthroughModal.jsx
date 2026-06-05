import PropTypes from 'prop-types';
import { useState, useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { X, ChevronLeft, ChevronRight, CheckCircle, HelpCircle } from 'lucide-react';

/**
 * Parse a BPMN XML string into a lightweight graph: nodes (id/name/tag/description),
 * outgoing sequence flows, and the start event id. Derived at runtime — the
 * walkthrough therefore reflects the language of whichever diagram XML is passed in.
 */
function parseBpmnGraph(xml) {
  const empty = { nodes: {}, flows: {}, startId: null };
  if (!xml) return empty;
  try {
    const doc = new DOMParser().parseFromString(xml, 'text/xml');
    if (doc.querySelector('parsererror')) return empty;

    const skip = new Set([
      'definitions', 'collaboration', 'participant', 'laneSet',
      'childLaneSet', 'lane', 'messageFlow', 'process', 'sequenceFlow',
      'association', 'textAnnotation', 'extensionElements',
    ]);
    const nodes = {};
    doc.querySelectorAll('[id]').forEach((el) => {
      const tag = el.localName;
      if (skip.has(tag)) return;
      const id = el.getAttribute('id');
      const name = el.getAttribute('name')?.trim() || '';
      const description = el.querySelector('documentation')?.textContent?.trim() || '';
      nodes[id] = { id, name, tag, description };
    });

    const flows = {};
    doc.querySelectorAll('sequenceFlow').forEach((f) => {
      const src = f.getAttribute('sourceRef');
      const tgt = f.getAttribute('targetRef');
      const condName = f.getAttribute('name')?.trim() || '';
      if (!src || !tgt) return;
      (flows[src] ??= []).push({ tgt, condName });
    });

    const startId = doc.querySelector('startEvent')?.getAttribute('id')
      || Object.keys(nodes)[0]
      || null;

    return { nodes, flows, startId };
  } catch {
    return empty;
  }
}

function nodeTypeInfo(node, isGateway, t) {
  const tag = (node.tag || '').toLowerCase();
  if (tag.includes('startevent')) {
    return { label: t('processMaps.walkthrough.node_start'), color: 'bg-green-100 text-green-700', icon: '🟢' };
  }
  if (tag.includes('endevent')) {
    return { label: t('processMaps.walkthrough.node_end'), color: 'bg-red-100 text-red-700', icon: '🔴' };
  }
  if (isGateway) {
    return { label: t('processMaps.walkthrough.node_decision'), color: 'bg-amber-100 text-amber-700', icon: '🔷' };
  }
  return { label: t('processMaps.walkthrough.node_activity'), color: 'bg-[#1C3755]/10 text-[#1C3755]', icon: '📋' };
}

export default function WalkthroughModal({ xml, onClose }) {
  const { t } = useTranslation('common');
  const graph = useMemo(() => parseBpmnGraph(xml), [xml]);

  const [currentId, setCurrentId] = useState(null);
  const [history, setHistory] = useState([]);

  useEffect(() => {
    setCurrentId(graph.startId);
    setHistory(graph.startId ? [graph.startId] : []);
  }, [graph]);

  const totalNodes = Object.keys(graph.nodes).length;

  if (totalNodes === 0 || !currentId) {
    return (
      <Shell onClose={onClose} title={t('processMaps.walkthrough.title')} count={0} t={t}>
        <p className="text-sm text-slate-500 text-center py-8">{t('processMaps.walkthrough.empty')}</p>
      </Shell>
    );
  }

  const node = graph.nodes[currentId] || {};
  const nexts = graph.flows[currentId] || [];
  const isGateway = (node.tag || '').toLowerCase().includes('gateway');
  const isEnd = (node.tag || '').toLowerCase().includes('endevent') || nexts.length === 0;
  const type = nodeTypeInfo(node, isGateway, t);

  const goTo = (id) => {
    if (!id) return;
    setHistory((h) => [...h, id]);
    setCurrentId(id);
  };
  const goBack = () => {
    if (history.length <= 1) return;
    const next = history.slice(0, -1);
    setHistory(next);
    setCurrentId(next[next.length - 1]);
  };

  return (
    <Shell onClose={onClose} title={t('processMaps.walkthrough.title')} count={history.length} t={t}>
      <div className="flex items-center gap-2 mb-3">
        <span className="text-lg">{type.icon}</span>
        <span className={`text-xs font-bold px-2.5 py-1 rounded-full ${type.color}`}>{type.label}</span>
      </div>

      <h2 className="text-[#1C3755] text-lg font-extrabold mb-3 leading-snug break-words">
        {history.length}. {node.name || type.label}
      </h2>

      {node.description && (
        <div className="bg-[#F8F9FB] rounded-xl p-4 mb-4 border border-black/5">
          <p className="text-slate-600 text-sm leading-relaxed whitespace-pre-line break-words">
            {node.description}
          </p>
        </div>
      )}

      {isGateway && nexts.length > 0 && (
        <div className="space-y-2">
          <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-1">
            <HelpCircle size={12} /> {t('processMaps.walkthrough.question')}
          </p>
          {nexts.map(({ tgt, condName }) => {
            const label = condName || graph.nodes[tgt]?.name || tgt;
            return (
              <button
                key={tgt}
                onClick={() => goTo(tgt)}
                className="w-full flex items-center gap-3 px-4 py-3 rounded-xl border-2 border-[#1C3755]/20 bg-[#F8F9FB] text-[#1C3755] font-bold text-sm hover:border-[#1C3755] transition text-left"
              >
                <ChevronRight size={15} className="shrink-0" /> {label}
              </button>
            );
          })}
        </div>
      )}

      <div className="flex items-center justify-between mt-6 pt-4 border-t border-black/8">
        <button
          onClick={goBack}
          disabled={history.length <= 1}
          className="flex items-center gap-2 px-4 py-2 rounded-xl border border-black/10 text-sm font-bold text-slate-600 hover:text-[#1C3755] disabled:opacity-30 transition"
        >
          <ChevronLeft size={16} /> {t('processMaps.walkthrough.back')}
        </button>
        {isEnd ? (
          <button
            onClick={onClose}
            className="flex items-center gap-2 px-5 py-2 rounded-xl bg-green-600 text-white text-sm font-bold hover:opacity-90 transition"
          >
            <CheckCircle size={16} /> {t('processMaps.walkthrough.complete')}
          </button>
        ) : (
          !isGateway && (
            <button
              onClick={() => goTo(nexts[0].tgt)}
              className="flex items-center gap-2 px-5 py-2 rounded-xl bg-[#1C3755] text-white text-sm font-bold hover:opacity-90 transition"
            >
              {t('processMaps.walkthrough.next')} <ChevronRight size={16} />
            </button>
          )
        )}
      </div>
    </Shell>
  );
}

WalkthroughModal.propTypes = {
  xml: PropTypes.string,
  onClose: PropTypes.func.isRequired,
};

function Shell({ title, count, onClose, t, children }) {
  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div className="flex items-center justify-between px-6 py-4 bg-[#1C3755]">
          <div className="flex items-center gap-2">
            <span className="text-white font-bold text-sm">{title}</span>
            <span className="text-white/50 text-xs">{t('processMaps.walkthrough.steps', { count })}</span>
          </div>
          <button onClick={onClose} className="w-8 h-8 rounded-lg flex items-center justify-center text-white/60 hover:text-white hover:bg-white/10 transition">
            <X size={16} />
          </button>
        </div>
        <div className="p-6">{children}</div>
      </div>
    </div>
  );
}

Shell.propTypes = {
  title: PropTypes.string.isRequired,
  count: PropTypes.number.isRequired,
  onClose: PropTypes.func.isRequired,
  t: PropTypes.func.isRequired,
  children: PropTypes.node.isRequired,
};
