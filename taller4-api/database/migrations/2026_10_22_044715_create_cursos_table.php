<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->unsignedTinyInteger('creditos');
            $table->string('periodo', 10);
            $table->unsignedSmallInteger('cupo_maximo');
            $table->unsignedSmallInteger('cupo_disponible');
            $table->string('estado')->default('ABIERTO');
            $table->foreignId('docente_id')->constrained('docentes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cursos');
    }
};
