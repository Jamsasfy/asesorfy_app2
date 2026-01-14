<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->boolean('bloquea_recurrente')
                ->default(false)
                ->after('requiere_proyecto_activacion');

            $table->index('bloquea_recurrente');
        });
    }

    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->dropIndex(['bloquea_recurrente']);
            $table->dropColumn('bloquea_recurrente');
        });
    }
};

