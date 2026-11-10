<?php

return [
    'require_https' => (bool) env('REQUIRE_HTTPS', true),
    'trusted_proxies' => array_values(array_filter(explode(',', env('TRUSTED_PROXIES', '')))),
    'api_per_minute' => (int) env('API_RATE_LIMIT', 120),
    'auth_per_minute' => (int) env('AUTH_RATE_LIMIT', 10),
];
