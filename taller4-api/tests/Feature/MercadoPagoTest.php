<?php

namespace Tests\Feature;

use App\Models\Pago;
use App\Models\PagoTransaccion;
use App\Services\MatriculaService;
use App\Services\MercadoPagoService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\ApiTestCase;

class MercadoPagoTest extends ApiTestCase
{
    private function orden(): array
    {
        config(['integrations.payments.driver' => 'mercadopago', 'integrations.mercadopago' => [
            'public_key' => 'TEST-public-key', 'access_token' => 'test-access-token-server-only',
            'webhook_secret' => 'test-webhook-secret', 'collector_id' => '1234',
            'test_mode' => true, 'public_url' => 'https://universidad.example.test',
        ]]);
        Http::preventStrayRequests();
        $user = $this->usuario('ESTUDIANTE');
        $curso = $this->curso();
        $m = app(MatriculaService::class)->crear(['estudianteId' => $user->estudiante_id, 'cursoId' => $curso->id, 'periodo' => '2026-2'], $user);
        $this->como($user);
        $id = $this->postJson('/api/v1/pagos', ['matriculaId' => $m->id])->assertCreated()->json('id');

        return [$user, Pago::findOrFail($id)];
    }

    private function brickPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'token' => 'card-token-prueba-1',
            'payment_method_id' => 'visa',
            'installments' => 1,
            'issuer_id' => '310',
            'transaction_amount' => 1, // Must be ignored in favor of server amount.
            'payer' => [
                'email' => 'comprador@example.test',
                'identification' => ['type' => 'CC', 'number' => '123456789'],
            ],
        ], $overrides);
    }

    private function checkout(Pago $p, string $status = 'pending', array $payloadOverrides = [], int $paymentId = 9876): void
    {
        Http::fake(['api.mercadopago.com/v1/payments' => function ($request) use ($p, $status, $paymentId) {
            return Http::response($this->remote($p, [
                'id' => $paymentId,
                'status' => $status,
                'external_reference' => $request['external_reference'],
                'date_last_updated' => '2026-11-13T14:00:00Z',
            ]));
        }]);
        $this->postJson("/api/v1/pagos/{$p->id}/checkout", $this->brickPayload($payloadOverrides))
            ->assertOk()
            ->assertJsonPath('modoPrueba', true);
        $p->refresh();
    }

    private function remote(Pago $p, array $overrides = []): array
    {
        return array_replace(['id' => 9876, 'external_reference' => $p->mp_external_reference, 'transaction_amount' => 150000,
            'currency_id' => 'COP', 'collector_id' => 1234, 'live_mode' => false, 'status' => 'approved',
            'date_last_updated' => '2026-11-13T15:00:00Z', 'transaction_amount_refunded' => 0], $overrides);
    }

    private function webhook(array $remote)
    {
        Http::swap(new Factory);
        Http::fake(['api.mercadopago.com/v1/payments/9876' => Http::response($remote)]);
        Http::preventStrayRequests();
        $ts = (string) time();
        $rid = 'request-prueba';
        $sig = hash_hmac('sha256', "id:9876;request-id:$rid;ts:$ts;", 'test-webhook-secret');

        return $this->withHeaders(['x-signature' => "ts=$ts,v1=$sig", 'x-request-id' => $rid])
            ->postJson('/api/v1/webhooks/mercadopago?data.id=9876&type=payment', ['type' => 'payment', 'data' => ['id' => 'INJECTED-ID']]);
    }

    public function test_checkout_api_usa_importe_servidor_y_bloquea_doble_pago_en_proceso(): void
    {
        [, $p] = $this->orden();
        $this->checkout($p, 'pending');
        $p->refresh();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => (float) $r['transaction_amount'] === 150000.0
            && $r['external_reference'] === $p->mp_external_reference
            && $r->hasHeader('X-Idempotency-Key')
            && str_starts_with($r['notification_url'], 'https://universidad.example.test/api/v1/webhooks/'));
        $this->assertSame('PENDIENTE', $p->estado);
        $this->postJson("/api/v1/pagos/{$p->id}/checkout", $this->brickPayload(['token' => 'otro-token']))->assertConflict();
        $this->deleteJson('/api/v1/matriculas/'.$p->matricula_id)->assertConflict();
        $this->withHeader('Idempotency-Key', 'fake-approval')->postJson("/api/v1/pagos/{$p->id}/procesar", ['resultado' => 'APROBADO'])->assertConflict();
    }

    public function test_checkout_api_rechazado_permite_reintento_y_aprueba_con_segundo_intento(): void
    {
        [, $p] = $this->orden();
        $this->checkout($p, 'rejected', ['token' => 'token-rechazado'], 9875);
        $this->assertSame('PENDIENTE', $p->estado);

        Http::swap(new Factory);
        Http::preventStrayRequests();
        $this->checkout($p, 'approved', ['token' => 'token-aprobado'], 9876);
        $this->assertSame('APROBADO', $p->estado);
        $this->assertSame('MP-9876', $p->referencia);
    }

    public function test_webhook_firmado_verifica_pago_real_y_es_idempotente(): void
    {
        [, $p] = $this->orden();
        $this->checkout($p);
        $this->webhook($this->remote($p))->assertOk();
        $this->webhook($this->remote($p))->assertOk();
        $this->assertSame('APROBADO', $p->fresh()->estado);
        $this->assertSame(2, PagoTransaccion::where('pago_id', $p->id)->count());
        $this->assertSame(1, DB::table('mercado_pago_receipts')->count());
        $this->webhook($this->remote($p, ['status' => 'refunded', 'date_last_updated' => '2026-11-13T16:00:00Z']))->assertOk();
        $this->assertSame('REEMBOLSADO', $p->fresh()->estado);
        $this->webhook($this->remote($p))->assertOk(); // Out of order notification cannot restore approval.
        $this->assertSame('REEMBOLSADO', $p->fresh()->estado);
    }

    public function test_firma_invalida_no_consulta_proveedor(): void
    {
        $this->orden();
        Http::fake();
        $this->postJson('/api/v1/webhooks/mercadopago?data.id=9876', ['type' => 'payment'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_rechaza_importe_moneda_cuenta_y_modo_incorrectos(): void
    {
        [, $p] = $this->orden();
        $this->checkout($p);
        foreach ([['transaction_amount' => 1], ['currency_id' => 'ARS'], ['collector_id' => 555], ['live_mode' => true]] as $override) {
            $this->webhook($this->remote($p, $override))->assertConflict();
            $this->assertSame('PENDIENTE', $p->fresh()->estado);
        }
    }

    public function test_timeout_no_recrea_checkout_ni_aprueba_pago(): void
    {
        [, $p] = $this->orden();
        Http::fake(fn () => throw new ConnectionException('test timeout'));
        $this->postJson("/api/v1/pagos/{$p->id}/checkout", $this->brickPayload())->assertStatus(503);
        $this->postJson("/api/v1/pagos/{$p->id}/checkout", $this->brickPayload())->assertConflict();
        $this->assertSame('PENDIENTE', $p->fresh()->estado);
    }

    public function test_propietario_y_configuracion_se_validan_antes_de_checkout(): void
    {
        [, $p] = $this->orden();
        Http::fake();
        $this->como($this->usuario('ESTUDIANTE'))->postJson("/api/v1/pagos/{$p->id}/checkout", $this->brickPayload())->assertForbidden();
        Http::assertNothingSent();
        $this->como($this->usuario());
        config(['integrations.mercadopago.access_token' => null]);
        $this->postJson("/api/v1/pagos/{$p->id}/checkout", $this->brickPayload())->assertStatus(503);
    }

    public function test_sincronizacion_consulta_referencia_local_y_no_un_id_del_navegador(): void
    {
        [, $p] = $this->orden();
        $this->checkout($p);
        Http::swap(new Factory);
        Http::fake([
            'api.mercadopago.com/v1/payments/search*' => Http::response(['results' => [['id' => 9876]]]),
            'api.mercadopago.com/v1/payments/9876' => Http::response($this->remote($p)),
        ]);
        Http::preventStrayRequests();
        $this->postJson("/api/v1/pagos/{$p->id}/sincronizar", ['payment_id' => 'falso'])->assertOk()->assertJsonPath('estado', 'APROBADO');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'external_reference='.$p->mp_external_reference));
    }

    public function test_segundo_pago_aprobado_queda_identificado_para_revision(): void
    {
        [, $p] = $this->orden();
        $this->checkout($p);
        $this->webhook($this->remote($p))->assertOk();
        Http::swap(new Factory);
        Http::fake(['api.mercadopago.com/v1/payments/9877' => Http::response($this->remote($p, ['id' => 9877]))]);
        Http::preventStrayRequests();
        app(MercadoPagoService::class)->recibir('9877');
        $this->assertTrue((bool) $p->fresh()->requiere_revision);
        $this->assertSame(2, DB::table('mercado_pago_receipts')->count());
    }
}
