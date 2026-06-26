<?php

namespace App\Models;

use Database\Factories\SubProcessFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $process_id
 * @property string $code
 * @property string $name_es
 * @property string $name_en
 * @property string|null $description
 * @property int $order_index
 * @property string|null $bpmn_xml_es
 * @property string|null $bpmn_xml_en
 * @property array<int, mixed>|null $walkthrough_es
 * @property array<int, mixed>|null $walkthrough_en
 * @property array<string, array{type: string, value: int|string}>|null $node_links
 * @property int|null $manual_document_id
 * @property-read Collection<int, Document> $documents
 * @property-read Collection<int, Document> $currentDocuments
 */
class SubProcess extends Model
{
    /** @use HasFactory<SubProcessFactory> */
    use HasFactory;

    protected $fillable = [
        'process_id',
        'code',
        'name_es',
        'name_en',
        'description',
        'order_index',
        'bpmn_xml_es',
        'bpmn_xml_en',
        'walkthrough_es',
        'walkthrough_en',
        'node_links',
        'manual_document_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'walkthrough_es' => 'array',
            'walkthrough_en' => 'array',
            'node_links' => 'array',
        ];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    public function subSubProcesses(): HasMany
    {
        return $this->hasMany(SubSubProcess::class, 'sub_process_id')->orderBy('order_index');
    }

    /**
     * All documents (all versions, including soft-deleted). Used by DocumentService
     * to generate sequential codes and persist new documents.
     *
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Current active documents only. Used by the Repository "Process Documents" tab.
     *
     * @return MorphMany<Document, $this>
     */
    public function currentDocuments(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')
            ->where('is_current', true)
            ->orderBy('code');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function manualDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'manual_document_id');
    }
}
