<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_comisiones', function (Blueprint $table) {
            $table->id();
            $table->json('emails_notificacion_despido');
            $table->integer('meses_consecutivos_despido')->default(3);
            $table->integer('meses_alternos_despido')->default(5);
            $table->integer('periodo_meses_alternos')->default(12);
            $table->timestamps();
        });

        DB::table('configuracion_comisiones')->insert([
            'emails_notificacion_despido' => json_encode([]),
            'meses_consecutivos_despido'  => 3,
            'meses_alternos_despido'      => 5,
            'periodo_meses_alternos'      => 12,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_comisiones');
    }
};
