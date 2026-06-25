<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ContractService
{
    public function __construct(private DocuSealService $docuseal) {}

    /**
     * List contracts visible to the given user, with optional filters.
     *
     * Role scoping:
     *   - superadmin / system_admin / readonly → all contracts
     *   - admin_sm → contracts whose company belongs to their franchise
     *   - bb_employee → contracts of their own company only
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, User $user): LengthAwarePaginator
    {
        $query = Contract::query()->with(['company.franchise', 'client']);

        $this->applyScope($query, $user);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('company', function (Builder $c) use ($search): void {
                        $c->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 24;

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * @param  Builder<Contract>  $query
     */
    private function applyScope(Builder $query, User $user): void
    {
        if ($user->hasAnyRole([Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::SYSTEM_ADMIN_READONLY])) {
            return;
        }

        if ($user->hasRole(Role::ADMIN_SM)) {
            $franchiseId = $user->sm_franchise_id;
            $query->whereHas('company', function (Builder $q) use ($franchiseId): void {
                $q->where('sm_franchise_id', $franchiseId);
            });

            return;
        }

        if ($user->hasRole(Role::BB_EMPLOYEE)) {
            $query->where('company_id', $user->company_id);

            return;
        }

        // Any other role sees nothing.
        $query->whereRaw('1 = 0');
    }

    /**
     * Create a draft contract. company_id is derived from the client user.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contract
    {
        $client = User::findOrFail((int) $data['client_user_id']);

        if ($client->company_id === null) {
            throw ValidationException::withMessages([
                'client_user_id' => 'contracts.client_without_company',
            ]);
        }

        $contract = DB::transaction(function () use ($data, $client): Contract {
            return Contract::create([
                'company_id' => $client->company_id,
                'client_user_id' => $client->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'draft_url' => $data['draft_url'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'status' => Contract::STATUS_DRAFT,
            ]);
        });

        Log::info('Contract created', [
            'contract_id' => $contract->id,
            'company_id' => $contract->company_id,
            'client_user_id' => $contract->client_user_id,
        ]);

        return $contract->load(['company.franchise', 'client']);
    }

    /**
     * Update an editable (draft) contract.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Contract $contract, array $data): Contract
    {
        if ($contract->status !== Contract::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'contracts.only_draft_editable',
            ]);
        }

        $payload = array_intersect_key($data, array_flip([
            'title', 'description', 'draft_url', 'expires_at',
        ]));

        DB::transaction(function () use ($contract, $payload): void {
            $contract->update($payload);
        });

        Log::info('Contract updated', ['contract_id' => $contract->id]);

        return $contract->load(['company.franchise', 'client']);
    }

    /**
     * Soft-delete a contract, cleaning up its DocuSeal submission when present.
     */
    public function delete(Contract $contract): void
    {
        if (is_string($contract->docuseal_submission_id) && $contract->docuseal_submission_id !== '') {
            try {
                $this->docuseal->deleteSubmission($contract->docuseal_submission_id);
            } catch (\Throwable $e) {
                Log::warning('DocuSeal submission delete failed', [
                    'contract_id' => $contract->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $contract->delete();

        Log::info('Contract deleted', ['contract_id' => $contract->id]);
    }

    /**
     * Send a draft contract for signing via DocuSeal.
     *
     * @param  array{template_id: int|string, signers: array<int, array<string, mixed>>}  $data
     */
    public function send(Contract $contract, array $data): Contract
    {
        if ($contract->status !== Contract::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'contracts.only_draft_sendable',
            ]);
        }

        $templateId = (int) $data['template_id'];

        /** @var array<int, array{name: string, email: string, role?: string|null}> $signerInput */
        $signerInput = array_map(fn (array $s): array => [
            'name' => (string) $s['name'],
            'email' => (string) $s['email'],
            'role' => isset($s['role']) ? (string) $s['role'] : null,
        ], $data['signers']);

        $submission = $this->docuseal->createSubmission($templateId, $signerInput, [
            'contract_id' => $contract->id,
        ]);

        $submissionId = $this->extractSubmissionId($submission);

        $storedSigners = array_map(fn (array $s): array => [
            'name' => $s['name'],
            'email' => $s['email'],
            'role' => $s['role'],
            'status' => 'pending',
        ], $signerInput);

        DB::transaction(function () use ($contract, $templateId, $submissionId, $storedSigners): void {
            $contract->update([
                'docuseal_template_id' => (string) $templateId,
                'docuseal_submission_id' => $submissionId,
                'status' => Contract::STATUS_SENT,
                'signers' => $storedSigners,
                'sent_at' => now(),
            ]);
        });

        Log::info('Contract sent for signing', [
            'contract_id' => $contract->id,
            'docuseal_submission_id' => $submissionId,
        ]);

        return $contract->load(['company.franchise', 'client']);
    }

    /**
     * Reconcile a sent contract's status with DocuSeal.
     */
    public function sync(Contract $contract): Contract
    {
        if (! is_string($contract->docuseal_submission_id) || $contract->docuseal_submission_id === '') {
            throw ValidationException::withMessages([
                'docuseal_submission_id' => 'contracts.no_submission_to_sync',
            ]);
        }

        $submission = $this->docuseal->getSubmission($contract->docuseal_submission_id);
        $mappedStatus = $this->mapSubmissionStatus($submission);

        if ($mappedStatus === null) {
            return $contract->load(['company.franchise', 'client']);
        }

        $updates = ['status' => $mappedStatus];

        if ($mappedStatus === Contract::STATUS_SIGNED) {
            $docs = $this->docuseal->getSubmissionDocuments($contract->docuseal_submission_id);
            $updates['signed_at'] = now();
            $updates['signed_document_url'] = $this->extractDocumentUrl($docs, 'document');
            $updates['certificate_url'] = $this->extractDocumentUrl($docs, 'certificate');
        }

        DB::transaction(function () use ($contract, $updates): void {
            $contract->update($updates);
        });

        Log::info('Contract synced', [
            'contract_id' => $contract->id,
            'status' => $mappedStatus,
        ]);

        return $contract->load(['company.franchise', 'client']);
    }

    /**
     * @param  array<string, mixed>  $submission
     */
    private function extractSubmissionId(array $submission): ?string
    {
        // DocuSeal returns the submission id directly, or a list of submitters
        // each carrying a shared submission_id.
        $id = $submission['id'] ?? null;

        if ($id === null && isset($submission['submitters'])) {
            $submitters = $submission['submitters'];
            if (is_array($submitters) && isset($submitters[0]) && is_array($submitters[0])) {
                $id = $submitters[0]['submission_id'] ?? null;
            }
        }

        if ($id === null || is_array($id)) {
            return null;
        }

        return (string) $id;
    }

    /**
     * Map a DocuSeal submission payload to a local contract status.
     *
     * @param  array<string, mixed>  $submission
     */
    private function mapSubmissionStatus(array $submission): ?string
    {
        $status = $submission['status'] ?? null;

        if (! is_string($status)) {
            return null;
        }

        return match (strtolower($status)) {
            'completed', 'signed' => Contract::STATUS_SIGNED,
            'expired' => Contract::STATUS_EXPIRED,
            'declined', 'cancelled', 'canceled' => Contract::STATUS_CANCELLED,
            'pending', 'sent', 'opened', 'in_progress' => Contract::STATUS_SENT,
            default => null,
        };
    }

    /**
     * Pull a document/certificate URL from a DocuSeal documents payload.
     *
     * @param  array<string, mixed>  $docs
     */
    private function extractDocumentUrl(array $docs, string $kind): ?string
    {
        $list = $docs['documents'] ?? $docs;

        if (! is_array($list)) {
            return null;
        }

        foreach ($list as $doc) {
            if (! is_array($doc)) {
                continue;
            }

            $name = isset($doc['name']) && is_string($doc['name']) ? strtolower($doc['name']) : '';
            $isCertificate = str_contains($name, 'certificate');

            $matches = $kind === 'certificate' ? $isCertificate : ! $isCertificate;

            if ($matches && isset($doc['url']) && is_string($doc['url'])) {
                return $doc['url'];
            }
        }

        return null;
    }
}
