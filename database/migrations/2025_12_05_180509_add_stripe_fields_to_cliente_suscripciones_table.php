<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('cliente_suscripciones', function (Blueprint $table) {
        $table->string('stripe_status')->nullable()->after('stripe_subscription_id');
        $table->timestamp('stripe_current_period_end')->nullable()->after('stripe_status');
        $table->string('stripe_default_payment_method')->nullable()->after('stripe_current_period_end');
    });
}

public function down()
{
    Schema::table('cliente_suscripciones', function (Blueprint $table) {
        $table->dropColumn([
            'stripe_status',
            'stripe_current_period_end',
            'stripe_default_payment_method',
        ]);
    });
}
};
