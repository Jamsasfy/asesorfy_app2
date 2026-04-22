<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comercial_historial_objetivos', function (Blueprint $table) {
            $table->json('datos_adicionales')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('comercial_historial_objetivos', function (Blueprint $table) {
            $table->dropColumn('datos_adicionales');
        });
    }
};
