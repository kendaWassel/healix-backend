<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
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
        // Telescope is a dev-only dependency, so its provider is registered here
        // conditionally instead of in bootstrap/providers.php. This keeps
        // `composer install --no-dev` (production/CI builds) from failing with
        // "TelescopeApplicationServiceProvider not found".
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
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

        $this->registerSqliteRegexp();
    }

    /**
     * Teach SQLite the REGEXP operator.
     *
     * MySQL/MariaDB ship REGEXP built in; SQLite does not, and calling it
     * raises "no such function: REGEXP". App\Support\ArabicSearch relies on it
     * to fold Arabic letter variants, so without this the Arabic search path
     * works in production but throws under the SQLite test connection.
     *
     * Registering it here keeps one code path across both drivers instead of
     * branching the query builder on the connection type.
     */
    private function registerSqliteRegexp(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();

        if (! method_exists($pdo, 'sqliteCreateFunction')) {
            return;
        }

        $pdo->sqliteCreateFunction(
            'regexp',
            // SQLite calls this as: <value> REGEXP <pattern> => (pattern, value)
            static function ($pattern, $value): int {
                if ($pattern === null || $value === null) {
                    return 0;
                }

                return (int) (bool) preg_match('/' . $pattern . '/u', (string) $value);
            },
            2
        );
    }
}
