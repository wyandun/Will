<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $name
 * @property bool $is_active
 */
class Franchise extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'parent_company_id',
        'owner_user_id',
        'region',
        'address',
        'phone',
        'email',
        'country',
        'timezone',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * The user who owns or manages this franchise.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Users whose sm_franchise_id points to this franchise
     * (staff of an SM franchise).
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'sm_franchise_id');
    }

    /**
     * Companies that belong to this SM franchise.
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'sm_franchise_id');
    }

    // ---------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------
    // TODO: Client-side filtering on a paginated dataset is broken by design:
    //       the user can only search within the current page and results on
    //       subsequent pages are invisible. Before production, add backend
    //       ?search= and ?active= query parameters in FranchiseService::list()
    //       using these scopes.

    /**
     * Scope a query to only include active franchises.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive franchises.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }
}
