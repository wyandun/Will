<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * The company this process map belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
