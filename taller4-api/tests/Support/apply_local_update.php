<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local')) {
    echo 'Este verificador solo aplica actualizaciones en el entorno local.'.PHP_EOL;
    exit(1);
}
$db = DB::connection();
$columns = [];
$before = [];
$counts = [];
foreach (['users', 'estudiantes', 'docentes', 'cursos', 'matriculas'] as $table) {
    $columns[$table] = Schema::getColumnListing($table);
    $rows = $db->table($table)->select($columns[$table])->orderBy('id')->get();
    $counts[$table] = $rows->count();
    $before[$table] = hash('sha256', $rows->toJson());
}
$status = Artisan::call('migrate', ['--force' => true]);
echo Artisan::output();
if ($status !== 0) {
    exit($status);
}
foreach ($columns as $table => $names) {
    $after = hash('sha256', $db->table($table)->select($names)->orderBy('id')->get()->toJson());
    if (! hash_equals($before[$table], $after)) {
        echo 'Verificar cambios inesperados en '.$table.PHP_EOL;
        exit(1);
    }
}
echo json_encode(['existing_rows_unchanged' => true, 'counts' => $counts, 'database_version' => $db->selectOne('SELECT VERSION() AS version')->version]).PHP_EOL;
