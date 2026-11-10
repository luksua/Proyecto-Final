<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificacionService
{
    public static function registrar(int $estudianteId, string $tipo, int $resourceId, string $key): void
    {
        $user = User::where('estudiante_id', $estudianteId)->where('rol', 'ESTUDIANTE')->first();
        if (! $user) {
            return;
        }
        DB::table('notification_outbox')->insertOrIgnore(['event_key' => $key, 'user_id' => $user->id,
            'tipo' => $tipo, 'resource_id' => $resourceId, 'created_at' => now()]);
    }
}
