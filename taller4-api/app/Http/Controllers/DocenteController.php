<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultaRequest;
use App\Http\Requests\DocenteRequest;
use App\Http\Resources\CursoResource;
use App\Http\Resources\DocenteResource;
use App\Models\Docente;
use App\Services\DocenteService;
use Illuminate\Http\JsonResponse;

class DocenteController extends Controller
{
    // GET /api/v1/docentes?page&limit&departamento
    public function index(ConsultaRequest $request)
    {
        $limit = min(max((int) $request->query('limit', 15), 1), 100);

        $docentes = Docente::query()
            ->when($request->query('departamento'), fn ($q, $v) => $q->where('departamento', $v))
            ->orderBy('apellidos')
            ->paginate($limit);

        return DocenteResource::collection($docentes);
    }

    // GET /api/v1/docentes/{id}/cursos?periodo
    public function cursos(ConsultaRequest $request, Docente $docente)
    {
        $cursos = $docente->cursos()
            ->with('docente')
            ->when($request->query('periodo'), fn ($q, $v) => $q->where('periodo', $v))
            ->orderBy('nombre')
            ->get();

        return CursoResource::collection($cursos);
    }

    public function store(DocenteRequest $request, DocenteService $service): JsonResponse
    {
        $docente = $service->crear($request->validated());

        return (new DocenteResource($docente))->response()->setStatusCode(201)
            ->header('Location', url("/api/v1/docentes/{$docente->id}"));
    }

    public function destroy(Docente $docente, DocenteService $service)
    {
        $service->eliminar($docente);

        return response()->noContent();
    }
}
