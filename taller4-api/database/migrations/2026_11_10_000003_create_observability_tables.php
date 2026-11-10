<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('actor_id')->nullable()->index();
            $t->string('action', 80);
            $t->string('resource', 40);
            $t->unsignedBigInteger('resource_id');
            $t->uuid('correlation_id')->nullable()->index();
            $t->json('properties')->nullable();
            $t->timestamp('created_at')->index();
        });
        Schema::create('api_metrics', function (Blueprint $t) {
            $t->id();
            $t->date('day');
            $t->string('route', 160);
            $t->string('method', 10);
            $t->unsignedSmallInteger('status');
            $t->unsignedBigInteger('requests')->default(0);
            $t->double('total_ms')->default(0);
            $t->double('max_ms')->default(0);
            $t->unique(['day', 'route', 'method', 'status'], 'api_metric_bucket');
        });
        Schema::create('notificaciones', function (Blueprint $t) {
            $t->id();
            $t->string('event_key', 100)->unique();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('tipo', 60);
            $t->unsignedBigInteger('resource_id');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
        Schema::dropIfExists('api_metrics');
        Schema::dropIfExists('audit_events');
    }
};
