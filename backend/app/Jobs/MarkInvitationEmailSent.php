<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Marks a user's invitation email as successfully sent.
 *
 * Dispatched by the NotificationSent event listener in AppServiceProvider
 * after the mail channel confirms the notification was handed off to the
 * mail transport. Runs on sm_queue for consistency with other notifications.
 */
class MarkInvitationEmailSent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(private readonly User $user) {}

    public function handle(): void
    {
        // Re-fetch to avoid acting on a stale model (the notification pipeline
        // may have serialized the user before email_sent_at was reset by resend).
        $fresh = User::withTrashed()->find($this->user->id);

        if (! $fresh) {
            return;
        }

        $fresh->email_sent_at = now();
        $fresh->save();
    }
}
