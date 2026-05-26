<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Candidate;
use App\Models\Question;
use App\Models\TestSession;
use App\Services\FeedbackService;
use App\Services\ScoringService;
use App\Services\TranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InterviewController extends Controller
{
    public function index()
    {
        return view('welcome', [
            'questions' => Question::active()
                ->orderBy('order_index')
                ->get(['id', 'order_index', 'japanese_text', 'indonesian_translation']),
        ]);
    }

    public function storeSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $questionCount = Question::active()->count();

        if ($questionCount !== 10) {
            throw ValidationException::withMessages([
                'questions' => 'The interview requires exactly 10 active questions.',
            ]);
        }

        $session = DB::transaction(function () use ($validated): TestSession {
            $candidate = Candidate::create([
                'name'                 => $validated['username'],
                'email'                => null,
                'candidate_identifier' => null,
            ]);

            return TestSession::create([
                'candidate_id'           => $candidate->id,
                'current_question_index' => 1,
                'status'                 => 'in_progress',
                'started_at'             => now(),
            ]);
        });

        return response()->json([
            'session'   => $this->sessionPayload($session->load('candidate')),
            'questions' => $this->questionPayload(),
        ], 201);
    }

    public function submitAnswer(
        Request $request,
        TestSession $session,
        TranscriptionService $transcriber,
        ScoringService $scorer,
        FeedbackService $feedbacker,
    ): JsonResponse {
        if ($session->status === 'completed') {
            throw ValidationException::withMessages([
                'session' => 'This interview session is already completed.',
            ]);
        }

        $validated = $request->validate([
            'question_id'      => ['required', 'integer', 'exists:questions,id'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:1800'],
            'audio'            => [
                'required',
                'file',
                'max:25600',
                'mimetypes:audio/webm,audio/wav,audio/x-wav,audio/mpeg,audio/mp4,audio/mp4a-latm,audio/ogg,video/webm,video/mp4',
            ],
        ]);

        $question = Question::active()
            ->whereKey($validated['question_id'])
            ->firstOrFail();

        if ((int) $question->order_index !== (int) $session->current_question_index) {
            throw ValidationException::withMessages([
                'question_id' => 'Answers must be submitted in question order.',
            ]);
        }

        $audio     = $request->file('audio');
        $audioPath = $audio->store("interviews/{$session->public_id}");

        $answer = Answer::updateOrCreate(
            [
                'test_session_id' => $session->id,
                'question_id'     => $question->id,
            ],
            [
                'status'         => 'processing',
                'audio_path'     => $audioPath,
                'audio_mime_type' => $audio->getClientMimeType(),
                'duration_seconds' => $validated['duration_seconds'] ?? null,
                'error_message'  => null,
            ],
        );

        // Step 1: Transcribe via Whisper API
        try {
            $transcription = $transcriber->transcribe($audio);
        } catch (RuntimeException $exception) {
            report($exception);

            $answer->update([
                'status'        => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Transkripsi gagal. Coba rekam ulang dengan suara lebih jelas.',
                'detail'  => config('app.debug') ? $exception->getMessage() : null,
            ], 502);
        }

        $transcript      = $transcription['text'];
        $durationSeconds = $validated['duration_seconds'] ?? null;

        // Step 2: Rule-based scoring (no API call)
        $evaluation = $scorer->evaluate($question, $transcript, $durationSeconds);

        // Step 3: Template-based feedback (no API call)
        $feedback = $feedbacker->generate($evaluation, $transcript);

        // Step 4: Persist result
        $answer->update([
            'status'             => 'completed',
            'transcribed_text'   => $transcript,
            'score'              => $evaluation['score'],
            'pronunciation_score' => $evaluation['pronunciation_score'],
            'fluency_score'      => $evaluation['fluency_score'],
            'grammar_score'      => $evaluation['grammar_score'],
            'feedback'           => $feedback,
            'raw_transcription'  => $transcription['raw'],
            'raw_evaluation'     => $evaluation['details'],
            'answered_at'        => now(),
        ]);

        $completed = $question->order_index >= 10;
        $session->update([
            'current_question_index' => $completed ? 10 : $question->order_index + 1,
            'status'                 => $completed ? 'completed' : 'in_progress',
            'completed_at'           => $completed ? now() : null,
            'total_score'            => $completed ? $this->calculateTotalScore($session) : null,
        ]);

        $nextQuestion = $completed
            ? null
            : Question::active()->where('order_index', $question->order_index + 1)->first();

        return response()->json([
            'answer'        => $this->answerPayload($answer->fresh('question')),
            'session'       => $this->sessionPayload($session->fresh('candidate')),
            'next_question' => $nextQuestion ? $this->questionItemPayload($nextQuestion) : null,
            'completed'     => $completed,
        ]);
    }

    public function results(TestSession $session): JsonResponse
    {
        return response()->json([
            'session' => $this->sessionPayload($session->load('candidate')),
            'answers' => $session->answers()
                ->with('question')
                ->orderBy(
                    Question::select('order_index')
                        ->whereColumn('questions.id', 'answers.question_id'),
                )
                ->get()
                ->map(fn (Answer $answer): array => $this->answerPayload($answer))
                ->values(),
        ]);
    }

    protected function calculateTotalScore(TestSession $session): float
    {
        return round((float) $session->answers()->where('status', 'completed')->avg('score'), 2);
    }

    protected function questionPayload()
    {
        return Question::active()
            ->orderBy('order_index')
            ->get()
            ->map(fn (Question $question): array => $this->questionItemPayload($question))
            ->values();
    }

    protected function questionItemPayload(Question $question): array
    {
        return [
            'id'                     => $question->id,
            'order_index'            => $question->order_index,
            'japanese_text'          => $question->japanese_text,
            'indonesian_translation' => $question->indonesian_translation,
        ];
    }

    protected function answerPayload(Answer $answer): array
    {
        return [
            'id'                 => $answer->id,
            'question_id'        => $answer->question_id,
            'number'             => $answer->question?->order_index,
            'question'           => $answer->question?->japanese_text,
            'duration_seconds'   => $answer->duration_seconds,
            'transcript'         => $answer->transcribed_text,
            'score'              => $answer->score !== null ? (float) $answer->score : null,
            'pronunciation_score' => $answer->pronunciation_score,
            'fluency_score'      => $answer->fluency_score,
            'grammar_score'      => $answer->grammar_score,
            'feedback'           => $answer->feedback,
            'status'             => $answer->status,
        ];
    }

    protected function sessionPayload(TestSession $session): array
    {
        return [
            'public_id'              => $session->public_id,
            'status'                 => $session->status,
            'current_question_index' => $session->current_question_index,
            'total_score'            => $session->total_score !== null ? (float) $session->total_score : null,
            'candidate'              => [
                'name'                 => $session->candidate?->name,
                'email'                => $session->candidate?->email,
                'candidate_identifier' => $session->candidate?->candidate_identifier,
            ],
        ];
    }
}
