<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$name = 'integrador_test_'.date('Ymd_His').'_'.random_int(1000, 9999);
DB::connection('mysql')->statement("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $name]);
DB::purge('mysql');
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    echo 'Migration failed';
    exit(1);
}
Artisan::call('db:seed', ['--force' => true]);
file_put_contents(storage_path('framework/testing/demo-schema.json'), json_encode(['schema' => $name]));
echo json_encode(['schema' => $name, 'seeded' => true]).PHP_EOL;
