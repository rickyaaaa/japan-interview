<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('candidate_identifier')->nullable();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('order_index')->unique();
            $table->string('japanese_text');
            $table->string('indonesian_translation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('test_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('current_question_index')->default(1);
            $table->string('status')->default('in_progress');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('processing');
            $table->string('audio_path')->nullable();
            $table->string('audio_mime_type')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->text('transcribed_text')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->unsignedTinyInteger('pronunciation_score')->nullable();
            $table->unsignedTinyInteger('fluency_score')->nullable();
            $table->unsignedTinyInteger('grammar_score')->nullable();
            $table->text('feedback')->nullable();
            $table->json('raw_transcription')->nullable();
            $table->json('raw_evaluation')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['test_session_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
        Schema::dropIfExists('test_sessions');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('candidates');
    }
};
