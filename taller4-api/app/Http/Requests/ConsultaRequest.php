<?php

namespace App\Http\Requests;

class ConsultaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'], 'limit' => ['sometimes', 'integer', 'between:1,100'],
            'periodo' => ['sometimes', 'nullable', 'regex:/^\d{4}-[12]$/'],
            'docenteId' => ['sometimes', 'integer', 'min:1'], 'estudianteId' => ['sometimes', 'integer', 'min:1'],
            'cursoId' => ['sometimes', 'integer', 'min:1'], 'estado' => ['sometimes', 'nullable', 'string', 'max:20'],
            'sort' => ['sometimes', 'string', 'regex:/^(nombre|codigo|creditos|periodo|estado|cupoMaximo|cupoDisponible),(asc|desc)$/'],
            'q' => ['sometimes', 'nullable', 'string', 'max:150'], 'programa' => ['sometimes', 'nullable', 'string', 'max:150'],
            'departamento' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }
}
