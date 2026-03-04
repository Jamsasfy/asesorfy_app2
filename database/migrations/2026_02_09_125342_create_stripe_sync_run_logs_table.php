<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stripe_sync_run_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stripe_sync_run_id')
                ->constrained('stripe_sync_runs')
                ->cascadeOnDelete();

            $table->string('level', 20)->default('error'); // info|warning|error
            $table->foreignId('cliente_suscripcion_id')->nullable()->constrained('cliente_suscripciones')->nullOnDelete();

            $table->string('stripe_subscription_id')->nullable();
            $table->string('message', 1024);
            $table->json('context')->nullable();

            $table->timestamps();

            $table->index(['stripe_sync_run_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_sync_run_logs');
    }
};
