<?php

namespace App\Enums;

enum ClientType: string
{
    case OWNER = 'owner';
    case INVESTOR = 'investor';

    /**
     * Resolve the Spatie role constant for this client type.
     */
    public function role(): string
    {
        return match ($this) {
            self::OWNER => Role::SB_OWNER,
            self::INVESTOR => Role::BB_EMPLOYEE,
        };
    }

    /**
     * Laravel validation rule fragment: 'in:owner,investor'
     */
    public static function validationRule(): string
    {
        return 'in:'.implode(',', array_column(self::cases(), 'value'));
    }
}
