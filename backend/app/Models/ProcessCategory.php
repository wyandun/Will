<?php

namespace App\Models;

use Database\Factories\ProcessCategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $process_map_id
 * @property string $type
 * @property string $name_es
 * @property string $name_en
 * @property int $order_index
 * @property-read Collection<int, Process> $processes
 */
class ProcessCategory extends Model
{
    /** @use HasFactory<ProcessCategoryFactory> */
    use HasFactory;

    public const TYPE_STRATEGIC = 'strategic';

    public const TYPE_VALUE_CHAIN = 'value_chain';

    public const TYPE_SUPPORT = 'support';

    protected $fillable = ['process_map_id', 'type', 'name_es', 'name_en', 'order_index'];

    public function processMap(): BelongsTo
    {
        return $this->belongsTo(ProcessMap::class);
    }

    public function processes(): HasMany
    {
        return $this->hasMany(Process::class, 'category_id')->orderBy('order_index');
    }
}
