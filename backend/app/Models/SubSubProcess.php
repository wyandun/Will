<?php

namespace App\Models;

use Database\Factories\SubSubProcessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property string|null $description
 * @property string|null $bpmn_xml_es
 * @property string|null $bpmn_xml_en
 * @property array<int, mixed>|null $walkthrough_es
 * @property array<int, mixed>|null $walkthrough_en
 * @property array<string, array{type: string, value: int|string}>|null $node_links
 * @property int|null $manual_document_id
 */
class SubSubProcess extends Model
{
    /** @use HasFactory<SubSubProcessFactory> */
    use HasFactory;

    protected $table = 'sub_sub_processes';

    protected $fillable = [
        'sub_process_id',
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

    public function subProcess(): BelongsTo
    {
        return $this->belongsTo(SubProcess::class, 'sub_process_id');
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function manualDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'manual_document_id');
    }
}
