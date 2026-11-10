<?php

namespace App\Services;

use App\Integrations\Identity\IdentidadSimulada;
use App\Integrations\Identity\ProveedorIdentidad;
use App\Models\IdentidadExterna;
use App\Models\IdentityFlow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class IdentidadService
{
    public function __construct(private ProveedorIdentidad $proveedor, private TokenService $tokens) {}

    public function iniciar(string $challenge): array
    {
        $state = Str::random(64);
        $nonce = Str::random(64);
        $url = $this->proveedor->authorizationUrl($state, $nonce, $challenge);
        IdentityFlow::create(['state_hash' => hash('sha256', $state), 'driver' => config('integrations.identity.driver'),
            'nonce' => $nonce, 'challenge' => $challenge, 'expires_at' => now()->addMinutes(5)]);

        return ['state' => $state, 'authorizationUrl' => $url, 'simulado' => config('integrations.identity.driver') === 'simulado', 'expiresIn' => 300];
    }

    public function codigoSimulado(array $data): string
    {
        abort_unless($this->proveedor instanceof IdentidadSimulada, 404, 'Ruta no disponible');
        $this->proveedor->comprobarEntorno();
        $user = User::where('email', $data['email'])->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 401, 'Credenciales inválidas');

        return DB::transaction(function () use ($data, $user) {
            $flow = $this->flujo($data['state']);
            abort_if($flow->code_hash, 400, 'Ya se emitió un código para este inicio de sesión');
            $subject = 'mock-user-'.$user->id;
            IdentidadExterna::firstOrCreate(['issuer' => IdentidadSimulada::ISSUER, 'subject' => $subject], ['user_id' => $user->id]);
            $code = Str::random(64);
            $flow->update(['code_hash' => hash('sha256', $code), 'subject' => $subject]);

            return $code;
        }, 3);
    }

    private function flujo(string $state): IdentityFlow
    {
        $flow = IdentityFlow::where('state_hash', hash('sha256', $state))->lockForUpdate()->first();
        abort_unless($flow && ! $flow->used_at && $flow->expires_at->isFuture()
            && $flow->driver === config('integrations.identity.driver'), 400, 'Inicio de sesión inválido o vencido');

        return $flow;
    }

    public function intercambiar(array $data): array
    {
        $flow = DB::transaction(function () use ($data) {
            $flow = $this->flujo($data['state']);
            $challenge = rtrim(strtr(base64_encode(hash('sha256', $data['codeVerifier'], true)), '+/', '-_'), '=');
            abort_unless(hash_equals($flow->challenge, $challenge), 400, 'Verificación PKCE no válida');
            $flow->update(['used_at' => now()]); // Consume before calling the remote provider.

            return $flow;
        }, 3);
        $identity = $this->proveedor->validar($data['code'], $data['codeVerifier'], $flow);
        $link = IdentidadExterna::with('user')->where('issuer', $identity['issuer'])->where('subject', $identity['subject'])->first();
        abort_unless($link?->user, 403, 'La identidad institucional no tiene una cuenta local vinculada');

        return $this->tokens->emitir($link->user);
    }
}
