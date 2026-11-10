<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class SeguridadOperacionTest extends ApiTestCase
{
    public function test_salud_correlacion_json_invalido_y_errores_uniformes(): void
    {
        $id = '18a3913e-cf7b-4c3c-a56c-d65434ec5242';
        $this->withHeader('X-Correlation-ID', $id)->getJson('/api/v1/health')->assertOk()->assertHeader('X-Correlation-ID', $id)->assertHeader('X-Response-Time-Ms');
        $this->getJson('/api/v1/cursos')->assertUnauthorized()->assertJsonPath('correlationId', $id)->assertJsonStructure(['timestamp', 'status', 'error', 'message', 'path']);
        $this->call('POST', '/api/v1/auth/login', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{malformado')
            ->assertBadRequest()->assertJsonPath('message', 'El cuerpo JSON no es válido');
        $this->getJson('/api/v1/inexistente')->assertNotFound()->assertJsonPath('message', 'Recurso no encontrado');
    }

    public function test_tokens_expirados_y_audiencia_incorrecta(): void
    {
        $u = $this->usuario();
        foreach ([['exp' => time() - 5, 'aud' => config('jwt.audience')], ['exp' => time() + 300, 'aud' => 'otra-app']] as $case) {
            $token = JWT::encode(['sub' => (string) $u->id, 'iss' => config('jwt.issuer'), ...$case], config('jwt.secret'), 'HS256');
            $this->withToken($token)->getJson('/api/v1/cursos')->assertUnauthorized();
        }
    }

    public function test_limite_de_login_preserva_retry_after(): void
    {
        config(['security.auth_per_minute' => 1]);
        $u = $this->usuario();
        $body = ['email' => $u->email, 'password' => 'clave-segura-prueba'];
        $this->postJson('/api/v1/auth/login', $body)->assertOk();
        $this->postJson('/api/v1/auth/login', $body)->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('status', 429);
    }

    public function test_auditoria_y_metricas_solo_administrador(): void
    {
        $this->como($this->usuario());
        $this->postJson('/api/v1/estudiantes', ['documento' => 'observado', 'nombres' => 'Privado', 'apellidos' => 'Ejemplo', 'correo' => 'privado@example.test', 'programa' => 'Sistemas'])->assertCreated();
        $this->getJson('/api/v1/auditoria')->assertOk();
        $this->getJson('/api/v1/metricas')->assertOk()->assertJsonStructure(['solicitudes', 'errores5xx', 'duracionPromedioMs', 'porRuta']);
        $raw = json_encode(DB::table('audit_events')->get());
        $this->assertStringNotContainsString('privado@example.test', $raw);
        $this->assertStringNotContainsString('clave-segura', $raw);
        $this->como($this->usuario('ESTUDIANTE'))->getJson('/api/v1/metricas')->assertForbidden();
        $this->getJson('/api/v1/auditoria')->assertForbidden();
    }

    public function test_produccion_exige_https_y_bloquea_simulacion(): void
    {
        $this->app->instance('env', 'production');
        $this->getJson('/api/v1/health')->assertStatus(426);
        config(['security.require_https' => false]);
        $this->postJson('/api/v1/auth/oidc/inicio', ['codeChallenge' => str_repeat('a', 43)])->assertStatus(503);
    }
}
