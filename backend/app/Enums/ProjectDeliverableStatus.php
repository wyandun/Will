<?php

namespace App\Enums;

enum ProjectDeliverableStatus: string
{
    case Pending = 'pending';

    case InProgress = 'in_progress';

    case Completed = 'completed';

    case Blocked = 'blocked';
}
