<?php

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*'));

        $exceptions->render(function (ValidationException $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'Les données fournies sont invalides.',
                ['errors' => $e->errors()],
                $e->status,
            );
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error('Non authentifié.', null, 401);
        });

        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error('Ressource introuvable.', null, 404);
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error('Ressource introuvable.', null, 404);
        });

        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error('Trop de tentatives. Merci de réessayer plus tard.', null, 429);
        });
    })->create();