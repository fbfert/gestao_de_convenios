<?php

namespace Database\Seeders;

use App\Models\Cid;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CidSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();

        foreach ([
            'F84.0' => 'Autismo infantil',
            'F80.1' => 'Transtorno da linguagem expressiva',
            'F90.0' => 'Distúrbio da atividade e da atenção (TDAH)',
        ] as $codigo => $descricao) {
            Cid::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'codigo' => $codigo,
                ],
                [
                    'descricao' => $descricao,
                    'ativo' => true,
                ]
            );
        }
    }
}
