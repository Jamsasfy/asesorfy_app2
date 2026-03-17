<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('portal_activo')->default(true)->after('acceso_app');
            $table->timestamp('cuenta_activada_at')->nullable()->after('portal_activo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['portal_activo', 'cuenta_activada_at']);
        });
    }
};
