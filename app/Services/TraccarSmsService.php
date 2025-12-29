<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TraccarSmsService
{
    protected $url;
    protected $apiKey;

    public function __construct()
    {
        $this->url = config('services.traccar_sms.url');
        $this->apiKey = config('services.traccar_sms.api_key');
    }

    public function sendSms(string $to, string $message): bool
    {
        Log::info('Sending SMS via Traccar', ['to' => $to, 'message' => $message]);

        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($this->url, [
            'to' => $to,
            'message' => $message,
        ]);

        if ($response->successful()) {
            Log::info('SMS sent successfully.', ['response' => $response->json()]);
            return true;
        } else {
            Log::error('SMS sending failed.', ['status' => $response->status(), 'body' => $response->body()]);
            return false;
        }
    }
}
