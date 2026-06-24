import { useState, useEffect } from 'react';
import PropTypes from 'prop-types';
import { useTranslation } from 'react-i18next';
import { repositoriesApi } from '../../api/repositories';

// ─── Constants ──────────────────────────────────────────────────────────────

// Fixed sections, always rendered in this order.
const SECTIONS = ['strategic', 'value_chain', 'support'];

// Fallback labels for document types not present in the i18n catalog.
const DOC_TYPE_FALLBACK = {
  MP: 'Manual de Proceso',
  FOR: 'Formato',
  MN: 'Manual',
  IN: 'Instructivo',
  AN: 'Anexo',
  PO: 'Política',
  PR: 'Procedimiento',
  CR: 'Criterio',
};

const BADGE_COLORS = [
  'bg-blue-100 text-blue-700',
  'bg-violet-100 text-violet-700',
  'bg-emerald-100 text-emerald-700',
  'bg-amber-100 text-amber-700',
  'bg-rose-100 text-rose-700',
  'bg-cyan-100 text-cyan-700',
];

// Deterministically maps a process code to one of the fixed badge colors.
function badgeColor(code) {
  const sum = (code ?? '').split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
  return BADGE_COLORS[sum % BADGE_COLORS.length];
}

// Extracts the short badge label (first segment of the code, e.g. "GTH").
function badgeLabel(code) {
  if (!code) return '—';
  return code.split('-')[0].slice(0, 3).toUpperCase();
}

// ─── Icons ──────────────────────────────────────────────────────────────────

function IconChevronDown() {
  return (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
    </svg>
  );
}

function IconChevronUp() {
  return (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
    </svg>
  );
}

function IconView() {
  return (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
      <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
  );
}

// ─── Document row ─────────────────────────────────────────────────────────────

function DocumentRow({ doc }) {
  const { t, i18n } = useTranslation('common');
  const title = i18n.language === 'es' ? doc.title_es : doc.title_en;
  const typeLabel = t(`repository.doc_types.${doc.type}`, {
    defaultValue: DOC_TYPE_FALLBACK[doc.type] ?? doc.type,
  });

  return (
    <div className="flex items-center gap-3 px-4 py-2.5 bg-white rounded-lg border border-slate-100 hover:border-slate-200 transition-colors">
      <div className="flex-1 min-w-0 flex flex-wrap items-center gap-x-2 gap-y-1">
        <span className="text-xs font-mono font-semibold text-slate-500 shrink-0">{doc.code}</span>
        <span className="text-sm font-medium text-slate-800 truncate">{title}</span>
        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
          {typeLabel}
        </span>
        <span className="text-xs text-slate-400">v{doc.version}</span>
      </div>

      {doc.file_url && (
        <a
          href={doc.file_url}
          target="_blank"
          rel="noopener noreferrer"
          className="shrink-0 inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-slate-600 border border-slate-200 hover:bg-slate-50 transition-colors"
        >
          <IconView />
          {t('repository.view_btn')}
        </a>
      )}
    </div>
  );
}

DocumentRow.propTypes = {
  doc: PropTypes.object.isRequired,
};

// ─── Sub-process block ────────────────────────────────────────────────────────

function SubProcessBlock({ subProcess }) {
  const { i18n } = useTranslation('common');
  const name = i18n.language === 'es' ? subProcess.name_es : subProcess.name_en;
  const documents = subProcess.documents ?? [];

  return (
    <div className="space-y-2">
      <div className="flex items-center gap-2">
        <span className="text-xs font-mono font-semibold text-slate-400">{subProcess.code}</span>
        <span className="text-sm font-medium text-slate-700">{name}</span>
      </div>
      <div className="space-y-1.5 pl-1">
        {documents.map((doc) => (
          <DocumentRow key={doc.id} doc={doc} />
        ))}
      </div>
    </div>
  );
}

SubProcessBlock.propTypes = {
  subProcess: PropTypes.object.isRequired,
};

// ─── Process block (collapsible) ──────────────────────────────────────────────

