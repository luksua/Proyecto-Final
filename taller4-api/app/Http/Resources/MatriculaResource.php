<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatriculaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estudiante' => $this->whenLoaded('estudiante', fn () => [
                'id' => $this->estudiante->id,
                'nombres' => $this->estudiante->nombres,
                'apellidos' => $this->estudiante->apellidos,
            ]),
            'curso' => $this->whenLoaded('curso', fn () => [
                'id' => $this->curso->id,
                'codigo' => $this->curso->codigo,
                'nombre' => $this->curso->nombre,
                'creditos' => $this->curso->creditos,
            ]),
            'periodo' => $this->periodo,
            'estado' => $this->estado,
            'fechaMatricula' => $this->fecha_matricula?->toIso8601String(),
            'pago' => $this->when($request->user()?->rol !== 'DOCENTE' && $this->relationLoaded('pagos'), fn () => $this->pagos->first() ? [
                'id' => $this->pagos->first()->id, 'estado' => $this->pagos->first()->estado,
                'valor' => $this->pagos->first()->valor, 'moneda' => $this->pagos->first()->moneda,
            ] : null),
        ];
    }
}
