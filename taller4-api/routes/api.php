<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\DocenteController;
use App\Http\Controllers\EstudianteController;
use App\Http\Controllers\IdentidadController;
use App\Http\Controllers\MatriculaController;
use App\Http\Controllers\OperacionController;
use App\Http\Controllers\PagoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Pública: entrega el token
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/oidc/inicio', [IdentidadController::class, 'inicio'])->middleware('throttle:login');
    Route::post('auth/oidc/simulada', [IdentidadController::class, 'simulada'])->middleware('throttle:login');
    Route::post('auth/oidc/intercambio', [IdentidadController::class, 'intercambio'])->middleware('throttle:login');
    Route::get('health', [OperacionController::class, 'health'])->middleware('throttle:60,1');
    Route::get('configuracion', [OperacionController::class, 'configuracion'])->middleware('throttle:60,1');

    // Todo lo demás exige token válido
    Route::middleware(['jwt', 'throttle:api'])->group(function () {
        Route::get('notificaciones', [OperacionController::class, 'notificaciones']);
        Route::get('metricas', [OperacionController::class, 'metricas'])->middleware('role:ADMIN');
        Route::get('auditoria', [OperacionController::class, 'auditoria'])->middleware('role:ADMIN');
        Route::middleware('role:ADMIN,ESTUDIANTE')->group(function () {
            Route::get('pagos', [PagoController::class, 'index']);
            Route::post('pagos', [PagoController::class, 'store']);
            Route::get('pagos/{pago}', [PagoController::class, 'show']);
            Route::post('pagos/{pago}/procesar', [PagoController::class, 'procesar']);
            Route::post('pagos/{pago}/reembolsar', [PagoController::class, 'reembolsar'])->middleware('role:ADMIN')->name('pagos.reembolsar');
        });

        // Estudiantes
        Route::get('estudiantes', [EstudianteController::class, 'index'])->middleware('role:ADMIN,DOCENTE');
        Route::post('estudiantes', [EstudianteController::class, 'store'])->middleware('role:ADMIN');
        Route::get('estudiantes/{estudiante}', [EstudianteController::class, 'show']);
        Route::delete('estudiantes/{estudiante}', [EstudianteController::class, 'destroy'])->middleware('role:ADMIN');

        // Docentes
        Route::get('docentes', [DocenteController::class, 'index']);
        Route::post('docentes', [DocenteController::class, 'store'])->middleware('role:ADMIN');
        Route::delete('docentes/{docente}', [DocenteController::class, 'destroy'])->middleware('role:ADMIN');
        Route::get('docentes/{docente}/cursos', [DocenteController::class, 'cursos']);

        // Cursos
        Route::get('cursos', [CursoController::class, 'index']);
        Route::get('cursos/{curso}', [CursoController::class, 'show']);
        Route::post('cursos', [CursoController::class, 'store'])->middleware('role:ADMIN');
        Route::put('cursos/{curso}', [CursoController::class, 'update'])->middleware('role:ADMIN');
        Route::patch('cursos/{curso}', [CursoController::class, 'patch'])->middleware('role:ADMIN');
        Route::delete('cursos/{curso}', [CursoController::class, 'destroy'])->middleware('role:ADMIN');

        // Matrículas
        Route::post('matriculas', [MatriculaController::class, 'store'])->middleware('role:ESTUDIANTE,ADMIN');
        Route::get('matriculas', [MatriculaController::class, 'index']);
        Route::get('matriculas/{matricula}', [MatriculaController::class, 'show']);
        Route::delete('matriculas/{matricula}', [MatriculaController::class, 'destroy']);
    });
});
