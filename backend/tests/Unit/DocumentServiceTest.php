<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithProcessMaps;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use InteractsWithProcessMaps;
    use RefreshDatabase;

    private DocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentService;
    }

    public function test_code_is_auto_generated_and_increments_per_type(): void
    {
        $subProcess = $this->buildFullTree($this->createCompany()); // code OPS-P01

        $first = $this->service->create($subProcess, ['type' => 'FOR', 'title_es' => 'A', 'title_en' => 'A']);
        $second = $this->service->create($subProcess, ['type' => 'FOR', 'title_es' => 'B', 'title_en' => 'B']);
        $other = $this->service->create($subProcess, ['type' => 'MN', 'title_es' => 'C', 'title_en' => 'C']);

        $this->assertSame('OPS-P01-FOR-01', $first->code);
        $this->assertSame('OPS-P01-FOR-02', $second->code);
        $this->assertSame('OPS-P01-MN-01', $other->code);
    }

    public function test_sequence_accounts_for_soft_deleted_documents(): void
    {
        $subProcess = $this->buildFullTree($this->createCompany());

        $first = $this->service->create($subProcess, ['type' => 'FOR', 'title_es' => 'A', 'title_en' => 'A']);
        $this->service->delete($first); // soft delete

        $second = $this->service->create($subProcess, ['type' => 'FOR', 'title_es' => 'B', 'title_en' => 'B']);

        // The soft-deleted code is not reused (unique code/version is preserved).
        $this->assertSame('OPS-P01-FOR-02', $second->code);
    }

    public function test_manual_type_sets_manual_document_id(): void
    {
        $subProcess = $this->buildFullTree($this->createCompany());

        $manual = $this->service->create($subProcess, ['type' => 'MP', 'title_es' => 'M', 'title_en' => 'M']);

        $this->assertSame($manual->id, $subProcess->fresh()->manual_document_id);
    }

    public function test_is_manual_flag_sets_manual_document_id_for_non_mp_type(): void
    {
        $subProcess = $this->buildFullTree($this->createCompany());

        $doc = $this->service->create($subProcess, [
            'type' => 'MN', 'title_es' => 'M', 'title_en' => 'M', 'is_manual' => true,
        ]);

        $this->assertSame($doc->id, $subProcess->fresh()->manual_document_id);
    }

    public function test_uploaded_files_are_stored_for_both_languages(): void
    {
        Storage::fake('public');
        $subProcess = $this->buildFullTree($this->createCompany());

        $doc = $this->service->create($subProcess, [
            'type' => 'FOR', 'title_es' => 'A', 'title_en' => 'A',
            'file_es' => UploadedFile::fake()->create('es.pdf', 50, 'application/pdf'),
            'file_en' => UploadedFile::fake()->create('en.docx', 50),
        ]);

        $this->assertSame('es.pdf', $doc->file_name);
        $this->assertNotNull($doc->file_url);
        $this->assertSame('en.docx', $doc->file_name_en);
        $this->assertNotNull($doc->file_url_en);
    }

    public function test_reviewer_and_approver_set_timestamps(): void
    {
        $subProcess = $this->buildFullTree($this->createCompany());
        $reviewer = User::factory()->create();
        $approver = User::factory()->create();

        $doc = $this->service->create($subProcess, [
            'type' => 'FOR', 'title_es' => 'A', 'title_en' => 'A',
            'reviewed_by' => $reviewer->id, 'approved_by' => $approver->id,
            'valid_from' => '2026-06-01', 'notes' => 'note',
        ]);

        $this->assertSame($reviewer->id, $doc->reviewed_by);
        $this->assertNotNull($doc->reviewed_at);
        $this->assertSame($approver->id, $doc->approved_by);
        $this->assertNotNull($doc->approved_at);
        $this->assertSame('2026-06-01', $doc->valid_from->toDateString());
        $this->assertSame('note', $doc->notes);
    }

    public function test_update_changes_metadata_and_replaces_file(): void
    {
        Storage::fake('public');
        $subProcess = $this->buildFullTree($this->createCompany());
        $doc = $this->service->create($subProcess, ['type' => 'FOR', 'title_es' => 'Old', 'title_en' => 'Old']);

        $updated = $this->service->update($doc, [
            'title_es' => 'New',
            'file_en' => UploadedFile::fake()->create('new.xlsx', 30),
        ]);

        $this->assertSame('New', $updated->title_es);
        $this->assertSame('Old', $updated->title_en);
        $this->assertSame('new.xlsx', $updated->file_name_en);
    }

    public function test_delete_soft_deletes_document(): void
    {
        $subProcess = $this->buildFullTree($this->createCompany());
        $doc = $this->service->create($subProcess, ['type' => 'FOR', 'title_es' => 'A', 'title_en' => 'A']);

        $this->service->delete($doc);

        $this->assertSoftDeleted('process_documents', ['id' => $doc->id]);
    }
}
