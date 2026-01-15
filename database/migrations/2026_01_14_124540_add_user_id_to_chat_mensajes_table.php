<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Columna (si no existe)
        Schema::table('chat_mensajes', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_mensajes', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('chat_id');
            }
        });

        // 2) Índice con nombre propio (si no existe)
        $idxExists = DB::selectOne("
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'chat_mensajes'
              AND INDEX_NAME = 'chat_mensajes_user_id_idx'
            LIMIT 1
        ");

        if (! $idxExists) {
            Schema::table('chat_mensajes', function (Blueprint $table) {
                $table->index('user_id', 'chat_mensajes_user_id_idx');
            });
        }

        // 3) FK con nombre propio (si no existe)
        $fkExists = DB::selectOne("
            SELECT 1
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'chat_mensajes'
              AND COLUMN_NAME = 'user_id'
              AND REFERENCED_TABLE_NAME = 'users'
            LIMIT 1
        ");

        if (! $fkExists) {
            Schema::table('chat_mensajes', function (Blueprint $table) {
                $table->foreign('user_id', 'chat_mensajes_user_id_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // FK (si existe)
        $fkExists = DB::selectOne("
            SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = 'chat_mensajes'
              AND CONSTRAINT_NAME = 'chat_mensajes_user_id_fk'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            LIMIT 1
        ");

        if ($fkExists) {
            Schema::table('chat_mensajes', function (Blueprint $table) {
                $table->dropForeign('chat_mensajes_user_id_fk');
            });
        }

        // Índice (si existe)
        $idxExists = DB::selectOne("
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'chat_mensajes'
              AND INDEX_NAME = 'chat_mensajes_user_id_idx'
            LIMIT 1
        ");

        if ($idxExists) {
            Schema::table('chat_mensajes', function (Blueprint $table) {
                $table->dropIndex('chat_mensajes_user_id_idx');
            });
        }

        // Columna (si existe)
        Schema::table('chat_mensajes', function (Blueprint $table) {
            if (Schema::hasColumn('chat_mensajes', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });
    }
};
