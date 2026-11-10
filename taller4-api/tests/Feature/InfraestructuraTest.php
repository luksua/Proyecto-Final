<?php

namespace Tests\Feature;

use App\Jobs\EntregarNotificacion;
use App\Services\MatriculaService;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class InfraestructuraTest extends ApiTestCase
{
    public function test_oferta_cacheada_se_invalida_al_editar_y_matricular(): void
    {
        $u = $this->usuario('ESTUDIANTE');
        $curso = $this->curso(2);
        $this->como($this->usuario());
        $this->getJson('/api/v1/cursos')->assertJsonPath('data.0.nombre', 'Arquitectura')->assertJsonPath('data.0.cupoDisponible', 2);
        $this->patchJson('/api/v1/cursos/'.$curso->id, ['nombre' => 'Arquitectura actualizada'])->assertOk();
        $this->getJson('/api/v1/cursos')->assertJsonPath('data.0.nombre', 'Arquitectura actualizada');
        app(MatriculaService::class)->crear(['estudianteId' => $u->estudiante_id, 'cursoId' => $curso->id, 'periodo' => '2026-2'], $u);
        $this->getJson('/api/v1/cursos')->assertJsonPath('data.0.cupoDisponible', 1);
    }

    public function test_outbox_cola_database_y_notificacion_idempotente(): void
    {
        $u = $this->usuario('ESTUDIANTE');
        $curso = $this->curso();
        app(MatriculaService::class)->crear(['estudianteId' => $u->estudiante_id, 'cursoId' => $curso->id, 'periodo' => '2026-2'], $u);
        $this->assertSame(1, DB::table('notification_outbox')->count());
        $this->assertSame(0, DB::table('notificaciones')->count());
        config(['queue.default' => 'database']);
        $this->artisan('notificaciones:despachar')->assertExitCode(0);
        $this->assertSame(1, DB::table('jobs')->count());
        $this->artisan('queue:work', ['--once' => true, '--tries' => 1])->assertExitCode(0);
        $this->assertSame(1, DB::table('notificaciones')->count());
        (new EntregarNotificacion(DB::table('notification_outbox')->value('id')))->handle();
        $this->assertSame(1, DB::table('notificaciones')->count());
        $this->como($u)->getJson('/api/v1/notificaciones')->assertOk()->assertJsonCount(1, 'data');
        $this->como($this->usuario('ESTUDIANTE'))->getJson('/api/v1/notificaciones')->assertOk()->assertJsonCount(0, 'data');
    }
}
