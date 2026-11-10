<?php

return [
    // Clave con la que se firman los tokens. Por defecto usa APP_KEY.
    'secret' => env('JWT_SECRET', env('APP_KEY')),

    // Duración del token en segundos (900 = 15 minutos)
    'ttl' => (int) env('JWT_TTL', 900),
    'issuer' => env('JWT_ISSUER', 'universidad-api'),
    'audience' => env('JWT_AUDIENCE', 'universidad-web'),
];
