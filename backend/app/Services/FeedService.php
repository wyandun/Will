<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FeedService
{
    // -------------------------------------------------------------------------
    // Interactions
    // -------------------------------------------------------------------------

    /**
     * React to a post (toggle).
     *
     * If the user has already reacted with the same emoji, the reaction is removed.
     * If the user has reacted with a different emoji, it is replaced.
     * If the user has not reacted, a new like interaction is inserted.
     *
     * The entire toggle runs inside a DB::transaction with a lockForUpdate
     * on the existing row to prevent race conditions from concurrent requests.
     *
     * The likes count is derived via delta arithmetic instead of a fresh COUNT(*)
     * to avoid a second read after the write.
     *
     * @return array{likes_count: int, user_reaction: string|null}
     */
    public function react(int $postId, User $user, string $emoji): array
    {
        $this->findPostOrFail($postId);

        $userReaction = null;
        $likesCount = 0;

        DB::transaction(function () use ($postId, $user, $emoji, &$userReaction, &$likesCount): void {
            // Read the current count and lock the existing reaction row atomically.
            $previousCount = (int) DB::table('post_interactions')
                ->where('post_id', $postId)
                ->where('type', 'like')
                ->count();

            $existing = DB::table('post_interactions')
                ->where('post_id', $postId)
                ->where('user_id', $user->id)
                ->where('type', 'like')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->content === $emoji) {
                    // Same emoji — toggle off
                    DB::table('post_interactions')->where('id', $existing->id)->delete();
                    $userReaction = null;
                    $likesCount = max(0, $previousCount - 1);
                } else {
                    // Different emoji — replace (count unchanged)
                    DB::table('post_interactions')
                        ->where('id', $existing->id)
                        ->update(['content' => $emoji, 'updated_at' => now()]);
                    $userReaction = $emoji;
                    $likesCount = $previousCount;
                }
            } else {
                // No prior reaction — insert
                DB::table('post_interactions')->insert([
                    'post_id' => $postId,
                    'user_id' => $user->id,
                    'type' => 'like',
                    'content' => $emoji,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $userReaction = $emoji;
                $likesCount = $previousCount + 1;
            }
        });

        return [
            'likes_count' => $likesCount,
            'user_reaction' => $userReaction,
        ];
    }

    /**
     * Return paginated comments for a post.
     *
     * Each comment includes author info, the `is_own` flag, and the author's role.
     *
     * @return array{items: array<int, array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function getComments(int $postId, User $user, int $page = 1, int $perPage = 10): array
    {
        $this->findPostOrFail($postId);

        $paginator = DB::table('post_interactions as pi')
            ->join('users as u', 'u.id', '=', 'pi.user_id')
            ->where('pi.post_id', $postId)
            ->where('pi.type', 'comment')
            ->select([
                'pi.id',
                'pi.content',
                'pi.created_at',
                'pi.user_id',
                'u.name as author_name',
                'u.avatar_path as author_avatar_path',
            ])
            ->orderBy('pi.created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $rows = collect($paginator->items());

        // Resolve roles for all comment authors
        $authorIds = $rows->pluck('user_id')->unique()->values()->all();
        $roleMap = [];

        if (! empty($authorIds)) {
            $roleMap = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereIn('model_has_roles.model_id', $authorIds)
                ->where('model_has_roles.model_type', User::class)
                ->select('model_has_roles.model_id', 'roles.name')
                ->get()
                ->groupBy('model_id')
                ->map(fn ($rows2) => $rows2->first()->name)
                ->all();
        }

        $items = $rows->map(function ($row) use ($user, $roleMap) {
            return [
                'id' => $row->id,
                'content' => $row->content,
                'author_name' => $row->author_name,
                'author_avatar_url' => $row->author_avatar_path
                    ? Storage::disk('public')->url($row->author_avatar_path)
                    : null,
                'author_role' => $roleMap[$row->user_id] ?? null,
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : null,
                'is_own' => $row->user_id === $user->id,
            ];
        })->all();

        return [
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Add a comment to a post.
     *
     * @return array<string, mixed>
     */
    public function addComment(int $postId, User $user, string $content): array
    {
        $this->findPostOrFail($postId);

        $id = DB::table('post_interactions')->insertGetId([
            'post_id' => $postId,
            'user_id' => $user->id,
            'type' => 'comment',
            'content' => $content,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Resolve role for response
        $role = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', User::class)
            ->value('roles.name');

        return [
            'id' => $id,
            'content' => $content,
            'author_name' => $user->name,
            'author_avatar_url' => $user->avatar_path
                ? Storage::disk('public')->url($user->avatar_path)
                : null,
            'author_role' => $role,
            'created_at' => Carbon::now()->toIso8601String(),
            'is_own' => true,
        ];
    }

    /**
     * Delete a comment (physical delete).
     *
     * Only the comment author or a superadmin may delete.
     * Physical delete is used because comments have no audit requirement
     * and the table has no deleted_at column.
     */
    public function deleteComment(int $commentId, User $user): void
    {
        $comment = DB::table('post_interactions')
            ->where('id', $commentId)
            ->where('type', 'comment')
            ->first();

        if ($comment === null) {
            throw new NotFoundHttpException('Comment not found.');
        }

        if ((int) $comment->user_id !== $user->id && ! $user->hasAnyRole(['superadmin', 'system_admin'])) {
            Log::warning('Unauthorized comment delete attempt', [
                'user_id' => $user->id,
                'comment_id' => $commentId,
            ]);
            throw new AccessDeniedHttpException('You are not allowed to delete this comment.');
        }

        DB::table('post_interactions')->where('id', $commentId)->delete();
    }

    // -------------------------------------------------------------------------
    // Posts
    // -------------------------------------------------------------------------

    /**
     * Return a paginated list of posts visible to the given user.
     *
     * Visibility rules:
     *   superadmin → all posts
     *   admin_sm   → global + own franchise
     *   all others → global + franchise of their company
     *
     * If $search is provided, filters by title, body, or author name (ILIKE).
     *
     * @return array{items: array<int, array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function getPosts(User $user, ?string $search = null, int $page = 1, int $perPage = 10): array
    {
        $query = DB::table('posts')
            ->join('users as authors', 'authors.id', '=', 'posts.author_id')
            ->whereNull('posts.deleted_at')
            ->where(function ($q) {
                $q->whereNull('posts.published_at')
                    ->orWhere('posts.published_at', '<=', now());
            });

        // Visibility scoping
        if (! $user->hasAnyRole(['superadmin', 'system_admin', 'system_admin_readonly'])) {
            $franchiseId = $user->sm_franchise_id;
            $query->where(function ($q) use ($franchiseId) {
                $q->where('posts.visibility', 'global')
                    ->orWhere(function ($q2) use ($franchiseId) {
                        $q2->where('posts.visibility', 'franchise')
                            ->where('posts.franchise_id', $franchiseId);
                    });
            });
        }

        // Full-text search across title, body and author name
        if ($search !== null && $search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('posts.title ILIKE ?', [$term])
                    ->orWhereRaw('posts.body ILIKE ?', [$term])
                    ->orWhereRaw('authors.name ILIKE ?', [$term]);
            });
        }

        $query->select([
            'posts.id',
            'posts.title',
            'posts.body',
            'posts.type',
            'posts.is_pinned',
            'posts.image_url',
            'posts.file_url',
            'posts.file_name',
            'posts.created_at',
            'authors.id as author_id',
            'authors.name as author_name',
            'authors.avatar_path as author_avatar',
        ])
            ->orderByDesc('posts.is_pinned')
            ->orderByDesc('posts.created_at');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $rows = collect($paginator->items());

        if ($rows->isEmpty()) {
            return [
                'items' => [],
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ];
        }

        $postIds = $rows->pluck('id')->all();

        $likes = DB::table('post_interactions')
            ->whereIn('post_id', $postIds)
            ->where('type', 'like')
            ->select('post_id', DB::raw('COUNT(*) as total'))
            ->groupBy('post_id')
            ->pluck('total', 'post_id');

        $comments = DB::table('post_interactions')
            ->whereIn('post_id', $postIds)
            ->where('type', 'comment')
            ->select('post_id', DB::raw('COUNT(*) as total'))
            ->groupBy('post_id')
            ->pluck('total', 'post_id');

        // Current user's reaction per post (emoji stored in content)
        $userReactions = DB::table('post_interactions')
            ->whereIn('post_id', $postIds)
            ->where('user_id', $user->id)
            ->where('type', 'like')
            ->pluck('content', 'post_id');

        // Resolve the primary role for each unique author
        $authorIds = $rows->pluck('author_id')->unique()->values()->all();
        $authorRoleMap = [];

        if (! empty($authorIds)) {
            $authorRoleMap = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereIn('model_has_roles.model_id', $authorIds)
                ->where('model_has_roles.model_type', User::class)
                ->select('model_has_roles.model_id', 'roles.name')
                ->get()
                ->groupBy('model_id')
                ->map(fn ($roleRows) => $roleRows->first()->name)
                ->all();
        }

        $items = $rows->map(function ($post) use ($likes, $comments, $userReactions, $authorRoleMap) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'body' => $post->body,
                'type' => $post->type,
                'is_pinned' => (bool) $post->is_pinned,
                'image_url' => $post->image_url,
                'file_url' => $post->file_url,
                'file_name' => $post->file_name,
                'author_id' => (int) $post->author_id,
                'author_name' => $post->author_name,
                'author_role' => $authorRoleMap[$post->author_id] ?? null,
                'author_avatar' => $post->author_avatar
                    ? Storage::disk('public')->url($post->author_avatar)
                    : null,
                'likes_count' => (int) ($likes[$post->id] ?? 0),
                'comments_count' => (int) ($comments[$post->id] ?? 0),
                'user_reaction' => $userReactions[$post->id] ?? null,
                'created_at' => $post->created_at ? Carbon::parse($post->created_at)->toIso8601String() : null,
            ];
        })->all();

        return [
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Presence
    // -------------------------------------------------------------------------

    /**
     * Return "Online Now" and "Recently Active" user lists.
     *
     * Online Now  : last_seen_at within the last 5 minutes.
     * Recently Active : last_seen_at older than 5 min, order by recency, limit 10.
     *
     * User visibility scoping:
     *   superadmin → all users
     *   admin_sm   → users of their sm_franchise
     *   all others → users sharing the same company_id
     *
     * Each user entry includes a flag so the frontend can show "You".
     *
     * @return array{online: array<int, array<string, mixed>>, recently_active: array<int, array<string, mixed>>}
     */
    public function getPresence(User $user): array
    {
        $onlineThreshold = now()->subMinutes(5);

        $baseQuery = DB::table('users')
            ->whereNull('users.deleted_at')
            ->select([
                'users.id',
                'users.name',
                'users.avatar_path',
                'users.last_seen_at',
            ]);

        // Scope which users are visible to this user
        if ($user->hasAnyRole(['superadmin', 'system_admin', 'system_admin_readonly'])) {
            // no filter — system-level roles see all users
        } elseif ($user->hasRole('admin_sm')) {
            $baseQuery->where('users.sm_franchise_id', $user->sm_franchise_id);
        } else {
            $baseQuery->where('users.company_id', $user->company_id);
        }

        $allVisible = $baseQuery->get();

        // Resolve the primary Spatie role name for each user in one query
        $userIds = $allVisible->pluck('id')->all();
        $roleMap = [];

        if (! empty($userIds)) {
            $roleMap = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereIn('model_has_roles.model_id', $userIds)
                ->where('model_has_roles.model_type', User::class)
                ->select('model_has_roles.model_id', 'roles.name')
                ->get()
                ->groupBy('model_id')
                ->map(fn ($rows) => $rows->first()->name)
                ->all();
        }

        $mapUser = function ($u) use ($user, $roleMap) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'avatar_url' => $u->avatar_path
                    ? Storage::disk('public')->url($u->avatar_path)
                    : null,
                'role' => $roleMap[$u->id] ?? null,
                'last_seen_at' => $u->last_seen_at,
                'is_current_user' => $u->id === $user->id,
            ];
        };

        $online = $allVisible
            ->filter(fn ($u) => $u->last_seen_at !== null
                && Carbon::parse($u->last_seen_at)->gte($onlineThreshold))
            ->sortByDesc('last_seen_at')
            ->values()
            ->map($mapUser)
            ->all();

        $onlineIds = array_column($online, 'id');

        $recentlyActive = $allVisible
            ->filter(fn ($u) => $u->last_seen_at !== null
                && Carbon::parse($u->last_seen_at)->lt($onlineThreshold)
                && ! in_array($u->id, $onlineIds, true))
            ->sortByDesc('last_seen_at')
            ->take(10)
            ->values()
            ->map($mapUser)
            ->all();

        return [
            'online' => $online,
            'recently_active' => $recentlyActive,
        ];
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a new post and optionally persist an image and/or attachment.
     *
     * Files are uploaded before the DB insert. If the insert fails, any
     * uploaded files are cleaned up so no orphans are left on disk.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPost(User $user, array $data, ?UploadedFile $image = null, ?UploadedFile $attachment = null): array
    {
        $imagePath = null;
        $attachmentPath = null;

        try {
            if ($image !== null) {
                $imagePath = (string) $image->store('posts', 'public');
            }

            if ($attachment !== null) {
                $attachmentPath = (string) $attachment->store('attachments', 'public');
            }

            $postId = DB::transaction(function () use ($user, $data, $attachment, $imagePath, $attachmentPath) {
                return DB::table('posts')->insertGetId([
                    'author_id' => $user->id,
                    'franchise_id' => $user->sm_franchise_id ?? null,
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'type' => $data['type'],
                    'visibility' => $data['visibility'],
                    'is_pinned' => isset($data['is_pinned']) ? (bool) $data['is_pinned'] : false,
                    'published_at' => $data['published_at'] ?? null,
                    'image_url' => $imagePath !== null ? Storage::disk('public')->url($imagePath) : null,
                    'file_url' => $attachmentPath !== null ? Storage::disk('public')->url($attachmentPath) : null,
                    'file_name' => $attachment !== null ? $attachment->getClientOriginalName() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            $post = DB::table('posts')->where('id', $postId)->first();

            return (array) $post;
        } catch (\Throwable $e) {
            // Clean up uploaded files to avoid orphans if the DB insert failed
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }
            if ($attachmentPath !== null) {
                Storage::disk('public')->delete($attachmentPath);
            }
            throw $e;
        }
    }

    /**
     * Update an existing post.
     *
     * Only the post author or a superadmin may edit.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updatePost(int $postId, User $user, array $data, ?UploadedFile $image = null, ?UploadedFile $attachment = null): array
    {
        $post = DB::table('posts')->whereNull('deleted_at')->where('id', $postId)->first();

        if ($post === null) {
            throw new NotFoundHttpException('Post not found.');
        }

        if ((int) $post->author_id !== $user->id && ! $user->hasAnyRole(['superadmin', 'system_admin'])) {
            Log::warning('Unauthorized post update attempt', [
                'user_id' => $user->id,
                'post_id' => $postId,
            ]);
            throw new AccessDeniedHttpException('You are not allowed to edit this post.');
        }

        $updates = [];

        foreach (['title', 'body', 'type', 'visibility', 'published_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if (array_key_exists('is_pinned', $data)) {
            $updates['is_pinned'] = (bool) $data['is_pinned'];
        }

        if ($image !== null) {
            $path = $image->store('posts', 'public');
            $updates['image_url'] = Storage::disk('public')->url((string) $path);
        }

        if ($attachment !== null) {
            $path = $attachment->store('attachments', 'public');
            $updates['file_url'] = Storage::disk('public')->url((string) $path);
            $updates['file_name'] = $attachment->getClientOriginalName();
        }

        if (! empty($updates)) {
            $updates['updated_at'] = now();
            DB::table('posts')->where('id', $postId)->update($updates);
        }

        $fresh = DB::table('posts')->where('id', $postId)->first();

        return (array) $fresh;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Find a non-deleted post by ID or throw a 404.
     *
     * Centralizes the repeated "find post or fail" pattern used across
     * react(), getComments(), and addComment().
     *
     * @throws NotFoundHttpException
     */
    private function findPostOrFail(int $postId): object
    {
        $post = DB::table('posts')->whereNull('deleted_at')->where('id', $postId)->first();

        if ($post === null) {
            throw new NotFoundHttpException('Post not found.');
        }

        return $post;
    }

    /**
     * Soft-delete a post.
     *
     * Only the post author or a superadmin may delete.
     */
    public function deletePost(int $postId, User $user): void
    {
        $post = DB::table('posts')->whereNull('deleted_at')->where('id', $postId)->first();

        if ($post === null) {
            throw new NotFoundHttpException('Post not found.');
        }

        if ((int) $post->author_id !== $user->id && ! $user->hasAnyRole(['superadmin', 'system_admin'])) {
            Log::warning('Unauthorized post delete attempt', [
                'user_id' => $user->id,
                'post_id' => $postId,
            ]);
            throw new AccessDeniedHttpException('You are not allowed to delete this post.');
        }

        DB::table('posts')->where('id', $postId)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
