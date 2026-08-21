<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Stateless JSON routes for the Cera Cep mobile app. Registered
        // separately from web.php because the api group has no session and no
        // CSRF check — a phone has neither.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            // Lets the mobile app choose its language per request (?lang=en).
            'api.locale' => \App\Http\Middleware\ApiLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The assistant endpoints are fetch()/JSON endpoints even though they
        // live under /assistant rather than /api, so errors there must come
        // back as JSON too — otherwise a validation failure returns a redirect
        // and the chat's res.json() call chokes on HTML.
        //
        // expectsJson() is repeated here on purpose: this callback REPLACES
        // Laravel's default test rather than adding to it, and dropping it
        // broke every other fetch endpoint in the admin panel — the Image
        // Paths table showed "Network error" for what was really a 422.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->is('assistant/*')
                || $request->expectsJson(),
        );
    })->create();
