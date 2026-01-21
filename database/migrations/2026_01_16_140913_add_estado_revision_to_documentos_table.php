<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->string('estado', 20)->default('pendiente')->after('verificado');
            $table->timestamp('revisado_at')->nullable()->after('estado');
            $table->foreignId('revisado_por_id')->nullable()->after('revisado_at')
                ->constrained('users')->nullOnDelete();

            $table->text('motivo_rechazo')->nullable()->after('observaciones');

            $table->index(['estado']);
            $table->index(['cliente_id', 'estado']);
        });

        // Backfill desde el boolean actual
        DB::table('documentos')
            ->where('verificado', 1)
            ->update(['estado' => 'verificado']);

        DB::table('documentos')
            ->where('verificado', 0)
            ->update(['estado' => 'pendiente']);
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropIndex(['estado']);
            $table->dropIndex(['cliente_id', 'estado']);

            $table->dropConstrainedForeignId('revisado_por_id');
            $table->dropColumn(['estado', 'revisado_at', 'motivo_rechazo']);
        });
    }
};
