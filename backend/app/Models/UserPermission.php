<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    /**
     * All modules managed by the permission system.
     */
    public const ALL_MODULES = [
        'feed',
        'contracts',
        'repository',
        'processes',
        'accounting',
        'inventory',
        'tracking',
        'catalog',
        'calendar',
    ];

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

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Create or update module permission records for a user based on their role.
     *
     * Permission rules:
     *   superadmin / system_admin / admin_sm → can_read + can_write on all modules
     *   system_admin_readonly                → can_read only on all modules
     *   all other roles                      → can_read only on all modules
     */
    public static function syncForRole(int $userId, string $role): void
    {
        $canWrite = in_array($role, [
            Role::SUPERADMIN,
            Role::SYSTEM_ADMIN,
            Role::ADMIN_SM,
        ], true);

        foreach (self::ALL_MODULES as $module) {
            self::updateOrCreate(
                ['user_id' => $userId, 'module' => $module],
                ['can_read' => true, 'can_write' => $canWrite],
            );
        }
    }
}
