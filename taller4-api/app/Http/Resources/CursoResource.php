<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CursoResource extends JsonResource
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
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'creditos' => $this->creditos,
            'periodo' => $this->periodo,
            'cupoMaximo' => $this->cupo_maximo,
            'cupoDisponible' => $this->cupo_disponible,
            'estado' => $this->estado,
            'docente' => $this->whenLoaded('docente', fn () => [
                'id' => $this->docente->id,
                'nombres' => $this->docente->nombres,
                'apellidos' => $this->docente->apellidos,
            ]),
        ];
    }
}
