<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->timestamp('purged_at')->nullable()->after('motivo_rechazo');

            $table->foreignId('purged_by_id')
                ->nullable()
                ->after('purged_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('purge_reason', 30)
                ->nullable()
                ->after('purged_by_id');

            $table->index(['purged_at']);
            $table->index(['estado', 'purged_at']);
            $table->index(['cliente_id', 'estado', 'purged_at']);
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropIndex(['purged_at']);
            $table->dropIndex(['estado', 'purged_at']);
            $table->dropIndex(['cliente_id', 'estado', 'purged_at']);

            $table->dropConstrainedForeignId('purged_by_id');
            $table->dropColumn(['purged_at', 'purge_reason']);
        });
    }
};
