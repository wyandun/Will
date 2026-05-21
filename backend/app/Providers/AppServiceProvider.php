<?php

namespace App\Providers;

use App\Jobs\MarkInvitationEmailSent;
use App\Models\BbAssignment;
use App\Models\Company;
use App\Models\Event as EventModel;
use App\Models\Franchise;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use App\Policies\BbAssignmentPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\EventPolicy;
use App\Policies\FranchisePolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(BbAssignment::class, BbAssignmentPolicy::class);
        Gate::policy(Franchise::class, FranchisePolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(EventModel::class, EventPolicy::class);

        // When the mail channel successfully hands off a UserInvitationNotification,
        // dispatch a job to stamp email_sent_at on the user. Using a job (rather than
        // a synchronous write here) keeps the listener fast and consistent with the
        // rest of the notification pipeline, which is already queued.
        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if (
                $event->channel === 'mail'
                && $event->notification instanceof UserInvitationNotification
                && $event->notifiable instanceof User
            ) {
                MarkInvitationEmailSent::dispatch($event->notifiable)->onQueue('sm_queue');
            }
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // 5 attempts per minute keyed by email + IP to slow brute-force attacks.
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->input('email').$request->ip());
        });

        // 10 requests per minute per IP for public invitation endpoints (verify + accept).
        // Prevents token enumeration and brute-force on the accept flow.
        RateLimiter::for('invitation', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Global API rate limiter: 120 requests per minute per authenticated user (or IP
        // for unauthenticated requests). Prevents abuse of high-per_page endpoints like
        // events (per_page=200) and general API spam from compromised accounts.
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
