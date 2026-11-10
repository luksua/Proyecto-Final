<?php

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Matricula;
use App\Models\Pago;
use App\Models\PagoTransaccion;
use App\Models\User;
use App\Services\MatriculaService;
use App\Services\PagoService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// CLI-only integration test. Creates its own schema; never drops databases or clears gestion.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function selectSchema(string $name): void
{
    if (! preg_match('/^integrador_test_[0-9_]+$/', $name)) {
        throw new RuntimeException('Invalid test schema');
    }
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => $name, 'cache.default' => 'array',
        'queue.default' => 'database', 'integrations.payments.driver' => 'simulado', 'integrations.identity.driver' => 'simulado']);
    DB::purge('mysql');
}
if (($argv[1] ?? '') === 'worker') {
    selectSchema($argv[2]);
    [$action, $uid, $resourceId, $signal] = array_slice($argv, 3);
    file_put_contents($signal.'.'.getmypid().'.ready', 'ready');
    $deadline = microtime(true) + 20;
    while (! is_file($signal) && microtime(true) < $deadline) {
        usleep(10000);
    }
    try {
        $u = User::findOrFail($uid);
        if ($action === 'matricular') {
            app(MatriculaService::class)->crear(['estudianteId' => $u->estudiante_id, 'cursoId' => (int) $resourceId, 'periodo' => '2026-2'], $u);
        } elseif ($action === 'cancelar') {
            app(MatriculaService::class)->cancelar(Matricula::findOrFail($resourceId), $u);
        } elseif ($action === 'pagar') {
            app(PagoService::class)->procesar(Pago::findOrFail($resourceId), $u, 'misma-clave-concurrente', 'APROBADO');
        }
        echo json_encode(['status' => 200]);
    } catch (HttpExceptionInterface $e) {
        echo json_encode(['status' => $e->getStatusCode()]);
    } catch (Throwable $e) {
        echo json_encode(['status' => 500, 'type' => get_class($e)]);
    }
    exit;
}

$schema = 'integrador_test_'.date('Ymd_His').'_'.random_int(100, 999);
DB::connection('mysql')->statement("CREATE DATABASE `$schema` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
selectSchema($schema);
$exit = Artisan::call('migrate', ['--force' => true]);
if ($exit !== 0) {
    echo Artisan::output();
    exit(1);
}
$app->instance('env', 'testing');
$dir = storage_path('framework/testing/concurrency/'.$schema);
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

function parallel(array $tasks, string $schema, string $dir): array
{
    $signal = $dir.'/go_'.uniqid();
    $processes = [];
    foreach ($tasks as [$action, $uid, $id]) {
        $pipes = [];
        $proc = proc_open([PHP_BINARY, __FILE__, 'worker', $schema, $action, (string) $uid, (string) $id, $signal],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 2), null, ['bypass_shell' => true]);
        fclose($pipes[0]);
        $processes[] = [$proc, $pipes];
    }
    $deadline = microtime(true) + 15;
    while (count(glob($signal.'.*.ready')) < count($tasks) && microtime(true) < $deadline) {
        usleep(20000);
    }
    if (count(glob($signal.'.*.ready')) !== count($tasks)) {
        throw new RuntimeException('Workers did not reach barrier');
    }
    file_put_contents($signal, 'go');
    $statuses = [];
    foreach ($processes as [$proc, $pipes]) {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $result = json_decode($out, true);
        if (! $result) {
            throw new RuntimeException('Invalid worker result: '.substr($err, 0, 150));
        }
        $statuses[] = $result['status'];
    }

    return array_count_values($statuses);
}
function verify(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException($label);
    }
}

$docente = Docente::create(['nombres' => 'Docente', 'apellidos' => 'Concurrencia', 'correo' => 'docente.concurrente@example.test', 'departamento' => 'Pruebas']);
$makeCourse = fn ($codigo, $cupos) => Curso::create(['codigo' => $codigo, 'nombre' => 'Prueba concurrente', 'creditos' => 3, 'periodo' => '2026-2',
    'estado' => 'ABIERTO', 'docente_id' => $docente->id, 'cupo_maximo' => $cupos, 'cupo_disponible' => $cupos]);
$users = [];
for ($i = 0; $i < 8; $i++) {
    $e = Estudiante::create(['documento' => 'concurrente-'.$i, 'nombres' => 'Estudiante', 'apellidos' => 'Prueba', 'correo' => "est$i@example.test", 'programa' => 'Sistemas']);
    $users[] = User::create(['name' => 'Prueba', 'email' => "user$i@example.test", 'password' => 'solo-pruebas', 'rol' => 'ESTUDIANTE', 'estudiante_id' => $e->id]);
}
$curso = $makeCourse('CONC-ULTIMO', 2);
$cupos = parallel(array_map(fn ($u) => ['matricular', $u->id, $curso->id], $users), $schema, $dir);
verify(($cupos[200] ?? 0) === 2 && ($cupos[409] ?? 0) === 6 && $curso->fresh()->cupo_disponible === 0, 'Last places invariant failed');
$duplicado = $makeCourse('CONC-DUPLICADO', 8);
$dups = parallel(array_fill(0, 8, ['matricular', $users[0]->id, $duplicado->id]), $schema, $dir);
verify(($dups[200] ?? 0) === 1 && ($dups[409] ?? 0) === 7 && $duplicado->fresh()->cupo_disponible === 7, 'Duplicate invariant failed');
$m = Matricula::where('curso_id', $duplicado->id)->first();
$cancel = parallel(array_fill(0, 8, ['cancelar', $users[0]->id, $m->id]), $schema, $dir);
verify(($cancel[200] ?? 0) === 1 && ($cancel[409] ?? 0) === 7 && $duplicado->fresh()->cupo_disponible === 8, 'Cancellation invariant failed');
$m = Matricula::where('curso_id', $curso->id)->first();
$owner = User::where('estudiante_id', $m->estudiante_id)->first();
$p = app(PagoService::class)->crear($m->id, $owner);
$pagos = parallel(array_fill(0, 8, ['pagar', $owner->id, $p->id]), $schema, $dir);
verify(($pagos[200] ?? 0) === 8 && PagoTransaccion::where('pago_id', $p->id)->count() === 1 && $p->fresh()->estado === 'APROBADO', 'Payment idempotency failed');
$result = ['schema' => $schema, 'workers_per_case' => 8, 'last_places' => $cupos, 'duplicates' => $dups, 'cancellation' => $cancel, 'payments' => $pagos, 'result' => 'passed'];
file_put_contents($dir.'/results.json', json_encode($result, JSON_PRETTY_PRINT));
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
// Preserve the isolated schema for inspection. No DROP, truncate or migrate:fresh is used.
