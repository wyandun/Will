<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Helpers\StringHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitation\AcceptInvitationRequest;
use App\Http\Requests\Invitation\SendInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $service) {}

    // ─── Protected endpoints ──────────────────────────────────────────────────
    // index()/store()  → class-level policy: 'inviteUsers' on User::class
    // resend()/destroy() → instance-level policy: 'manageInvitation' on $user

    /**
     * List all pending (not yet accepted) invitations.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('inviteUsers', User::class);

        /** @var User $authUser */
        $authUser = auth()->user();

        $query = User::pendingInvitation()
            ->with(['roles', 'invitedBy:id,name'])
            ->orderByDesc('created_at');

        // System-level roles (SUPERADMIN, SYSTEM_ADMIN, SYSTEM_ADMIN_READONLY) intentionally
        // bypass franchise scoping to see all invitations across all franchises. This is
        // required for platform-wide monitoring and audit. SYSTEM_ADMIN_READONLY can list
        // but cannot send/resend/revoke (blocked by inviteUsers policy). Franchise-scoped
        // roles (ADMIN_SM) are filtered to their own franchise below.
        // SB_OWNER and SUB_FRANCHISE_OWNER are scoped to their company/sub-franchise.
        if ($authUser->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY])) {
            // No filter — system roles see all invitations.
        } elseif ($authUser->hasRole(Role::SB_OWNER)) {
            abort_if(is_null($authUser->company_id), 403, 'invitation.no_company_context');
            $query->where('company_id', $authUser->company_id);
        } elseif ($authUser->hasRole(Role::SUB_FRANCHISE_OWNER)) {
            abort_if(is_null($authUser->sub_franchise_id), 403, 'invitation.no_sub_franchise_context');
            $query->where('sub_franchise_id', $authUser->sub_franchise_id);
        } else {
            // ADMIN_SM and any other role: scoped to their franchise.
            // Guard against null franchise: WHERE sm_franchise_id = NULL would
            // silently match other null-franchise rows (cross-tenant data leak).
            abort_if(is_null($authUser->sm_franchise_id), 403, 'invitation.no_franchise_context');
            $query->where('sm_franchise_id', $authUser->sm_franchise_id);
        }

        $pending = $query->paginate(config('pagination.invitation_per_page', 25));

        return InvitationResource::collection($pending)->additional(['success' => true]);
    }

    /**
     * Send a new invitation (or regenerate if a pending one already exists).
     */
    public function store(SendInvitationRequest $request): JsonResponse
    {
        $this->authorize('inviteUsers', User::class);

        $result = $this->service->send($request->validated(), auth()->user());

        $data = ['user' => new InvitationResource($result['user'])];

        if (config('invitation.expose_activation_url')) {
            $data['activation_url'] = $result['activation_url'];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'invitation.sent_success',
        ], 201);
    }

    /**
     * Resend an existing pending invitation (regenerates the token).
     */
    public function resend(User $user): JsonResponse
    {
        $this->authorize('manageInvitation', $user);

        $result = $this->service->resendById($user, auth()->user());

        $data = ['user' => new InvitationResource($result['user'])];

        if (config('invitation.expose_activation_url')) {
            $data['activation_url'] = $result['activation_url'];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'invitation.resent_success',
        ]);
    }

    /**
     * Revoke a pending invitation (soft-deletes the placeholder user record).
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('manageInvitation', $user);

        $this->service->revoke($user);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'invitation.revoked_success',
        ]);
    }

    // ─── Public endpoints (no auth required) ─────────────────────────────────

    /**
     * Verify that an invitation token is valid and not expired.
     * Returns safe user info for the activation form (name, email only).
     */
    public function verify(string $token): JsonResponse
    {
        $user = $this->service->verify($token);

        return response()->json([
            'success' => true,
            'data' => [
                'email' => StringHelper::maskEmail($user->email),
            ],
        ]);
    }

    /**
     * Accept an invitation: user sets their password and receives a Sanctum
     * token for immediate auto-login on the frontend.
     */
    public function accept(AcceptInvitationRequest $request, string $token): JsonResponse
    {
        // verify() + write are performed atomically inside accept() via a
        // DB transaction with lockForUpdate() — no separate verify() call here.
        $result = $this->service->accept($token, $request->validated()['password']);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'invitation.accepted_success',
        ]);
    }
}
