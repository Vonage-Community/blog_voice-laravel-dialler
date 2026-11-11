<?php

use App\Http\Controllers\AnswerphoneAudioController;
use App\Http\Controllers\VonageWebhookController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::phone')->name('phone');
Route::livewire('/settings', 'pages::settings')->name('settings');

Route::get('/answerphone/greeting.mp3', AnswerphoneAudioController::class)->name('answerphone.audio');

Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::match(['get', 'post'], '/answer', [VonageWebhookController::class, 'answer'])->name('answer');
    Route::match(['get', 'post'], '/event', [VonageWebhookController::class, 'event'])->name('event');
});
