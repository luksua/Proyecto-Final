<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CursoRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? ['sometimes', 'required'] : ['required'];

        return [
            'codigo' => [...$required, 'string', 'max:20', Rule::unique('cursos', 'codigo')->ignore($this->route('curso')?->id)],
            'nombre' => [...$required, 'string', 'max:150'],
            'creditos' => [...$required, 'integer', 'between:1,10'],
            'cupoMaximo' => [...$required, 'integer', 'between:1,500'],
            'periodo' => [...$required, 'string', 'regex:/^\d{4}-[12]$/'],
            'docenteId' => [...$required, 'integer', 'exists:docentes,id'],
            'estado' => ['sometimes', 'required', Rule::in(['ABIERTO', 'CERRADO'])],
        ];
    }
}
