<?php

namespace App\Providers;

use App\Models\BbAssignment;
use App\Models\Company;
use App\Models\Franchise;
use App\Policies\BbAssignmentPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\FranchisePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // 5 attempts per minute keyed by email + IP to slow brute-force attacks.
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->input('email').$request->ip());
        });
    }
}
