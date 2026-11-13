<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperacionController extends Controller
{
    public function health()
    {
        try {
            DB::select('SELECT 1');
        } catch (\Throwable $e) {
            return response()->json(['status' => 'degraded'], 503);
        }

        return response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]);
    }

    public function configuracion()
    {
        return response()->json(['identityDriver' => config('integrations.identity.driver'),
            'paymentsDriver' => config('integrations.payments.driver'),
            'mercadoPagoTestMode' => config('integrations.mercadopago.test_mode'),
            'mercadoPagoPublicKey' => config('integrations.payments.driver') === 'mercadopago' ? config('integrations.mercadopago.public_key') : null,
            'simulatedEnabled' => ! app()->isProduction() && config('integrations.payments.simulados_habilitados')]);
    }

    public function metricas(Request $request)
    {
        $data = $request->validate(['dia' => ['sometimes', 'date_format:Y-m-d']]);
        $rows = DB::table('api_metrics')->where('day', $data['dia'] ?? now()->toDateString())->get();
        $n = $rows->sum('requests');

        return response()->json(['solicitudes' => $n, 'errores5xx' => $rows->where('status', '>=', 500)->sum('requests'),
            'duracionPromedioMs' => $n ? round($rows->sum('total_ms') / $n, 2) : 0,
            'duracionMaximaMs' => $rows->max('max_ms') ?? 0, 'porRuta' => $rows,
            'nota' => 'Contadores, promedio y máximo; no representan percentiles ni pruebas de carga.']);
    }

    public function auditoria(Request $request)
    {
        $filters = $request->validate(['limit' => ['sometimes', 'integer', 'between:1,100'], 'page' => ['sometimes', 'integer', 'min:1']]);

        return response()->json(DB::table('audit_events')->orderByDesc('id')->paginate($filters['limit'] ?? 20));
    }

    public function notificaciones(Request $request)
    {
        $filters = $request->validate(['limit' => ['sometimes', 'integer', 'between:1,100'], 'page' => ['sometimes', 'integer', 'min:1']]);

        return response()->json(DB::table('notificaciones')->where('user_id', $request->user()->id)->orderByDesc('id')->paginate($filters['limit'] ?? 15));
    }
}
