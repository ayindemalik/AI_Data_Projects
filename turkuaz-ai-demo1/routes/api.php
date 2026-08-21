<?php

use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API (v1) — used by the Cera Cep Flutter app
|--------------------------------------------------------------------------
|
| These routes exist because a phone is not a browser: there is no cookie
| session and no CSRF token, so the web assistant routes in assistant.php
| cannot be called from an app. Everything here is stateless — the app sends
| whatever context it needs (its language, its chat session token) on each
| request.
|
| The web app keeps using assistant.php. Nothing in this file changes it.
|
| Versioned as /api/v1 from day one so the app on someone's phone keeps
| working when the shape of an answer has to change; a v2 can live beside it.
|
| Rate limits are written on each route rather than in a shared limiter so
| that what protects each endpoint is visible right here. /chat is the tight
| one — every call costs an OpenAI request.
|
*/

Route::prefix('v1')->middleware('api.locale')->group(function () {

    // Called by the app's Settings screen to check a backend address before
    // saving it. Cheap on purpose: no OpenAI, one small count query.
    Route::get('/health', function () {
        return response()->json([
            'ok' => true,
            'app' => config('app.name'),
            'locale' => app()->getLocale(),
            'assistant_enabled' => (bool) Setting::get('assistant_enabled', true),
            'products' => Product::where('status', 'active')->count(),
        ]);
    })->middleware('throttle:60,1');

    // --- Catalog ---------------------------------------------------------
    Route::get('/filters', [CatalogController::class, 'filters'])
        ->middleware('throttle:60,1');

    Route::get('/products', [CatalogController::class, 'index'])
        ->middleware('throttle:120,1');

    // Fires while typing (debounced by the app), so its limit is the loosest.
    Route::get('/suggest', [CatalogController::class, 'suggest'])
        ->middleware('throttle:120,1');

    // Last: a bare {slug} would otherwise swallow /products/... siblings.
    Route::get('/products/{slug}', [CatalogController::class, 'show'])
        ->middleware('throttle:120,1');

    // --- Assistant -------------------------------------------------------
    Route::post('/chat', [ChatController::class, 'send'])
        ->middleware('throttle:20,1');

    Route::post('/chat/feedback', [ChatController::class, 'feedback'])
        ->middleware('throttle:60,1');

    Route::get('/chat/history', [ChatController::class, 'history'])
        ->middleware('throttle:60,1');
});
