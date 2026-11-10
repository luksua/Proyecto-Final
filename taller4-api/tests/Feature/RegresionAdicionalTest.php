<?php

namespace Tests\Feature;

use App\Models\Matricula;
use App\Services\MatriculaService;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\ApiTestCase;

class RegresionAdicionalTest extends ApiTestCase
{
    public function test_curso_cerrado_periodo_incorrecto_y_propiedad(): void
    {
        $u = $this->usuario('ESTUDIANTE');
        $otro = $this->usuario('ESTUDIANTE');
        $c = $this->curso();
        $this->como($u);
        $data = ['estudianteId' => $u->estudiante_id, 'cursoId' => $c->id, 'periodo' => '2026-2'];
        $this->postJson('/api/v1/matriculas', [...$data, 'estudianteId' => $otro->estudiante_id])->assertForbidden();
        $this->postJson('/api/v1/matriculas', [...$data, 'periodo' => '2026-1'])->assertUnprocessable();
        $c->update(['estado' => 'CERRADO']);
        $this->postJson('/api/v1/matriculas', $data)->assertConflict();
        $this->assertSame(0, Matricula::count());
        $this->assertSame(2, $c->fresh()->cupo_disponible);
    }

    public function test_indice_unico_permita_historial_cancelado_y_bloquee_duplicado_activo(): void
    {
        $u = $this->usuario('ESTUDIANTE');
        $c = $this->curso();
        $m = app(MatriculaService::class)->crear(['estudianteId' => $u->estudiante_id, 'cursoId' => $c->id, 'periodo' => '2026-2'], $u);
        $this->expectException(UniqueConstraintViolationException::class);
        Matricula::create(['estudiante_id' => $u->estudiante_id, 'curso_id' => $c->id, 'periodo' => '2026-2', 'estado' => 'ACTIVA']);
    }

    public function test_docente_no_recibe_informacion_financiera(): void
    {
        $u = $this->usuario('ESTUDIANTE');
        $docente = $this->usuario('DOCENTE');
        $c = $this->curso();
        $c->update(['docente_id' => $docente->docente_id]);
        app(MatriculaService::class)->crear(['estudianteId' => $u->estudiante_id, 'cursoId' => $c->id, 'periodo' => '2026-2'], $u);
        $this->como($docente)->getJson('/api/v1/matriculas')->assertOk()->assertJsonMissingPath('data.0.pago');
    }
}
