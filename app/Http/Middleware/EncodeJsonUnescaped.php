<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emits JSON responses as raw UTF-8 instead of \uXXXX escape sequences.
 *
 * PHP's json_encode escapes every non-ASCII character by default, so Arabic was
 * going out as "صداع". That is valid JSON and every client
 * decodes it back to the identical string — this is a readability and payload
 * change, not a correctness fix:
 *
 *   - Size: an Arabic character costs 6 bytes escaped vs 2 bytes raw, so Arabic
 *     payloads shrink by roughly two thirds.
 *   - Debuggability: logs, proxies and `curl` output become readable.
 *
 * Decoded values are byte-for-byte identical, so no existing consumer changes.
 * Only JsonResponse instances are touched; file downloads (the lab report PDFs)
 * and streamed responses pass through untouched.
 */
class EncodeJsonUnescaped
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->setEncodingOptions(
                $response->getEncodingOptions()
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
            );
        }

        return $response;
    }
}
