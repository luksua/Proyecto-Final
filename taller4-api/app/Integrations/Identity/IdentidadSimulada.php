<?php

namespace App\Integrations\Identity;

use App\Models\IdentityFlow;

class IdentidadSimulada implements ProveedorIdentidad
{
    public const ISSUER = 'urn:universidad:identidad:simulada';

    public function comprobarEntorno(): void
    {
        abort_if(app()->isProduction() || ! config('integrations.payments.simulados_habilitados'), 503, 'La identidad simulada está deshabilitada');
    }

    public function authorizationUrl(string $state, string $nonce, string $challenge): ?string
    {
        $this->comprobarEntorno();

        return null; // The demo uses an explicitly labelled local provider form.
    }

    public function validar(string $code, string $verifier, IdentityFlow $flow): array
    {
        $this->comprobarEntorno();
        abort_unless($flow->code_hash && hash_equals($flow->code_hash, hash('sha256', $code)) && $flow->subject, 400, 'Código de identidad no válido');

        return ['issuer' => self::ISSUER, 'subject' => $flow->subject];
    }
}
