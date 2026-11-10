<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identidades_externas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('issuer', 255);
            $table->string('subject', 255);
            $table->timestamps();
            $table->unique(['issuer', 'subject']);
        });
        Schema::create('identity_flows', function (Blueprint $table) {
            $table->id();
            $table->char('state_hash', 64)->unique();
            $table->string('driver', 20);
            $table->string('nonce', 64);
            $table->string('challenge', 43);
            $table->char('code_hash', 64)->nullable();
            $table->string('subject')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_flows');
        Schema::dropIfExists('identidades_externas');
    }
};
