<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $level
 * @property int|null $parent_id
 * @property string $name_es
 * @property string $name_en
 * @property string|null $description_es
 * @property string|null $description_en
 * @property bool $is_monthly
 * @property int $order_index
 * @property float|null $estimated_hours
 * @property string|null $service_type
 * @property-read float $total_hours
 */
class CatalogItem extends Model
{
    use HasFactory;

    public const LEVEL_BUNDLE = 'bundle';

    public const LEVEL_SERVICE = 'service';

    public const LEVEL_DELIVERABLE = 'deliverable';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'level',
        'parent_id',
        'name_es',
        'name_en',
        'description_es',
        'description_en',
        'is_monthly',
        'order_index',
        'estimated_hours',
        'service_type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_monthly' => 'boolean',
            'estimated_hours' => 'float',
            'order_index' => 'integer',
        ];
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * Parent catalog item (self-referential).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Direct children, ordered by display order_index.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order_index');
    }

    // ---------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------

    public function scopeBundles(Builder $query): Builder
    {
        return $query->where('level', self::LEVEL_BUNDLE);
    }

    public function scopeServices(Builder $query): Builder
    {
        return $query->where('level', self::LEVEL_SERVICE);
    }

    public function scopeDeliverables(Builder $query): Builder
    {
        return $query->where('level', self::LEVEL_DELIVERABLE);
    }

    // ---------------------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------------------

    /**
     * Computed total hours.
     *   - deliverable: own estimated_hours
     *   - service:     sum of children deliverables' estimated_hours
     *   - bundle:      sum across all descendant deliverables (children services
     *                  and their children deliverables)
     *
     * Uses the already-loaded `children` relation when present to avoid
     * extra queries; otherwise falls back to fresh queries.
     */
    protected function totalHours(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                if ($this->level === self::LEVEL_DELIVERABLE) {
                    return (float) ($this->estimated_hours ?? 0);
                }

                if ($this->level === self::LEVEL_SERVICE) {
                    /** @var Collection<int, self> $children */
                    $children = $this->relationLoaded('children')
                        ? $this->children
                        : $this->children()->where('level', self::LEVEL_DELIVERABLE)->get();

                    return (float) $children->sum('estimated_hours');
                }

                if ($this->level === self::LEVEL_BUNDLE) {
                    /** @var Collection<int, self> $services */
                    $services = $this->relationLoaded('children')
                        ? $this->children
                        : $this->children()->with('children')->get();

                    return (float) $services->sum(fn ($service) => $service->total_hours);
                }

                return 0.0;
            }
        );
    }
}
