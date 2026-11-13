<?php

namespace App\Integrations\Payments;

use App\Models\Pago;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoAdapter implements ProveedorPago
{
    public function nombre(): string
    {
        return 'mercadopago';
    }

    public function configuracion(): array
    {
        $cfg = config('integrations.mercadopago');
        abort_unless($cfg['access_token'] && $cfg['webhook_secret'] && ctype_digit((string) $cfg['collector_id']), 503,
            'Configura las credenciales de Mercado Pago en el servidor antes de iniciar el checkout');
        abort_unless(filter_var($cfg['public_url'], FILTER_VALIDATE_URL) && parse_url($cfg['public_url'], PHP_URL_SCHEME) === 'https'
            && ! parse_url($cfg['public_url'], PHP_URL_QUERY) && ! parse_url($cfg['public_url'], PHP_URL_FRAGMENT), 503,
            'Mercado Pago requiere MP_PUBLIC_URL con la dirección HTTPS pública de esta aplicación');

        return $cfg;
    }

    public function request(string $method, string $path, array $data = [], array $headers = []): array
    {
        $cfg = $this->configuracion();
        try {
            $response = Http::withToken($cfg['access_token'])->withHeaders($headers)->acceptJson()->connectTimeout(3)->timeout(10)
                ->withoutRedirecting()->send($method, 'https://api.mercadopago.com'.$path, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        } catch (ConnectionException $e) {
            abort(503, 'Mercado Pago no respondió. Consulta el estado antes de volver a intentar');
        }
        if (! $response->successful() || ! is_array($response->json())) {
            $body = $response->json();
            Log::channel('structured')->warning('mercadopago_http_error', [
                'path' => $path,
                'status' => $response->status(),
                'message' => is_array($body) ? ($body['message'] ?? null) : null,
                'cause' => is_array($body) ? ($body['cause'] ?? null) : null,
            ]);
            if ($response->clientError()) {
                $detalle = is_array($body) && is_string($body['message'] ?? null) ? $body['message'] : 'datos rechazados por el proveedor';
                abort(422, 'Mercado Pago rechazó la solicitud: '.$detalle);
            }
            abort(503, 'No se pudo completar la operación con Mercado Pago');
        }

        return $response->json();
    }

    public function crearPago(Pago $pago, array $datos, string $idempotencyKey): array
    {
        $cfg = $this->configuracion();
        $base = rtrim($cfg['public_url'], '/');
        $payer = ['email' => $datos['payer']['email']];
        if (! empty($datos['payer']['identification']['type']) && ! empty($datos['payer']['identification']['number'])) {
            $payer['identification'] = [
                'type' => (string) $datos['payer']['identification']['type'],
                'number' => (string) $datos['payer']['identification']['number'],
            ];
        }
        $payload = [
            'transaction_amount' => (float) $pago->valor,
            'token' => (string) $datos['token'],
            'description' => 'Matrícula académica #'.$pago->matricula_id,
            'installments' => (int) $datos['installments'],
            'payment_method_id' => (string) $datos['payment_method_id'],
            'external_reference' => $pago->mp_external_reference,
            'notification_url' => $base.'/api/v1/webhooks/mercadopago?source_news=webhooks',
            'payer' => $payer,
        ];
        if (isset($datos['issuer_id']) && $datos['issuer_id'] !== '' && $datos['issuer_id'] !== null) {
            $payload['issuer_id'] = (string) $datos['issuer_id'];
        }

        return $this->request('POST', '/v1/payments', $payload, ['X-Idempotency-Key' => $idempotencyKey]);
    }

    public function procesar(Pago $pago, string $operacion, string $resultado): ResultadoPago
    {
        abort(409, 'Este pago se procesa mediante Checkout API y se confirma con Mercado Pago');
    }

    public function reembolsar(Pago $pago, string $operacion): ResultadoPago
    {
        abort(409, 'Gestiona el reembolso en Mercado Pago y después sincroniza el estado de la orden');
    }
}
