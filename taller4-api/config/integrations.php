<?php

return [
    'payments' => [
        'driver' => env('PAYMENTS_DRIVER', 'simulado'),
        // Demonstration tariff only; never a value supplied by the browser.
        'precio_credito_centavos' => (int) env('PAYMENTS_CREDIT_PRICE_CENTS', 5000000),
        'moneda' => env('PAYMENTS_CURRENCY', 'COP'),
        'simulados_habilitados' => (bool) env('SIMULATED_INTEGRATIONS_ENABLED', true),
        'soap_wsdl' => env('BANK_SOAP_WSDL'),
        'soap_certificado' => env('BANK_SOAP_CERTIFICATE'),
    ],
    'identity' => [
        'driver' => env('IDENTITY_DRIVER', 'simulado'),
        'issuer' => env('OIDC_ISSUER'),
        'client_id' => env('OIDC_CLIENT_ID'),
        'client_secret' => env('OIDC_CLIENT_SECRET'),
        'redirect_uri' => env('OIDC_REDIRECT_URI', 'http://127.0.0.1:8000/app/index.html'),
        'authorization_endpoint' => env('OIDC_AUTHORIZATION_ENDPOINT'),
        'token_endpoint' => env('OIDC_TOKEN_ENDPOINT'),
        'jwks_uri' => env('OIDC_JWKS_URI'),
    ],
];
