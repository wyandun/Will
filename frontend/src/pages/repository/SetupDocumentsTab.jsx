import { useState, useEffect, useCallback } from 'react';
import PropTypes from 'prop-types';
import { useTranslation } from 'react-i18next';
import { repositoryDocumentsApi } from '../../api/repositoryDocuments';
import UploadDocumentModal from './UploadDocumentModal';

// ─── Helpers ─────────────────────────────────────────────────────────────────

const CATEGORIES = ['legal', 'hr', 'certificates', 'marketing', 'sops'];

function formatFileSize(bytes) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatMime(mime) {
  if (!mime) return 'FILE';
  if (mime.includes('pdf')) return 'PDF';
  if (mime.includes('word') || mime.includes('msword')) return 'DOC';
  if (mime.includes('excel') || mime.includes('spreadsheet')) return 'XLS';
  if (mime.startsWith('image/')) return 'IMG';
  return 'FILE';
}

// ─── Icons ────────────────────────────────────────────────────────────────────

function IconDocument() {
  return (
    <svg className="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 24 24">
      <path d="M14.25 2.25H6a2.25 2.25 0 00-2.25 2.25v15A2.25 2.25 0 006 21.75h12A2.25 2.25 0 0020.25 18V8.25L14.25 2.25z" />
      <path fill="white" d="M14.25 2.25v6h6" />
    </svg>
  );
}

function IconUpload() {
  return (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
    </svg>
  );
}

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

function IconTrash() {
  return (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
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

function DocumentRow({ doc, repositoryId, onDeleted }) {
  const { t } = useTranslation('common');
  const [hovering, setHovering] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const uploaderName = doc.uploader?.name ?? doc.uploaded_by_type ?? '—';
  const orgBadge = doc.uploaded_by_type === 'sm' ? 'Strategic Mates' : uploaderName;
  const formatLabel = formatMime(doc.file_type);
  const sizeLabel = formatFileSize(doc.file_size ?? 0);
  const date = doc.created_at
    ? new Date(doc.created_at).toLocaleDateString(undefined, { month: 'short', day: '2-digit', year: 'numeric' })
    : '—';

  const handleDelete = async () => {
    if (!window.confirm(t('repository.doc_delete_confirm', { name: doc.title }))) return;
    setDeleting(true);
    try {
      await repositoryDocumentsApi.delete(repositoryId, doc.id);
      onDeleted(doc.id);
    } catch {
      alert(t('repository.doc_delete_error'));
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div
      className="flex items-center gap-3 px-4 py-3 bg-white rounded-lg border border-slate-100 hover:border-slate-200 transition-colors relative"
      onMouseEnter={() => setHovering(true)}
      onMouseLeave={() => setHovering(false)}
    >
      {/* File icon */}
      <div className="shrink-0">
        <IconDocument />
      </div>

      {/* Name + badges */}
      <div className="flex-1 min-w-0 flex flex-wrap items-center gap-x-2 gap-y-1">
        <span className="text-sm font-medium text-slate-800 truncate">{doc.title}</span>
        <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-red-100 text-red-600">
          {formatLabel}
        </span>
        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
          {orgBadge}
        </span>
        <span className="text-xs text-slate-400">{uploaderName}</span>
        <span className="text-xs text-slate-400">{date}</span>
        <span className="text-xs text-slate-400">{sizeLabel}</span>
      </div>

      {/* Actions */}
      <div className="shrink-0 flex items-center gap-2">
        {doc.file_url && (
          <a
            href={doc.file_url}
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

DocumentRow.propTypes = {
  doc: PropTypes.object.isRequired,
  repositoryId: PropTypes.number.isRequired,
  onDeleted: PropTypes.func.isRequired,
};

// ─── Category section ─────────────────────────────────────────────────────────

function CategorySection({ category, repositoryId, initialOpen }) {
  const { t } = useTranslation('common');
  const [open, setOpen] = useState(initialOpen);
  const [docs, setDocs] = useState([]);
  const [loaded, setLoaded] = useState(false);
  const [loading, setLoading] = useState(false);
  const [uploadModal, setUploadModal] = useState(false);

  const load = useCallback(() => {
    if (loaded) return;
    setLoading(true);
    repositoryDocumentsApi
      .list(repositoryId, { section: 'setup', category })
      .then((data) => {
        setDocs(Array.isArray(data) ? data : []);
        setLoaded(true);
      })
      .catch(() => setLoaded(true))
      .finally(() => setLoading(false));
  }, [repositoryId, category, loaded]);

  useEffect(() => {
    if (open) load();
  }, [open, load]);

  const handleUploaded = (doc) => {
    setDocs((prev) => [doc, ...prev]);
  };

  const handleDeleted = (docId) => {
    setDocs((prev) => prev.filter((d) => d.id !== docId));
  };

  const toggle = () => setOpen((v) => !v);

  const categoryLabel = t(`repository.categories.${category}`);

  return (
    <>
      <div className="border border-slate-200 rounded-xl overflow-hidden">
        {/* Header */}
        <div className="flex items-center gap-3 px-5 py-4">
          <div className="w-1 self-stretch rounded-full bg-blue-600 shrink-0" />
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <span className="text-sm font-semibold text-slate-800">{categoryLabel}</span>
              {docs.length > 0 && (
                <span className="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
                  {docs.length}
                </span>
              )}
            </div>
            <p className="text-xs text-slate-400 mt-0.5">{t(`repository.category_desc.${category}`)}</p>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <button
              type="button"
              onClick={() => setUploadModal(true)}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-600 border border-slate-200 hover:bg-slate-50 transition-colors"
            >
              <IconUpload />
              {t('repository.upload_btn')}
            </button>
            <button
              type="button"
              onClick={toggle}
              className="p-1.5 rounded-lg text-slate-400 hover:bg-slate-50 transition-colors"
              aria-label={open ? 'Collapse' : 'Expand'}
            >
              {open ? <IconChevronUp /> : <IconChevronDown />}
            </button>
          </div>
        </div>

        {/* Document list */}
        {open && (
          <div className="border-t border-slate-100 px-5 py-3 space-y-2">
            {loading && <p className="text-xs text-slate-400 py-4 text-center">{t('common.loading')}</p>}
            {!loading && docs.length === 0 && (
              <p className="text-xs text-slate-400 py-4 text-center">{t('common.coming_soon')}</p>
            )}
            {docs.map((doc) => (
              <DocumentRow
                key={doc.id}
                doc={doc}
                repositoryId={repositoryId}
                onDeleted={handleDeleted}
              />
            ))}
          </div>
        )}
      </div>

      {uploadModal && (
        <UploadDocumentModal
          repositoryId={repositoryId}
          category={category}
          categoryLabel={categoryLabel}
          onClose={() => setUploadModal(false)}
          onUploaded={handleUploaded}
        />
      )}
    </>
  );
}

CategorySection.propTypes = {
  category: PropTypes.string.isRequired,
  repositoryId: PropTypes.number.isRequired,
  initialOpen: PropTypes.bool,
};

CategorySection.defaultProps = {
  initialOpen: false,
};

// ─── Tab ─────────────────────────────────────────────────────────────────────

export default function SetupDocumentsTab({ repositoryId }) {
  return (
    <div className="space-y-3">
      {CATEGORIES.map((cat, i) => (
        <CategorySection
          key={cat}
          category={cat}
          repositoryId={repositoryId}
          initialOpen={i === 0}
        />
      ))}
    </div>
  );
}

SetupDocumentsTab.propTypes = {
  repositoryId: PropTypes.number.isRequired,
};
