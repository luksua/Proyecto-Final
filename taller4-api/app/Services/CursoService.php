<?php

namespace App\Services;

use App\Models\Curso;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CursoService
{
    public function guardar(array $data, ?Curso $curso = null): Curso
    {
        return DB::transaction(function () use ($data, $curso) {
            $actual = $curso ? Curso::lockForUpdate()->findOrFail($curso->id) : new Curso;
            if ($actual->exists && $actual->matriculas()->exists()) {
                if ((isset($data['periodo']) && $data['periodo'] !== $actual->periodo)) {
                    throw ValidationException::withMessages(['periodo' => ['No puede cambiarse el periodo de un curso con historial de matrículas']]);
                }
            }
            foreach (['codigo', 'nombre', 'creditos', 'periodo', 'estado'] as $campo) {
                if (array_key_exists($campo, $data)) {
                    $actual->{$campo} = $data[$campo];
                }
            }
            if (isset($data['docenteId'])) {
                $actual->docente_id = $data['docenteId'];
            }
            if (isset($data['cupoMaximo'])) {
                $ocupados = $actual->exists ? $actual->cupo_maximo - $actual->cupo_disponible : 0;
                if ($data['cupoMaximo'] < $ocupados) {
                    throw ValidationException::withMessages(['cupoMaximo' => ["No puede ser menor que los cupos ya ocupados ({$ocupados})"]]);
                }
                $actual->cupo_maximo = $data['cupoMaximo'];
                $actual->cupo_disponible = $data['cupoMaximo'] - $ocupados;
            }
            $actual->save();

            return $actual->refresh();
        }, 3);
    }

    public function eliminar(Curso $curso): void
    {
        DB::transaction(function () use ($curso) {
            $actual = Curso::lockForUpdate()->findOrFail($curso->id);
            abort_if($actual->matriculas()->where('estado', 'ACTIVA')->exists(), 409, 'El curso tiene matrículas activas y no puede eliminarse');
            abort_if($actual->matriculas()->whereHas('pagos')->exists(), 409, 'El curso tiene historial financiero y no puede eliminarse');
            $actual->matriculas()->delete();
            $actual->delete();
        }, 3);
    }
}
