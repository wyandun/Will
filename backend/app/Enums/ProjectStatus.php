<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';

    case Completed = 'completed';

    case Paused = 'paused';

    case Cancelled = 'cancelled';
}
