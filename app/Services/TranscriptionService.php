<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * TranscriptionService
 *
 * Handles ONLY speech-to-text transcription via OpenAI Whisper API.
 * All evaluation logic has been moved to ScoringService and FeedbackService.
 */
class TranscriptionService
{
    public function transcribe(UploadedFile $audio): array
    {
        $response = $this->client()
            ->attach(
                'file',
                file_get_contents($audio->getRealPath()),
                $audio->getClientOriginalName() ?: 'answer.webm',
            )
            ->post($this->url('/audio/transcriptions'), [
                'model'           => config('openai.transcription_model'),
                'language'        => 'ja',
                'response_format' => 'json',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI Whisper transcription failed: ' . $response->body());
        }

        $payload = $response->json();
        $text    = trim((string) Arr::get($payload, 'text', ''));

        if ($text === '') {
            // Return empty transcript instead of throwing an error
            // so that ScoringService can give it a 0 score.
        }

        return [
            'text' => $text,
            'raw'  => $payload,
        ];
    }

    protected function client(): PendingRequest
    {
        $apiKey = config('openai.api_key');

        if (! $apiKey) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        return Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(config('openai.timeout'))
            ->retry(2, 500);
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('openai.base_url'), '/') . $path;
    }
}
