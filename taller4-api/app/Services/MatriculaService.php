<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Matricula;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatriculaService
{
    public function crear(array $data, User $actor): Matricula
    {
        abort_unless(in_array($actor->rol, ['ADMIN', 'ESTUDIANTE'], true), 403, 'No tienes permisos para matricular');
        abort_if($actor->rol === 'ESTUDIANTE' && $actor->estudiante_id !== (int) $data['estudianteId'], 403, 'Solo puedes matricularte a ti mismo');

        return DB::transaction(function () use ($data) {
            // Every capacity mutation locks the same course first.
            $curso = Curso::lockForUpdate()->findOrFail($data['cursoId']);
            $estudiante = Estudiante::findOrFail($data['estudianteId']);
            abort_unless($estudiante->estado === 'ACTIVO', 409, 'El estudiante no está activo');
            abort_unless($curso->estado === 'ABIERTO', 409, "El curso {$curso->id} no está abierto para matrícula");
            if ($curso->periodo !== $data['periodo']) {
                throw ValidationException::withMessages(['periodo' => ["El curso pertenece al periodo {$curso->periodo}"]]);
            }
            abort_if(Matricula::where('estudiante_id', $data['estudianteId'])->where('curso_id', $curso->id)
                ->where('periodo', $data['periodo'])->where('estado', 'ACTIVA')->exists(), 409, 'El estudiante ya está matriculado en este curso');
            abort_if($curso->cupo_disponible <= 0, 409, "El curso {$curso->id} no tiene cupos disponibles");
            $matricula = Matricula::create([
                'estudiante_id' => $estudiante->id, 'curso_id' => $curso->id,
                'periodo' => $data['periodo'], 'estado' => 'ACTIVA', 'fecha_matricula' => now(),
            ]);
            $curso->decrement('cupo_disponible');
            NotificacionService::registrar($estudiante->id, 'matricula.creada', $matricula->id, 'matricula:'.$matricula->id.':creada');

            return $matricula;
        }, 3);
    }

    public function autorizarLectura(Matricula $matricula, User $actor): void
    {
        $puede = $actor->rol === 'ADMIN'
            || ($actor->rol === 'ESTUDIANTE' && $actor->estudiante_id === $matricula->estudiante_id)
            || ($actor->rol === 'DOCENTE' && $matricula->curso->docente_id === $actor->docente_id);
        abort_unless($puede, 403, 'No tienes permisos para ver esta matrícula');
    }

    public function cancelar(Matricula $matricula, User $actor): void
    {
        abort_unless($actor->rol === 'ADMIN' || ($actor->rol === 'ESTUDIANTE' && $actor->estudiante_id === $matricula->estudiante_id), 403, 'No tienes permisos para cancelar esta matrícula');
        DB::transaction(function () use ($matricula) {
            $curso = Curso::lockForUpdate()->findOrFail($matricula->curso_id);
            $actual = Matricula::lockForUpdate()->findOrFail($matricula->id);
            abort_unless($actual->estado === 'ACTIVA', 409, 'La matrícula ya fue cancelada');
            abort_if($actual->pagos()->where('proveedor', 'mercadopago')->whereNotNull('mp_checkout_requested_at')->exists(), 409,
                'La matrícula tiene un checkout externo iniciado. Requiere revisión administrativa antes de cancelarla');
            abort_if($actual->pagos()->where('estado', 'APROBADO')->lockForUpdate()->exists(), 409, 'Un administrador debe reembolsar el pago aprobado antes de cancelar la matrícula');
            $actual->update(['estado' => 'CANCELADA']);
            $curso->increment('cupo_disponible');
            NotificacionService::registrar($actual->estudiante_id, 'matricula.cancelada', $actual->id, 'matricula:'.$actual->id.':cancelada');
        }, 3);
    }
}
