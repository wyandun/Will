<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitation\AcceptInvitationRequest;
use App\Http\Requests\Invitation\SendInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $service) {}

    // ─── Protected endpoints (auth:sanctum + inviteUsers policy) ─────────────

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

        if (! $authUser->hasRole(Role::SUPERADMIN)) {
            $query->where('sm_franchise_id', $authUser->sm_franchise_id);
        }

        $pending = $query->paginate(config('pagination.invitation_per_page', 25));

        return InvitationResource::collection($pending)->additional([
            'success' => true,
        ]);
    }

    /**
     * Send a new invitation (or regenerate if a pending one already exists).
     */
    public function store(SendInvitationRequest $request): JsonResponse
    {
        $this->authorize('inviteUsers', User::class);

        $result = $this->service->send($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'invitation.sent_success',
        ], 201);
    }

    /**
     * Resend an existing pending invitation (regenerates the token).
     */
    public function resend(Request $request, User $user): JsonResponse
    {
        $this->authorize('manageInvitation', $user);

        $result = $this->service->resendById($user, $request->user());

        return response()->json([
            'success' => true,
            'data' => $result,
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
     * Returns safe user info for the activation form (name, email, role).
     */
    public function verify(string $token): JsonResponse
    {
        $user = $this->service->verify($token);

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first() ?? '',
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
