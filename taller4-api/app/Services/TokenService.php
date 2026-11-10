<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;

class TokenService
{
    public function emitir(User $user): array
    {
        abort_unless(in_array($user->rol, ['ADMIN', 'DOCENTE', 'ESTUDIANTE'], true), 403, 'La cuenta no tiene un rol autorizado');
        $secret = (string) config('jwt.secret');
        abort_if(strlen($secret) < 32, 503, 'La autenticación no está configurada correctamente');
        $ttl = max(60, min((int) config('jwt.ttl'), 3600));
        $now = time();

        return [
            'accessToken' => JWT::encode(['sub' => (string) $user->id, 'rol' => $user->rol, 'iat' => $now,
                'exp' => $now + $ttl, 'iss' => config('jwt.issuer'), 'aud' => config('jwt.audience')], $secret, 'HS256'),
            'tokenType' => 'Bearer', 'expiresIn' => $ttl, 'rol' => $user->rol,
            'nombre' => $user->name, 'estudianteId' => $user->estudiante_id, 'docenteId' => $user->docente_id,
        ];
    }
}
