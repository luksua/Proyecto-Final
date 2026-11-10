<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            abort(401, 'Token no proporcionado');
        }

        try {
            $payload = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
            if (($payload->iss ?? null) !== config('jwt.issuer') || ($payload->aud ?? null) !== config('jwt.audience')
                || ! isset($payload->exp, $payload->sub) || ! ctype_digit((string) $payload->sub)) {
                abort(401, 'Token inválido o vencido');
            }
        } catch (\Throwable $e) {
            abort(401, 'Token inválido o vencido');
        }

        $user = User::find($payload->sub ?? null);

        if (! $user) {
            abort(401, 'El usuario del token no existe');
        }
        if (! in_array($user->rol, ['ADMIN', 'DOCENTE', 'ESTUDIANTE'], true)) {
            abort(403, 'Rol no autorizado');
        }

        // Deja disponible al usuario como $request->user() en los controladores
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
