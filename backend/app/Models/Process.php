<?php

namespace App\Models;

use Database\Factories\ProcessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $description
 */
class Process extends Model
{
    /** @use HasFactory<ProcessFactory> */
    use HasFactory;

    protected $fillable = ['category_id', 'code', 'name_es', 'name_en', 'description', 'order_index'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProcessCategory::class, 'category_id');
    }

    public function subProcesses(): HasMany
    {
        return $this->hasMany(SubProcess::class, 'process_id')->orderBy('order_index');
    }
}
