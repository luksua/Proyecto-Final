<?php

namespace App\Services;

use App\Integrations\Payments\MercadoPagoAdapter;
use App\Models\Matricula;
use App\Models\Pago;
use App\Models\PagoTransaccion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MercadoPagoService
{
    public function __construct(private MercadoPagoAdapter $mp) {}

    public function checkout(Pago $pago, array $datos): array
    {
        $this->mp->configuracion();

        // Distributed cache lock serializes the HTTP call without an open SQL transaction.
        return Cache::lock('mp:checkout:'.$pago->id, 30)->block(2, function () use ($pago, $datos) {
            $pago = DB::transaction(function () use ($pago) {
                $matricula = Matricula::lockForUpdate()->findOrFail($pago->matricula_id);
                $pago = Pago::lockForUpdate()->findOrFail($pago->id);
                abort_unless($pago->proveedor === 'mercadopago', 409, 'Esta orden pertenece a otro proveedor; crea una matrícula nueva para probar Mercado Pago');
                abort_unless($matricula->estado === 'ACTIVA' && $pago->estado === 'PENDIENTE' && ! $pago->requiere_revision, 409, 'La orden no permite iniciar otro checkout');
                abort_unless($pago->moneda === 'COP', 409, 'Esta integración está configurada para Colombia y COP');
                $receipts = DB::table('mercado_pago_receipts')->where('pago_id', $pago->id);
                // An ambiguous timeout without a recorded receipt must not blindly create another charge.
                abort_if($pago->mp_checkout_requested_at && ! (clone $receipts)->exists(), 409,
                    'El pago anterior quedó en estado incierto. Sincroniza la orden o solicita revisión administrativa');
                abort_if((clone $receipts)->whereIn('status', ['pending', 'in_process', 'authorized'])->exists(), 409,
                    'Ya existe un pago en proceso para esta orden. Consulta el estado antes de volver a intentar');

                $pago->forceFill([
                    'mp_external_reference' => $pago->mp_external_reference ?: (string) Str::uuid(),
                    'mp_checkout_requested_at' => now(),
                ])->save();

                return $pago;
            }, 3);

            $idempotencyKey = hash('sha256', $pago->id.':'.$pago->mp_external_reference.':'.$datos['token']);
            try {
                $remote = $this->mp->crearPago($pago, $datos, $idempotencyKey);
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 422) {
                    $pago->forceFill(['mp_checkout_requested_at' => null])->save();
                }
                throw $e;
            }
            $remoteId = (string) ($remote['id'] ?? '');
            abort_unless($remoteId !== '' && ctype_digit($remoteId), 503, 'Mercado Pago no devolvió un identificador de pago válido');

            $this->aplicarPagoRemoto($remote, $remoteId);
            $pago->refresh();

            return [
                'pagoId' => $pago->id,
                'estado' => $pago->estado,
                'mpStatus' => $remote['status'] ?? null,
                'mpStatusDetail' => $remote['status_detail'] ?? null,
                'referencia' => $pago->referencia,
                'modoPrueba' => config('integrations.mercadopago.test_mode'),
            ];
        });
    }

    public function sincronizar(Pago $pago): Pago
    {
        abort_unless($pago->proveedor === 'mercadopago' && $pago->mp_external_reference, 409, 'La orden aún no tiene un checkout de Mercado Pago');
        $result = $this->mp->request('GET', '/v1/payments/search', ['external_reference' => $pago->mp_external_reference, 'sort' => 'date_created', 'criteria' => 'desc', 'limit' => 100]);
        abort_unless(isset($result['results']) && is_array($result['results']), 503, 'No se pudo consultar el resultado de Mercado Pago');
        foreach ($result['results'] as $payment) {
            if (isset($payment['id']) && ctype_digit((string) $payment['id'])) {
                $this->recibir((string) $payment['id']);
            }
        }

        return $pago->refresh();
    }

    public function recibir(string $id): void
    {
        // The webhook payload is never trusted as proof of payment.
        $remote = $this->mp->request('GET', '/v1/payments/'.$id);
        $this->aplicarPagoRemoto($remote, $id);
    }

    private function aplicarPagoRemoto(array $remote, string $id): void
    {
        $pago = Pago::where('mp_external_reference', $remote['external_reference'] ?? '')->where('proveedor', 'mercadopago')->first();
        if (! $pago) {
            return;
        } // A valid notification for another integration in the seller's account.
        $cfg = $this->mp->configuracion();
        $amount = $remote['transaction_amount'] ?? null;
        abort_unless((string) ($remote['id'] ?? '') === $id && is_numeric($amount)
            && number_format((float) $amount, 2, '.', '') === $pago->valor
            && ($remote['currency_id'] ?? '') === $pago->moneda
            && (string) ($remote['collector_id'] ?? '') === (string) $cfg['collector_id']
            && isset($remote['live_mode']) && $remote['live_mode'] === ! $cfg['test_mode'], 409, 'El pago recibido no coincide con la orden o el entorno configurado');
        abort_unless(in_array($remote['status'] ?? '', ['approved', 'pending', 'in_process', 'in_mediation', 'authorized', 'rejected', 'cancelled', 'refunded', 'charged_back'], true)
            && isset($remote['date_last_updated']), 409, 'Estado de Mercado Pago no reconocido');
        $updated = CarbonImmutable::parse($remote['date_last_updated'])->utc();
        DB::transaction(function () use ($pago, $remote, $id, $updated) {
            $m = Matricula::lockForUpdate()->findOrFail($pago->matricula_id);
            $actual = Pago::lockForUpdate()->findOrFail($pago->id);
            $previous = DB::table('mercado_pago_receipts')->where('provider_payment_id', $id)->first();
            if ($previous && CarbonImmutable::parse($previous->provider_updated_at)->greaterThanOrEqualTo($updated)) {
                return;
            }
            DB::table('mercado_pago_receipts')->updateOrInsert(['provider_payment_id' => $id], ['pago_id' => $actual->id,
                'status' => $remote['status'], 'provider_updated_at' => $updated->format('Y-m-d H:i:s.u'), 'created_at' => $previous?->created_at ?? now(), 'updated_at' => now()]);
            $approved = DB::table('mercado_pago_receipts')->where('pago_id', $actual->id)->where('status', 'approved')->get();
            $refunded = DB::table('mercado_pago_receipts')->where('pago_id', $actual->id)->where('status', 'refunded')->exists();
            $estado = $approved->isNotEmpty() ? 'APROBADO' : ($refunded ? 'REEMBOLSADO' : 'PENDIENTE');
            // Rejections remain pending locally: the student may retry with another card.
            $revision = $actual->requiere_revision || $approved->count() > 1 || ($estado === 'APROBADO' && $m->estado !== 'ACTIVA')
                || in_array($remote['status'], ['charged_back', 'in_mediation'], true) || (float) ($remote['transaction_amount_refunded'] ?? 0) > 0 && $remote['status'] !== 'refunded';
            if ($remote['status'] === 'charged_back' && $approved->isEmpty()) {
                $estado = 'PENDIENTE';
            }
            $actual->forceFill(['estado' => $estado, 'referencia' => 'MP-'.($approved->first()?->provider_payment_id ?? $id),
                'procesado_at' => now(), 'requiere_revision' => $revision])->save();
            $key = 'mp:'.hash('sha256', $id.':'.$remote['status'].':'.$updated->format('U.u'));
            $t = PagoTransaccion::firstOrCreate(['actor_id' => 0, 'idempotency_key' => $key], ['pago_id' => $actual->id,
                'solicitud_hash' => hash('sha256', $key), 'accion' => 'MERCADOPAGO', 'estado' => strtoupper($remote['status']), 'referencia' => 'MP-'.$id]);
            if ($t->wasRecentlyCreated) {
                NotificacionService::registrar($m->estudiante_id, 'pago.'.strtolower($estado), $actual->id, 'transaccion:'.$t->id);
            }
        }, 3);
    }
}
