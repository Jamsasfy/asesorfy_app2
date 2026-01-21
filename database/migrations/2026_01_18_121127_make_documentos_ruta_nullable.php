<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            // Requiere doctrine/dbal para change()
            $table->string('ruta')->nullable()->change();
            $table->string('mime_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->string('ruta')->nullable(false)->change();
            $table->string('mime_type')->nullable(false)->change();
        });
    }
};
