<?php

namespace Tests\Feature;

use App\Services\DocuSealService;

/**
 * In-memory DocuSeal stub so the contracts feature tests never hit the network.
 */
class FakeDocuSealService extends DocuSealService
{
    public bool $createCalled = false;

    public bool $deleteCalled = false;

    public function __construct()
    {
        // Skip parent constructor (no config lookup needed for the fake).
    }

    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTemplates(?string $folder = null): array
    {
        $templates = [
            ['id' => 1, 'name' => 'Franchise Agreement', 'folder' => 'SM Florida'],
            ['id' => 2, 'name' => 'NDA', 'folder' => 'SM Texas'],
        ];

        if ($folder === null) {
            return $templates;
        }

        return array_values(array_filter(
            $templates,
            fn (array $t): bool => ($t['folder'] ?? null) === $folder,
        ));
    }

    /**
     * @param  array<int, array{name: string, email: string, role?: string|null}>  $signers
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function createSubmission(int $templateId, array $signers, array $metadata = []): array
    {
        $this->createCalled = true;

        return ['id' => 'fake-sub-123', 'status' => 'pending'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubmission(string $submissionId): array
    {
        return ['id' => $submissionId, 'status' => 'completed'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubmissionDocuments(string $submissionId): array
    {
        return [
            'documents' => [
                ['name' => 'agreement.pdf', 'url' => 'https://docuseal.test/signed.pdf'],
                ['name' => 'certificate.pdf', 'url' => 'https://docuseal.test/certificate.pdf'],
            ],
        ];
    }

    public function deleteSubmission(string $submissionId): bool
    {
        $this->deleteCalled = true;

        return true;
    }
}
