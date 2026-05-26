<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_session_id',
        'question_id',
        'status',
        'audio_path',
        'audio_mime_type',
        'duration_seconds',
        'transcribed_text',
        'score',
        'pronunciation_score',
        'fluency_score',
        'grammar_score',
        'feedback',
        'raw_transcription',
        'raw_evaluation',
        'error_message',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'raw_transcription' => 'array',
            'raw_evaluation' => 'array',
            'answered_at' => 'datetime',
        ];
    }

    public function testSession(): BelongsTo
    {
        return $this->belongsTo(TestSession::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
