<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_conversion_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();

            $table->string('token', 64)->unique()->index();
            $table->string('mode', 20)->index(); // 'automatico' | 'manual' (origen)
            $table->string('email_to')->nullable();

            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('used_at')->nullable()->index();

            $table->json('meta')->nullable(); // servicios preseleccionados, etc.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_conversion_links');
    }
};
