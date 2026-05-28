import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { feedApi } from '../../api/feed';
import { useAuthStore } from '../../store/authStore';
import { usePermissions } from '../../hooks/usePermissions';
import NewsModal from './NewsModal';
import PostFormModal from './PostFormModal';
import { timeAgo } from '../../utils/time';

// ─── Icons ────────────────────────────────────────────────────────────────────

function IconSearch({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
    </svg>
  );
}

function IconPin({ className = 'w-3.5 h-3.5' }) {
  return (
    <svg className={className} fill="currentColor" viewBox="0 0 24 24">
      <path d="M16 12V4h1a1 1 0 000-2H7a1 1 0 000 2h1v8l-2 2v2h5v5l1 1 1-1v-5h5v-2l-2-2z" />
    </svg>
  );
}

function IconComment({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    </svg>
  );
}

function IconTrash({ className = 'w-3.5 h-3.5' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m-7 0a2 2 0 012-2h4a2 2 0 012 2M4 7h16" />
    </svg>
  );
}

function IconX({ className = 'w-5 h-5' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
    </svg>
  );
}

function IconExternalLink({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
    </svg>
  );
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const TYPE_COLORS = {
  announcement: 'bg-blue-100 text-blue-700',
  news: 'bg-green-100 text-green-700',
  training: 'bg-purple-100 text-purple-700',
  alert: 'bg-red-100 text-red-700',
};

function typeBadgeClass(type) {
  return TYPE_COLORS[type] ?? 'bg-slate-100 text-slate-600';
}

// Role badge config: role → { label, className }
const ROLE_BADGES = {
  superadmin: { label: 'Super Admin', className: 'bg-purple-100 text-purple-700' },
  admin_sm: { label: 'Admin SM', className: 'bg-blue-100 text-blue-700' },
  sb_owner: { label: 'SB Owner', className: 'bg-green-100 text-green-700' },
  sb_employee: { label: 'Empleado', className: 'bg-slate-100 text-slate-600' },
  bb_employee: { label: 'Business Bishop', className: 'bg-amber-100 text-amber-700' },
};

const EMOJI_OPTIONS = ['👍', '❤️', '😂', '🎉', '😮'];

/**
 * Convert URLs in plain text to clickable <a> elements.
 * Handles http:// and https:// links.
 */
const URL_SPLIT_RE = /(https?:\/\/[^\s]+)/g;
const URL_TEST_RE = /^https?:\/\/[^\s]+$/;

function LinkifiedText({ text }) {
  if (!text) return null;

  const parts = text.split(URL_SPLIT_RE);

  return (
    <>
      {parts.map((part, i) =>
        URL_TEST_RE.test(part) ? (
          <a
            key={i}
            href={part}
            target="_blank"
            rel="noopener noreferrer"
            className="text-blue-600 underline break-all hover:text-blue-800"
            onClick={(e) => e.stopPropagation()}
          >
            {part}
          </a>
        ) : (
          <span key={i}>{part}</span>
        )
      )}
    </>
  );
}

// ─── Skeleton ─────────────────────────────────────────────────────────────────

function Skeleton({ className }) {
  return <div className={`animate-pulse bg-slate-200 rounded-xl ${className}`} />;
}

// ─── CommentPanel ─────────────────────────────────────────────────────────────

function CommentPanel({ postId, onToast, onCommentCountChange }) {
  const { t } = useTranslation('common');
  const [comments, setComments] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [text, setText] = useState('');
  const inputRef = useRef(null);
  const sendingRef = useRef(false);

  const fetchComments = (page = 1) => {
    setLoading(true);
    feedApi.getComments(postId, page)
      .then((res) => {
        const payload = res.data.data ?? {};
        const items = payload.items ?? [];
        setComments((prev) => page === 1 ? items : [...prev, ...items]);
        setMeta(payload.meta ?? null);
      })
      .catch(() => onToast(t('feed.load_comments_error')))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchComments(1);
    inputRef.current?.focus();
  }, [postId]); // eslint-disable-line react-hooks/exhaustive-deps

  function handleSend() {
    const trimmed = text.trim();
    if (!trimmed || sendingRef.current) return;
    sendingRef.current = true;
    setSending(true);
    feedApi.addComment(postId, trimmed)
      .then((res) => {
        const comment = res.data.data?.comment;
        if (comment) setComments((prev) => [...prev, comment]);
        setText('');
        if (meta) setMeta((m) => m ? { ...m, total: m.total + 1 } : m);
        onCommentCountChange?.(1);
      })
      .catch(() => onToast(t('feed.comment_error')))
      .finally(() => { sendingRef.current = false; setSending(false); });
  }

  function handleKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  }

  function handleDelete(commentId) {
    if (!window.confirm(t('feed.comment_delete_confirm'))) return;
    feedApi.deleteComment(commentId)
      .then(() => {
        setComments((prev) => prev.filter((c) => c.id !== commentId));
        onToast(t('feed.comment_deleted'));
        onCommentCountChange?.(-1);
      })
      .catch(() => onToast(t('common.unexpected_error')));
  }

  return (
    <div className="border-t border-slate-100 pt-3 flex flex-col gap-3">
      {/* Comment list */}
      {loading ? (
        <div className="flex flex-col gap-2">
          {[1, 2].map((i) => <Skeleton key={i} className="h-10" />)}
        </div>
      ) : comments.length === 0 ? (
        <p className="text-xs text-slate-400 text-center py-2">{t('feed.no_comments')}</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {comments.map((c) => (
            <li key={c.id} className="flex items-start gap-2 group">
              {c.author_avatar_url ? (
                <img src={c.author_avatar_url} alt={c.author_name} className="w-7 h-7 rounded-full object-cover flex-shrink-0 mt-0.5" />
              ) : (
                <div className="w-7 h-7 rounded-full bg-slate-200 text-slate-600 text-xs font-bold flex items-center justify-center flex-shrink-0 mt-0.5">
                  {(c.author_name ?? '?')[0].toUpperCase()}
                </div>
              )}
              <div className="flex-1 min-w-0 bg-slate-50 rounded-xl px-3 py-1.5">
                <p className="text-xs font-semibold text-slate-700">{c.author_name}</p>
                <p className="text-xs text-slate-600 break-words">
                  <LinkifiedText text={c.content} />
                </p>
                <p className="text-[10px] text-slate-400 mt-0.5">{timeAgo(c.created_at)}</p>
              </div>
              {c.is_own && (
                <button
                  type="button"
                  onClick={() => handleDelete(c.id)}
                  className="opacity-0 group-hover:opacity-100 p-1 text-slate-400 hover:text-red-500 transition-all flex-shrink-0 mt-1"
                  aria-label="Delete comment"
                >
                  <IconTrash />
                </button>
              )}
            </li>
          ))}
        </ul>
      )}

      {/* Load more */}
      {meta && meta.current_page < meta.last_page && (
        <button
          type="button"
          onClick={() => fetchComments(meta.current_page + 1)}
          className="text-xs text-blue-600 hover:underline self-center"
        >
          {t('feed.next')}
        </button>
      )}

      {/* Input */}
      <div className="flex items-center gap-2">
        <input
          ref={inputRef}
          type="text"
          value={text}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={handleKeyDown}
          maxLength={2000}
          placeholder={t('feed.comment_placeholder')}
          className="flex-1 text-xs border border-slate-200 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent bg-white"
        />
        <button
          type="button"
          onClick={handleSend}
          disabled={!text.trim() || sending}
          className="px-3 py-1.5 rounded-xl text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          {t('feed.comment_send')}
        </button>
      </div>
    </div>
  );
}

