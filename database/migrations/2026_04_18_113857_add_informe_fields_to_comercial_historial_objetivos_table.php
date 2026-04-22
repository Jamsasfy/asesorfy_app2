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
        Schema::table('comercial_historial_objetivos', function (Blueprint $table) {
            $table->string('informe_pdf_path')->nullable()->after('alcanzo_todos_minimos_obligatorios');
            $table->string('informe_hash', 64)->nullable()->after('informe_pdf_path');
            $table->timestamp('informe_generado_at')->nullable()->after('informe_hash');
        });
    }

    public function down(): void
    {
        Schema::table('comercial_historial_objetivos', function (Blueprint $table) {
            $table->dropColumn(['informe_pdf_path', 'informe_hash', 'informe_generado_at']);
        });
    }
};
