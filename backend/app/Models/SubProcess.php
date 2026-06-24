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
 * @property-read Collection<int, ProcessDocument> $documents
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
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    public function subSubProcesses(): HasMany
    {
        return $this->hasMany(SubSubProcess::class, 'sub_process_id')->orderBy('order_index');
    }

    /**
     * Current (non-deleted) process documents attached to this sub-process.
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
