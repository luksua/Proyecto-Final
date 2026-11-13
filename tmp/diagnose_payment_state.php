<?php
require __DIR__.'/../taller4-api/vendor/autoload.php';
$app = require __DIR__.'/../taller4-api/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode([
    'pagos' => Illuminate\Support\Facades\DB::table('pagos')->whereIn('id', [1, 2])->get(['id', 'matricula_id', 'estado', 'proveedor', 'mp_checkout_requested_at']),
    'matriculas' => Illuminate\Support\Facades\DB::table('matriculas')->whereIn('id', [1, 2])->get(['id', 'estado']),
], JSON_PRETTY_PRINT);
