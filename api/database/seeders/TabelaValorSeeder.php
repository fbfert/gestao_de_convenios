<?php

namespace Database\Seeders;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Profissional;
use App\Models\TabelaValor;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TabelaValorSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();

        $convenios = Convenio::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('nome');

        $especialidades = Especialidade::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('nome');

        $profissionais = Profissional::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('nome');

        $hoje = now()->toDateString();

        $valores = [
            [
                'convenio' => 'Unimed',
                'especialidade' => null,
                'profissional' => null,
                'valor' => '120.00',
            ],
            [
                'convenio' => 'Unimed',
                'especialidade' => 'Fisioterapia',
                'profissional' => null,
                'valor' => '140.00',
            ],
            [
                'convenio' => 'Unimed',
                'especialidade' => 'Fisioterapia',
                'profissional' => 'Dra. Marina Tavares',
                'valor' => '160.00',
            ],
            [
                'convenio' => 'SC Saúde',
                'especialidade' => null,
                'profissional' => null,
                'valor' => '110.00',
            ],
        ];

        foreach ($valores as $valor) {
            TabelaValor::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'convenio_id' => $convenios[$valor['convenio']]->id,
                    'especialidade_id' => $valor['especialidade'] ? $especialidades[$valor['especialidade']]->id : null,
                    'profissional_id' => $valor['profissional'] ? $profissionais[$valor['profissional']]->id : null,
                ],
                [
                    'valor' => $valor['valor'],
                    'vigente_desde' => $hoje,
                    'vigente_ate' => null,
                ]
            );
        }
    }
}
