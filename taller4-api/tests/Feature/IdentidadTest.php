<?php

namespace Tests\Feature;

use App\Models\IdentidadExterna;
use App\Models\IdentityFlow;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\ApiTestCase;

class IdentidadTest extends ApiTestCase
{
    private function inicio(): array
    {
        $verifier = str_repeat('a', 64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $state = $this->postJson('/api/v1/auth/oidc/inicio', ['codeChallenge' => $challenge])->assertOk()->json('state');

        return [$state, $verifier];
    }

    public function test_simulacion_emite_jwt_con_rol_local_y_codigo_de_un_solo_uso(): void
    {
        $u = $this->usuario('ESTUDIANTE');
        [$state, $verifier] = $this->inicio();
        $code = $this->postJson('/api/v1/auth/oidc/simulada', ['state' => $state, 'email' => $u->email, 'password' => 'clave-segura-prueba'])
            ->assertOk()->assertJsonPath('simulado', true)->json('code');
        $body = ['state' => $state, 'code' => $code, 'codeVerifier' => $verifier, 'rol' => 'ADMIN'];
        $token = $this->postJson('/api/v1/auth/oidc/intercambio', $body)->assertOk()->assertJsonPath('rol', 'ESTUDIANTE')->json('accessToken');
        $this->withToken($token)->getJson('/api/v1/cursos')->assertOk();
        $this->postJson('/api/v1/auth/oidc/intercambio', $body)->assertBadRequest();
    }

    public function test_pkce_vencimiento_y_simulacion_desactivada(): void
    {
        [$state] = $this->inicio();
        $this->postJson('/api/v1/auth/oidc/intercambio', ['state' => $state, 'code' => 'falso', 'codeVerifier' => str_repeat('z', 64)])->assertBadRequest();
        IdentityFlow::query()->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/v1/auth/oidc/intercambio', ['state' => $state, 'code' => 'falso', 'codeVerifier' => str_repeat('a', 64)])->assertBadRequest();
        config(['integrations.payments.simulados_habilitados' => false]);
        $this->postJson('/api/v1/auth/oidc/inicio', ['codeChallenge' => str_repeat('a', 43)])->assertStatus(503);
    }

    public function test_adaptador_oidc_valida_firma_nonce_audiencia_y_vinculo_sin_red_real(): void
    {
        config(['integrations.identity' => [
            'driver' => 'oidc', 'issuer' => 'https://idp.example.test', 'client_id' => 'universidad', 'client_secret' => null,
            'redirect_uri' => 'https://universidad.example.test/app/index.html',
            'authorization_endpoint' => 'https://idp.example.test/auth', 'token_endpoint' => 'https://idp.example.test/token', 'jwks_uri' => 'https://idp.example.test/jwks',
        ]]);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => 'C:/xampp/apache/conf/openssl.cnf']);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $private, null, ['config' => 'C:/xampp/apache/conf/openssl.cnf']);
        $details = openssl_pkey_get_details($key);
        $b64 = fn ($v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $jwks = ['keys' => [['kty' => 'RSA', 'kid' => 'test-key', 'alg' => 'RS256', 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])]]];
        $u = $this->usuario('DOCENTE');
        IdentidadExterna::create(['user_id' => $u->id, 'issuer' => 'https://idp.example.test', 'subject' => 'persona-verificada']);
        foreach (['valid', 'bad_nonce', 'bad_aud', 'unlinked'] as $scenario) {
            [$state, $verifier] = $this->inicio();
            $flow = IdentityFlow::latest('id')->first();
            $token = JWT::encode(['iss' => 'https://idp.example.test', 'aud' => $scenario === 'bad_aud' ? 'otro' : 'universidad',
                'sub' => $scenario === 'unlinked' ? 'sin-vinculo' : 'persona-verificada', 'nonce' => $scenario === 'bad_nonce' ? 'otro' : $flow->nonce,
                'iat' => time(), 'exp' => time() + 300], $private, 'RS256', 'test-key');
            Http::swap(new Factory);
            Http::fake(['https://idp.example.test/token' => Http::response(['id_token' => $token]), 'https://idp.example.test/jwks' => Http::response($jwks)]);
            Http::preventStrayRequests();
            $r = $this->postJson('/api/v1/auth/oidc/intercambio', ['state' => $state, 'code' => 'codigo-proveedor', 'codeVerifier' => $verifier]);
            if ($scenario === 'valid') {
                $r->assertOk()->assertJsonPath('rol', 'DOCENTE');
            } elseif ($scenario === 'unlinked') {
                $r->assertForbidden();
            } else {
                $r->assertBadRequest();
            }
        }
    }
}
