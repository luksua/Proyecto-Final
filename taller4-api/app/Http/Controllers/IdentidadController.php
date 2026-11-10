<?php

namespace App\Http\Controllers;

use App\Http\Requests\IdentidadRequest;
use App\Services\IdentidadService;

class IdentidadController extends Controller
{
    public function inicio(IdentidadRequest $request, IdentidadService $service)
    {
        return response()->json($service->iniciar($request->validated('codeChallenge')));
    }

    public function simulada(IdentidadRequest $request, IdentidadService $service)
    {
        return response()->json(['code' => $service->codigoSimulado($request->validated()), 'simulado' => true]);
    }

    public function intercambio(IdentidadRequest $request, IdentidadService $service)
    {
        return response()->json($service->intercambiar($request->validated()));
    }
}
