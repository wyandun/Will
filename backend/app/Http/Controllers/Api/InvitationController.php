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

        if (! $authUser->hasRole(Role::SUPERADMIN)) {
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

        return response()->json([
            'success' => true,
            'data' => [
                'user' => new InvitationResource($result['user']),
                'activation_url' => $result['activation_url'],
            ],
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

        return response()->json([
            'success' => true,
            'data' => [
                'user' => new InvitationResource($result['user']),
                'activation_url' => $result['activation_url'],
            ],
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
                'email' => $this->maskEmail($user->email),
            ],
        ]);
    }

    /**
     * Mask an email address to prevent enumeration.
     *
     * e.g., john@example.com -> j***@example.com
     */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email);
        $masked = substr($local, 0, 1).str_repeat('*', max(strlen($local) - 1, 3));

        return "{$masked}@{$domain}";
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
