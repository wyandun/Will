<?php

namespace App\Enums;

/**
 * All permission modules available on the platform.
 *
 * This enum is the single source of truth — FranchiseMemberService,
 * UserPermission model, and any future module-related logic should
 * reference these cases instead of maintaining independent lists.
 */
enum Module: string
{
    case FEED = 'feed';
    case CONTRACTS = 'contracts';
    case REPOSITORY = 'repository';
    case PROCESSES = 'processes';
    case ACCOUNTING = 'accounting';
    case INVENTORY = 'inventory';
    case TRACKING = 'tracking';
    case CATALOG = 'catalog';
    case CALENDAR = 'calendar';
    case APPLICATIONS = 'applications';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
