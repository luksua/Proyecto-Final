<?php

namespace App\Http\Requests;

class IdentidadRequest extends ApiRequest
{
    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'inicio' => ['codeChallenge' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{43}$/']],
            'simulada' => ['state' => ['required', 'string', 'size:64'], 'email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:1024']],
            default => ['state' => ['required', 'string', 'size:64'], 'code' => ['required', 'string', 'max:4096'],
                'codeVerifier' => ['required', 'string', 'min:43', 'max:128', 'regex:/^[A-Za-z0-9._~-]+$/']],
        };
    }
}
