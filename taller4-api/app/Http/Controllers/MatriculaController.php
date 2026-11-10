<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultaRequest;
use App\Http\Requests\MatriculaRequest;
use App\Http\Resources\MatriculaResource;
use App\Models\Matricula;
use App\Services\MatriculaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatriculaController extends Controller
{
    // GET /api/v1/matriculas
    public function index(ConsultaRequest $request)
    {
        $user = $request->user();
        $limit = min(max((int) $request->query('limit', 15), 1), 100);

        $query = Matricula::with(['estudiante', 'curso', 'pagos'])->orderByDesc('id');

        // Cada rol solo ve lo que le corresponde
        if ($user->rol === 'ESTUDIANTE') {
            $query->where('estudiante_id', $user->estudiante_id);
        } elseif ($user->rol === 'DOCENTE') {
            $query->whereHas('curso', fn ($c) => $c->where('docente_id', $user->docente_id));
        }

        $query
            ->when($request->query('estudianteId'), fn ($q, $v) => $q->where('estudiante_id', $v))
            ->when($request->query('cursoId'), fn ($q, $v) => $q->where('curso_id', $v))
            ->when($request->query('periodo'), fn ($q, $v) => $q->where('periodo', $v))
            ->when($request->query('estado'), fn ($q, $v) => $q->where('estado', $v));

        return MatriculaResource::collection($query->paginate($limit));
    }

    public function store(MatriculaRequest $request, MatriculaService $service): JsonResponse
    {
        $matricula = $service->crear($request->validated(), $request->user());

        return (new MatriculaResource($matricula->load(['estudiante', 'curso'])))->response()->setStatusCode(201)
            ->header('Location', url("/api/v1/matriculas/{$matricula->id}"));
    }

    public function show(Request $request, Matricula $matricula, MatriculaService $service)
    {
        $service->autorizarLectura($matricula, $request->user());

        return new MatriculaResource($matricula->load(['estudiante', 'curso', 'pagos']));
    }

    public function destroy(Request $request, Matricula $matricula, MatriculaService $service)
    {
        $service->cancelar($matricula, $request->user());

        return response()->noContent();
    }
}
