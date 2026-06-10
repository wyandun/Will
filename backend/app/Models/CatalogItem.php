<?php

namespace App\Models;

use App\Enums\CatalogLevel;
use App\Enums\CatalogServiceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property CatalogLevel $level
 * @property int|null $parent_id
 * @property string $name_es
 * @property string $name_en
 * @property string|null $description_es
 * @property string|null $description_en
 * @property bool $is_monthly
 * @property int $order_index
 * @property float|null $estimated_hours
 * @property CatalogServiceType|null $service_type
 * @property-read float $total_hours
 */
class CatalogItem extends Model
{
    use HasFactory;

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
            'level' => CatalogLevel::class,
            'service_type' => CatalogServiceType::class,
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
        return $query->where('level', CatalogLevel::Bundle->value);
    }

    public function scopeServices(Builder $query): Builder
    {
        return $query->where('level', CatalogLevel::Service->value);
    }

    public function scopeDeliverables(Builder $query): Builder
    {
        return $query->where('level', CatalogLevel::Deliverable->value);
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
            get: fn (): float => match ($this->level) {
                CatalogLevel::Deliverable => (float) ($this->estimated_hours ?? 0),
                CatalogLevel::Service => $this->sumServiceHours(),
                CatalogLevel::Bundle => $this->sumBundleHours(),
            }
        );
    }

    /**
     * Sum of estimated_hours across this service's deliverable children.
     */
    private function sumServiceHours(): float
    {
        /** @var Collection<int, self> $children */
        $children = $this->relationLoaded('children')
            ? $this->children
            : $this->children()->where('level', CatalogLevel::Deliverable->value)->get();

        return (float) $children->sum('estimated_hours');
    }

    /**
     * Sum of total_hours across this bundle's service children.
     */
    private function sumBundleHours(): float
    {
        /** @var Collection<int, self> $services */
        $services = $this->relationLoaded('children')
            ? $this->children
            : $this->children()->with('children')->get();

        return (float) $services->sum(fn ($service) => $service->total_hours);
    }
}
