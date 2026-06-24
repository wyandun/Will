import { useState, useEffect, useMemo } from 'react';
import PropTypes from 'prop-types';
import { useTranslation } from 'react-i18next';
import { repositoriesApi } from '../../api/repositories';
import { repositoryDocumentsApi } from '../../api/repositoryDocuments';
import AddRecordModal from './AddRecordModal';

// ─── Constants ──────────────────────────────────────────────────────────────

// Fixed sections, always rendered in this order (mirrors the process map tree).
const SECTIONS = ['strategic', 'value_chain', 'support'];

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

function formatFileSize(bytes) {
  if (!bytes) return '—';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

// Maps a mime/file type to an icon color matching the record file type.
function fileIconColor(mime) {
  if (!mime) return 'text-slate-400';
  if (mime.includes('pdf')) return 'text-red-500';
  if (mime.startsWith('image/')) return 'text-emerald-500';
  if (mime.includes('excel') || mime.includes('spreadsheet')) return 'text-green-700';
  if (mime.includes('word') || mime.includes('msword')) return 'text-blue-500';
  return 'text-slate-400';
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

function IconTrash() {
  return (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
    </svg>
  );
}

function IconFileDoc({ colorClass }) {
  return (
    <svg className={`w-4 h-4 ${colorClass}`} fill="currentColor" viewBox="0 0 24 24">
      <path d="M14.25 2.25H6a2.25 2.25 0 00-2.25 2.25v15A2.25 2.25 0 006 21.75h12A2.25 2.25 0 0020.25 18V8.25L14.25 2.25z" />
      <path fill="white" d="M14.25 2.25v6h6" />
    </svg>
  );
}

IconFileDoc.propTypes = {
  colorClass: PropTypes.string.isRequired,
};

function IconPlus() {
  return (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
    </svg>
  );
}

// ─── Record row ───────────────────────────────────────────────────────────────

function RecordRow({ record, repositoryId, onDeleted }) {
  const { t } = useTranslation('common');
  const [hovering, setHovering] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const uploaderName = record.uploader?.name ?? '—';
  const sizeLabel = formatFileSize(record.file_size);
  const date = record.created_at
    ? new Date(record.created_at).toLocaleDateString(undefined, { month: 'short', day: '2-digit', year: 'numeric' })
    : '—';

  const handleDelete = async () => {
    if (!window.confirm(t('repository.record_delete_confirm', { name: record.title }))) return;
    setDeleting(true);
    try {
      await repositoryDocumentsApi.delete(repositoryId, record.id);
      onDeleted(record.id);
    } catch {
      alert(t('repository.record_delete_error'));
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div
      className="flex items-center gap-3 px-4 py-2.5 bg-white rounded-lg border border-slate-100 hover:border-slate-200 transition-colors"
      onMouseEnter={() => setHovering(true)}
      onMouseLeave={() => setHovering(false)}
    >
      <div className="shrink-0">
        <IconFileDoc colorClass={fileIconColor(record.file_type)} />
      </div>

      <div className="flex-1 min-w-0 flex flex-wrap items-center gap-x-2 gap-y-1">
        <span className="text-sm font-medium text-slate-800 truncate">{record.title}</span>
        <span className="text-xs text-slate-400">{uploaderName}</span>
        <span className="text-xs text-slate-400">{date}</span>
        <span className="text-xs text-slate-400">{sizeLabel}</span>
      </div>

      <div className="shrink-0 flex items-center gap-2">
        {record.file_url && (
          <a
            href={record.file_url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-slate-600 border border-slate-200 hover:bg-slate-50 transition-colors"
          >
            <IconView />
            {t('repository.view_btn')}
          </a>
        )}
        {hovering && (
          <button
            type="button"
            onClick={handleDelete}
            disabled={deleting}
            className="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
            aria-label={t('common.delete')}
          >
            <IconTrash />
          </button>
        )}
      </div>
    </div>
  );
}

RecordRow.propTypes = {
  record: PropTypes.object.isRequired,
  repositoryId: PropTypes.number.isRequired,
  onDeleted: PropTypes.func.isRequired,
};

// ─── Sub-process block ──────────────────────────────────────────────────────

function SubProcessBlock({ subProcess, records, repositoryId, onDeleted }) {
  const { i18n } = useTranslation('common');
  const name = i18n.language === 'es' ? subProcess.name_es : subProcess.name_en;

  return (
    <div className="space-y-2">
      <div className="flex items-center gap-2">
        <span className="text-xs font-mono font-semibold text-slate-400">{subProcess.code}</span>
        <span className="text-sm font-medium text-slate-700">{name}</span>
      </div>
      {records.length > 0 && (
        <div className="space-y-1.5 pl-1">
          {records.map((record) => (
            <RecordRow
              key={record.id}
              record={record}
              repositoryId={repositoryId}
              onDeleted={onDeleted}
            />
          ))}
        </div>
      )}
    </div>
  );
}

SubProcessBlock.propTypes = {
  subProcess: PropTypes.object.isRequired,
  records: PropTypes.array.isRequired,
  repositoryId: PropTypes.number.isRequired,
  onDeleted: PropTypes.func.isRequired,
};

// ─── Process block (collapsible) ────────────────────────────────────────────

function countProcessRecords(process, recordsByCode) {
  return (process.sub_processes ?? []).reduce(
    (acc, sub) => acc + (recordsByCode[sub.code]?.length ?? 0),
    0,
  );
}

function ProcessBlock({ process, recordsByCode, repositoryId, onDeleted }) {
  const { i18n } = useTranslation('common');
  const [open, setOpen] = useState(false);
  const name = i18n.language === 'es' ? process.name_es : process.name_en;
  const subProcesses = process.sub_processes ?? [];
  const recordCount = countProcessRecords(process, recordsByCode);

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
        {recordCount > 0 && (
          <span className="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 shrink-0">
            {recordCount}
          </span>
        )}
        <span className="shrink-0 text-slate-400">{open ? <IconChevronUp /> : <IconChevronDown />}</span>
      </button>

      {open && (
        <div className="border-t border-slate-100 px-4 py-3 space-y-4 bg-slate-50/50">
          {subProcesses.map((sub) => (
            <SubProcessBlock
              key={sub.id}
              subProcess={sub}
              records={recordsByCode[sub.code] ?? []}
              repositoryId={repositoryId}
              onDeleted={onDeleted}
            />
          ))}
        </div>
      )}
    </div>
  );
}

ProcessBlock.propTypes = {
  process: PropTypes.object.isRequired,
  recordsByCode: PropTypes.object.isRequired,
  repositoryId: PropTypes.number.isRequired,
  onDeleted: PropTypes.func.isRequired,
};

// ─── Section (collapsible) ──────────────────────────────────────────────────

function countSectionRecords(category, recordsByCode) {
  if (!category) return 0;
  return (category.processes ?? []).reduce(
    (acc, proc) => acc + countProcessRecords(proc, recordsByCode),
    0,
  );
}

function SectionBlock({ sectionKey, category, recordsByCode, repositoryId, onDeleted, initialOpen }) {
  const { t } = useTranslation('common');
  const [open, setOpen] = useState(initialOpen);
  const processes = category?.processes ?? [];
  const recordCount = countSectionRecords(category, recordsByCode);

  return (
    <div className="border border-slate-200 rounded-xl overflow-hidden">
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
          {recordCount > 0 && (
            <span className="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
              {recordCount}
            </span>
          )}
        </div>
        <span className="shrink-0 text-slate-400">{open ? <IconChevronUp /> : <IconChevronDown />}</span>
      </button>

      {open && (
        <div className="border-t border-slate-100 px-5 py-3 space-y-2">
          {processes.length === 0 ? (
            <p className="text-xs text-slate-400 py-4 text-center">
              {t('repository.process_section_empty')}
            </p>
          ) : (
            processes.map((proc) => (
              <ProcessBlock
                key={proc.id}
                process={proc}
                recordsByCode={recordsByCode}
                repositoryId={repositoryId}
                onDeleted={onDeleted}
              />
            ))
          )}
        </div>
      )}
    </div>
  );
}

SectionBlock.propTypes = {
  sectionKey: PropTypes.string.isRequired,
  category: PropTypes.object,
  recordsByCode: PropTypes.object.isRequired,
  repositoryId: PropTypes.number.isRequired,
  onDeleted: PropTypes.func.isRequired,
  initialOpen: PropTypes.bool,
};

SectionBlock.defaultProps = {
  category: null,
  initialOpen: false,
};

// ─── Tab ──────────────────────────────────────────────────────────────────────

export default function RecordsTab({ repositoryId }) {
  const { t } = useTranslation('common');
  const [tree, setTree] = useState(null);
  const [records, setRecords] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [modalOpen, setModalOpen] = useState(false);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError('');

    Promise.all([
      repositoriesApi.getProcessDocuments(repositoryId),
      repositoryDocumentsApi.list(repositoryId, { section: 'record' }),
    ])
      .then(([treeData, recordData]) => {
        if (cancelled) return;
        setTree(treeData);
        setRecords(Array.isArray(recordData) ? recordData : []);
      })
      .catch(() => {
        if (!cancelled) setError(t('repository.records_load_error'));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [repositoryId, t]);

  // Group records by their linked sub-process code.
  const recordsByCode = useMemo(() => {
    const map = {};
    records.forEach((record) => {
      const code = record.process_code;
      if (!code) return;
      (map[code] ??= []).push(record);
    });
    return map;
  }, [records]);

  // Flatten the tree into a list of processes (each with sub_processes) for the modal dropdown.
  const processesForModal = useMemo(() => {
    if (!tree) return [];
    return (tree.categories ?? []).flatMap((cat) => cat.processes ?? []);
  }, [tree]);

  const handleUploaded = (record) => {
    setRecords((prev) => [record, ...prev]);
  };

  const handleDeleted = (recordId) => {
    setRecords((prev) => prev.filter((r) => r.id !== recordId));
  };

  if (loading) {
    return <p className="text-sm text-slate-400 py-12 text-center">{t('common.loading')}</p>;
  }

  if (error) {
    return <p className="text-sm text-red-600 py-12 text-center">{error}</p>;
  }

  // No ProcessMap associated with the company.
  if (tree === null) {
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
  (tree.categories ?? []).forEach((cat) => {
    categoriesByType[cat.type] = cat;
  });

  return (
    <div className="space-y-3">
      {/* Header with the add-record action */}
      <div className="flex justify-end">
        <button
          type="button"
          onClick={() => setModalOpen(true)}
          className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-white bg-slate-800 hover:bg-slate-700 transition-colors"
        >
          <IconPlus />
          {t('repository.add_record_btn')}
        </button>
      </div>

      {SECTIONS.map((sectionKey, i) => (
        <SectionBlock
          key={sectionKey}
          sectionKey={sectionKey}
          category={categoriesByType[sectionKey] ?? null}
          recordsByCode={recordsByCode}
          repositoryId={repositoryId}
          onDeleted={handleDeleted}
          initialOpen={i === 0}
        />
      ))}

      {modalOpen && (
        <AddRecordModal
          repositoryId={repositoryId}
          processes={processesForModal}
          onClose={() => setModalOpen(false)}
          onUploaded={handleUploaded}
        />
      )}
    </div>
  );
}

RecordsTab.propTypes = {
  repositoryId: PropTypes.number.isRequired,
};
