<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $sub_franchise_id
 * @property-read Company|null $company
 * @property-read Franchise|null $subFranchise
 * @property-read Collection<int, RepositoryDocument> $documents
 */
class Repository extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'sub_franchise_id',
    ];

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * The company this repository belongs to.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * The sub-franchise this repository belongs to (null for company-level repos).
     *
     * @return BelongsTo<Franchise, $this>
     */
    public function subFranchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class, 'sub_franchise_id');
    }

    /**
     * All documents stored in this repository.
     *
     * @return HasMany<RepositoryDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(RepositoryDocument::class, 'repository_id');
    }
}
