<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserPermission extends Model
{
    /**
     * Canonical list of module-permission keys used across the system.
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

    /**
     * Module keys: feed, contracts, repository, processes, accounting,
     *              inventory, tracking, catalog, calendar
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

    // ---------------------------------------------------------------------------
    // Centralized permission sync
    // ---------------------------------------------------------------------------

    /**
     * Create or update UserPermission rows for every module based on the given role.
     *
     * - SUPERADMIN / SYSTEM_ADMIN / ADMIN_SM → can_read=true, can_write=true
     * - SYSTEM_ADMIN_READONLY                → can_read=true, can_write=false
     * - All other roles (sb_owner, etc.)     → can_read=true, can_write=false
     *
     * Security notes (reviewed 2026-05):
     * - Unknown roles default to can_write=false (safest permission level).
     *   All production callers validate the role via FormRequest before calling this method.
     * - Concurrency-safe: unique index on (user_id, module) prevents duplicates,
     *   and updateOrCreate is atomic at the SQL level. The wrapping transaction
     *   ensures all 9 modules are synced consistently.
     * - 9 individual updateOrCreate calls (one per module) are acceptable for the
     *   current call volume (interactive requests and seeder). Consider upsert()
     *   if batch provisioning is added.
     */
    /**
     * Update individual module permissions for a user.
     *
     * @param  array<int, array{module: string, can_read: bool, can_write: bool}>  $permissions
     */
    public static function updateForUser(int $userId, array $permissions): void
    {
        DB::transaction(function () use ($userId, $permissions) {
            foreach ($permissions as $perm) {
                self::updateOrCreate(
                    ['user_id' => $userId, 'module' => $perm['module']],
                    ['can_read' => $perm['can_read'], 'can_write' => $perm['can_write']],
                );
            }
        });
    }

    public static function syncForRole(int $userId, string $role): void
    {
        DB::transaction(function () use ($userId, $role) {
            $canWrite = in_array($role, [Role::SUPERADMIN, Role::SYSTEM_ADMIN, Role::ADMIN_SM], true);

            foreach (self::ALL_MODULES as $module) {
                self::updateOrCreate(
                    ['user_id' => $userId, 'module' => $module],
                    ['can_read' => true, 'can_write' => $canWrite],
                );
            }
        });
    }
}
