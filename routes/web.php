<?php

use App\Http\Controllers\InterviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [InterviewController::class, 'index'])->name('interviews.index');
Route::post('/sessions', [InterviewController::class, 'storeSession'])->name('sessions.store');
Route::post('/sessions/{session}/answers', [InterviewController::class, 'submitAnswer'])->name('sessions.answers.store');
Route::get('/sessions/{session}/results', [InterviewController::class, 'results'])->name('sessions.results');
