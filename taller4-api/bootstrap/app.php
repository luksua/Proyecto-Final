<?php

use App\Http\Middleware\ApiObservability;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\JwtAuth;
use App\Support\ApiError;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ApiObservability::class);
        $middleware->alias([
            'jwt' => JwtAuth::class,
            'role' => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'code', 'codeVerifier', 'accessToken', 'client_secret']);
        $exceptions->report(function (Throwable $e) {
            if (request()->is('api/*')) {
                Log::channel('structured')->error('api_exception', [
                    'type' => get_class($e), 'correlation_id' => request()->attributes->get('correlation_id'),
                ]);

                return false;
            }
        });
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*') && $e->getCode() === '23000') {
                return ApiError::response(409, 'La operación entra en conflicto con un registro existente', $request);
            }
        });
        // Las rutas /api/* siempre responden JSON, aunque no envíen "Accept: application/json"
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        // 422: errores de validación
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiError::response(422, 'Error de validación', $request, $e->errors());
            }
        });

        // 404: el registro no existe (por ejemplo /cursos/9999)
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiError::response(404, 'Recurso no encontrado', $request);
            }
        });

        // 401, 403, 404 (ruta), 405, 409: los que lanzamos con abort()
        $exceptions->render(function (HttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();
            $message = $status === 404
                ? 'Recurso no encontrado'
                : ($e->getMessage() ?: (Response::$statusTexts[$status] ?? 'Error'));

            return ApiError::response($status, $status >= 500 && $status !== 503 ? 'Error interno del servidor' : $message, $request, null, $e->getHeaders());
        });

        // API errors never expose internal details, including in local debug mode.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiError::response(500, 'Error interno del servidor', $request);
            }
        });
    })->create();
