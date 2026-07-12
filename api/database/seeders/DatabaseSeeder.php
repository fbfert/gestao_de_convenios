<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            TenantSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            EspecialidadeSeeder::class,
            ProfissionalSeeder::class,
            MedicoSeeder::class,
            UserSeeder::class,
            ConvenioSeeder::class,
            ConvenioRegraSeeder::class,
            PacienteSeeder::class,
            TabelaValorSeeder::class,
        ]);
    }
}
