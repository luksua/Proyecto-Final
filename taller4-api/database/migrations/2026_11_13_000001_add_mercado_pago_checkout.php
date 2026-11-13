<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $t) {
            $t->uuid('mp_external_reference')->nullable()->unique();
            $t->string('mp_preference_id')->nullable();
            $t->text('mp_checkout_url')->nullable();
            $t->timestamp('mp_checkout_requested_at')->nullable();
            $t->boolean('requiere_revision')->default(false);
        });
        Schema::create('mercado_pago_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pago_id')->constrained('pagos')->restrictOnDelete();
            $t->string('provider_payment_id', 80)->unique();
            $t->string('status', 40);
            $t->timestamp('provider_updated_at', 6);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercado_pago_receipts');
        Schema::table('pagos', fn (Blueprint $t) => $t->dropColumn(['mp_external_reference', 'mp_preference_id', 'mp_checkout_url', 'mp_checkout_requested_at', 'requiere_revision']));
    }
};
