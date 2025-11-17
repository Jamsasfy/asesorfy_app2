<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::table('leads', function (Blueprint $table) {
        $table->string('last_notified_estado')->nullable()->after('estado');
        $table->timestamp('last_email_sent_at')->nullable()->after('last_notified_estado');
    });
}
public function down(): void
{
    Schema::table('leads', function (Blueprint $table) {
        $table->dropColumn(['last_notified_estado', 'last_email_sent_at']);
    });
}
};
