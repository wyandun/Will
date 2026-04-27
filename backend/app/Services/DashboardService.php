<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Calculate the 4 dashboard KPIs for the authenticated user.
     *
     * Each KPI is scoped according to the user's role:
     *   - superadmin          → no scope filter
     *   - admin_sm            → companies belonging to their sm_franchise_id
     *   - sb_owner/sb_employee/bb → their company_id only
     *   - sub_franchise_owner/sub_franchise_admin → same as sb_owner (company_id)
     *
     * @return array{
     *     events_next_14_days: int,
     *     pending_signature: int,
     *     projects_active: int,
     *     unreviewed_docs: int
     * }
     */
    public function getKpis(User $user): array
    {
        return [
            'events_next_14_days' => $this->countEventsNext14Days($user),
            'pending_signature'   => $this->countPendingSignature($user),
            'projects_active'     => $this->countProjectsActive($user),
            'unreviewed_docs'     => $this->countUnreviewedDocs($user),
        ];
    }

    // ---------------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------------

    /**
     * Returns the list of company IDs visible to this user, or null if no filter
     * should be applied (superadmin sees everything).
     *
     * @return int[]|null  null = no filter (superadmin)
     */
    private function resolveCompanyScope(User $user): ?array
    {
        if ($user->hasRole('superadmin')) {
            return null;
        }

        if ($user->hasRole('admin_sm')) {
            // All companies belonging to the same SM franchise
            return DB::table('companies')
                ->where('sm_franchise_id', $user->sm_franchise_id)
                ->pluck('id')
                ->all();
        }

        // sb_owner, sb_employee, bb, sub_franchise_owner, sub_franchise_admin
        return $user->company_id ? [$user->company_id] : [];
    }

    /**
     * Events visible to this user that start within the next 14 days.
     *
     * Visibility rules:
     *   public    → always visible
     *   franchise → visible to users sharing the same sm_franchise_id
     *   private   → only visible to the owner or users listed in event_shares
     */
    private function countEventsNext14Days(User $user): int
    {
        $now   = now();
        $until = now()->addDays(14);

        return DB::table('events')
            ->where('start_at', '>=', $now)
            ->where('start_at', '<=', $until)
            ->where(function ($query) use ($user) {
                // Public — always visible
                $query->where('visibility', 'public');

                // Franchise — same sm_franchise_id as the requesting user
                if ($user->sm_franchise_id) {
                    $franchiseUserIds = DB::table('users')
                        ->where('sm_franchise_id', $user->sm_franchise_id)
                        ->pluck('id');

                    $query->orWhere(function ($q) use ($franchiseUserIds) {
                        $q->where('visibility', 'franchise')
                          ->whereIn('user_id', $franchiseUserIds);
                    });
                }

                // Private — owned by user OR shared with user via event_shares
                $query->orWhere(function ($q) use ($user) {
                    $q->where('visibility', 'private')
                      ->where(function ($inner) use ($user) {
                          $inner->where('user_id', $user->id)
                                ->orWhereExists(function ($sub) use ($user) {
                                    $sub->select(DB::raw(1))
                                        ->from('event_shares')
                                        ->whereColumn('event_shares.event_id', 'events.id')
                                        ->where('event_shares.user_id', $user->id);
                                });
                      });
                });
            })
            ->count();
    }

    /**
     * Contracts awaiting signature (status = 'sent') within the user's scope.
     */
    private function countPendingSignature(User $user): int
    {
        $companyIds = $this->resolveCompanyScope($user);

        $query = DB::table('contracts')->where('status', 'sent');

        if ($companyIds !== null) {
            $query->whereIn('company_id', $companyIds);
        }

        return $query->count();
    }

    /**
     * Active client tracking projects (pending, in_progress, review) in scope.
     */
    private function countProjectsActive(User $user): int
    {
        $companyIds = $this->resolveCompanyScope($user);

        $query = DB::table('client_trackings')
            ->whereIn('status', ['pending', 'in_progress', 'review']);

        if ($companyIds !== null) {
            $query->whereIn('company_id', $companyIds);
        }

        return $query->count();
    }

    /**
     * Repository documents uploaded by the client that have not been reviewed yet.
     *
     * Conditions:
     *   - is_current = true
     *   - reviewed_at IS NULL
     *   - uploaded_by_type = 'client'
     *   - The parent repository belongs to a company in the user's scope
     */
    private function countUnreviewedDocs(User $user): int
    {
        $companyIds = $this->resolveCompanyScope($user);

        $query = DB::table('repository_documents')
            ->join('repositories', 'repositories.id', '=', 'repository_documents.repository_id')
            ->where('repository_documents.is_current', true)
            ->whereNull('repository_documents.reviewed_at')
            ->where('repository_documents.uploaded_by_type', 'client')
            ->whereNull('repository_documents.deleted_at');

        if ($companyIds !== null) {
            $query->whereIn('repositories.company_id', $companyIds);
        }

        return $query->count();
    }
}
