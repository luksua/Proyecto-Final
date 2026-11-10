<?php

namespace Tests\Feature;

use App\Models\Matricula;
use App\Services\MatriculaService;
use Tests\ApiTestCase;

class AcademicoTest extends ApiTestCase
{
    public function test_login_y_permisos(): void
    {
        $this->getJson('/api/v1/cursos')->assertUnauthorized();
        $user = $this->usuario('ESTUDIANTE');
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'incorrecta'])->assertUnauthorized();
        $this->como($user)->postJson('/api/v1/cursos', [])->assertForbidden();
    }

    public function test_registro_y_consulta_de_estudiantes(): void
    {
        $this->como($this->usuario());
        $data = ['documento' => '123456', 'nombres' => 'Luna', 'apellidos' => 'Prueba', 'correo' => 'luna@example.test', 'programa' => 'Sistemas'];
        $id = $this->postJson('/api/v1/estudiantes', $data)->assertCreated()->json('id');
        $this->getJson('/api/v1/estudiantes/'.$id)->assertOk()->assertJsonPath('documento', '123456');
        $this->postJson('/api/v1/estudiantes', $data)->assertUnprocessable();
    }

    public function test_cursos_y_validacion_de_cupo_ocupado(): void
    {
        $user = $this->usuario('ESTUDIANTE');
        $curso = $this->curso(2);
        app(MatriculaService::class)->crear(['estudianteId' => $user->estudiante_id, 'cursoId' => $curso->id, 'periodo' => '2026-2'], $user);
        $this->como($this->usuario());
        $this->getJson('/api/v1/cursos')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson('/api/v1/cursos/'.$curso->id, ['cupoMaximo' => 3])->assertOk()->assertJsonPath('cupoDisponible', 2);
        $this->patchJson('/api/v1/cursos/'.$curso->id, ['cupoMaximo' => 0])->assertUnprocessable();
        $this->deleteJson('/api/v1/cursos/'.$curso->id)->assertConflict();
    }

    public function test_matricula_duplicada_sin_cupos_y_cancelacion(): void
    {
        $u = $this->usuario('ESTUDIANTE');
        $otro = $this->usuario('ESTUDIANTE');
        $c = $this->curso(1);
        $this->como($u);
        $body = ['estudianteId' => $u->estudiante_id, 'cursoId' => $c->id, 'periodo' => '2026-2'];
        $id = $this->postJson('/api/v1/matriculas', $body)->assertCreated()->json('id');
        $this->getJson('/api/v1/matriculas/'.$id)->assertOk();
        $this->postJson('/api/v1/matriculas', $body)->assertConflict();
        $this->como($otro)->postJson('/api/v1/matriculas', [...$body, 'estudianteId' => $otro->estudiante_id])->assertConflict();
        $this->deleteJson('/api/v1/matriculas/'.$id)->assertForbidden();
        $this->como($u)->deleteJson('/api/v1/matriculas/'.$id)->assertNoContent();
        $this->deleteJson('/api/v1/matriculas/'.$id)->assertConflict();
        $this->assertSame(1, $c->fresh()->cupo_disponible);
        $this->postJson('/api/v1/matriculas', $body)->assertCreated();
        $this->assertSame(2, Matricula::count());
    }
}
