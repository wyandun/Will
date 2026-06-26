<?php

namespace App\Models;

use App\Enums\ProcessMapType;
use Database\Factories\ProcessMapFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $type
 * @property string $name_es
 * @property string $name_en
 * @property string|null $description
 * @property string|null $brand_color
 * @property string|null $logo_url
 * @property array<string, mixed>|null $node_styles
 * @property bool $is_active
 * @property-read Company|null $company
 */
class ProcessMap extends Model
{
    /** @use HasFactory<ProcessMapFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'type',
        'name_es',
        'name_en',
        'description',
        'is_active',
        'brand_color',
        'logo_url',
        'node_styles',
    ];

    /**
     * Attribute casts.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'node_styles' => 'array',
    ];

    // ---------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------

    /**
     * Order results so the 'franquiciadora' map always comes first.
     * Falls back to any available map type for companies created manually
     * (without Close Deal), which only receive a 'franquiciada' map.
     *
     * @param  Builder<ProcessMap>  $query
     * @return Builder<ProcessMap>
     */
    public function scopePreferFranquiciadora(Builder $query): Builder
    {
        return $query->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [
            ProcessMapType::Franquiciadora->value,
        ]);
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * The company this process map belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * The process categories for this map, ordered for display.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(ProcessCategory::class, 'process_map_id')->orderBy('order_index');
    }
}
