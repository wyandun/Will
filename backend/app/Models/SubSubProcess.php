<?php

namespace App\Models;

use Database\Factories\SubSubProcessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property string|null $description
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
    ];

    public function subProcess(): BelongsTo
    {
        return $this->belongsTo(SubProcess::class, 'sub_process_id');
    }

    /**
     * Process documents attached directly to this sub-sub-process.
     *
     * @return MorphMany<ProcessDocument, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(ProcessDocument::class, 'documentable')
            ->where('is_current', true)
            ->orderBy('code');
    }
}
