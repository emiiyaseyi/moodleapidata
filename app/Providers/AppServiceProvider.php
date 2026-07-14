<?php

namespace App\Providers;

use App\Services\MoodleService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MoodleService::class, function () {
            return new MoodleService(
                baseUrl: rtrim(config('services.moodle.base_url'), '/'),
                token: config('services.moodle.token'),
                cacheTtl: (int) config('services.moodle.cache_ttl', 900),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });
    }
}
