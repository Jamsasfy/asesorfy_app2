<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =========================
        // Columnas (idempotente)
        // =========================
        Schema::table('documentos', function (Blueprint $table) {
            if (! Schema::hasColumn('documentos', 'duplicate_of_id')) {
                $table->unsignedBigInteger('duplicate_of_id')
                    ->nullable()
                    ->after('file_sha256');
            }

            if (! Schema::hasColumn('documentos', 'duplicate_status')) {
                $table->string('duplicate_status', 20)
                    ->nullable()
                    ->after('duplicate_of_id');
            }

            if (! Schema::hasColumn('documentos', 'duplicate_checked_at')) {
                $table->timestamp('duplicate_checked_at')
                    ->nullable()
                    ->after('duplicate_status');
            }

            if (! Schema::hasColumn('documentos', 'duplicate_checked_by_id')) {
                $table->unsignedBigInteger('duplicate_checked_by_id')
                    ->nullable()
                    ->after('duplicate_checked_at');
            }
        });

        // =========================
        // Índices (idempotente)
        // =========================
        Schema::table('documentos', function (Blueprint $table) {
            if (! $this->indexExists('documentos', 'documentos_duplicate_status_idx')) {
                $table->index(['duplicate_status'], 'documentos_duplicate_status_idx');
            }

            if (! $this->indexExists('documentos', 'documentos_duplicate_of_idx')) {
                $table->index(['duplicate_of_id'], 'documentos_duplicate_of_idx');
            }
        });

        // =========================
        // FKs (idempotente)
        // =========================
        Schema::table('documentos', function (Blueprint $table) {
            if (! $this->foreignKeyExists('documentos', 'documentos_duplicate_of_id_foreign')) {
                $table->foreign('duplicate_of_id', 'documentos_duplicate_of_id_foreign')
                    ->references('id')->on('documentos')
                    ->nullOnDelete();
            }

            if (! $this->foreignKeyExists('documentos', 'documentos_duplicate_checked_by_id_foreign')) {
                $table->foreign('duplicate_checked_by_id', 'documentos_duplicate_checked_by_id_foreign')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            // drop FK si existen
            if ($this->foreignKeyExists('documentos', 'documentos_duplicate_of_id_foreign')) {
                $table->dropForeign('documentos_duplicate_of_id_foreign');
            }

            if ($this->foreignKeyExists('documentos', 'documentos_duplicate_checked_by_id_foreign')) {
                $table->dropForeign('documentos_duplicate_checked_by_id_foreign');
            }

            // drop índices si existen
            if ($this->indexExists('documentos', 'documentos_duplicate_status_idx')) {
                $table->dropIndex('documentos_duplicate_status_idx');
            }

            if ($this->indexExists('documentos', 'documentos_duplicate_of_idx')) {
                $table->dropIndex('documentos_duplicate_of_idx');
            }

            // drop columnas si existen
            $cols = [];
            foreach (['duplicate_of_id', 'duplicate_status', 'duplicate_checked_at', 'duplicate_checked_by_id'] as $col) {
                if (Schema::hasColumn('documentos', $col)) {
                    $cols[] = $col;
                }
            }

            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $db = DB::getDatabaseName();

        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', $db)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();

        return $exists;
    }

    private function foreignKeyExists(string $table, string $fkName): bool
    {
        $db = DB::getDatabaseName();

        $exists = DB::table('information_schema.table_constraints')
            ->where('table_schema', $db)
            ->where('table_name', $table)
            ->where('constraint_name', $fkName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();

        return $exists;
    }
};
