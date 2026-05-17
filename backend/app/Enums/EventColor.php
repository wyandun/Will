<?php

namespace App\Enums;

enum EventColor: string
{
    case Red = '#EF4444';
    case Orange = '#F97316';
    case Yellow = '#EAB308';
    case Green = '#10B981';
    case Blue = '#3B82F6';
    case Purple = '#8B5CF6';
    case Pink = '#EC4899';
    case Indigo = '#6366F1';
    case Teal = '#14B8A6';
    case Gray = '#6B7280';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
