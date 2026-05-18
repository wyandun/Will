<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DashboardService
{
    // -------------------------------------------------------------------------
    // Scope resolution
    // -------------------------------------------------------------------------

    /**
     * Returns an array of company IDs the user can see, or null for no filter (superadmin).
     *
     * @return int[]|null
     */
    private function resolveCompanyScope(User $user): ?array
    {
        if ($user->hasRole('superadmin')) {
            return null;
        }

        if ($user->hasRole('admin_sm')) {
            return DB::table('companies')
                ->where('sm_franchise_id', $user->sm_franchise_id)
                ->pluck('id')
                ->all();
        }

        return $user->company_id ? [$user->company_id] : [];
    }

    /**
     * Applies a company scope WHERE clause to a query builder.
     * Returns false when the scope is an empty array (user has no accessible companies).
     *
     * @param  Builder  $query
     * @param  int[]|null  $scope
     * @param  string  $column  Fully-qualified column name
     * @return bool false means "no results possible"
     */
    private function applyScope($query, ?array $scope, string $column = 'company_id'): bool
    {
        if ($scope === null) {
            return true; // superadmin — no filter
        }

        if (empty($scope)) {
            return false; // user has no companies
        }

        $query->whereIn($column, $scope);

        return true;
    }

    // -------------------------------------------------------------------------
    // Event visibility helper
    // -------------------------------------------------------------------------

    /**
     * Applies event visibility rules to a query builder.
     * Visibility: public always, franchise if same sm_franchise_id, private if own or shared.
     */
    private function applyEventVisibility($query, User $user): void
    {
        $query->where(function ($q) use ($user) {
            $q->where('events.visibility', 'public')
                ->orWhere(function ($q2) use ($user) {
                    $q2->where('events.visibility', 'franchise')
                        ->whereExists(function ($sub) use ($user) {
                            $sub->select(DB::raw(1))
                                ->from('users as eu')
                                ->whereColumn('eu.id', 'events.user_id')
                                ->where('eu.sm_franchise_id', $user->sm_franchise_id);
                        });
                })
                ->orWhere(function ($q3) use ($user) {
                    $q3->where('events.visibility', 'private')
                        ->where(function ($q4) use ($user) {
                            $q4->where('events.user_id', $user->id)
                                ->orWhereExists(function ($sub) use ($user) {
                                    $sub->select(DB::raw(1))
                                        ->from('event_shares')
                                        ->whereColumn('event_shares.event_id', 'events.id')
                                        ->where('event_shares.user_id', $user->id);
                                });
                        });
                });
        });
    }

    // -------------------------------------------------------------------------
    // KPIs
    // -------------------------------------------------------------------------

    public function getKpis(User $user): array
    {
        $scope = $this->resolveCompanyScope($user);

        // Events in the next 14 days
        $eventsQuery = DB::table('events')
            ->whereNull('events.deleted_at')
            ->whereBetween('events.start_at', [now(), now()->addDays(14)]);
        $this->applyEventVisibility($eventsQuery, $user);
        $eventsNext14Days = $eventsQuery->count();

        // Contracts pending signature (status = 'sent')
        $pendingSignature = 0;
        $contractsQuery = DB::table('contracts')->where('status', 'sent');
        if ($this->applyScope($contractsQuery, $scope)) {
            $pendingSignature = $contractsQuery->count();
        }

        // Active projects
        $projectsActive = 0;
        $trackingQuery = DB::table('client_trackings')
            ->whereIn('status', ['pending', 'in_progress', 'review']);
        if ($this->applyScope($trackingQuery, $scope)) {
            $projectsActive = $trackingQuery->count();
        }

        // Documents to review
        $toReview = 0;
        $docsQuery = DB::table('repository_documents')
            ->join('repositories', 'repositories.id', '=', 'repository_documents.repository_id')
            ->where('repository_documents.is_current', true)
            ->whereNull('repository_documents.deleted_at');
        if ($this->applyScope($docsQuery, $scope, 'repositories.company_id')) {
            $toReview = $docsQuery->count();
        }

        return [
            'events_next_14_days' => $eventsNext14Days,
            'pending_signature' => $pendingSignature,
            'projects_active' => $projectsActive,
            'to_review' => $toReview,
        ];
    }

    // -------------------------------------------------------------------------
    // Feed
    // -------------------------------------------------------------------------

    public function getFeed(User $user): array
    {
        $query = DB::table('posts')
            ->join('users as authors', 'authors.id', '=', 'posts.author_id')
            ->whereNull('posts.deleted_at')
            ->where(function ($q) {
                $q->whereNull('posts.published_at')
                    ->orWhere('posts.published_at', '<=', now());
            })
            ->select([
                'posts.id',
                'posts.title',
                'posts.body',
                'posts.image_url',
                'posts.created_at',
                'authors.name as author_name',
                'authors.avatar_path as author_avatar',
            ])
            ->orderByDesc('posts.is_pinned')
            ->orderByDesc('posts.created_at')
            ->limit(5);

        // Visibility scoping — mirrors FeedService::getPosts
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

        $posts = $query->get();

        if ($posts->isEmpty()) {
            return [];
        }

        $postIds = $posts->pluck('id')->all();

        // Count likes and comments per post in two queries
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

        return $posts->map(function ($post) use ($likes, $comments) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'author_name' => $post->author_name,
                'author_avatar' => $post->author_avatar
                    ? Storage::disk('public')->url($post->author_avatar)
                    : null,
                'content' => $post->body,
                'image_path' => $post->image_url,
                'likes_count' => (int) ($likes[$post->id] ?? 0),
                'comments_count' => (int) ($comments[$post->id] ?? 0),
                'created_at' => $post->created_at ? Carbon::parse($post->created_at)->toIso8601String() : null,
            ];
        })->all();
    }

    // -------------------------------------------------------------------------
    // Events
    // -------------------------------------------------------------------------

    public function getEvents(User $user): array
    {
        $query = DB::table('events')
            ->whereNull('events.deleted_at')
            ->where('events.start_at', '>=', now())
            ->select([
                'events.id',
                'events.title',
                'events.start_at',
                'events.end_at',
                'events.all_day',
                'events.color',
            ])
            ->orderBy('events.start_at')
            ->limit(5);

        $this->applyEventVisibility($query, $user);

        return $query->get()->map(fn ($e) => [
            'id' => $e->id,
            'title' => $e->title,
            'start' => $e->start_at,
            'end' => $e->end_at,
            'all_day' => (bool) $e->all_day,
            'color' => $e->color,
        ])->all();
    }

    // -------------------------------------------------------------------------
    // Tracking
    // -------------------------------------------------------------------------

    public function getTracking(User $user): array
    {
        $scope = $this->resolveCompanyScope($user);

        $query = DB::table('client_trackings')
            ->join('companies', 'companies.id', '=', 'client_trackings.company_id')
            ->join('catalog_items', 'catalog_items.id', '=', 'client_trackings.catalog_item_id')
            ->whereIn('client_trackings.status', ['pending', 'in_progress', 'review'])
            ->select([
                'client_trackings.id',
                'companies.name as company_name',
                'catalog_items.name_es as item_name',
                'client_trackings.status',
                'client_trackings.progress_percent',
            ])
            ->orderByDesc('client_trackings.id')
            ->limit(5);

        if (! $this->applyScope($query, $scope, 'client_trackings.company_id')) {
            return [];
        }

        return $query->get()->map(fn ($t) => [
            'id' => $t->id,
            'company_name' => $t->company_name,
            'name' => $t->item_name,
            'status' => $t->status,
            'progress' => (int) $t->progress_percent,
        ])->all();
    }

    // -------------------------------------------------------------------------
    // Contracts
    // -------------------------------------------------------------------------

    public function getContracts(User $user): array
    {
        $scope = $this->resolveCompanyScope($user);

        $pending = 0;
        $signed = 0;
        $recent = [];

        $baseQuery = DB::table('contracts');
        if (! $this->applyScope($baseQuery, $scope)) {
            return ['pending' => 0, 'signed' => 0, 'recent' => []];
        }

        // Clone scope for each count
        $pendingQuery = DB::table('contracts')->where('status', 'sent');
        $this->applyScope($pendingQuery, $scope);
        $pending = $pendingQuery->count();

        $signedQuery = DB::table('contracts')->where('status', 'signed');
        $this->applyScope($signedQuery, $scope);
        $signed = $signedQuery->count();

        $recentQuery = DB::table('contracts')
            ->join('companies', 'companies.id', '=', 'contracts.company_id')
            ->select([
                'contracts.id',
                'contracts.title',
                'contracts.status',
                'companies.name as company_name',
            ])
            ->orderByDesc('contracts.created_at')
            ->limit(3);
        $this->applyScope($recentQuery, $scope, 'contracts.company_id');

        $recent = $recentQuery->get()->map(fn ($c) => [
            'id' => $c->id,
            'title' => $c->title,
            'status' => $c->status,
            'company_name' => $c->company_name,
        ])->all();

        return [
            'pending' => $pending,
            'signed' => $signed,
            'recent' => $recent,
        ];
    }

    // -------------------------------------------------------------------------
    // Documents
    // -------------------------------------------------------------------------

    public function getDocuments(User $user): array
    {
        $scope = $this->resolveCompanyScope($user);

        $query = DB::table('repository_documents')
            ->join('repositories', 'repositories.id', '=', 'repository_documents.repository_id')
            ->where('repository_documents.is_current', true)
            ->whereNull('repository_documents.deleted_at')
            ->select([
                'repository_documents.id',
                'repository_documents.title',
                'repository_documents.file_type',
                'repository_documents.created_at',
            ])
            ->orderByDesc('repository_documents.created_at')
            ->limit(20);

        if (! $this->applyScope($query, $scope, 'repositories.company_id')) {
            return [];
        }

        return $query->get()->map(fn ($d) => [
            'id' => $d->id,
            'file_name' => $d->title,
            'source' => 'Repository',
            'file_type' => $d->file_type,
            'created_at' => $d->created_at ? Carbon::parse($d->created_at)->toIso8601String() : null,
        ])->all();
    }

    // -------------------------------------------------------------------------
    // Process Maps
    // -------------------------------------------------------------------------

    public function getProcessMaps(User $user): array
    {
        $scope = $this->resolveCompanyScope($user);

        $query = DB::table('process_maps')
            ->join('companies', 'companies.id', '=', 'process_maps.company_id')
            ->select([
                'process_maps.id',
                'companies.name as company_name',
                'process_maps.name_es as name',
                'process_maps.type',
            ])
            ->limit(5);

        if (! $this->applyScope($query, $scope, 'process_maps.company_id')) {
            return [];
        }

        return $query->get()->map(fn ($m) => [
            'id' => $m->id,
            'company_name' => $m->company_name,
            'name' => $m->name,
            'type' => $m->type,
        ])->all();
    }
}
