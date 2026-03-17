<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificaciones_portal', function (Blueprint $table) {
            $table->boolean('enviada')->default(false)->after('activa');
            $table->timestamp('enviada_at')->nullable()->after('enviada');
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones_portal', function (Blueprint $table) {
            $table->dropColumn(['enviada', 'enviada_at']);
        });
    }
};
