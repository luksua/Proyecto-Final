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
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol')->default('ESTUDIANTE');             // ESTUDIANTE | DOCENTE | ADMIN
            $table->unsignedBigInteger('estudiante_id')->nullable();  // ficha de estudiante del usuario
            $table->unsignedBigInteger('docente_id')->nullable();     // ficha de docente del usuario
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rol', 'estudiante_id', 'docente_id']);
        });
    }
};
