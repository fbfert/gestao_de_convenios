<?php

namespace Database\Seeders;

use App\Models\Especialidade;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class EspecialidadeSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();

        foreach ([
            'Fisioterapia',
            'Fonoaudiologia',
            'Terapia ABA',
        ] as $nome) {
            Especialidade::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'nome' => $nome,
                ],
                [
                    'ativo' => true,
                ]
            );
        }
    }
}
