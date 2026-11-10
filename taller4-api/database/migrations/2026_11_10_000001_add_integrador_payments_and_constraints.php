<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('matriculas')->where('estado', 'ACTIVA')->select('estudiante_id', 'curso_id', 'periodo')
            ->groupBy('estudiante_id', 'curso_id', 'periodo')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Existen matrículas activas duplicadas. Resolverlas antes de aplicar esta migración; no se modificaron los registros.');
        }
        Schema::table('matriculas', function (Blueprint $table) {
            // NULL permits any number of cancelled historical enrolments.
            $table->unsignedTinyInteger('activa_unica')->nullable()->virtualAs("CASE WHEN estado = 'ACTIVA' THEN 1 ELSE NULL END");
            $table->unique(['estudiante_id', 'curso_id', 'periodo', 'activa_unica'], 'matricula_activa_unique');
            $table->index(['estudiante_id', 'periodo', 'estado'], 'matricula_estudiante_filtro');
            $table->index(['curso_id', 'periodo', 'estado'], 'matricula_curso_filtro');
        });
        Schema::table('cursos', fn (Blueprint $t) => $t->index(['periodo', 'estado'], 'curso_oferta_filtro'));
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->unique()->constrained('matriculas')->restrictOnDelete();
            $table->decimal('valor', 12, 2);
            $table->char('moneda', 3)->default('COP');
            $table->string('estado', 20)->default('PENDIENTE')->index();
            $table->string('proveedor', 30);
            $table->string('referencia', 100)->nullable()->unique();
            $table->timestamp('procesado_at')->nullable();
            $table->timestamps();
        });
        Schema::create('pago_transacciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->restrictOnDelete();
            $table->unsignedBigInteger('actor_id');
            $table->string('idempotency_key', 100);
            $table->char('solicitud_hash', 64);
            $table->string('accion', 20);
            $table->string('estado', 20);
            $table->string('referencia', 100);
            $table->timestamps();
            $table->unique(['actor_id', 'idempotency_key'], 'pago_transaccion_idempotencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_transacciones');
        Schema::dropIfExists('pagos');
        Schema::table('cursos', fn (Blueprint $t) => $t->dropIndex('curso_oferta_filtro'));
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropUnique('matricula_activa_unique');
            $table->dropIndex('matricula_estudiante_filtro');
            $table->dropIndex('matricula_curso_filtro');
            $table->dropColumn('activa_unica');
        });
    }
};
