<?php

namespace Database\Seeders;

use App\Models\Especialidade;
use App\Models\Profissional;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ProfissionalSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();

        $especialidades = Especialidade::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->get()
            ->keyBy('nome');

        $profissionais = [
            [
                'nome' => 'Dra. Marina Tavares',
                'especialidade' => 'Fisioterapia',
                'conselho_registro' => 'CREFITO 123456-F',
            ],
            [
                'nome' => 'Dra. Paula Menezes',
                'especialidade' => 'Fonoaudiologia',
                'conselho_registro' => 'CRFa 78910',
            ],
            [
                'nome' => 'Dr. Rafael Nascimento',
                'especialidade' => 'Terapia ABA',
                'conselho_registro' => 'CRP 11/22334',
            ],
        ];

        foreach ($profissionais as $profissional) {
            Profissional::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'nome' => $profissional['nome'],
                ],
                [
                    'especialidade_id' => $especialidades[$profissional['especialidade']]->id,
                    'conselho_registro' => $profissional['conselho_registro'],
                    'ativo' => true,
                ]
            );
        }
    }
}
