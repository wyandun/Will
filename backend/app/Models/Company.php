<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'industry',
        'state',
        'country',
        'sm_franchise_id',
        'employees_count',
        'annual_revenue',
        'years_operating',
        'logo_path',
        'address',
        'phone',
        'website',
    ];

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * The SM franchise that manages this company.
     */
    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class, 'sm_franchise_id');
    }

    /**
     * Users whose company_id points to this company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id');
    }

    /**
     * BPMN process maps for this company.
     * Every company has exactly two: 'franquiciadora' and 'franquiciada'.
     */
    public function processMaps(): HasMany
    {
        return $this->hasMany(ProcessMap::class, 'company_id');
    }

    /**
     * The Business Bishop assignment for this company (at most one).
     * A company can only have one active BB sponsor.
     */
    public function bbAssignment(): HasOne
    {
        return $this->hasOne(BbAssignment::class, 'company_id');
    }
}
