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

// "Was this helpful?" on a single answer. Cheap (no OpenAI call), so the
// limit is looser than /send — but still capped, since it writes to the DB.
Route::post('/assistant/feedback', [ChatController::class, 'feedback'])
    ->middleware('throttle:60,1')
    ->name('assistant.feedback');
