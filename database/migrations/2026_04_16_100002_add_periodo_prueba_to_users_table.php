<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('fecha_inicio_comercial')->nullable()->after('email');
            $table->tinyInteger('meses_prueba')->default(3)->nullable()->after('fecha_inicio_comercial');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fecha_inicio_comercial', 'meses_prueba']);
        });
    }
};
