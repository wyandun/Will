<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Process extends Model
{
    /** @use HasFactory<\Database\Factories\ProcessFactory> */
    use HasFactory;

    protected $fillable = ['category_id', 'code', 'name_es', 'name_en', 'order_index'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProcessCategory::class, 'category_id');
    }

    public function subProcesses(): HasMany
    {
        return $this->hasMany(SubProcess::class, 'process_id')->orderBy('order_index');
    }
}
