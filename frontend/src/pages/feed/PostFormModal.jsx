import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { feedApi } from '../../api/feed';

const EMPTY_FORM = {
  title: '',
  body: '',
  type: 'announcement',
  visibility: 'global',
  is_pinned: false,
  published_at: '',
};

export default function PostFormModal({ post, onClose, onSaved }) {
  const { t } = useTranslation('common');
  const isEditing = post !== null;

  const [form, setForm] = useState(EMPTY_FORM);
  const [imageFile, setImageFile] = useState(null);
  const [attachmentFile, setAttachmentFile] = useState(null);
  const [errors, setErrors] = useState({});
  const [apiError, setApiError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const imageInputRef = useRef(null);
  const attachmentInputRef = useRef(null);

  useEffect(() => {
    if (post) {
      setForm({
        title: post.title ?? '',
        body: post.body ?? '',
        type: post.type ?? 'announcement',
        visibility: post.visibility ?? 'global',
        is_pinned: post.is_pinned ?? false,
        published_at: post.published_at ? post.published_at.slice(0, 16) : '',
      });
    } else {
      setForm(EMPTY_FORM);
    }
    setImageFile(null);
    setAttachmentFile(null);
    setErrors({});
    setApiError('');
  }, [post]);

  function handleChange(e) {
    const { name, value, type, checked } = e.target;
    const next = type === 'checkbox' ? checked : value;
    setForm((prev) => ({ ...prev, [name]: next }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  }

  function handleImageChange(e) {
    setImageFile(e.target.files[0] ?? null);
  }

  function handleAttachmentChange(e) {
    setAttachmentFile(e.target.files[0] ?? null);
  }

  function validate() {
    const next = {};
    if (!form.title.trim()) next.title = t('feed.field_title') + ' is required.';
    if (!form.body.trim()) next.body = t('feed.field_content') + ' is required.';
    return next;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setApiError('');

    const fieldErrors = validate();
    if (Object.keys(fieldErrors).length > 0) {
      setErrors(fieldErrors);
      return;
    }

    const formData = new FormData();
    formData.append('title', form.title.trim());
    formData.append('body', form.body.trim());
    formData.append('type', form.type);
    formData.append('visibility', form.visibility);
    formData.append('is_pinned', form.is_pinned ? '1' : '0');

    if (form.published_at) {
      formData.append('published_at', form.published_at);
    }
    if (imageFile) {
      formData.append('image', imageFile);
    }
    if (attachmentFile) {
      formData.append('attachment', attachmentFile);
    }

    setIsSubmitting(true);
    try {
      if (isEditing) {
        await feedApi.updatePost(post.id, formData);
      } else {
        await feedApi.createPost(formData);
      }
      onSaved(isEditing ? t('feed.post_updated') : t('feed.post_created'));
    } catch (error) {
      const serverErrors = error?.response?.data?.errors;
      if (serverErrors) {
        const mapped = {};
        Object.entries(serverErrors).forEach(([field, messages]) => {
          mapped[field] = Array.isArray(messages) ? messages[0] : messages;
        });
        setErrors(mapped);
      } else {
        const msg = error?.response?.data?.message;
        setApiError(msg || t('common.unexpected_error'));
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  const typeOptions = [
    { value: 'announcement', label: t('feed.type_options.announcement') },
    { value: 'news', label: t('feed.type_options.news') },
    { value: 'training', label: t('feed.type_options.training') },
    { value: 'alert', label: t('feed.type_options.alert') },
  ];

  return (
    <div
      className="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div className="relative z-50 w-full max-w-lg mx-4 bg-white rounded-2xl shadow-xl max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <div className="flex items-center gap-3">
            <h2 className="text-base font-semibold text-slate-800">
              {isEditing ? t('feed.edit_post_title') : t('feed.create_post_title')}
            </h2>
            <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">
              {isEditing ? t('feed.badge_edit') : t('feed.badge_create')}
            </span>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label={t('common.close')}
            className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form onSubmit={handleSubmit} noValidate className="overflow-y-auto flex-1">
          <div className="px-6 py-5 space-y-4">
            {apiError && (
              <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3">
                <p className="text-sm text-red-700">{apiError}</p>
              </div>
            )}

            {/* Title */}
            <div>
              <label htmlFor="pf-title" className="block text-sm font-medium text-slate-700 mb-1">
                {t('feed.field_title')} <span className="text-red-500">{t('common.required')}</span>
              </label>
              <input
                id="pf-title"
                name="title"
                type="text"
                value={form.title}
                onChange={handleChange}
                disabled={isSubmitting}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition ${errors.title ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title}</p>}
            </div>

            {/* Content */}
            <div>
              <label htmlFor="pf-body" className="block text-sm font-medium text-slate-700 mb-1">
                {t('feed.field_content')} <span className="text-red-500">{t('common.required')}</span>
              </label>
              <textarea
                id="pf-body"
                name="body"
                rows={4}
                value={form.body}
                onChange={handleChange}
                disabled={isSubmitting}
                className={`w-full rounded-lg border px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition resize-none ${errors.body ? 'border-red-400 bg-red-50' : 'border-slate-300'}`}
              />
              {errors.body && <p className="mt-1 text-xs text-red-600">{errors.body}</p>}
            </div>

            {/* Type + Visibility */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="pf-type" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('feed.field_type')}
                </label>
                <select
                  id="pf-type"
                  name="type"
                  value={form.type}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition appearance-none bg-white"
                >
                  {typeOptions.map((opt) => (
                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                  ))}
                </select>
              </div>
              <div>
                <label htmlFor="pf-visibility" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('feed.field_visibility')}
                </label>
                <select
                  id="pf-visibility"
                  name="visibility"
                  value={form.visibility}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition appearance-none bg-white"
                >
                  <option value="global">{t('feed.visibility_global')}</option>
                  <option value="franchise">{t('feed.visibility_franchise')}</option>
                </select>
              </div>
            </div>

            {/* Pin + Publish date */}
            <div className="grid grid-cols-2 gap-4 items-start">
              <div className="flex items-center gap-2 pt-6">
                <input
                  id="pf-pinned"
                  name="is_pinned"
                  type="checkbox"
                  checked={form.is_pinned}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                />
                <label htmlFor="pf-pinned" className="text-sm font-medium text-slate-700">
                  {t('feed.field_pin')}
                </label>
              </div>
              <div>
                <label htmlFor="pf-published-at" className="block text-sm font-medium text-slate-700 mb-1">
                  {t('feed.field_publish_date')}
                </label>
                <input
                  id="pf-published-at"
                  name="published_at"
                  type="datetime-local"
                  value={form.published_at}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-slate-50 disabled:text-slate-400 transition"
                />
              </div>
            </div>

            {/* Image */}
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">
                {t('feed.field_image')}
              </label>
              <button
                type="button"
                onClick={() => imageInputRef.current?.click()}
                disabled={isSubmitting}
                className="w-full rounded-lg border border-dashed border-slate-300 px-3 py-2.5 text-sm text-slate-500 hover:border-blue-400 hover:text-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition text-left"
              >
                {imageFile ? imageFile.name : t('feed.field_image') + '…'}
              </button>
              <input
                ref={imageInputRef}
                type="file"
                accept="image/*"
                onChange={handleImageChange}
                className="hidden"
              />
            </div>

            {/* Attachment */}
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">
                {t('feed.field_attachment')}
              </label>
              <button
                type="button"
                onClick={() => attachmentInputRef.current?.click()}
                disabled={isSubmitting}
                className="w-full rounded-lg border border-dashed border-slate-300 px-3 py-2.5 text-sm text-slate-500 hover:border-blue-400 hover:text-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition text-left"
              >
                {attachmentFile ? attachmentFile.name : t('feed.field_attachment') + '…'}
              </button>
              <input
                ref={attachmentInputRef}
                type="file"
                onChange={handleAttachmentChange}
                className="hidden"
              />
            </div>
          </div>

          {/* Footer */}
          <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-2xl">
            <button
              type="button"
              onClick={onClose}
              disabled={isSubmitting}
              className="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {t('common.cancel')}
            </button>
            <button
              type="submit"
              disabled={isSubmitting || !form.title.trim() || !form.body.trim()}
              className="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {isSubmitting
                ? t('common.saving')
                : isEditing ? t('common.save') : t('feed.publish')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

PostFormModal.propTypes = {
  post: PropTypes.shape({
    id: PropTypes.number,
    title: PropTypes.string,
    body: PropTypes.string,
    type: PropTypes.string,
    visibility: PropTypes.string,
    is_pinned: PropTypes.bool,
    published_at: PropTypes.string,
  }),
  onClose: PropTypes.func.isRequired,
  onSaved: PropTypes.func.isRequired,
};
