<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            if (!Schema::hasColumn('documentos', 'purge_note')) {
                $table->text('purge_note')->nullable()->after('purge_reason');
            }

            if (!Schema::hasColumn('documentos', 'hidden_in_portal')) {
                $table->boolean('hidden_in_portal')->default(false)->after('purge_note');
                $table->index('hidden_in_portal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            if (Schema::hasColumn('documentos', 'hidden_in_portal')) {
                $table->dropIndex(['hidden_in_portal']);
                $table->dropColumn('hidden_in_portal');
            }

            if (Schema::hasColumn('documentos', 'purge_note')) {
                $table->dropColumn('purge_note');
            }
        });
    }
};
