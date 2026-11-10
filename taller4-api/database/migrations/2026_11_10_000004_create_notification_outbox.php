<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_outbox', function (Blueprint $t) {
            $t->id();
            $t->string('event_key', 100)->unique();
            $t->unsignedBigInteger('user_id');
            $t->string('tipo', 60);
            $t->unsignedBigInteger('resource_id');
            $t->timestamp('created_at');
            $t->timestamp('dispatched_at')->nullable();
            $t->timestamp('delivered_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_outbox');
    }
};
