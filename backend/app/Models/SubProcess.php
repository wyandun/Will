<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubProcess extends Model
{
    protected $fillable = [
        'process_id',
        'code',
        'name_es',
        'name_en',
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
}
