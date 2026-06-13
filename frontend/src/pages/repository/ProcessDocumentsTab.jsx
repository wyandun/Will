import { useState, useEffect } from 'react';
import PropTypes from 'prop-types';
import { useTranslation } from 'react-i18next';
import { repositoryDocumentsApi } from '../../api/repositoryDocuments';

// ─── Constants ────────────────────────────────────────────────────────────────

const CATEGORY_COLORS = {
  strategic:   { bg: '#1C3755', text: '#ffffff' },
  value_chain: { bg: '#D5B170', text: '#1C3755' },
  support:     { bg: '#5C7A5E', text: '#ffffff' },
};

const TYPE_BADGE_COLORS = {
  MP:  'bg-blue-100 text-blue-700',
  FOR: 'bg-amber-100 text-amber-700',
  MN:  'bg-indigo-100 text-indigo-700',
  IN:  'bg-teal-100 text-teal-700',
  AN:  'bg-slate-100 text-slate-600',
  PO:  'bg-purple-100 text-purple-700',
  PR:  'bg-green-100 text-green-700',
  CR:  'bg-rose-100 text-rose-700',
};

function getViewUrl(fileUrl, fileType) {
  if (!fileUrl) return null;
  const isOffice = /\.(docx?|xlsx?|pptx?)$/i.test(fileUrl) || (fileType && (fileType.includes('word') || fileType.includes('excel') || fileType.includes('spreadsheet') || fileType.includes('presentation')));
  if (isOffice) {
    return `https://view.officeapps.live.com/op/view.aspx?src=${encodeURIComponent(fileUrl)}`;
  }
  return fileUrl;
}

// ─── Icons ────────────────────────────────────────────────────────────────────

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

function IconDocument() {
  return (
    <svg className="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
    </svg>
  );
}

// ─── Document row ─────────────────────────────────────────────────────────────

