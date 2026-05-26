<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InterviewController;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Admin / Dashboard Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/', [InterviewController::class, 'index'])->name('interviews.index');
    Route::post('/sessions', [InterviewController::class, 'storeSession'])->name('sessions.store');
    Route::post('/sessions/{session}/answers', [InterviewController::class, 'submitAnswer'])->name('sessions.answers.store');
    Route::get('/sessions/{session}/results', [InterviewController::class, 'results'])->name('sessions.results');
});
