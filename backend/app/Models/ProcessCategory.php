<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessCategory extends Model
{
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
