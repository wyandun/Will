<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the self-hosted DocuSeal e-signing service.
 *
 * Degrades gracefully when DocuSeal is not configured (no URL/token): read
 * operations return empty results and write operations return [] / false,
 * so the Contracts module remains usable for drafts without DocuSeal.
 */
class DocuSealService
{
    private ?string $baseUrl;

    private ?string $token;

    public function __construct()
    {
        $url = config('services.docuseal.url');
        $token = config('services.docuseal.token');

        $this->baseUrl = is_string($url) && $url !== '' ? rtrim($url, '/') : null;
        $this->token = is_string($token) && $token !== '' ? $token : null;
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== null && $this->token !== null;
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'X-Auth-Token' => (string) $this->token,
            'Content-Type' => 'application/json',
        ])->baseUrl((string) $this->baseUrl);
    }

    /**
     * List available templates. Returns [] when DocuSeal is not configured.
     *
     * When $folder is given, templates are scoped to that DocuSeal folder so an
     * admin_sm only sees their own franchise's templates.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTemplates(?string $folder = null): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $query = $folder !== null && $folder !== '' ? ['folder' => $folder] : [];

        $response = $this->http()->get('/api/templates', $query);
        $json = $response->json();

        if (! is_array($json)) {
            return [];
        }

        // DocuSeal may return either a bare list or { data: [...] }.
        if (isset($json['data']) && is_array($json['data'])) {
            /** @var array<int, array<string, mixed>> */
            return array_values($json['data']);
        }

        /** @var array<int, array<string, mixed>> */
        return array_values($json);
    }

    /**
     * Create a submission (send for signing).
     *
     * @param  array<int, array{name: string, email: string, role?: string|null}>  $signers
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function createSubmission(int $templateId, array $signers, array $metadata = []): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $payload = [
            'template_id' => $templateId,
            'send_email' => true,
            'submitters' => array_map(fn (array $s): array => [
                'name' => $s['name'],
                'email' => $s['email'],
                'role' => $s['role'] ?? 'Signer',
            ], $signers),
        ];

        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        $response = $this->http()->post('/api/submissions', $payload);
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Fetch a submission's current status.
     *
     * @return array<string, mixed>
     */
    public function getSubmission(string $submissionId): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $response = $this->http()->get("/api/submissions/{$submissionId}");
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Fetch a submission's signed documents (signed PDF + certificate).
     *
     * @return array<string, mixed>
     */
    public function getSubmissionDocuments(string $submissionId): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $response = $this->http()->get("/api/submissions/{$submissionId}/documents");
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Delete a submission. Returns false when not configured / request fails.
     */
    public function deleteSubmission(string $submissionId): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        return $this->http()->delete("/api/submissions/{$submissionId}")->successful();
    }
}
