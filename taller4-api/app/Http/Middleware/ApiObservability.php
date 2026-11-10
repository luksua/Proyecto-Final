<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ApiObservability
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }
        $start = hrtime(true);
        $id = $request->header('X-Correlation-ID');
        $id = is_string($id) && Str::isUuid($id) ? $id : (string) Str::uuid();
        $request->attributes->set('correlation_id', $id);
        try {
            abort_if(app()->isProduction() && config('security.require_https') && ! $request->isSecure(), 426, 'Se requiere HTTPS');
            if ($request->isJson() && $request->getContent() !== '') {
                try {
                    json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    abort(400, 'El cuerpo JSON no es válido');
                }
            }
            $response = $next($request);
        } catch (\Throwable $e) {
            app(ExceptionHandler::class)->report($e);
            $response = app(ExceptionHandler::class)->render($request, $e);
        }
        $ms = round((hrtime(true) - $start) / 1_000_000, 2);
        $response->headers->set('X-Correlation-ID', $id);
        $response->headers->set('X-Response-Time-Ms', (string) $ms);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        $route = $request->route()?->uri() ?? 'api/unmatched';
        $context = ['route' => $route, 'method' => $request->method(), 'status' => $response->getStatusCode(),
            'duration_ms' => $ms, 'correlation_id' => $id];
        Log::channel('structured')->info('http_request', $context);
        try {
            $bucket = ['day' => now()->toDateString(), 'route' => substr($route, 0, 160), 'method' => $request->method(), 'status' => $response->getStatusCode()];
            DB::table('api_metrics')->insertOrIgnore([...$bucket, 'requests' => 0, 'total_ms' => 0, 'max_ms' => 0]);
            DB::table('api_metrics')->where($bucket)->update(['requests' => DB::raw('requests + 1'),
                'total_ms' => DB::raw('total_ms + '.number_format($ms, 2, '.', '')),
                'max_ms' => DB::raw('CASE WHEN max_ms < '.number_format($ms, 2, '.', '').' THEN '.number_format($ms, 2, '.', '').' ELSE max_ms END')]);
        } catch (\Throwable $e) {
            // Monitoring failure must not replace a successful academic response or leak a DSN.
            Log::channel('structured')->warning('metrics_unavailable', ['correlation_id' => $id]);
        }

        return $response;
    }
}
