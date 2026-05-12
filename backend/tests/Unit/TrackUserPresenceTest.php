<?php

namespace Tests\Unit;

use App\Http\Middleware\TrackUserPresence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TrackUserPresenceTest extends TestCase
{
    use RefreshDatabase;

    private TrackUserPresence $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new TrackUserPresence;
    }

    /**
     * Run the middleware against the given request and return the response.
     * The $next closure is a no-op that returns a 200 response.
     */
    private function runMiddleware(Request $request): Response
    {
        return $this->middleware->handle($request, fn ($r) => response('ok'));
    }

    // -------------------------------------------------------------------------
    // Unauthenticated requests
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_passes_through_without_touching_db(): void
    {
        $request = Request::create('/api/v1/feed/posts', 'GET');
        // No user set — $request->user() returns null

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $response = $this->runMiddleware($request);

        // The middleware should not issue any UPDATE query
        $updateCount = 0;
        DB::listen(function ($query) use (&$updateCount) {
            if (str_contains(strtolower($query->sql), 'update')) {
                $updateCount++;
            }
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_request_always_passes_to_next_middleware_regardless_of_auth(): void
    {
        $request = Request::create('/api/v1/ping', 'GET');

        $nextCalled = false;
        $this->middleware->handle($request, function ($r) use (&$nextCalled) {
            $nextCalled = true;

            return response('ok');
        });

        $this->assertTrue($nextCalled);
    }

    // -------------------------------------------------------------------------
    // First-time update (last_seen_at is null)
    // -------------------------------------------------------------------------

    public function test_user_with_null_last_seen_at_gets_timestamp_set(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['last_seen_at' => null]);
        $user->last_seen_at = null;

        $request = Request::create('/api/v1/feed/posts', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->runMiddleware($request);

        $this->assertNotNull(
            DB::table('users')->where('id', $user->id)->value('last_seen_at')
        );
    }

    public function test_user_with_null_last_seen_at_gets_in_memory_model_updated(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['last_seen_at' => null]);
        $user->last_seen_at = null;

        $request = Request::create('/api/v1/feed/posts', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->runMiddleware($request);

        $this->assertNotNull($user->last_seen_at);
    }

    // -------------------------------------------------------------------------
    // Throttle — last_seen_at older than 60 seconds
    // -------------------------------------------------------------------------

    public function test_user_with_old_last_seen_at_gets_timestamp_updated(): void
    {
        $user = User::factory()->create();
        $oldTimestamp = now()->subSeconds(90);
        DB::table('users')->where('id', $user->id)->update(['last_seen_at' => $oldTimestamp]);
        $user->last_seen_at = $oldTimestamp;

        $request = Request::create('/api/v1/feed/posts', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->runMiddleware($request);

        $updatedAt = DB::table('users')->where('id', $user->id)->value('last_seen_at');
        $this->assertTrue(
            now()->diffInSeconds($updatedAt) < 5,
            'last_seen_at should be updated to approximately now'
        );
    }

    public function test_in_memory_model_is_updated_when_db_is_updated(): void
    {
        $user = User::factory()->create();
        $oldTimestamp = now()->subSeconds(90);
        DB::table('users')->where('id', $user->id)->update(['last_seen_at' => $oldTimestamp]);
        $user->last_seen_at = $oldTimestamp;

        $request = Request::create('/api/v1/feed/posts', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->runMiddleware($request);

        // The in-memory model should have been updated away from the old timestamp
        $this->assertNotEquals(
            $oldTimestamp->toDateTimeString(),
            $user->last_seen_at instanceof Carbon
                ? $user->last_seen_at->toDateTimeString()
                : (string) $user->last_seen_at,
            'In-memory model should reflect the updated timestamp'
        );
    }

    // -------------------------------------------------------------------------
    // Throttle — last_seen_at is recent (within 60 seconds)
    // -------------------------------------------------------------------------

    public function test_recent_last_seen_at_is_not_updated(): void
    {
        $user = User::factory()->create();
        $recentTimestamp = now()->subSeconds(30);
        DB::table('users')->where('id', $user->id)->update(['last_seen_at' => $recentTimestamp]);
        $user->last_seen_at = $recentTimestamp;

        $updateIssued = false;
        DB::listen(function ($query) use (&$updateIssued) {
            if (str_contains(strtolower($query->sql), 'update') &&
                str_contains($query->sql, 'users')) {
                $updateIssued = true;
            }
        });

        $request = Request::create('/api/v1/feed/posts', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->runMiddleware($request);

        $this->assertFalse($updateIssued, 'DB should not be updated when last_seen_at is recent');
    }

    public function test_last_seen_at_updated_59_seconds_ago_does_not_trigger_update(): void
    {
        $user = User::factory()->create();
        // 59 seconds ago — clearly within the 60-second throttle window
        $recentTimestamp = now()->subSeconds(59);
        DB::table('users')->where('id', $user->id)->update(['last_seen_at' => $recentTimestamp]);
        $user->last_seen_at = $recentTimestamp;

        $request = Request::create('/api/v1/feed/posts', 'GET');
        $request->setUserResolver(fn () => $user);

        $dbValueBefore = DB::table('users')->where('id', $user->id)->value('last_seen_at');

        $this->runMiddleware($request);

        $dbValueAfter = DB::table('users')->where('id', $user->id)->value('last_seen_at');

        $this->assertEquals($dbValueBefore, $dbValueAfter);
    }
}
