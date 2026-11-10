<?php

namespace Tests\Feature;

use App\Integrations\Payments\BancoSoapSimulado;
use App\Models\Pago;
use App\Models\PagoTransaccion;
use App\Services\MatriculaService;
use Tests\ApiTestCase;

class PagosTest extends ApiTestCase
{
    private function orden(): array
    {
        $u = $this->usuario('ESTUDIANTE');
        $c = $this->curso();
        $m = app(MatriculaService::class)->crear(['estudianteId' => $u->estudiante_id, 'cursoId' => $c->id, 'periodo' => '2026-2'], $u);
        $this->como($u);
        $id = $this->postJson('/api/v1/pagos', ['matriculaId' => $m->id])->assertCreated()->assertJsonPath('estado', 'PENDIENTE')->json('id');

        return [$u, $m, $id];
    }

    public function test_orden_unica_y_valor_calculado_en_servidor(): void
    {
        [, $m, $id] = $this->orden();
        $this->postJson('/api/v1/pagos', ['matriculaId' => $m->id])->assertOk()->assertJsonPath('id', $id)->assertJsonPath('valor', '150000.00');
        $this->postJson('/api/v1/pagos', ['matriculaId' => $m->id, 'valor' => 1])->assertUnprocessable();
        $this->getJson('/api/v1/pagos?estado=PENDIENTE')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(1, Pago::count());
    }

    public function test_aprobacion_idempotente_y_doble_pago_bloqueado(): void
    {
        [, , $id] = $this->orden();
        $this->withHeader('Idempotency-Key', 'prueba-pago-uno');
        $this->postJson("/api/v1/pagos/$id/procesar", ['resultado' => 'APROBADO'])->assertOk()->assertJsonPath('estado', 'APROBADO');
        $this->postJson("/api/v1/pagos/$id/procesar", ['resultado' => 'APROBADO'])->assertOk();
        $this->postJson("/api/v1/pagos/$id/procesar", ['resultado' => 'RECHAZADO'])->assertConflict();
        $this->withHeader('Idempotency-Key', 'prueba-pago-dos')->postJson("/api/v1/pagos/$id/procesar", ['resultado' => 'APROBADO'])->assertConflict();
        $this->assertSame(1, PagoTransaccion::count());
    }

    public function test_rechazo_reintento_y_reembolso_administrativo(): void
    {
        [$u, $m, $id] = $this->orden();
        $this->withHeader('Idempotency-Key', 'rechazo-prueba')->postJson("/api/v1/pagos/$id/procesar", ['resultado' => 'RECHAZADO'])->assertOk()->assertJsonPath('estado', 'RECHAZADO');
        $this->withHeader('Idempotency-Key', 'aprobado-prueba')->postJson("/api/v1/pagos/$id/procesar", ['resultado' => 'APROBADO'])->assertOk();
        $this->deleteJson('/api/v1/matriculas/'.$m->id)->assertConflict();
        $this->withHeader('Idempotency-Key', 'reembolso-prueba')->postJson("/api/v1/pagos/$id/reembolsar")->assertForbidden();
        $this->como($this->usuario())->postJson("/api/v1/pagos/$id/reembolsar")->assertOk()->assertJsonPath('estado', 'REEMBOLSADO');
        $this->postJson("/api/v1/pagos/$id/reembolsar")->assertOk();
        $this->como($u)->deleteJson('/api/v1/matriculas/'.$m->id)->assertNoContent();
        $this->getJson("/api/v1/pagos/$id")->assertOk()->assertJsonCount(3, 'transacciones');
        $this->como($this->usuario())->deleteJson('/api/v1/estudiantes/'.$u->estudiante_id)->assertConflict();
    }

    public function test_error_integracion_no_confirma_y_permite_reintentar(): void
    {
        [, , $id] = $this->orden();
        $this->withHeader('Idempotency-Key', 'error-prueba-pago')->postJson("/api/v1/pagos/$id/procesar", ['resultado' => 'ERROR'])->assertStatus(503);
        $this->assertSame('PENDIENTE', Pago::find($id)->estado);
        $this->assertSame(0, PagoTransaccion::count());
        $this->withHeader('Idempotency-Key', 'nueva-prueba-pago')->postJson("/api/v1/pagos/$id/procesar", ['resultado' => 'APROBADO'])->assertOk();
    }

    public function test_pagos_privados_y_roles(): void
    {
        [, , $id] = $this->orden();
        $this->como($this->usuario('ESTUDIANTE'))->getJson("/api/v1/pagos/$id")->assertForbidden();
        $this->getJson('/api/v1/pagos')->assertOk()->assertJsonCount(0, 'data');
        $this->como($this->usuario('DOCENTE'))->getJson('/api/v1/pagos')->assertForbidden();
    }

    public function test_adaptador_soap_es_simulado_y_generar_xml(): void
    {
        config(['integrations.payments.driver' => 'soap_simulado']);
        [, , $id] = $this->orden();
        $xml = (new BancoSoapSimulado)->sobre(Pago::find($id), 'operacion-prueba');
        $this->assertStringContainsString('Envelope', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->withHeader('Idempotency-Key', 'soap-prueba-pago')->postJson("/api/v1/pagos/$id/procesar", ['resultado' => 'APROBADO'])
            ->assertOk()->assertJsonPath('proveedor', 'soap_simulado')->assertJsonPath('simulado', true);
    }
}
