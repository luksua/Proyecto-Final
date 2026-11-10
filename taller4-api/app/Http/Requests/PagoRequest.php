<?php

namespace App\Http\Requests;

class PagoRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['matriculaId' => ['required', 'integer', 'exists:matriculas,id'],
            'valor' => ['prohibited'], 'tarjeta' => ['prohibited']];
    }
}
