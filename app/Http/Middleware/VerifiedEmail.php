<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class VerifiedEmail
{
    /**
     * Handle email verification check.
     * Ensures user has verified their email before accessing protected routes.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return response()->json(['message' => __('auth.unauthenticated')], 401);
        }

        $user = Auth::user();

        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => __('auth.email_not_verified'),
                'error' => __('auth.email_verification_required'),
            ], 403);
        }

        $this->syncPreferredLocale($user);

        return $next($request);
    }

    /**
     * Keep the user's stored language preference in sync with whatever
     * locale this request actually negotiated (see SetLocale — it runs
     * earlier in the pipeline and already applied it via App::setLocale()).
     *
     * preferred_locale was previously only ever set once, at registration —
     * a user who later switches their app's language never had that
     * reflected anywhere, so every notification kept rendering in whatever
     * language was active the day they signed up. This middleware already
     * runs on nearly every authenticated route (it's paired with
     * auth:sanctum everywhere except one low-sensitivity browsing route),
     * so it's the natural place to keep the column current — cheap
     * in-memory check on every request, a write only when it actually
     * changes.
     */
    protected function syncPreferredLocale($user): void
    {
        $currentLocale = App::getLocale();
        $supported = (array) config('localization.supported', ['en']);

        if ($user->preferred_locale === $currentLocale || !in_array($currentLocale, $supported, true)) {
            return;
        }

        $user->forceFill(['preferred_locale' => $currentLocale])->save();
    }
}
