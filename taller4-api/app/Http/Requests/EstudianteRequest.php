<?php

namespace App\Http\Requests;

class EstudianteRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'documento' => ['required', 'string', 'max:20', 'unique:estudiantes,documento'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:150', 'unique:estudiantes,correo'],
            'programa' => ['required', 'string', 'max:150'],
        ];
    }
}
