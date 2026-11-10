<?php

namespace App\Integrations\Payments;

use App\Models\Pago;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class ProveedorSimulado implements ProveedorPago
{
    public function nombre(): string
    {
        return 'simulado';
    }

    protected function comprobarEntorno(): void
    {
        if (app()->isProduction() || ! config('integrations.payments.simulados_habilitados')) {
            throw new ServiceUnavailableHttpException(null, 'El proveedor simulado solo está disponible en desarrollo y pruebas');
        }
    }

    public function procesar(Pago $pago, string $operacion, string $resultado): ResultadoPago
    {
        $this->comprobarEntorno();
        if ($resultado === 'ERROR') {
            throw new ServiceUnavailableHttpException(5, 'El proveedor simulado no está disponible. No se registró un cobro');
        }

        return new ResultadoPago($resultado, 'SIM-'.substr(hash('sha256', $operacion), 0, 32));
    }

    public function reembolsar(Pago $pago, string $operacion): ResultadoPago
    {
        $this->comprobarEntorno();

        return new ResultadoPago('REEMBOLSADO', 'SIM-R-'.substr(hash('sha256', $operacion), 0, 30));
    }
}
