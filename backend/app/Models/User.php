<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int|null $sm_franchise_id
 * @property int|null $company_id
 * @property int|null $sub_franchise_id
 * @property string|null $avatar_path
 * @property \Carbon\Carbon|null $birth_date
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'job_title',
        'bio',
        'birth_date',
        'avatar_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'sm_franchise_id' => 'integer',
            'company_id' => 'integer',
            'sub_franchise_id' => 'integer',
        ];
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * Module-level permissions for this user (user_permissions table).
     * Each row represents one module with can_read / can_write flags.
     *
     * @return HasMany<UserPermission, $this>
     */
    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }
}
