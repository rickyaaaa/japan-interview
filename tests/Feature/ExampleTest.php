<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Services\OpenAiInterviewEvaluator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Japanese Interview');
    }

    public function test_candidate_can_create_session_and_submit_audio_answer(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->app->instance(OpenAiInterviewEvaluator::class, new class extends OpenAiInterviewEvaluator
        {
            public function transcribe(UploadedFile $audio): array
            {
                return [
                    'text' => 'はい、タバコは吸いません。',
                    'raw' => ['text' => 'はい、タバコは吸いません。'],
                ];
            }

            public function evaluate(Question $question, string $transcript): array
            {
                return [
                    'score' => 84,
                    'pronunciation_score' => 80,
                    'fluency_score' => 82,
                    'grammar_score' => 86,
                    'feedback' => 'Jawaban jelas dan relevan.',
                    'raw' => ['score' => 84],
                ];
            }
        });

        $sessionResponse = $this->postJson('/sessions', [
            'name' => 'Ayu Tanaka',
            'email' => 'ayu@example.com',
            'candidate_identifier' => 'JP-INT-010',
        ]);

        $sessionResponse->assertCreated();

        $sessionId = $sessionResponse->json('session.public_id');
        $questionId = $sessionResponse->json('questions.0.id');

        $answerResponse = $this->post(
            "/sessions/{$sessionId}/answers",
            [
                'question_id' => $questionId,
                'duration_seconds' => 12,
                'audio' => UploadedFile::fake()->create('answer.webm', 128, 'audio/webm'),
            ],
            ['Accept' => 'application/json'],
        );

        $answerResponse->assertOk();
        $answerResponse->assertJsonPath('answer.score', 84);
        $answerResponse->assertJsonPath('answer.transcript', 'はい、タバコは吸いません。');
        $answerResponse->assertJsonPath('next_question.order_index', 2);
    }
}
