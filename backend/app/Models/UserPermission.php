<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    /**
     * The `module` column should contain values defined in \App\Enums\Module.
     */
    protected $fillable = [
        'user_id',
        'module',
        'can_read',
        'can_write',
    ];

    protected function casts(): array
    {
        return [
            'can_read' => 'boolean',
            'can_write' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
