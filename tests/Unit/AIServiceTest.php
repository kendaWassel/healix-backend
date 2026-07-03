<?php

namespace Tests\Unit;

use App\Services\AI\AIService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIServiceTest extends TestCase
{
    public function test_it_sends_only_audio_path_to_fastapi(): void
    {
        // FastAPI's SpeechToTextRequest schema rejects a request that sets both
        // audio_path and audio_url (see app/schemas/speech.py). Laravel and
        // FastAPI share a filesystem, so only audio_path is sent.
        Http::fake([
            'http://127.0.0.1:8001/api/speech-to-text' => Http::response([
                'success' => true,
                'text' => 'Fever and cough',
            ], 200),
        ]);

        config(['services.ai.url' => 'http://127.0.0.1:8001']);

        $service = new AIService(new \App\Services\AI\FastApiClient());

        $text = $service->speechToText('/tmp/audio.m4a');

        $this->assertSame('Fever and cough', $text);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://127.0.0.1:8001/api/speech-to-text'
                && $request['audio_path'] === '/tmp/audio.m4a'
                && ! array_key_exists('audio_url', $request->data());
        });
    }
}