function ProcessBlock({ process }) {
  const { i18n } = useTranslation('common');
  const [open, setOpen] = useState(false);
  const name = i18n.language === 'es' ? process.name_es : process.name_en;
  const subProcesses = process.sub_processes ?? [];

  return (
    <div className="border border-slate-100 rounded-lg overflow-hidden">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="w-full flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors text-left"
      >
        <span
          className={[
            'inline-flex items-center justify-center px-2 py-0.5 rounded text-xs font-bold shrink-0',
            badgeColor(process.code),
          ].join(' ')}
        >
          {badgeLabel(process.code)}
        </span>
        <span className="flex-1 min-w-0 text-sm font-semibold text-slate-800 truncate">{name}</span>
        <span className="shrink-0 text-slate-400">{open ? <IconChevronUp /> : <IconChevronDown />}</span>
      </button>

      {open && (
        <div className="border-t border-slate-100 px-4 py-3 space-y-4 bg-slate-50/50">
          {subProcesses.map((sub) => (
            <SubProcessBlock key={sub.id} subProcess={sub} />
          ))}
        </div>
      )}
    </div>
  );
}

ProcessBlock.propTypes = {
  process: PropTypes.object.isRequired,
};

// ─── Section (collapsible) ────────────────────────────────────────────────────

function countSectionDocs(category) {
  if (!category) return 0;
  return (category.processes ?? []).reduce(
    (procAcc, proc) =>
      procAcc +
      (proc.sub_processes ?? []).reduce(
        (subAcc, sub) => subAcc + (sub.documents ?? []).length,
        0,
      ),
    0,
  );
}

function SectionBlock({ sectionKey, category, initialOpen }) {
  const { t } = useTranslation('common');
  const [open, setOpen] = useState(initialOpen);
  const processes = category?.processes ?? [];
  const docCount = countSectionDocs(category);

  return (
    <div className="border border-slate-200 rounded-xl overflow-hidden">
      {/* Header */}
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="w-full flex items-center gap-3 px-5 py-4 hover:bg-slate-50 transition-colors text-left"
      >
        <div className="w-1 self-stretch rounded-full bg-blue-600 shrink-0" />
        <div className="flex-1 min-w-0 flex items-center gap-2 flex-wrap">
          <span className="text-sm font-semibold text-slate-800">
            {t(`repository.process_sections.${sectionKey}`)}
          </span>
          {docCount > 0 && (
            <span className="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
              {docCount}
            </span>
          )}
        </div>
        <span className="shrink-0 text-slate-400">{open ? <IconChevronUp /> : <IconChevronDown />}</span>
      </button>

      {/* Content */}
      {open && (
        <div className="border-t border-slate-100 px-5 py-3 space-y-2">
          {processes.length === 0 ? (
            <p className="text-xs text-slate-400 py-4 text-center">
              {t('repository.process_section_empty')}
            </p>
          ) : (
            processes.map((proc) => <ProcessBlock key={proc.id} process={proc} />)
          )}
        </div>
      )}
    </div>
  );
}

SectionBlock.propTypes = {
  sectionKey: PropTypes.string.isRequired,
  category: PropTypes.object,
  initialOpen: PropTypes.bool,
};

SectionBlock.defaultProps = {
  category: null,
  initialOpen: false,
};

// ─── Tab ──────────────────────────────────────────────────────────────────────

export default function ProcessDocumentsTab({ repositoryId }) {
  const { t } = useTranslation('common');
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError('');

    repositoriesApi
      .getProcessDocuments(repositoryId)
      .then((result) => {
        if (!cancelled) setData(result);
      })
      .catch(() => {
        if (!cancelled) setError(t('repository.process_docs_load_error'));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [repositoryId, t]);

  if (loading) {
    return <p className="text-sm text-slate-400 py-12 text-center">{t('common.loading')}</p>;
  }

  if (error) {
    return <p className="text-sm text-red-600 py-12 text-center">{error}</p>;
  }

  // No ProcessMap associated with the company.
  if (data === null) {
    return (
      <div className="flex flex-col items-center justify-center py-16 text-center">
        <div className="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mb-4">
          <svg className="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776" />
          </svg>
        </div>
        <p className="text-sm text-slate-400">{t('repository.no_process_map')}</p>
      </div>
    );
  }

  // Index categories by type for quick lookup against the fixed section list.
  const categoriesByType = {};
  (data.categories ?? []).forEach((cat) => {
    categoriesByType[cat.type] = cat;
  });

  return (
    <div className="space-y-3">
      {SECTIONS.map((sectionKey, i) => (
        <SectionBlock
          key={sectionKey}
          sectionKey={sectionKey}
          category={categoriesByType[sectionKey] ?? null}
          initialOpen={i === 0}
        />
      ))}
    </div>
  );
}

ProcessDocumentsTab.propTypes = {
  repositoryId: PropTypes.number.isRequired,
};