function DocumentRow({ doc }) {
  const { t, i18n } = useTranslation('common');
  const isEs = i18n.language?.startsWith('es');
  const title = isEs ? doc.title_es : (doc.title_en || doc.title_es);
  const typeLabel = t(`repository.process_doc_types.${doc.type}`, { defaultValue: doc.type });
  const typeBadgeClass = TYPE_BADGE_COLORS[doc.type] ?? 'bg-slate-100 text-slate-600';
  const viewUrl = getViewUrl(doc.file_url);

  return (
    <div className="flex items-center gap-3 py-2 px-3 rounded-lg hover:bg-slate-50 transition-colors group">
      <IconDocument />
      <span className="text-xs font-mono text-slate-400 shrink-0">{doc.code}</span>
      <span className="flex-1 text-sm text-slate-700 truncate">{title}</span>
      <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium shrink-0 ${typeBadgeClass}`}>
        {typeLabel}
      </span>
      <span className="text-xs text-slate-400 shrink-0">v{doc.version}.0</span>
      {viewUrl && (
        <a
          href={viewUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="invisible group-hover:visible inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium text-slate-600 border border-slate-200 hover:bg-white transition-colors shrink-0"
        >
          {t('repository.view_btn')}
        </a>
      )}
    </div>
  );
}

DocumentRow.propTypes = {
  doc: PropTypes.object.isRequired,
};

// ─── Subprocess section ───────────────────────────────────────────────────────

function SubProcessSection({ sub }) {
  const hasDocuments = sub.docs_count > 0;
  if (!hasDocuments) return null;

  return (
    <div className="pl-4 border-l-2 border-slate-100 space-y-1">
      <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide py-1">
        {sub.code} — {sub.name_es}
      </p>
      {sub.documents.map((doc) => (
        <DocumentRow key={doc.id} doc={doc} />
      ))}
      {sub.sub_sub_processes?.filter((s) => s.docs_count > 0).map((ssub) => (
        <div key={ssub.id} className="pl-4 border-l-2 border-slate-100 space-y-1">
          <p className="text-xs font-medium text-slate-400 py-1">{ssub.code} — {ssub.name_es}</p>
          {ssub.documents.map((doc) => (
            <DocumentRow key={doc.id} doc={doc} />
          ))}
        </div>
      ))}
    </div>
  );
}

SubProcessSection.propTypes = {
  sub: PropTypes.object.isRequired,
};

// ─── Process (macroprocess) block ─────────────────────────────────────────────

function MacroprocessBlock({ process, categoryColors }) {
  const [open, setOpen] = useState(true);

  if (process.docs_count === 0) return null;

  return (
    <div className="border border-slate-200 rounded-xl overflow-hidden">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="w-full flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors"
      >
        {/* 2-letter code badge */}
        <span
          className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs font-bold shrink-0"
          style={{ background: categoryColors.bg, color: categoryColors.text }}
        >
          {process.code?.substring(0, 3) ?? '??'}
        </span>
        <span className="flex-1 text-left text-sm font-semibold text-slate-800">{process.name_es}</span>
        {process.docs_count > 0 && (
          <span className="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
            {process.docs_count}
          </span>
        )}
        <span className="text-slate-400">{open ? <IconChevronUp /> : <IconChevronDown />}</span>
      </button>

      {open && (
        <div className="border-t border-slate-100 px-4 py-3 space-y-3">
          {process.sub_processes.map((sub) => (
            <SubProcessSection key={sub.id} sub={sub} />
          ))}
        </div>
      )}
    </div>
  );
}

MacroprocessBlock.propTypes = {
  process: PropTypes.object.isRequired,
  categoryColors: PropTypes.object.isRequired,
};

// ─── Category section ─────────────────────────────────────────────────────────

function CategorySection({ category }) {
  const [open, setOpen] = useState(true);
  const colors = CATEGORY_COLORS[category.type] ?? CATEGORY_COLORS.strategic;

  if (category.docs_count === 0) return null;

  return (
    <div className="border border-slate-200 rounded-xl overflow-hidden">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="w-full flex items-center gap-3 px-5 py-4 hover:bg-slate-50 transition-colors"
      >
        <span
          className="w-2.5 h-2.5 rounded-full shrink-0"
          style={{ background: colors.bg }}
        />
        <span className="flex-1 text-left text-sm font-bold text-slate-800">{category.name_es}</span>
        {category.docs_count > 0 && (
          <span className="inline-flex items-center justify-center min-w-6 h-6 px-2 rounded-full text-xs font-bold text-white shrink-0"
            style={{ background: colors.bg }}>
            {category.docs_count}
          </span>
        )}
        <span className="text-slate-400">{open ? <IconChevronUp /> : <IconChevronDown />}</span>
      </button>

      {open && (
        <div className="border-t border-slate-100 px-5 py-4 space-y-3">
          {category.processes.map((process) => (
            <MacroprocessBlock key={process.id} process={process} categoryColors={colors} />
          ))}
        </div>
      )}
    </div>
  );
}

CategorySection.propTypes = {
  category: PropTypes.object.isRequired,
};

// ─── Tab ─────────────────────────────────────────────────────────────────────

export default function ProcessDocumentsTab({ repositoryId }) {
  const { t } = useTranslation('common');
  const [tree, setTree] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError('');

    repositoryDocumentsApi
      .processDocuments(repositoryId)
      .then((data) => {
        if (!cancelled) setTree(data);
      })
      .catch(() => {
        if (!cancelled) setError(t('repository.doc_load_error'));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => { cancelled = true; };
  }, [repositoryId, t]);

  if (loading) {
    return <p className="text-sm text-slate-400 py-12 text-center">{t('common.loading')}</p>;
  }

  if (error) {
    return <p className="text-sm text-red-600 py-12 text-center">{error}</p>;
  }

  if (!tree) {
    return <p className="text-sm text-slate-400 py-12 text-center">{t('repository.no_process_map')}</p>;
  }

  return (
    <div className="space-y-4">
      {tree.categories.map((cat) => (
        <CategorySection key={cat.id} category={cat} />
      ))}
    </div>
  );
}

ProcessDocumentsTab.propTypes = {
  repositoryId: PropTypes.number.isRequired,
};
