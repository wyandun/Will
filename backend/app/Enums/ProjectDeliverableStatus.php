<?php

namespace App\Enums;

enum ProjectDeliverableStatus: string
{
    case Pending = 'pending';

    case InProgress = 'in_progress';

    case Completed = 'completed';

    case Blocked = 'blocked';

    /** Statuses considered "upcoming" for the dashboard tab. */
    public static function upcoming(): array
    {
        return [self::Pending, self::InProgress];
    }
}
