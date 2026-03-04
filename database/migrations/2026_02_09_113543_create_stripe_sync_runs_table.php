<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stripe_sync_runs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->boolean('solo_activas')->default(true);
            $table->boolean('backfill_invoices')->default(false);
            $table->unsignedInteger('chunk_size')->default(50);

            $table->string('status', 20)->default('queued'); // queued|running|finished|failed

            $table->unsignedInteger('total')->default(0);

            $table->unsignedInteger('subs_updated')->default(0);
            $table->unsignedInteger('subs_no_change')->default(0);
            $table->unsignedInteger('skipped_invalid')->default(0);

            $table->unsignedInteger('inv_created')->default(0);
            $table->unsignedInteger('inv_skipped')->default(0);
            $table->unsignedInteger('inv_errors')->default(0);

            $table->unsignedInteger('errors')->default(0);

            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_sync_runs');
    }
};
