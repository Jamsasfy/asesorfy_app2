<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


return new class extends Migration
{
    public function up(): void
    {
        // Evitamos errores si ya existe un usuario 9999
        $exists = DB::table('users')->where('id', 9999)->exists();
        if (! $exists) {
            DB::table('users')->insert([
                'id' => 9999,
                'name' => 'Boot IA Fy',
                'email' => 'boot-ia@asesorfy.com',
                'password' => Hash::make(Str::random(32)), // inaccesible
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('users')->where('id', 9999)->delete();
    }
};
