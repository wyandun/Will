<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $franchise_id
 * @property int $catalog_item_id
 * @property string $type
 * @property Carbon $start_date
 * @property string|null $notes
 * @property ProjectStatus $status
 * @property-read Company $company
 * @property-read Franchise $franchise
 * @property-read CatalogItem $catalogItem
 * @property-read Collection<int, ProjectDeliverable> $deliverables
 */
class Project extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'franchise_id',
        'catalog_item_id',
        'type',
        'start_date',
        'notes',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
        ];
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * The company this project belongs to.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The SM franchise that created this project.
     *
     * @return BelongsTo<Franchise, $this>
     */
    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    /**
     * The root catalog item assigned to this project.
     *
     * @return BelongsTo<CatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    /**
     * All deliverables generated for this project, ordered for display.
     *
     * @return HasMany<ProjectDeliverable, $this>
     */
    public function deliverables(): HasMany
    {
        return $this->hasMany(ProjectDeliverable::class)->orderBy('order');
    }
}
