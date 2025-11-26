<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('proyectos', 'lead_id')) {
            Schema::table('proyectos', function (Blueprint $table) {
                // Añadimos la columna nullable por si hay proyectos antiguos sin lead
                $table->foreignId('lead_id')
                      ->nullable()
                      ->after('cliente_id') // Para mantener orden visual
                      ->constrained('leads')
                      ->nullOnDelete(); // Si se borra el lead, el proyecto sigue existiendo (lead_id = null)
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('proyectos', 'lead_id')) {
            Schema::table('proyectos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('lead_id');
            });
        }
    }
};