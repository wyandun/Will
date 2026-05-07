<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TrackUserPresence
{
    /**
     * Minimum seconds between last_seen_at updates.
     * Avoids a DB write on every single API call.
     */
    private const THROTTLE_SECONDS = 60;

    /**
     * Update the authenticated user's last_seen_at timestamp.
     *
     * Only writes to the DB when the previous value is null or older than
     * THROTTLE_SECONDS, keeping the write load low while maintaining a
     * presence window accurate to within one minute.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $threshold = now()->subSeconds(self::THROTTLE_SECONDS);
            $needsUpdate = $user->last_seen_at === null
                || $user->last_seen_at->lt($threshold);

            if ($needsUpdate) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['last_seen_at' => now()]);

                // Keep the in-memory model in sync so callers in this request
                // see the updated timestamp without a reload.
                $user->last_seen_at = now();
            }
        }

        return $next($request);
    }
}