// ─── PostDetailModal ──────────────────────────────────────────────────────────

function PostDetailModal({ post, onClose, onToast, onPostUpdated }) {
  const { t } = useTranslation('common');
  const backdropRef = useRef(null);
  const emojiRef = useRef(null);
  const initial = (post.author_name ?? '?')[0].toUpperCase();
  const typeKey = `feed.type_${post.type}`;
  const roleBadge = post.author_role ? ROLE_BADGES[post.author_role] : null;

  const [emojiPickerOpen, setEmojiPickerOpen] = useState(false);

  const likesCount = post.likes_count ?? 0;
  const userReaction = post.user_reaction ?? null;
  const commentsCount = post.comments_count ?? 0;

  const reactionsLabel = t(
    likesCount === 1 ? 'feed.reactions_count_one' : 'feed.reactions_count_other',
    { count: likesCount }
  );
  const commentsLabel = t(
    commentsCount === 1 ? 'feed.comments_count_one' : 'feed.comments_count_other',
    { count: commentsCount }
  );

  useEffect(() => {
    function handleKeyDown(e) {
      if (e.key === 'Escape') onClose();
    }
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [onClose]);

  useEffect(() => {
    if (!emojiPickerOpen) return;
    function handleOutside(e) {
      if (emojiRef.current && !emojiRef.current.contains(e.target)) {
        setEmojiPickerOpen(false);
      }
    }
    document.addEventListener('mousedown', handleOutside);
    return () => document.removeEventListener('mousedown', handleOutside);
  }, [emojiPickerOpen]);

  function handleBackdrop(e) {
    if (e.target === backdropRef.current) onClose();
  }

  function handleReact(emoji) {
    setEmojiPickerOpen(false);
    feedApi.reactToPost(post.id, emoji)
      .then((res) => {
        const data = res.data.data ?? {};
        onPostUpdated?.(post.id, { likes_count: data.likes_count ?? 0, user_reaction: data.user_reaction ?? null });
      })
      .catch(() => onToast?.(t('common.unexpected_error')));
  }

  return (
    <div
      ref={backdropRef}
      onClick={handleBackdrop}
      className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
    >
      <div className="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[90vh]">
        {/* Close button */}
        <button
          type="button"
          onClick={onClose}
          className="absolute top-4 right-4 z-10 p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
          aria-label={t('common.close')}
        >
          <IconX />
        </button>

        {/* Scrollable body */}
        <div className="overflow-y-auto flex flex-col gap-4 p-6 pr-12" onClick={(e) => e.stopPropagation()}>
          {/* Type badge + pin + time */}
          <div className="flex items-center gap-2 flex-wrap">
            {post.type && (
              <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${typeBadgeClass(post.type)}`}>
                {t(typeKey, post.type)}
              </span>
            )}
            {post.is_pinned && (
              <span className="flex items-center gap-1 text-xs text-amber-600 font-medium">
                <IconPin className="w-3 h-3" />
                {t('feed.pinned')}
              </span>
            )}
            <span className="ml-auto text-xs text-slate-400">{timeAgo(post.created_at)}</span>
          </div>

          {/* Image */}
          {post.image_url && (
            <img
              src={post.image_url}
              alt={post.title}
              referrerPolicy="no-referrer"
              className="w-full max-h-64 object-cover rounded-xl"
              onError={(e) => { e.currentTarget.style.display = 'none'; }}
            />
          )}

          {/* Title */}
          <h2 className="text-lg font-bold text-slate-800 leading-snug">{post.title}</h2>

          {/* Body */}
          <p className="text-sm text-slate-600 leading-relaxed whitespace-pre-wrap">
            <LinkifiedText text={post.body} />
          </p>

          {/* Read original article */}
          {post.article_url && (
            <a
              href={post.article_url}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 self-start px-4 py-2 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
            >
              {t('feed.read_article')}
              <IconExternalLink className="w-4 h-4" />
            </a>
          )}

          {/* Author row */}
          <div className="flex items-center gap-2 pt-2 border-t border-slate-100">
            {post.author_avatar ? (
              <img
                src={post.author_avatar}
                alt={post.author_name}
                referrerPolicy="no-referrer"
                className="w-10 h-10 rounded-full object-cover flex-shrink-0"
                onError={(e) => { e.currentTarget.style.display = 'none'; }}
              />
            ) : (
              <div className="w-10 h-10 rounded-full bg-amber-100 text-amber-700 text-xs font-bold flex items-center justify-center flex-shrink-0">
                {initial}
              </div>
            )}
            <span className="text-sm text-slate-700 font-medium truncate">{post.author_name}</span>
            {roleBadge && (
              <span className={`text-[10px] font-semibold px-1.5 py-0.5 rounded-full flex-shrink-0 ${roleBadge.className}`}>
                {roleBadge.label}
              </span>
            )}
          </div>

          {/* Reaction + comment count */}
          <p className="text-xs text-slate-400">
            {reactionsLabel} · {commentsLabel}
          </p>

          {/* Action bar */}
          <div className="flex items-center gap-2 border-t border-slate-100 pt-2">
            <div className="relative" ref={emojiRef}>
              <button
                type="button"
                onClick={() => setEmojiPickerOpen((prev) => !prev)}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium transition-colors ${
                  userReaction
                    ? 'bg-amber-50 text-amber-700 hover:bg-amber-100'
                    : 'text-slate-500 hover:bg-slate-100'
                }`}
              >
                <span className="text-base leading-none">{userReaction ?? '😊'}</span>
                {t('feed.btn_react')}
              </button>
              {emojiPickerOpen && (
                <div className="absolute left-0 top-9 z-20 flex items-center gap-1 bg-white border border-slate-200 rounded-2xl shadow-lg px-2 py-1.5">
                  {EMOJI_OPTIONS.map((emoji) => (
                    <button
                      key={emoji}
                      type="button"
                      onClick={() => handleReact(emoji)}
                      className={`text-xl leading-none p-1 rounded-lg hover:bg-slate-100 transition-colors ${
                        userReaction === emoji ? 'bg-amber-100' : ''
                      }`}
                      aria-label={emoji}
                    >
                      {emoji}
                    </button>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Comments — always shown inside modal */}
          <CommentPanel
            postId={post.id}
            onToast={onToast ?? (() => {})}
            onCommentCountChange={(delta) =>
              onPostUpdated?.(post.id, { comments_count: (post.comments_count ?? 0) + delta })
            }
          />
        </div>
      </div>
    </div>
  );
}

// ─── PostCard ─────────────────────────────────────────────────────────────────

function PostCard({ post, currentUser, canWriteFeed, onEdit, onDelete, onToast, onOpen, onPostUpdated }) {
  const { t } = useTranslation('common');
  const [menuOpen, setMenuOpen] = useState(false);
  const [emojiPickerOpen, setEmojiPickerOpen] = useState(false);
  const [commentOpen, setCommentOpen] = useState(false);
  const menuRef = useRef(null);
  const emojiRef = useRef(null);

  const likesCount = post.likes_count ?? 0;
  const userReaction = post.user_reaction ?? null;
  const commentsCount = post.comments_count ?? 0;

  const initial = (post.author_name ?? '?')[0].toUpperCase();
  const typeKey = `feed.type_${post.type}`;
  const roleBadge = post.author_role ? ROLE_BADGES[post.author_role] : null;

  const canManage =
    currentUser &&
    (currentUser.id === post.author_id || canWriteFeed);

  // Close menus when clicking outside
  useEffect(() => {
    if (!menuOpen && !emojiPickerOpen) return;
    function handleOutside(e) {
      if (menuRef.current && !menuRef.current.contains(e.target)) {
        setMenuOpen(false);
      }
      if (emojiRef.current && !emojiRef.current.contains(e.target)) {
        setEmojiPickerOpen(false);
      }
    }
    document.addEventListener('mousedown', handleOutside);
    return () => document.removeEventListener('mousedown', handleOutside);
  }, [menuOpen, emojiPickerOpen]);

  function handleReact(emoji) {
    setEmojiPickerOpen(false);
    feedApi.reactToPost(post.id, emoji)
      .then((res) => {
        const data = res.data.data ?? {};
        onPostUpdated(post.id, { likes_count: data.likes_count ?? 0, user_reaction: data.user_reaction ?? null });
      })
      .catch(() => onToast(t('common.unexpected_error')));
  }

  function handleCopyLink() {
    setMenuOpen(false);
    navigator.clipboard.writeText(`${window.location.origin}/feed/posts/${post.id}`)
      .then(() => onToast(t('feed.link_copied')))
      .catch(() => onToast(t('common.unexpected_error')));
  }

  const reactionsLabel = t(
    likesCount === 1 ? 'feed.reactions_count_one' : 'feed.reactions_count_other',
    { count: likesCount }
  );
  const commentsLabel = t(
    commentsCount === 1 ? 'feed.comments_count_one' : 'feed.comments_count_other',
    { count: commentsCount }
  );

  return (
    <div
      className="flex flex-col gap-3 p-5 bg-white rounded-2xl border border-slate-100 hover:border-slate-200 hover:shadow-sm transition-all cursor-pointer"
      onClick={onOpen}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => { if (e.key === 'Enter') onOpen(); }}
    >
      {/* Header: type badge + pin + menu */}
      <div className="flex items-center gap-2">
        {post.type && (
          <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${typeBadgeClass(post.type)}`}>
            {t(typeKey, post.type)}
          </span>
        )}
        {post.is_pinned && (
          <span className="flex items-center gap-1 text-xs text-amber-600 font-medium">
            <IconPin className="w-3 h-3" />
            {t('feed.pinned')}
          </span>
        )}
        <span className="ml-auto text-xs text-slate-400">{timeAgo(post.created_at)}</span>

        {canManage && (
          <div className="relative" ref={menuRef} onClick={(e) => e.stopPropagation()}>
            <button
              type="button"
              onClick={() => setMenuOpen((prev) => !prev)}
              className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors leading-none"
              aria-label="Post menu"
            >
              ···
            </button>
            {menuOpen && (
              <div className="absolute right-0 top-7 z-20 w-40 bg-white border border-slate-200 rounded-xl shadow-lg py-1">
                <button
                  type="button"
                  onClick={() => { setMenuOpen(false); onEdit(post); }}
                  className="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
                >
                  {t('feed.menu_edit')}
                </button>
                <button
                  type="button"
                  onClick={() => { setMenuOpen(false); onDelete(post); }}
                  className="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors"
                >
                  {t('feed.menu_delete')}
                </button>
                <button
                  type="button"
                  onClick={handleCopyLink}
                  className="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
                >
                  {t('feed.menu_copy_link')}
                </button>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Image */}
      {post.image_url && (
        <img
          src={post.image_url}
          alt={post.title}
          referrerPolicy="no-referrer"
          className="w-full h-36 object-cover rounded-xl"
          onError={(e) => { e.currentTarget.style.display = 'none'; }}
        />
      )}

      {/* Title + body */}
      <div>
        <p className="text-sm font-semibold text-slate-800 line-clamp-1 group-hover:text-blue-600 transition-colors">
          {post.title}
        </p>
        <p className="text-xs text-slate-500 mt-1 line-clamp-3">
          <LinkifiedText text={post.body} />
        </p>
      </div>

      {/* Author row */}
      <div className="flex items-center gap-2">
        {post.author_avatar ? (
          <img
            src={post.author_avatar}
            alt={post.author_name}
            referrerPolicy="no-referrer"
            className="w-10 h-10 rounded-full object-cover flex-shrink-0"
            onError={(e) => { e.currentTarget.style.display = 'none'; }}
          />
        ) : (
          <div className="w-10 h-10 rounded-full bg-amber-100 text-amber-700 text-xs font-bold flex items-center justify-center flex-shrink-0">
            {initial}
          </div>
        )}
        <span className="text-xs text-slate-600 truncate">{post.author_name}</span>
        {roleBadge && (
          <span className={`text-[10px] font-semibold px-1.5 py-0.5 rounded-full flex-shrink-0 ${roleBadge.className}`}>
            {roleBadge.label}
          </span>
        )}
      </div>

      {/* Reaction + comment counter */}
      <div className="text-xs text-slate-400">
        {reactionsLabel} · {commentsLabel}
      </div>

      {/* Action buttons + inline comment panel — stopPropagation prevents card click */}
      <div onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center gap-2 pt-1 border-t border-slate-50">
          {/* React button with emoji picker */}
          <div className="relative" ref={emojiRef}>
            <button
              type="button"
              onClick={() => setEmojiPickerOpen((prev) => !prev)}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium transition-colors ${
                userReaction
                  ? 'bg-amber-50 text-amber-700 hover:bg-amber-100'
                  : 'text-slate-500 hover:bg-slate-100'
              }`}
            >
              <span className="text-base leading-none">{userReaction ?? '😊'}</span>
              {t('feed.btn_react')}
            </button>
            {emojiPickerOpen && (
              <div className="absolute left-0 bottom-9 z-20 flex items-center gap-1 bg-white border border-slate-200 rounded-2xl shadow-lg px-2 py-1.5">
                {EMOJI_OPTIONS.map((emoji) => (
                  <button
                    key={emoji}
                    type="button"
                    onClick={() => handleReact(emoji)}
                    className={`text-xl leading-none p-1 rounded-lg hover:bg-slate-100 transition-colors ${
                      userReaction === emoji ? 'bg-amber-100' : ''
                    }`}
                    aria-label={emoji}
                  >
                    {emoji}
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Comment button */}
          <button
            type="button"
            onClick={() => setCommentOpen((prev) => !prev)}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium transition-colors ${
              commentOpen
                ? 'bg-blue-50 text-blue-700 hover:bg-blue-100'
                : 'text-slate-500 hover:bg-slate-100'
            }`}
          >
            <IconComment className="w-4 h-4" />
            {t('feed.btn_comment')}
          </button>
        </div>

        {commentOpen && (
          <CommentPanel
            postId={post.id}
            onToast={onToast}
            onCommentCountChange={(delta) =>
              onPostUpdated(post.id, { comments_count: (post.comments_count ?? 0) + delta })
            }
          />
        )}
      </div>
    </div>
  );
}

// ─── UserAvatar ───────────────────────────────────────────────────────────────

function UserAvatar({ user }) {
  const [imgError, setImgError] = useState(false);
  const initial = (user.name ?? '?')[0].toUpperCase();

  if (user.avatar_url && !imgError) {
    return (
      <img
        src={user.avatar_url}
        alt={user.name}
        referrerPolicy="no-referrer"
        className="w-8 h-8 rounded-full object-cover flex-shrink-0"
        onError={() => setImgError(true)}
      />
    );
  }

  return (
    <div className="w-8 h-8 rounded-full bg-slate-200 text-slate-600 text-xs font-bold flex items-center justify-center flex-shrink-0">
      {initial}
    </div>
  );
}

// ─── ComposeBar ───────────────────────────────────────────────────────────────

function ComposeBar({ currentUser, canWriteFeed, onOpenCreate, onOpenNews }) {
  const { t } = useTranslation('common');

  if (!canWriteFeed) return null;

  const initial = (currentUser.name ?? '?')[0].toUpperCase();

  return (
    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 p-4 bg-white rounded-2xl border border-slate-100">
      <div className="flex items-center gap-3 flex-1 min-w-0">
        {currentUser.avatar_url ? (
          <img
            src={currentUser.avatar_url}
            alt={currentUser.name}
            className="w-9 h-9 rounded-full object-cover flex-shrink-0"
          />
        ) : (
          <div className="w-9 h-9 rounded-full bg-amber-100 text-amber-700 text-xs font-bold flex items-center justify-center flex-shrink-0">
            {initial}
          </div>
        )}
        <button
          type="button"
          onClick={onOpenCreate}
          className="flex-1 text-left text-sm text-slate-400 bg-slate-50 hover:bg-slate-100 rounded-xl px-4 py-2.5 transition-colors"
        >
          {t('feed.compose_placeholder')}
        </button>
      </div>
      <div className="flex gap-2 flex-shrink-0">
        <button
          type="button"
          onClick={onOpenCreate}
          className="flex-1 sm:flex-none px-4 py-2 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
        >
          {t('feed.new_post')}
        </button>
        <button
          type="button"
          onClick={onOpenNews}
          className="flex-1 sm:flex-none px-4 py-2 rounded-xl text-sm font-semibold text-amber-700 bg-amber-100 hover:bg-amber-200 transition-colors"
        >
          {t('feed.news_btn')}
        </button>
      </div>
    </div>
  );
}

// ─── OnlineNowPanel ───────────────────────────────────────────────────────────

function OnlineNowPanel({ users, loading }) {
  const { t } = useTranslation('common');

  return (
    <div className="bg-white rounded-2xl border border-slate-100 p-5">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <span className="w-2 h-2 rounded-full bg-green-500 flex-shrink-0" />
          <span className="font-semibold text-slate-700 text-sm">
            {t('feed.online_now')}
          </span>
          {!loading && (
            <span className="text-xs bg-green-100 text-green-700 rounded-full px-2 py-0.5 font-medium">
              {users.length}
            </span>
          )}
        </div>
      </div>

      {loading ? (
        <div className="flex flex-col gap-3">
          {[1, 2, 3].map((i) => (
            <Skeleton key={i} className="h-8" />
          ))}
        </div>
      ) : users.length === 0 ? (
        <p className="text-xs text-slate-400 py-3 text-center">{t('feed.no_online')}</p>
      ) : (
        <ul className="flex flex-col gap-3">
          {users.map((u) => (
            <li key={u.id} className="flex items-center gap-3">
              <div className="relative">
                <UserAvatar user={u} />
                <span className="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-green-500 border-2 border-white rounded-full" />
              </div>
              <span className="text-sm text-slate-700 truncate">{u.name}</span>
              {u.is_current_user && (
                <span className="ml-auto text-xs bg-amber-100 text-amber-700 rounded-full px-2 py-0.5 font-medium flex-shrink-0">
                  {t('feed.you')}
                </span>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

// ─── RecentlyActivePanel ──────────────────────────────────────────────────────

function RecentlyActivePanel({ users, loading }) {
  const { t } = useTranslation('common');

  function roleBadge(role) {
    if (!role) return '?';
    return role.split('_').map((w) => w[0].toUpperCase()).join('');
  }

  return (
    <div className="bg-white rounded-2xl border border-slate-100 p-5">
      <div className="flex items-center gap-2 mb-4">
        <span className="font-semibold text-slate-700 text-sm">{t('feed.recently_active')}</span>
      </div>

      {loading ? (
        <div className="flex flex-col gap-3">
          {[1, 2, 3].map((i) => (
            <Skeleton key={i} className="h-8" />
          ))}
        </div>
      ) : users.length === 0 ? (
        <p className="text-xs text-slate-400 py-3 text-center">{t('feed.no_recent')}</p>
      ) : (
        <ul className="flex flex-col gap-3">
          {users.map((u) => (
            <li key={u.id} className="flex items-center gap-2">
              <div className="relative">
                <UserAvatar user={u} />
                {u.role && (
                  <span className="absolute -bottom-0.5 -right-0.5 w-4 h-4 bg-slate-700 text-white text-[8px] font-bold rounded-full flex items-center justify-center leading-none">
                    {roleBadge(u.role)}
                  </span>
                )}
              </div>
              <div className="min-w-0 flex-1">
                <p className="text-sm text-slate-700 truncate">{u.name}</p>
                <p className="text-xs text-slate-400">{timeAgo(u.last_seen_at)}</p>
              </div>
              {u.is_current_user && (
                <span className="text-xs bg-amber-100 text-amber-700 rounded-full px-2 py-0.5 font-medium flex-shrink-0">
                  {t('feed.you')}
                </span>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

// ─── Pagination ───────────────────────────────────────────────────────────────

function Pagination({ meta, onPageChange, loading }) {
  const { t } = useTranslation('common');
  if (!meta || meta.last_page <= 1) return null;

  return (
    <div className="flex items-center justify-between pt-1">
      <button
        onClick={() => onPageChange(meta.current_page - 1)}
        disabled={meta.current_page <= 1 || loading}
        className="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-slate-200 text-sm font-medium text-slate-600 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
      >
        ‹ {t('feed.prev')}
      </button>

      <div className="flex flex-col items-center gap-0.5">
        <span className="text-sm font-medium text-slate-700">
          {t('feed.page_info', { current: meta.current_page, total: meta.last_page })}
        </span>
        <span className="text-xs text-slate-400">
          {t('feed.posts_total', { count: meta.total })}
        </span>
      </div>

      <button
        onClick={() => onPageChange(meta.current_page + 1)}
        disabled={meta.current_page >= meta.last_page || loading}
        className="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-slate-200 text-sm font-medium text-slate-600 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
      >
        {t('feed.next')} ›
      </button>
    </div>
  );
}

// ─── Toast ────────────────────────────────────────────────────────────────────

function Toast({ message, onDismiss }) {
  useEffect(() => {
    const timer = setTimeout(onDismiss, 3000);
    return () => clearTimeout(timer);
  }, [onDismiss]);

  return (
    <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-70 bg-slate-800 text-white text-sm px-5 py-3 rounded-xl shadow-lg">
      {message}
    </div>
  );
}

// ─── FeedPage ─────────────────────────────────────────────────────────────────

export default function FeedPage() {
  const { t } = useTranslation('common');
  const authUser = useAuthStore((s) => s.user);
  const { canWrite } = usePermissions();
  const canWriteFeed = canWrite('feed');

  const [posts, setPosts] = useState([]);
  const [meta, setMeta] = useState(null);
  const [postsLoading, setPostsLoading] = useState(true);
  const [postsError, setPostsError] = useState(null);

  const [presence, setPresence] = useState({ online: [], recently_active: [] });
  const [presenceLoading, setPresenceLoading] = useState(true);

  const [search, setSearch] = useState('');
  const debounceRef = useRef(null);

  // Modal state
  const [modalPost, setModalPost] = useState(undefined); // undefined=closed, null=create, object=edit
  const [newsModalOpen, setNewsModalOpen] = useState(false);
  const [detailPostId, setDetailPostId] = useState(null);
  const detailPost = posts.find((p) => p.id === detailPostId) ?? null;
  const [toast, setToast] = useState('');

  const fetchPosts = (term, pageNum) => {
    setPostsLoading(true);
    setPostsError(null);
    const params = { page: pageNum, per_page: 10, ...(term ? { search: term } : {}) };
    feedApi.getPosts(params)
      .then((res) => {
        const payload = res.data.data ?? {};
        setPosts(payload.items ?? []);
        setMeta(payload.meta ?? null);
      })
      .catch(() => setPostsError(t('feed.load_error')))
      .finally(() => setPostsLoading(false));
  };

  const fetchPresence = () => {
    setPresenceLoading(true);
    feedApi.getPresence()
      .then((res) => setPresence(res.data.data ?? { online: [], recently_active: [] }))
      .catch(() => {})
      .finally(() => setPresenceLoading(false));
  };

  useEffect(() => {
    fetchPosts('', 1);
    fetchPresence();
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  function handleSearchChange(e) {
    const value = e.target.value;
    setSearch(value);
    clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      fetchPosts(value, 1);
    }, 300);
  }

  function handlePageChange(newPage) {
    setDetailPostId(null);
    fetchPosts(search, newPage);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function handlePostSaved(message) {
    setModalPost(undefined);
    setToast(message);
    fetchPosts(search, meta?.current_page ?? 1);
  }

  function updatePostInList(postId, updates) {
    setPosts((prev) =>
      prev.map((p) => (p.id === postId ? { ...p, ...updates } : p))
    );
  }

  function handleDeletePost(post) {
    if (!window.confirm(t('feed.delete_post_confirm'))) return;
    feedApi.deletePost(post.id)
      .then(() => {
        setToast(t('feed.post_deleted'));
        fetchPosts(search, meta?.current_page ?? 1);
      })
      .catch(() => setToast(t('common.unexpected_error')));
  }

  return (
    <div className="flex flex-col lg:flex-row gap-5">
      {/* Left column — compose + search + posts */}
      <div className="flex-1 min-w-0 flex flex-col gap-5">
        {/* Compose bar */}
        <ComposeBar
          currentUser={authUser}
          canWriteFeed={canWriteFeed}
          onOpenCreate={() => setModalPost(null)}
          onOpenNews={() => setNewsModalOpen(true)}
        />

        {/* Search bar */}
        <div className="relative">
          <span className="absolute inset-y-0 left-3.5 flex items-center text-slate-400 pointer-events-none">
            <IconSearch />
          </span>
          <input
            type="text"
            value={search}
            onChange={handleSearchChange}
            placeholder={t('feed.search_placeholder')}
            className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent transition"
          />
        </div>

        {/* Posts list */}
        {postsLoading ? (
          <div className="flex flex-col gap-4">
            {[1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-44" />
            ))}
          </div>
        ) : postsError ? (
          <div className="flex items-center justify-center py-16">
            <p className="text-sm text-red-500">{postsError}</p>
          </div>
        ) : posts.length === 0 ? (
          <div className="flex items-center justify-center py-16">
            <p className="text-sm text-slate-400">
              {search ? t('feed.no_posts_search') : t('feed.no_posts')}
            </p>
          </div>
        ) : (
          <>
            <div className="flex flex-col gap-4">
              {posts.map((post) => (
                <PostCard
                  key={post.id}
                  post={post}
                  currentUser={authUser}
                  canWriteFeed={canWriteFeed}
                  onEdit={(p) => setModalPost(p)}
                  onDelete={handleDeletePost}
                  onToast={setToast}
                  onOpen={() => setDetailPostId(post.id)}
                  onPostUpdated={updatePostInList}
                />
              ))}
            </div>

            <Pagination meta={meta} onPageChange={handlePageChange} loading={postsLoading} />
          </>
        )}
      </div>

      {/* Right column — presence panels (hidden on small screens) */}
      <div className="hidden lg:flex flex-col w-72 flex-shrink-0 gap-4 sticky top-20 self-start">
        <OnlineNowPanel users={presence.online ?? []} loading={presenceLoading} />
        <RecentlyActivePanel users={presence.recently_active ?? []} loading={presenceLoading} />
      </div>

      {/* Post detail modal */}
      {detailPost && (
        <PostDetailModal
          post={detailPost}
          onClose={() => setDetailPostId(null)}
          onToast={setToast}
          onPostUpdated={updatePostInList}
        />
      )}

      {/* Post create/edit modal */}
      {modalPost !== undefined && (
        <PostFormModal
          post={modalPost}
          onClose={() => setModalPost(undefined)}
          onSaved={handlePostSaved}
        />
      )}

      {/* News modal */}
      {newsModalOpen && (
        <NewsModal
          onClose={() => setNewsModalOpen(false)}
          onPublished={() => fetchPosts(search, 1)}
        />
      )}

      {/* Toast notification */}
      {toast && <Toast message={toast} onDismiss={() => setToast('')} />}
    </div>
  );
}

// ─── PropTypes ────────────────────────────────────────────────────────────────

const classNameProp = { className: PropTypes.string };
IconSearch.propTypes = classNameProp;
IconPin.propTypes = classNameProp;
IconComment.propTypes = classNameProp;
IconTrash.propTypes = classNameProp;
IconX.propTypes = classNameProp;
IconExternalLink.propTypes = classNameProp;

Skeleton.propTypes = {
  className: PropTypes.string,
};

LinkifiedText.propTypes = {
  text: PropTypes.string,
};

Pagination.propTypes = {
  meta: PropTypes.shape({
    current_page: PropTypes.number.isRequired,
    last_page: PropTypes.number.isRequired,
    per_page: PropTypes.number.isRequired,
    total: PropTypes.number.isRequired,
  }),
  onPageChange: PropTypes.func.isRequired,
  loading: PropTypes.bool.isRequired,
};

const userShape = PropTypes.shape({
  id: PropTypes.number.isRequired,
  name: PropTypes.string,
  avatar_url: PropTypes.string,
  role: PropTypes.string,
  last_seen_at: PropTypes.string,
  is_current_user: PropTypes.bool,
});

CommentPanel.propTypes = {
  postId: PropTypes.number.isRequired,
  onToast: PropTypes.func.isRequired,
  onCommentCountChange: PropTypes.func,
};

const postShape = PropTypes.shape({
  id: PropTypes.number.isRequired,
  author_id: PropTypes.number,
  author_role: PropTypes.string,
  title: PropTypes.string,
  body: PropTypes.string,
  type: PropTypes.string,
  is_pinned: PropTypes.bool,
  image_url: PropTypes.string,
  article_url: PropTypes.string,
  author_name: PropTypes.string,
  author_avatar: PropTypes.string,
  likes_count: PropTypes.number,
  comments_count: PropTypes.number,
  user_reaction: PropTypes.string,
  created_at: PropTypes.string,
});

PostCard.propTypes = {
  post: postShape.isRequired,
  currentUser: PropTypes.shape({
    id: PropTypes.number,
    name: PropTypes.string,
    avatar_url: PropTypes.string,
  }),
  canWriteFeed: PropTypes.bool,
  onEdit: PropTypes.func.isRequired,
  onDelete: PropTypes.func.isRequired,
  onToast: PropTypes.func.isRequired,
  onOpen: PropTypes.func.isRequired,
  onPostUpdated: PropTypes.func.isRequired,
};

PostDetailModal.propTypes = {
  post: postShape.isRequired,
  onClose: PropTypes.func.isRequired,
  onToast: PropTypes.func,
  onPostUpdated: PropTypes.func,
};

UserAvatar.propTypes = {
  user: userShape.isRequired,
};

ComposeBar.propTypes = {
  currentUser: PropTypes.shape({
    id: PropTypes.number,
    name: PropTypes.string,
    avatar_url: PropTypes.string,
  }),
  canWriteFeed: PropTypes.bool,
  onOpenCreate: PropTypes.func.isRequired,
  onOpenNews: PropTypes.func.isRequired,
};

OnlineNowPanel.propTypes = {
  users: PropTypes.arrayOf(userShape).isRequired,
  loading: PropTypes.bool.isRequired,
};

RecentlyActivePanel.propTypes = {
  users: PropTypes.arrayOf(userShape).isRequired,
  loading: PropTypes.bool.isRequired,
};

Toast.propTypes = {
  message: PropTypes.string.isRequired,
  onDismiss: PropTypes.func.isRequired,
};
