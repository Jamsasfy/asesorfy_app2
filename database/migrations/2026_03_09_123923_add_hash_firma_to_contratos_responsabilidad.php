<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('contratos_responsabilidad', function (Blueprint $table) {
        $table->string('hash_firma', 64)->nullable()->after('ip_firma');
        $table->string('email_firma')->nullable()->after('hash_firma');
    });
}

public function down(): void
{
    Schema::table('contratos_responsabilidad', function (Blueprint $table) {
        $table->dropColumn(['hash_firma', 'email_firma']);
    });
}
};
