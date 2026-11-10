<?php

namespace App\Integrations\Payments;

use App\Models\Pago;

interface ProveedorPago
{
    public function nombre(): string;

    public function procesar(Pago $pago, string $operacion, string $resultado): ResultadoPago;

    public function reembolsar(Pago $pago, string $operacion): ResultadoPago;
}
