<?php

namespace App\Integrations\Identity;

use App\Models\IdentityFlow;

interface ProveedorIdentidad
{
    public function authorizationUrl(string $state, string $nonce, string $challenge): ?string;

    /** Returns verified issuer and subject only, never a client-selected local role. */
    public function validar(string $code, string $verifier, IdentityFlow $flow): array;
}
