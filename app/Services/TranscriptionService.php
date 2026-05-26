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
        try {
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
                return [
                    'text' => '',
                    'raw'  => $response->json() ?? ['error' => 'Whisper API Request failed'],
                ];
            }

            $payload = $response->json();
            $text    = trim((string) Arr::get($payload, 'text', ''));

            return [
                'text' => $text,
                'raw'  => $payload,
            ];
        } catch (\Throwable $exception) {
            // Safely handle network errors or other exceptions
            // by returning an empty string, so ScoringService triggers zeroScoreResult('empty')
            return [
                'text' => '',
                'raw'  => ['error' => $exception->getMessage()],
            ];
        }
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
