<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // OIDC subjects and idempotency keys are opaque and case-sensitive.
        // SQLite already uses binary comparison for these columns.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE identidades_externas MODIFY issuer VARCHAR(255) COLLATE utf8mb4_bin NOT NULL, MODIFY subject VARCHAR(255) COLLATE utf8mb4_bin NOT NULL');
            DB::statement('ALTER TABLE pago_transacciones MODIFY idempotency_key VARCHAR(100) COLLATE utf8mb4_bin NOT NULL');
        }
    }

    public function down(): void
    {
        // Preserve the stricter comparison on rollback to avoid merging distinct identities.
    }
};
