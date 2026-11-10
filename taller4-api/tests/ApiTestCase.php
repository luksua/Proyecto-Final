<?php

namespace Tests;

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Support\Facades\DB;

abstract class ApiTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Never migrate or clear the user's application database in feature tests.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'queue.default' => 'sync', 'session.driver' => 'array',
            'integrations.identity.driver' => 'simulado', 'integrations.payments.driver' => 'simulado',
            'integrations.payments.simulados_habilitados' => true,
            'jwt.secret' => 'test-only-signing-secret-at-least-32-characters']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    protected function usuario(string $rol = 'ADMIN'): User
    {
        $attrs = ['name' => 'Usuario prueba', 'email' => uniqid().'@example.test', 'password' => 'clave-segura-prueba', 'rol' => $rol];
        if ($rol === 'ESTUDIANTE') {
            $e = Estudiante::create(['documento' => uniqid(), 'nombres' => 'Ana', 'apellidos' => 'Prueba', 'correo' => uniqid().'@example.test', 'programa' => 'Sistemas']);
            $attrs['estudiante_id'] = $e->id;
        }
        if ($rol === 'DOCENTE') {
            $attrs['docente_id'] = $this->docente()->id;
        }

        return User::create($attrs);
    }

    protected function docente(): Docente
    {
        return Docente::create(['nombres' => 'Docente', 'apellidos' => 'Prueba', 'correo' => uniqid().'@example.test', 'departamento' => 'Ingeniería']);
    }

    protected function curso(int $cupos = 2): Curso
    {
        return Curso::create(['codigo' => uniqid(), 'nombre' => 'Arquitectura', 'creditos' => 3, 'periodo' => '2026-2', 'estado' => 'ABIERTO', 'docente_id' => $this->docente()->id, 'cupo_maximo' => $cupos, 'cupo_disponible' => $cupos]);
    }

    protected function token(User $user): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'clave-segura-prueba'])->assertOk()->json('accessToken');
    }

    protected function como(User $user): static
    {
        return $this->withToken($this->token($user));
    }
}
