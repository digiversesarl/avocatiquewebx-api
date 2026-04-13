<?php

use App\Http\Middleware\CheckPermission;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        |------------------------------------------------------------------
        | API : Ne jamais rediriger vers une route [login].
        | Les requêtes non authentifiées reçoivent directement un 401 JSON.
        |------------------------------------------------------------------
        */
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                abort(response()->json(['message' => 'Non authentifié.'], 401));
            }
        });

        /*
        |------------------------------------------------------------------
        | Middleware aliases
        |------------------------------------------------------------------
        */
        $middleware->alias([
            'permission' => CheckPermission::class,
        ]);

        /*
        |------------------------------------------------------------------
        | CSRF : exclure les routes API (token-based via Sanctum)
        | Laravel 13 utilise PreventRequestForgery (anciennement ValidateCsrfToken)
        |------------------------------------------------------------------
        */
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
        |------------------------------------------------------------------
        | Retourner du JSON pour toutes les erreurs sur /api/*
        |------------------------------------------------------------------
        */

        // 401 Unauthenticated → JSON
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(
                    ['message' => 'Non authentifié.'],
                    401
                );
            }
        });

        // 422 Validation → JSON structuré
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // 404 Not Found → JSON
        $exceptions->render(
            function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json(['message' => 'Ressource introuvable.'], 404);
                }
            }
        );

        // 403 Forbidden → JSON
        $exceptions->render(
            function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json(['message' => 'Accès refusé.'], 403);
                }
            }
        );
    })
    ->create();
