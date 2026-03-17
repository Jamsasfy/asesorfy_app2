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
            $table->dropForeign(['documento_id']);
            $table->dropColumn('documento_id');
        });
    }

    public function down(): void
    {
        Schema::table('contratos_responsabilidad', function (Blueprint $table) {
            $table->foreignId('documento_id')->nullable()->constrained('documentos')->nullOnDelete();
        });
    }
};
