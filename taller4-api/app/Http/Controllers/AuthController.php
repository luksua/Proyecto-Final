<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // POST /api/v1/auth/login
    public function login(LoginRequest $request, TokenService $tokens)
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            abort(401, 'Credenciales inválidas');
        }

        return response()->json($tokens->emitir($user));
    }
}
