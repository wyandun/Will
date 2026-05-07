import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { feedApi } from '../../api/feed';
import { useAuthStore } from '../../store/authStore';

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

function IconHeart({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="currentColor" viewBox="0 0 24 24">
      <path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z" />
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

// ─── Helpers ──────────────────────────────────────────────────────────────────

function timeAgo(dateStr) {
  if (!dateStr) return '';
  const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
  if (diff < 60) return `${diff}s ago`;
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return `${Math.floor(diff / 86400)}d ago`;
}

const TYPE_COLORS = {
  announcement: 'bg-blue-100 text-blue-700',
  news: 'bg-green-100 text-green-700',
  training: 'bg-purple-100 text-purple-700',
  alert: 'bg-red-100 text-red-700',
};

function typeBadgeClass(type) {
  return TYPE_COLORS[type] ?? 'bg-slate-100 text-slate-600';
}

// ─── Skeleton ─────────────────────────────────────────────────────────────────

function Skeleton({ className }) {
  return <div className={`animate-pulse bg-slate-200 rounded-xl ${className}`} />;
}

// ─── PostCard ─────────────────────────────────────────────────────────────────

function PostCard({ post }) {
  const { t } = useTranslation('common');
  const initial = (post.author_name ?? '?')[0].toUpperCase();
  const typeKey = `feed.type_${post.type}`;

  return (
    <div className="flex flex-col gap-3 p-5 bg-white rounded-2xl border border-slate-100 hover:border-slate-200 hover:shadow-sm transition-all">
      {/* Header: type badge + pin */}
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
      </div>

      {/* Image */}
      {post.image_url && (
        <img
          src={post.image_url}
          alt={post.title}
          className="w-full h-36 object-cover rounded-xl"
        />
      )}

      {/* Title + body snippet */}
      <div>
        <p className="text-sm font-semibold text-slate-800 line-clamp-1">{post.title}</p>
        <p className="text-xs text-slate-500 mt-1 line-clamp-2">{post.body}</p>
      </div>

      {/* Footer: author + interactions */}
      <div className="flex items-center justify-between mt-auto">
        <div className="flex items-center gap-2">
          {post.author_avatar ? (
            <img src={post.author_avatar} alt={post.author_name} className="w-6 h-6 rounded-full object-cover" />
          ) : (
            <div className="w-6 h-6 rounded-full bg-amber-100 text-amber-700 text-xs font-bold flex items-center justify-center flex-shrink-0">
              {initial}
            </div>
          )}
          <span className="text-xs text-slate-600 truncate max-w-[8rem]">{post.author_name}</span>
        </div>
        <div className="flex items-center gap-3 text-xs text-slate-400">
          <span className="flex items-center gap-1">
            <IconHeart className="text-rose-400" />
            {post.likes_count ?? 0}
          </span>
          <span className="flex items-center gap-1">
            <IconComment />
            {post.comments_count ?? 0}
          </span>
        </div>
      </div>
    </div>
  );
}

// ─── UserAvatar ───────────────────────────────────────────────────────────────

function UserAvatar({ user }) {
  const initial = (user.name ?? '?')[0].toUpperCase();

  return user.avatar_url ? (
    <img
      src={user.avatar_url}
      alt={user.name}
      className="w-8 h-8 rounded-full object-cover flex-shrink-0"
    />
  ) : (
    <div className="w-8 h-8 rounded-full bg-slate-200 text-slate-600 text-xs font-bold flex items-center justify-center flex-shrink-0">
      {initial}
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

  // Build a short role badge: first letter of each word in the role name
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

// ─── FeedPage ─────────────────────────────────────────────────────────────────

export default function FeedPage() {
  const { t } = useTranslation('common');
  const currentUserId = useAuthStore((s) => s.user?.id);

  const [posts, setPosts] = useState([]);
  const [meta, setMeta] = useState(null);
  const [postsLoading, setPostsLoading] = useState(true);
  const [postsError, setPostsError] = useState(null);

  const [presence, setPresence] = useState({ online: [], recently_active: [] });
  const [presenceLoading, setPresenceLoading] = useState(true);

  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const debounceRef = useRef(null);

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
    setPage(1);
    clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      fetchPosts(value, 1);
    }, 300);
  }

  function handlePageChange(newPage) {
    setPage(newPage);
    fetchPosts(search, newPage);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  return (
    <div className="flex gap-5">
      {/* Left column — search + posts */}
      <div className="flex-1 min-w-0 flex flex-col gap-5">
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
                <PostCard key={post.id} post={post} currentUserId={currentUserId} />
              ))}
            </div>

            <Pagination meta={meta} onPageChange={handlePageChange} loading={postsLoading} />
          </>
        )}
      </div>

      {/* Right column — presence panels */}
      <div className="w-72 flex-shrink-0 flex flex-col gap-4">
        <OnlineNowPanel users={presence.online ?? []} loading={presenceLoading} />
        <RecentlyActivePanel users={presence.recently_active ?? []} loading={presenceLoading} />
      </div>
    </div>
  );
}

// ─── PropTypes ────────────────────────────────────────────────────────────────

const classNameProp = { className: PropTypes.string };
IconSearch.propTypes = classNameProp;
IconPin.propTypes = classNameProp;
IconHeart.propTypes = classNameProp;
IconComment.propTypes = classNameProp;

Skeleton.propTypes = {
  className: PropTypes.string,
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

PostCard.propTypes = {
  post: PropTypes.shape({
    id: PropTypes.number.isRequired,
    title: PropTypes.string,
    body: PropTypes.string,
    type: PropTypes.string,
    is_pinned: PropTypes.bool,
    image_url: PropTypes.string,
    author_name: PropTypes.string,
    author_avatar: PropTypes.string,
    likes_count: PropTypes.number,
    comments_count: PropTypes.number,
    created_at: PropTypes.string,
  }).isRequired,
  currentUserId: PropTypes.number,
};

UserAvatar.propTypes = {
  user: userShape.isRequired,
};

OnlineNowPanel.propTypes = {
  users: PropTypes.arrayOf(userShape).isRequired,
  loading: PropTypes.bool.isRequired,
};

RecentlyActivePanel.propTypes = {
  users: PropTypes.arrayOf(userShape).isRequired,
  loading: PropTypes.bool.isRequired,
};
