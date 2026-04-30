<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;


/**
 * @property string $name
 */
class Franchise extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'region',
        'address',
        'phone',
        'email',
        'country',
        'timezone',
    ];

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
     * The Company model will be created in a later sprint.
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'sm_franchise_id');
    }
}
