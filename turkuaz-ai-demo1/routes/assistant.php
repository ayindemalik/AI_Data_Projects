<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Customer\AssistantController;
use App\Http\Controllers\Customer\ProductController;
use App\Http\Controllers\Customer\SearchController;
use Illuminate\Support\Facades\Route;

// Public assistant page — accessible to guests too (Guest role has use-assistant).
Route::get('/assistant', [AssistantController::class, 'index'])->name('assistant.index');

// Spec sheet behind each product card in a chat answer. Bound on slug rather
// than id so the address bar reads like a catalog page, not a database row.
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');

// Catalog search — the deterministic way through the same data, offered to
// anyone the assistant did not satisfy.
Route::get('/search', [SearchController::class, 'index'])->name('search.index');

// Both fire on every keystroke (debounced), so they are throttled per IP and
// return JSON only.
Route::get('/search/results', [SearchController::class, 'query'])
    ->middleware('throttle:120,1')->name('search.results');
Route::get('/search/suggest', [SearchController::class, 'suggest'])
    ->middleware('throttle:120,1')->name('search.suggest');

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
