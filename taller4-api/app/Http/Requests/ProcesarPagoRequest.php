<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ProcesarPagoRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotencyKey' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return [
            'idempotencyKey' => ['required', 'string', 'min:8', 'max:100', 'regex:/^[a-zA-Z0-9_.:-]+$/'],
            'resultado' => [$this->routeIs('pagos.reembolsar') ? 'prohibited' : 'required', Rule::in(['APROBADO', 'RECHAZADO', 'ERROR'])],
            'valor' => ['prohibited'], 'tarjeta' => ['prohibited'],
        ];
    }
}
