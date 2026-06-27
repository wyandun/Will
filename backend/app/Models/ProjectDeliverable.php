<?php

namespace App\Models;

use App\Enums\ProjectDeliverableStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $catalog_item_id
 * @property string $name
 * @property string|null $phase
 * @property Carbon|null $estimated_start_date
 * @property Carbon|null $estimated_end_date
 * @property ProjectDeliverableStatus $status
 * @property int $order
 * @property-read Project $project
 * @property-read CatalogItem $catalogItem
 */
class ProjectDeliverable extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'catalog_item_id',
        'name',
        'phase',
        'estimated_start_date',
        'estimated_end_date',
        'status',
        'order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectDeliverableStatus::class,
            'estimated_start_date' => 'date',
            'estimated_end_date' => 'date',
            'order' => 'integer',
        ];
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * The project this deliverable belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The catalog deliverable this row was generated from.
     *
     * @return BelongsTo<CatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }
}
