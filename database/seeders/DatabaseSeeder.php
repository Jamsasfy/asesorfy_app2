<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

         // 1. Llama primero a los seeders de datos base/lookup
         $this->call([
           
            OficinaSeeder::class,
            ProcedenciaSeeder::class,
            DepartamentoSeeder::class,
            TipoClienteSeeder::class,
            MotivoDescarteSeeder::class,
            DocumentoCategoriaSeeder::class,
            DocumentoSubtipoSeeder::class,
            VariablesConfiguracionSeeder::class,


             
        ]);

        
        // User::factory(10)->create();
       /*  User::create([
            'name'     => 'Admin',
            'email'    => 'admin@admin.com',
            'password' => Hash::make('75775990'),
        
        ]); */
    }
}
