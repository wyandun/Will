<?php

namespace App\Enums;

enum Area: string
{
    case FULL_ACCESS = 'full_access';
    case ACCOUNTING = 'accounting';
    case MARKETING = 'marketing';
    case OPERATIONS = 'operations';
    case LEGAL = 'legal';
    case HUMAN_RESOURCES = 'human_resources';

    /**
     * Laravel validation rule fragment: 'in:full_access,accounting,...'
     */
    public static function validationRule(): string
    {
        return 'in:'.implode(',', array_column(self::cases(), 'value'));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
