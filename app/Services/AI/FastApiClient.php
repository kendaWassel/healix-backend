<?php

namespace App\Services\AI;

use App\Exceptions\AI\AIServiceException;
use App\Exceptions\AI\AIServiceInvalidResponseException;
use App\Exceptions\AI\AIServiceTimeoutException;
use App\Exceptions\AI\AIServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FastApiClient
{
    /** Key under config('services.*') holding url/timeout/retries. */
    protected string $configKey = 'medical_assistant';

    /** Human-readable name used in logs and exception messages. */
    protected string $serviceLabel = 'Medical Assistant service';

    protected string $baseUrl;

    protected int $timeout;

    protected int $retries;

    public function __construct()
    {
        $this->baseUrl = rtrim(config("services.{$this->configKey}.url"), '/');
        $this->timeout = (int) config("services.{$this->configKey}.timeout", 60);
        $this->retries = (int) config("services.{$this->configKey}.retries", 3);
    }

    /**
     * @throws AIServiceException
     */
    public function post(string $endpoint, array $payload): array
    {
        return $this->send('post', $endpoint, $payload);
    }

    /**
     * @throws AIServiceException
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->send('get', $endpoint, $query);
    }

    /**
     * Send a multipart/form-data POST (file upload + optional form fields).
     *
     * @param  array<string, string|int>  $fields  Plain form fields sent alongside the file.
     *
     * @throws AIServiceException
     */
    public function postMultipart(string $endpoint, string $fileField, string $fileContents, string $fileName, array $fields = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        Log::info("{$this->serviceLabel} multipart request", [
            'url' => $url,
            'file_name' => $fileName,
            'file_size' => strlen($fileContents),
            'fields' => $fields,
        ]);

        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retries) {
            $attempt++;

            try {
                // The attachment is consumed per request, so rebuild it on every attempt.
                $response = Http::timeout($this->timeout)
                    ->acceptJson()
                    ->attach($fileField, $fileContents, $fileName)
                    ->post($url, $fields);

                Log::info("{$this->serviceLabel} multipart response", [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (! is_array($data)) {
                        throw new AIServiceInvalidResponseException("{$this->serviceLabel} response is not valid JSON.");
                    }

                    return $data;
                }

                if ($response->status() >= 500 && $attempt < $this->retries) {
                    Log::warning("{$this->serviceLabel} server error, retrying", [
                        'url' => $url,
                        'attempt' => $attempt,
                        'status' => $response->status(),
                    ]);
                    continue;
                }

                $detail = $response->json('detail');

                throw new AIServiceException(
                    is_string($detail) && $detail !== ''
                        ? $detail
                        : "{$this->serviceLabel} request failed with status " . $response->status() . '.',
                    $response->status() >= 400 && $response->status() < 600 ? $response->status() : 502
                );
            } catch (ConnectionException $e) {
                $lastException = $e;
                Log::warning("{$this->serviceLabel} connection error, retrying", [
                    'url' => $url,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt >= $this->retries) {
                    throw new AIServiceUnavailableException("Unable to connect to {$this->serviceLabel}.");
                }
            }
        }

        throw new AIServiceUnavailableException(
            $lastException?->getMessage() ?? "{$this->serviceLabel} is unavailable after multiple attempts."
        );
    }

    /**
     * Fetch a binary file (e.g. a generated PDF) and return the raw bytes.
     *
     * @throws AIServiceException
     */
    public function downloadBinary(string $endpoint): string
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        Log::info("{$this->serviceLabel} binary download", ['url' => $url]);

        try {
            $response = Http::timeout($this->timeout)->get($url);
        } catch (ConnectionException $e) {
            throw new AIServiceUnavailableException("Unable to connect to {$this->serviceLabel}.");
        }

        if (! $response->successful()) {
            $detail = $response->json('detail');

            throw new AIServiceException(
                is_string($detail) && $detail !== ''
                    ? $detail
                    : "{$this->serviceLabel} file download failed with status " . $response->status() . '.',
                $response->status() >= 400 && $response->status() < 600 ? $response->status() : 502
            );
        }

        return $response->body();
    }

    /**
     * @param  'get'|'post'  $method  Payload is sent as query string for GET, JSON body for POST.
     *
     * @throws AIServiceException
     */
    protected function send(string $method, string $endpoint, array $payload): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        Log::info("{$this->serviceLabel} request", [
            'url' => $url,
            'method' => $method,
            'payload' => $payload,
        ]);

        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retries) {
            $attempt++;

            try {
                $pending = Http::timeout($this->timeout)->acceptJson();

                $response = $method === 'get'
                    ? $pending->get($url, $payload)
                    : $pending->asJson()->post($url, $payload);

                Log::info("{$this->serviceLabel} response", [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (! is_array($data)) {
                        throw new AIServiceInvalidResponseException("{$this->serviceLabel} response is not valid JSON.");
                    }

                    return $data;
                }

                if ($response->status() >= 500 && $attempt < $this->retries) {
                    Log::warning("{$this->serviceLabel} server error, retrying", [
                        'url' => $url,
                        'attempt' => $attempt,
                        'status' => $response->status(),
                    ]);
                    continue;
                }

                // FastAPI reports errors as {"detail": "..."} — surface it when present.
                $detail = $response->json('detail');

                throw new AIServiceException(
                    is_string($detail) && $detail !== ''
                        ? $detail
                        : "{$this->serviceLabel} request failed with status " . $response->status() . '.',
                    $response->status() >= 400 && $response->status() < 600 ? $response->status() : 502
                );
            } catch (ConnectionException $e) {
                $lastException = $e;
                Log::warning("{$this->serviceLabel} connection error, retrying", [
                    'url' => $url,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt >= $this->retries) {
                    throw new AIServiceUnavailableException("Unable to connect to {$this->serviceLabel}.");
                }
            } catch (RequestException $e) {
                if ($e->response?->status() === 408 || str_contains(strtolower($e->getMessage()), 'timeout')) {
                    throw new AIServiceTimeoutException("{$this->serviceLabel} request timed out.");
                }

                throw new AIServiceException($e->getMessage(), $e->response?->status() ?? 502, $e);
            } catch (AIServiceInvalidResponseException $e) {
                throw $e;
            } catch (AIServiceException $e) {
                throw $e;
            }
        }

        throw new AIServiceUnavailableException(
            $lastException?->getMessage() ?? "{$this->serviceLabel} is unavailable after multiple attempts."
        );
    }
}
