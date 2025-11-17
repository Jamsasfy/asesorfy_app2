<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('autospam_activo')
                ->default(true)
                ->after('ultima_interaccion_manual_at'); // ajusta el `after` si lo necesitas
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('autospam_activo');
        });
    }
};

