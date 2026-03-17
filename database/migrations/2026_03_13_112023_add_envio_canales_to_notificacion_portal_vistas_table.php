<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificacion_portal_vistas', function (Blueprint $table) {
            $table->boolean('enviado_email')->default(false)->after('canal_recibido');
            $table->boolean('enviado_telegram')->default(false)->after('enviado_email');
            $table->timestamp('enviado_email_at')->nullable()->after('enviado_telegram');
            $table->timestamp('enviado_telegram_at')->nullable()->after('enviado_email_at');
        });
    }

    public function down(): void
    {
        Schema::table('notificacion_portal_vistas', function (Blueprint $table) {
            $table->dropColumn(['enviado_email', 'enviado_telegram', 'enviado_email_at', 'enviado_telegram_at']);
        });
    }
};
