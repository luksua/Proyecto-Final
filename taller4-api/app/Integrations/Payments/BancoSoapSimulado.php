<?php

namespace App\Integrations\Payments;

use App\Models\Pago;

/** Contract demonstration only. No WSDL, network call or bank credentials are used. */
class BancoSoapSimulado extends ProveedorSimulado
{
    public function nombre(): string
    {
        return 'soap_simulado';
    }

    public function sobre(Pago $pago, string $referencia): string
    {
        $xml = new \SimpleXMLElement('<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"/>');
        $body = $xml->addChild('s:Body', null, 'http://www.w3.org/2003/05/soap-envelope');
        $operation = $body->addChild('RegistrarPago', null, 'urn:universidad:pagos:simulacion');
        $operation->addChild('Referencia', htmlspecialchars($referencia, ENT_XML1));
        $operation->addChild('Valor', $pago->valor);
        $operation->addChild('Moneda', $pago->moneda);

        return $xml->asXML();
    }

    public function procesar(Pago $pago, string $operacion, string $resultado): ResultadoPago
    {
        $this->sobre($pago, $operacion); // Exercise XML mapping; deliberately no real SOAP transport.

        return parent::procesar($pago, $operacion, $resultado);
    }
}
