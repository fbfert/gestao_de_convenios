<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()->updateOrCreate(
            ['slug' => 'clinica-exemplo'],
            [
                'nome' => 'Clínica Exemplo',
                'cnpj' => '12.345.678/0001-90',
                'ativo' => true,
            ]
        );
    }
}
