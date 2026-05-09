<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invitation\AcceptInvitationRequest;
use App\Http\Requests\Invitation\SendInvitationRequest;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;

class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $service) {}

    // ─── Protected endpoints (auth:sanctum + inviteUsers policy) ─────────────

    /**
     * List all pending (not yet accepted) invitations.
     */
    public function index(): JsonResponse
    {
        $this->authorize('inviteUsers', User::class);

        $pending = User::whereNotNull('invitation_token')
            ->whereNull('invitation_accepted_at')
            ->with(['roles', 'invitedBy:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pending,
        ]);
    }

    /**
     * Send a new invitation (or regenerate if a pending one already exists).
     */
    public function store(SendInvitationRequest $request): JsonResponse
    {
        $this->authorize('inviteUsers', User::class);

        $result = $this->service->send($request->validated(), auth()->user());

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'invitation.sent_success',
        ], 201);
    }

    /**
     * Resend an existing pending invitation (regenerates the token).
     */
    public function resend(User $user): JsonResponse
    {
        $this->authorize('inviteUsers', User::class);

        $result = $this->service->resendById($user, auth()->user());

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
        $this->authorize('inviteUsers', User::class);

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
        $user = $this->service->verify($token);
        $result = $this->service->accept($user, $request->validated()['password']);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'invitation.accepted_success',
        ]);
    }
}
