<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendContractRequest;
use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\User;
use App\Services\ContractService;
use App\Services\DocuSealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContractController extends Controller
{
    public function __construct(
        private ContractService $service,
        private DocuSealService $docuseal,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contract::class);

        /** @var User $user */
        $user = $request->user();

        $filters = $request->only(['status', 'search', 'per_page']);

        return ContractResource::collection($this->service->list($filters, $user));
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $data = $request->validated();

        $client = User::findOrFail((int) $data['client_user_id']);

        // Policy::create needs the company id to apply admin_sm franchise-scope.
        // company_id is derived server-side from this resolved client, never from
        // the request payload (StoreContractRequest has no company_id) — no IDOR.
        $this->authorize('create', [Contract::class, $client->company_id]);

        $contract = $this->service->create($data, $client);

        return response()->json([
            'success' => true,
            'data' => new ContractResource($contract),
            'message' => 'contracts.created_success',
        ], 201);
    }

    public function show(Contract $contract): JsonResponse
    {
        $this->authorize('view', $contract);

        $contract->load(['company.franchise', 'client']);

        return response()->json([
            'success' => true,
            'data' => new ContractResource($contract),
        ]);
    }

    public function update(UpdateContractRequest $request, Contract $contract): JsonResponse
    {
        $this->authorize('update', $contract);

        $contract = $this->service->update($contract, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new ContractResource($contract),
            'message' => 'contracts.updated_success',
        ]);
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $this->authorize('delete', $contract);

        $this->service->delete($contract);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'contracts.deleted_success',
        ]);
    }

    public function send(SendContractRequest $request, Contract $contract): JsonResponse
    {
        $this->authorize('send', $contract);

        $contract = $this->service->send($contract, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new ContractResource($contract),
            'message' => 'contracts.sent_success',
        ]);
    }

    public function sync(Contract $contract): JsonResponse
    {
        $this->authorize('sync', $contract);

        $contract = $this->service->sync($contract);

        return response()->json([
            'success' => true,
            'data' => new ContractResource($contract),
            'message' => 'contracts.synced_success',
        ]);
    }

    public function templates(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Contract::class);

        /** @var User $user */
        $user = $request->user();

        // Templates are per-franchise. Global admins see every template; an
        // admin_sm is scoped to their own franchise's DocuSeal folder so they
        // never see other franchises' templates.
        $folder = null;

        if (! $user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY])
            && $user->hasRole(Role::ADMIN_SM)) {
            $folder = $user->franchise?->name;
        }

        return response()->json([
            'success' => true,
            'data' => $this->docuseal->getTemplates($folder),
        ]);
    }
}
