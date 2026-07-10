<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();

        User::query()->updateOrCreate(
            ['email' => 'admin@clinica-exemplo.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Admin Clínica Exemplo',
                'password' => 'password',
                'role' => 'admin',
                'ativo' => true,
            ]
        );
    }
}
