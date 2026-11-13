<?php

namespace App\Http\Controllers;

use App\Http\Resources\PagoResource;
use App\Models\Pago;
use App\Services\MercadoPagoService;
use App\Services\PagoService;
use Illuminate\Http\Request;

class MercadoPagoController extends Controller
{
    public function checkout(Request $request, Pago $pago, PagoService $pagos, MercadoPagoService $mp)
    {
        $pagos->autorizar($pago->matricula, $request->user());
        $datos = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'payment_method_id' => ['required', 'string', 'max:50'],
            'installments' => ['required', 'integer', 'min:1', 'max:48'],
            'issuer_id' => ['nullable', 'regex:/^[A-Za-z0-9_-]{1,50}$/'],
            'payer' => ['required', 'array'],
            'payer.email' => ['required', 'email', 'max:150'],
            'payer.identification' => ['sometimes', 'array'],
            'payer.identification.type' => ['required_with:payer.identification', 'string', 'max:20'],
            'payer.identification.number' => ['required_with:payer.identification', 'string', 'max:40'],
        ]);

        return response()->json($mp->checkout($pago, $datos));
    }

    public function sincronizar(Request $request, Pago $pago, PagoService $pagos, MercadoPagoService $mp)
    {
        $pagos->autorizar($pago->matricula, $request->user());

        return new PagoResource($mp->sincronizar($pago)->load('transacciones'));
    }

    public function webhook(Request $request, MercadoPagoService $mp)
    {
        $secret = config('integrations.mercadopago.webhook_secret');
        abort_unless(is_string($secret) && $secret !== '', 503, 'Webhook no configurado');
        // PHP normalizes dots in query names to underscores.
        $id = $request->query('data_id') ?? $request->query('data.id');
        $rid = $request->header('x-request-id');
        $parts = [];
        foreach (explode(',', (string) $request->header('x-signature')) as $item) {
            $pair = explode('=', trim($item), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }
        abort_unless(is_string($id) && ctype_digit($id) && strlen($id) <= 80 && is_string($rid) && strlen($rid) <= 200
            && ctype_digit($parts['ts'] ?? '') && preg_match('/^[a-f0-9]{64}$/', $parts['v1'] ?? ''), 401, 'Notificación no autorizada');
        $manifest = 'id:'.$id.';request-id:'.$rid.';ts:'.$parts['ts'].';';
        abort_unless(hash_equals(hash_hmac('sha256', $manifest, $secret), $parts['v1']), 401, 'Notificación no autorizada');
        // Replays are harmless: fetch the current provider state and persist idempotently.
        abort_unless(($request->input('type') ?? $request->query('type')) === 'payment', 400, 'Solo se admiten notificaciones de pago');
        $mp->recibir($id);

        return response()->json(['received' => true]);
    }
}
