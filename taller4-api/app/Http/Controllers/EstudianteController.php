<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultaRequest;
use App\Http\Requests\EstudianteRequest;
use App\Http\Resources\EstudianteResource;
use App\Models\Estudiante;
use App\Services\EstudianteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstudianteController extends Controller
{
    // GET /api/v1/estudiantes?page&limit&programa&q
    public function index(ConsultaRequest $request)
    {
        $limit = min(max((int) $request->query('limit', 15), 1), 100);

        $estudiantes = Estudiante::query()
            ->when($request->query('programa'), fn ($q, $v) => $q->where('programa', $v))
            ->when($request->query('q'), function ($q, $v) {
                $q->where(function ($w) use ($v) {
                    $w->where('nombres', 'like', "%{$v}%")
                        ->orWhere('apellidos', 'like', "%{$v}%")
                        ->orWhere('documento', 'like', "%{$v}%");
                });
            })
            ->orderBy('apellidos')
            ->paginate($limit);

        return EstudianteResource::collection($estudiantes);
    }

    public function store(EstudianteRequest $request, EstudianteService $service): JsonResponse
    {
        $estudiante = $service->crear($request->validated());

        return (new EstudianteResource($estudiante))->response()->setStatusCode(201)
            ->header('Location', url("/api/v1/estudiantes/{$estudiante->id}"));
    }

    // GET /api/v1/estudiantes/{id}
    public function show(Request $request, Estudiante $estudiante)
    {
        $user = $request->user();

        $puede = in_array($user->rol, ['ADMIN', 'DOCENTE'], true)
            || ($user->rol === 'ESTUDIANTE' && $user->estudiante_id === $estudiante->id);

        if (! $puede) {
            abort(403, 'No tienes permisos para ver este estudiante');
        }

        return new EstudianteResource($estudiante);
    }

    public function destroy(Estudiante $estudiante, EstudianteService $service)
    {
        $service->eliminar($estudiante);

        return response()->noContent();
    }
}
