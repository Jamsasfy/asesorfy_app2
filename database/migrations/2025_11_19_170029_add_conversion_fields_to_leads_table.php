<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('conversion_mode')->nullable()->after('estado'); // 'manual' | 'automatic'
            $table->string('form_token', 64)->nullable()->unique()->after('conversion_mode');
            $table->timestamp('form_submitted_at')->nullable()->after('form_token');
            $table->string('contract_pdf_path')->nullable()->after('form_submitted_at');
            $table->timestamp('contract_signed_at')->nullable()->after('contract_pdf_path');
        });
    }

    public function down(): void {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'conversion_mode',
                'form_token',
                'form_submitted_at',
                'contract_pdf_path',
                'contract_signed_at',
            ]);
        });
    }
};
