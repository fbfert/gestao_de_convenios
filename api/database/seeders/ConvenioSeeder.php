<?php

namespace Database\Seeders;

use App\Models\Convenio;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ConvenioSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();

        foreach ([
            'Unimed',
            'SC Saúde',
            'Celos',
        ] as $nome) {
            Convenio::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'nome' => $nome,
                ],
                [
                    'connector_type' => 'manual',
                    'connector_config' => null,
                    'ativo' => true,
                ]
            );
        }
    }
}
