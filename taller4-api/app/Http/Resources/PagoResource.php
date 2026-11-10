<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'matriculaId' => $this->matricula_id,
            'valor' => $this->valor, 'moneda' => $this->moneda, 'estado' => $this->estado,
            'proveedor' => $this->proveedor, 'simulado' => in_array($this->proveedor, ['simulado', 'soap_simulado']),
            'referencia' => $this->referencia, 'fechaCreacion' => $this->created_at?->toIso8601String(),
            'fechaProcesamiento' => $this->procesado_at?->toIso8601String(),
            'matricula' => $this->whenLoaded('matricula', fn () => new MatriculaResource($this->matricula)),
            'transacciones' => $this->whenLoaded('transacciones', fn () => $this->transacciones->map(fn ($t) => [
                'id' => $t->id, 'accion' => $t->accion, 'estado' => $t->estado,
                'referencia' => $t->referencia, 'fecha' => $t->created_at->toIso8601String(),
            ])),
        ];
    }
}
