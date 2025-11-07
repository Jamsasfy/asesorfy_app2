<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('clientes', 'lead_id')) {
            Schema::table('clientes', function (Blueprint $table) {
                // Elimina la FK y la columna en un paso
                $table->dropConstrainedForeignId('lead_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'lead_id')) {
                $table->foreignId('lead_id')
                    ->nullable()
                    ->constrained('leads')
                    ->nullOnDelete();
            }
        });
    }
};
