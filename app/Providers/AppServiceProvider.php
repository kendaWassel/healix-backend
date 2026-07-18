<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
        Gate::guessPolicyNamesUsing(function (string $modelClass) {
            return 'App\\Policies\\' . class_basename($modelClass) . 'Policy';
        });

        // Without this, generated absolute URLs (signed verification links,
        // route() calls, etc.) follow whatever Host the current request came
        // in on — which is localhost/127.0.0.1 in console/tinker context, and
        // can differ from the public tunnel if the app is ever hit directly
        // on its local address. Forcing the root to APP_URL makes every such
        // URL consistently point at the public ngrok domain.
        if ($appUrl = config('app.url')) {
            URL::forceRootUrl($appUrl);

            if (str_starts_with($appUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
