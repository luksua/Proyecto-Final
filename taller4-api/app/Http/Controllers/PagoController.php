<?php

namespace App\Http\Controllers;

use App\Http\Requests\PagoRequest;
use App\Http\Requests\ProcesarPagoRequest;
use App\Http\Resources\PagoResource;
use App\Models\Pago;
use App\Services\PagoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PagoController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['estado' => ['sometimes', Rule::in(['PENDIENTE', 'APROBADO', 'RECHAZADO', 'REEMBOLSADO'])],
            'matriculaId' => ['sometimes', 'integer'], 'page' => ['sometimes', 'integer', 'min:1'], 'limit' => ['sometimes', 'integer', 'between:1,100']]);
        $q = Pago::with(['matricula.estudiante', 'matricula.curso'])->orderByDesc('id');
        if ($request->user()->rol === 'ESTUDIANTE') {
            $q->whereHas('matricula', fn ($m) => $m->where('estudiante_id', $request->user()->estudiante_id));
        }
        if (isset($filters['estado'])) {
            $q->where('estado', $filters['estado']);
        }
        if (isset($filters['matriculaId'])) {
            $q->where('matricula_id', $filters['matriculaId']);
        }

        return PagoResource::collection($q->paginate($filters['limit'] ?? 15));
    }

    public function store(PagoRequest $request, PagoService $service)
    {
        $pago = $service->crear($request->validated('matriculaId'), $request->user());

        return (new PagoResource($pago))->response()->setStatusCode($pago->wasRecentlyCreated ? 201 : 200)
            ->header('Location', url("/api/v1/pagos/{$pago->id}"));
    }

    public function show(Request $request, Pago $pago, PagoService $service)
    {
        $service->autorizar($pago->matricula, $request->user());

        return new PagoResource($pago->load(['matricula.estudiante', 'matricula.curso', 'transacciones']));
    }

    public function procesar(ProcesarPagoRequest $request, Pago $pago, PagoService $service)
    {
        $data = $request->validated();

        return new PagoResource($service->procesar($pago, $request->user(), $data['idempotencyKey'], $data['resultado'])->load('transacciones'));
    }

    public function reembolsar(ProcesarPagoRequest $request, Pago $pago, PagoService $service)
    {
        return new PagoResource($service->procesar($pago, $request->user(), $request->validated('idempotencyKey'), 'REEMBOLSADO', true)->load('transacciones'));
    }
}
