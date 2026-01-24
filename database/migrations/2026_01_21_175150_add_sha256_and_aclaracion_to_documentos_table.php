<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            // =========================
            // SHA256 (posibles duplicados por contenido)
            // =========================
            $table->char('file_sha256', 64)
                ->nullable()
                ->after('mime_type');

            // índice para buscar rápido duplicados por cliente + hash (NO unique)
            $table->index(['cliente_id', 'file_sha256'], 'documentos_cliente_sha256_idx');

            // =========================
            // ACLARACIÓN (solo texto)
            // =========================
            $table->text('aclaracion_pregunta')
                ->nullable()
                ->after('motivo_rechazo');

            $table->timestamp('aclaracion_at')
                ->nullable()
                ->after('aclaracion_pregunta');

            $table->text('aclaracion_respuesta')
                ->nullable()
                ->after('aclaracion_at');

            $table->timestamp('aclaracion_respondida_at')
                ->nullable()
                ->after('aclaracion_respuesta');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropIndex('documentos_cliente_sha256_idx');

            $table->dropColumn([
                'file_sha256',
                'aclaracion_pregunta',
                'aclaracion_at',
                'aclaracion_respuesta',
                'aclaracion_respondida_at',
            ]);
        });
    }
};
