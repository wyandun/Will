<?php

namespace App\Events;

use App\Models\Franchise;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a franchise's active status is toggled.
 *
 * Listeners can react to this event for tasks such as:
 * - Invalidating cached permission sets
 * - Notifying the franchise owner
 * - Restricting user logins under inactive franchises
 */
class FranchiseStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Franchise $franchise,
    ) {}
}
