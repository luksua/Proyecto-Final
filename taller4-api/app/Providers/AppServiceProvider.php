<?php

namespace App\Providers;

use App\Integrations\Identity\IdentidadSimulada;
use App\Integrations\Identity\OpenIdConnectAdapter;
use App\Integrations\Identity\ProveedorIdentidad;
use App\Integrations\Payments\BancoSoapSimulado;
use App\Integrations\Payments\ProveedorPago;
use App\Integrations\Payments\ProveedorSimulado;
use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Matricula;
use App\Models\Pago;
use App\Observers\AuditObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ProveedorIdentidad::class, function () {
            return match (config('integrations.identity.driver')) {
                'simulado' => new IdentidadSimulada,
                'oidc' => new OpenIdConnectAdapter,
                default => throw new ServiceUnavailableHttpException(null, 'No hay proveedor de identidad configurado'),
            };
        });
        $this->app->bind(ProveedorPago::class, function () {
            return match (config('integrations.payments.driver')) {
                'simulado' => new ProveedorSimulado,
                'soap_simulado' => new BancoSoapSimulado,
                default => throw new ServiceUnavailableHttpException(null, 'No hay proveedor de pagos configurado'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TrustProxies::at(config('security.trusted_proxies'));
        foreach ([Estudiante::class, Docente::class, Curso::class, Matricula::class, Pago::class] as $model) {
            $model::observe(AuditObserver::class);
        }
        RateLimiter::for('login', fn ($request) => Limit::perMinute(config('security.auth_per_minute'))->by($request->ip()));
        RateLimiter::for('api', fn ($request) => Limit::perMinute(config('security.api_per_minute'))->by($request->user()?->id ?? $request->ip()));
        // Un solo recurso se devuelve sin envoltorio {"data": ...}, así el JSON trae "id" directo
        JsonResource::withoutWrapping();
    }
}
