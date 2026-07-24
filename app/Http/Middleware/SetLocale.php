<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Negotiates the request locale for every API and web request.
 *
 * Resolution order:
 *   1. ?lang= query parameter (opt-in, see config/localization.php)
 *   2. Accept-Language header, honouring q-values and region subtags
 *   3. config('localization.default')
 *
 * The negotiated locale is applied to the application and echoed back on the
 * response as Content-Language so clients and caches can see what they got.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);

        $response = $next($request);

        // Vary matters for any shared cache in front of the API: the same URL
        // legitimately returns different text per Accept-Language.
        $response->headers->set('Content-Language', $locale);
        $response->headers->set('Vary', 'Accept-Language', false);

        return $response;
    }

    /**
     * Pick the best supported locale for this request.
     */
    protected function resolveLocale(Request $request): string
    {
        $supported = (array) config('localization.supported', ['en']);
        $default = (string) config('localization.default', 'en');

        if ($queryKey = config('localization.query_parameter')) {
            $candidate = $this->normalize((string) $request->query($queryKey, ''));

            if ($candidate !== '' && in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        foreach ($this->parseAcceptLanguage($request->header('Accept-Language')) as $candidate) {
            if (in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        return in_array($default, $supported, true) ? $default : 'en';
    }

    /**
     * Expand an Accept-Language header into base language tags ordered by
     * descending q-value.
     *
     * "ar-SA,ar;q=0.9,en-US;q=0.8" => ['ar', 'ar', 'en']
     *
     * @return array<int, string>
     */
    protected function parseAcceptLanguage(?string $header): array
    {
        if (! $header) {
            return [];
        }

        $entries = [];

        foreach (explode(',', $header) as $index => $chunk) {
            $parts = explode(';', trim($chunk));
            $tag = $this->normalize($parts[0] ?? '');

            if ($tag === '' || $tag === '*') {
                continue;
            }

            $quality = 1.0;

            foreach (array_slice($parts, 1) as $parameter) {
                [$name, $value] = array_pad(explode('=', trim($parameter), 2), 2, null);

                if (strtolower(trim((string) $name)) === 'q') {
                    $quality = (float) $value;
                }
            }

            // $index keeps the original header order stable for equal q-values,
            // which is what the spec expects.
            $entries[] = ['tag' => $tag, 'q' => $quality, 'index' => $index];
        }

        usort($entries, fn (array $a, array $b) => $b['q'] <=> $a['q'] ?: $a['index'] <=> $b['index']);

        return array_column($entries, 'tag');
    }

    /**
     * Reduce a language tag to its lowercase base subtag: "ar-SA" => "ar".
     */
    protected function normalize(string $tag): string
    {
        $tag = strtolower(trim($tag));

        return $tag === '' ? '' : explode('-', str_replace('_', '-', $tag))[0];
    }
}
