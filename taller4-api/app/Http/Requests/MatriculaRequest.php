<?php

namespace App\Http\Requests;

class MatriculaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'estudianteId' => ['required', 'integer', 'exists:estudiantes,id'],
            'cursoId' => ['required', 'integer', 'exists:cursos,id'],
            'periodo' => ['required', 'string', 'regex:/^\d{4}-[12]$/'],
        ];
    }
}
