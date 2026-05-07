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
        if (! $user->hasRole('superadmin')) {
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

        $items = $rows->map(function ($post) use ($likes, $comments) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'body' => $post->body,
                'type' => $post->type,
                'is_pinned' => (bool) $post->is_pinned,
                'image_url' => $post->image_url,
                'file_url' => $post->file_url,
                'file_name' => $post->file_name,
                'author_name' => $post->author_name,
                'author_avatar' => $post->author_avatar
                    ? Storage::disk('public')->url($post->author_avatar)
                    : null,
                'likes_count' => (int) ($likes[$post->id] ?? 0),
                'comments_count' => (int) ($comments[$post->id] ?? 0),
                'created_at' => $post->created_at,
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
        if ($user->hasRole('superadmin')) {
            // no filter
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
                ->where('model_has_roles.model_type', 'App\\Models\\User')
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPost(User $user, array $data, ?UploadedFile $image = null, ?UploadedFile $attachment = null): array
    {
        $imageUrl = null;
        $fileUrl = null;
        $fileName = null;

        if ($image !== null) {
            $path = $image->store('posts', 'public');
            $imageUrl = Storage::disk('public')->url((string) $path);
        }

        if ($attachment !== null) {
            $path = $attachment->store('attachments', 'public');
            $fileUrl = Storage::disk('public')->url((string) $path);
            $fileName = $attachment->getClientOriginalName();
        }

        $postId = DB::table('posts')->insertGetId([
            'author_id' => $user->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'type' => $data['type'],
            'visibility' => $data['visibility'],
            'is_pinned' => isset($data['is_pinned']) ? (bool) $data['is_pinned'] : false,
            'published_at' => $data['published_at'] ?? null,
            'image_url' => $imageUrl,
            'file_url' => $fileUrl,
            'file_name' => $fileName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $post = DB::table('posts')->where('id', $postId)->first();

        return (array) $post;
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

        if ((int) $post->author_id !== $user->id && ! $user->hasRole('superadmin')) {
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

        if ((int) $post->author_id !== $user->id && ! $user->hasRole('superadmin')) {
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
