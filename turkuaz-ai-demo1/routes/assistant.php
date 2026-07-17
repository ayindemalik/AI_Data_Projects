<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Customer\AssistantController;
use Illuminate\Support\Facades\Route;

// Public assistant page — accessible to guests too (Guest role has use-assistant).
Route::get('/assistant', [AssistantController::class, 'index'])->name('assistant.index');

// Chat endpoint. Web middleware (session + CSRF) so it works for both guests
// and logged-in users; throttled to prevent abuse of the OpenAI budget.
Route::post('/assistant/send', [ChatController::class, 'send'])
    ->middleware('throttle:20,1')
    ->name('assistant.send');
