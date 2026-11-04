<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        Queue::looping(function (Looping $event): void {
            static $lastHeartbeatAt = 0;

            if (config('cache.default') === 'array' || time() - $lastHeartbeatAt < 10) {
                return;
            }

            try {
                Cache::put('poomsae:queue-worker:'.$event->connectionName, time(), 45);
                $lastHeartbeatAt = time();
            } catch (\Throwable) {
                // A cache outage must not stop the worker from processing scores.
            }
        });
    }
}
