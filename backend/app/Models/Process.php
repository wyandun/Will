<?php

namespace App\Models;

use Database\Factories\ProcessFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $category_id
 * @property string $code
 * @property string $name_es
 * @property string $name_en
 * @property string|null $description
 * @property int $order_index
 * @property-read Collection<int, SubProcess> $subProcesses
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
