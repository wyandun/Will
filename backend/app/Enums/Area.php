<?php

namespace App\Enums;

/**
 * Domain area constants used to categorise SM franchise admins.
 *
 * Centralises the magic strings so renaming an area is a single-file change.
 */
final class Area
{
    public const FULL_ACCESS = 'full_access';

    public const ACCOUNTING = 'accounting';

    public const MARKETING = 'marketing';

    public const OPERATIONS = 'operations';

    public const LEGAL = 'legal';

    public const HUMAN_RESOURCES = 'human_resources';

    /**
     * Prevent instantiation.
     */
    private function __construct() {}

    /**
     * All valid area values.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::FULL_ACCESS,
            self::ACCOUNTING,
            self::MARKETING,
            self::OPERATIONS,
            self::LEGAL,
            self::HUMAN_RESOURCES,
        ];
    }
}
