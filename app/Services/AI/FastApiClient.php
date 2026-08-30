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

    /**
     * Human-readable name used in LOGS ONLY. Deliberately not translated so
     * log lines stay greppable regardless of the caller's locale.
     */
    protected string $serviceLabel = 'Medical Assistant service';

    /** Translation key for the service name shown to end users. */
    protected string $serviceLabelKey = 'ai.service_label_medical_assistant';

    protected string $baseUrl;

    protected int $timeout;

    protected int $retries;

    /** Optional shared secret the FastAPI side gates its endpoints with. */
    protected ?string $apiKey;

    /** Header name used to transmit the shared secret. Matches the FastAPI's expected key. */
    protected string $apiKeyHeader = 'X-API-KEY';

    /**
     * Redact a request payload before it reaches the log sink. No-op by
     * default — every existing integration (Lab, DDI, MedicalAssistant)
     * keeps logging its full payload unchanged. Override
     * in a subclass whose payload carries raw patient/clinical free text
     * (see HealixAiClient) so that text never lands in storage/logs/*.log,
     * which — unlike the FastAPI side's own audit log (see that project's
     * CLAUDE.md > Audit logs and patient data) — has no dedicated handler,
     * retention policy, or access restriction of its own; it is whatever
     * general-purpose channel LOG_CHANNEL points at.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function redactPayloadForLogging(array $payload): array
    {
        return $payload;
    }

    /**
     * Redact a response body before it reaches the log sink. Same
     * reasoning as redactPayloadForLogging() above, the response-side
     * counterpart — a Healix /chat response body carries the patient-
     * facing reply text and full doctor/patient report bodies.
     *
     * @param  array<string, mixed>|string|null  $body
     * @return array<string, mixed>|string|null
     */
    protected function redactResponseBodyForLogging($body)
    {
        return $body;
    }

    public function __construct()
    {
        $this->baseUrl = rtrim(config("services.{$this->configKey}.url"), '/');
        $this->timeout = (int) config("services.{$this->configKey}.timeout", 60);
        $this->retries = (int) config("services.{$this->configKey}.retries", 3);
        $apiKey = config("services.{$this->configKey}.api_key");
        $this->apiKey = is_string($apiKey) && $apiKey !== '' ? $apiKey : null;
    }

    /**
     * Localized service name for user-facing exception messages.
     */
    protected function serviceName(): string
    {
        return __($this->serviceLabelKey);
    }

    /**
     * Build a pending HTTP request with the common headers: Accept: application/json
     * and, when configured, the X-API-KEY shared-secret header. Every outgoing call
     * (plain JSON, multipart, binary download) routes through this so adding a new
     * transport-level header is a one-line change.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function buildRequest()
    {
        $pending = Http::timeout($this->timeout)->acceptJson();

        if ($this->apiKey !== null) {
            $pending = $pending->withHeaders([$this->apiKeyHeader => $this->apiKey]);
        }

        return $pending;
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
                $response = $this->buildRequest()
                    ->attach($fileField, $fileContents, $fileName)
                    ->post($url, $fields);

                Log::info("{$this->serviceLabel} multipart response", [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (! is_array($data)) {
                        throw new AIServiceInvalidResponseException(__('ai.service_invalid_json', ['service' => $this->serviceName()]));
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
                        : __('ai.service_request_failed', ['service' => $this->serviceName(), 'status' => $response->status()]),
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
                    throw new AIServiceUnavailableException(__('ai.service_connection_failed', ['service' => $this->serviceName()]));
                }
            }
        }

        throw new AIServiceUnavailableException(
            $lastException?->getMessage() ?? __('ai.service_unavailable_named', ['service' => $this->serviceName()])
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
            $response = $this->buildRequest()->get($url);
        } catch (ConnectionException $e) {
            throw new AIServiceUnavailableException(__('ai.service_connection_failed', ['service' => $this->serviceName()]));
        }

        if (! $response->successful()) {
            $detail = $response->json('detail');

            throw new AIServiceException(
                is_string($detail) && $detail !== ''
                    ? $detail
                    : __('ai.service_download_failed', ['service' => $this->serviceName(), 'status' => $response->status()]),
                $response->status() >= 400 && $response->status() < 600 ? $response->status() : 502
            );
        }

        return $response->body();
    }

    /**
     * Send a JSON POST and return the raw response body bytes, not parsed
     * JSON — for an endpoint whose success response isn't JSON at all
     * (e.g. Healix's POST /speech/synthesize, which returns audio/mpeg
     * bytes directly). Same retry/timeout/logging transport as post()
     * and downloadBinary() above; downloadBinary() itself can't be reused
     * here since it's GET-only with no request body.
     *
     * @throws AIServiceException
     */
    public function postBinary(string $endpoint, array $payload): string
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        Log::info("{$this->serviceLabel} binary POST request", ['url' => $url]);

        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retries) {
            $attempt++;

            try {
                $response = $this->buildRequest()->asJson()->post($url, $payload);

                Log::info("{$this->serviceLabel} binary POST response", [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                if ($response->successful()) {
                    return $response->body();
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
                        : __('ai.service_request_failed', ['service' => $this->serviceName(), 'status' => $response->status()]),
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
                    throw new AIServiceUnavailableException(__('ai.service_connection_failed', ['service' => $this->serviceName()]));
                }
            } catch (AIServiceException $e) {
                throw $e;
            }
        }

        throw new AIServiceUnavailableException(
            $lastException?->getMessage() ?? __('ai.service_unavailable_named', ['service' => $this->serviceName()])
        );
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
            'payload' => $this->redactPayloadForLogging($payload),
        ]);

        $attempt = 0;
        $lastException = null;
        $startedAt = microtime(true);

        while ($attempt < $this->retries) {
            $attempt++;

            try {
                $pending = $this->buildRequest();

                $response = $method === 'get'
                    ? $pending->get($url, $payload)
                    : $pending->asJson()->post($url, $payload);

                Log::info("{$this->serviceLabel} response", [
                    'url' => $url,
                    'status' => $response->status(),
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'body' => $this->redactResponseBodyForLogging($response->json() ?? $response->body()),
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (! is_array($data)) {
                        throw new AIServiceInvalidResponseException(__('ai.service_invalid_json', ['service' => $this->serviceName()]));
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
                        : __('ai.service_request_failed', ['service' => $this->serviceName(), 'status' => $response->status()]),
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
                    throw new AIServiceUnavailableException(__('ai.service_connection_failed', ['service' => $this->serviceName()]));
                }
            } catch (RequestException $e) {
                if ($e->response?->status() === 408 || str_contains(strtolower($e->getMessage()), 'timeout')) {
                    throw new AIServiceTimeoutException(__('ai.service_timeout'));
                }

                throw new AIServiceException($e->getMessage(), $e->response?->status() ?? 502, $e);
            } catch (AIServiceInvalidResponseException $e) {
                throw $e;
            } catch (AIServiceException $e) {
                throw $e;
            }
        }

        throw new AIServiceUnavailableException(
            $lastException?->getMessage() ?? __('ai.service_unavailable_named', ['service' => $this->serviceName()])
        );
    }
}
