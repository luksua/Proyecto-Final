<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CursoCache
{
    public static function version(): string
    {
        return (string) Cache::get('cursos:version', 'inicial');
    }

    public static function invalidar(): void
    {
        // A changed namespace invalidates every cached filter/page without cache tags or Redis.
        Cache::forever('cursos:version', (string) Str::uuid());
    }
}
