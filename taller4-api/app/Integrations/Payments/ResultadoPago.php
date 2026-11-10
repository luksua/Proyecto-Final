<?php

namespace App\Integrations\Payments;

final readonly class ResultadoPago
{
    public function __construct(public string $estado, public string $referencia) {}
}
