import { useState, useRef, useCallback } from 'react';
import PropTypes from 'prop-types';
import { useTranslation } from 'react-i18next';
import { repositoryDocumentsApi } from '../../api/repositoryDocuments';
import { useAuthStore } from '../../store/authStore';

const ACCEPTED_MIME = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'image/jpeg',
  'image/png',
  'image/gif',
];

const MAX_SIZE_BYTES = 20 * 1024 * 1024; // 20 MB

export default function UploadDocumentModal({ repositoryId, category, categoryLabel, onClose, onUploaded }) {
  const { t } = useTranslation('common');
  const userName = useAuthStore((s) => s.user?.name ?? '');

  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [file, setFile] = useState(null);
  const [dragOver, setDragOver] = useState(false);
  const [fileError, setFileError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  const inputRef = useRef(null);

  const validateFile = useCallback((f) => {
    if (!ACCEPTED_MIME.includes(f.type)) {
      setFileError(t('repository.upload_modal_drop_hint'));
      return false;
    }
    if (f.size > MAX_SIZE_BYTES) {
      setFileError(t('repository.upload_modal_drop_hint'));
      return false;
    }
    setFileError('');
    return true;
  }, [t]);

  const handleFile = useCallback((f) => {
    if (f && validateFile(f)) setFile(f);
  }, [validateFile]);

  const handleDrop = useCallback((e) => {
    e.preventDefault();
    setDragOver(false);
    const f = e.dataTransfer.files[0];
    if (f) handleFile(f);
  }, [handleFile]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!file) return;
    setSubmitting(true);
    setError('');

    const formData = new FormData();
    formData.append('file', file);
    formData.append('title', title);
    formData.append('section', 'setup');
    formData.append('setup_category', category);
    if (description) formData.append('description', description);

    try {
      const doc = await repositoryDocumentsApi.upload(repositoryId, formData);
      onUploaded(doc);
      onClose();
    } catch {
      setError(t('repository.doc_upload_error'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-md">
        {/* Header */}
        <div className="px-6 pt-6 pb-4 border-b border-slate-100">
          <h2 className="text-lg font-semibold text-slate-800">{t('repository.upload_modal_title')}</h2>
          <p className="text-sm text-slate-500 mt-0.5">{categoryLabel}</p>
        </div>

        <form onSubmit={handleSubmit} className="px-6 py-5 space-y-4">
          {/* Document name */}
          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
              {t('repository.upload_modal_doc_name')}
            </label>
            <input
              type="text"
              required
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          {/* Description */}
          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">
              {t('repository.upload_modal_description')}
            </label>
            <textarea
              rows={2}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder={t('repository.upload_modal_description_placeholder')}
              className="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
            />
          </div>

          {/* Drop zone */}
          <div
            role="button"
            tabIndex={0}
            aria-label={t('repository.upload_modal_drop_zone')}
            onClick={() => inputRef.current?.click()}
            onKeyDown={(e) => e.key === 'Enter' && inputRef.current?.click()}
            onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
            onDragLeave={() => setDragOver(false)}
            onDrop={handleDrop}
            className={[
              'border-2 border-dashed rounded-xl py-8 text-center cursor-pointer transition-colors',
              dragOver ? 'border-blue-400 bg-blue-50' : 'border-slate-200 hover:border-slate-300',
            ].join(' ')}
          >
            <input
              ref={inputRef}
              type="file"
              accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif"
              className="hidden"
              onChange={(e) => handleFile(e.target.files[0])}
            />
            {file ? (
              <div className="space-y-1">
                <p className="text-sm font-medium text-slate-700">{file.name}</p>
                <p className="text-xs text-slate-400">{(file.size / 1024).toFixed(1)} KB</p>
              </div>
            ) : (
              <div className="space-y-1">
                <p className="text-sm text-slate-500">{t('repository.upload_modal_drop_zone')}</p>
                <p className="text-xs text-slate-400">{t('repository.upload_modal_drop_hint')}</p>
              </div>
            )}
          </div>
          {fileError && <p className="text-xs text-red-600">{fileError}</p>}

          {/* Auto-record note */}
          <p className="text-xs text-slate-400">
            {t('repository.upload_modal_auto_note', { name: userName })}
          </p>

          {error && <p className="text-sm text-red-600">{error}</p>}

          {/* Actions */}
          <div className="flex gap-3 pt-1">
            <button
              type="button"
              onClick={onClose}
              disabled={submitting}
              className="flex-1 px-4 py-2 rounded-lg text-sm font-medium text-slate-600 border border-slate-200 hover:bg-slate-50 transition-colors disabled:opacity-50"
            >
              {t('common.cancel')}
            </button>
            <button
              type="submit"
              disabled={submitting || !file || !title}
              className="flex-1 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-slate-800 hover:bg-slate-700 transition-colors disabled:opacity-50"
            >
              {submitting ? '…' : t('repository.upload_modal_submit')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

UploadDocumentModal.propTypes = {
  repositoryId: PropTypes.number.isRequired,
  category: PropTypes.string.isRequired,
  categoryLabel: PropTypes.string.isRequired,
  onClose: PropTypes.func.isRequired,
  onUploaded: PropTypes.func.isRequired,
};
