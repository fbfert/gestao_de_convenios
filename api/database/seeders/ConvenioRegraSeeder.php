<?php

namespace Database\Seeders;

use App\Models\Convenio;
use App\Models\ConvenioRegra;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ConvenioRegraSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $convenios = Convenio::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('nome');

        $hoje = now()->toDateString();

        $regras = [
            [
                'convenio' => 'Unimed',
                'tipo_terapia' => 'especializada',
                'frequencia_lancamento' => 'diaria',
                'qtd_autorizada_por_ciclo' => 1,
                'validade_senha_dias' => 30,
                'observacoes' => 'Regra especializada da Unimed com liberação diária e 1 sessão por dia.',
            ],
            [
                'convenio' => 'Unimed',
                'tipo_terapia' => 'convencional',
                'frequencia_lancamento' => 'mensal',
                'qtd_autorizada_por_ciclo' => 4,
                'validade_senha_dias' => 60,
                'observacoes' => 'Regra convencional da Unimed com fechamento mensal.',
            ],
            [
                'convenio' => 'SC Saúde',
                'tipo_terapia' => 'especializada',
                'frequencia_lancamento' => 'semanal',
                'qtd_autorizada_por_ciclo' => 1,
                'validade_senha_dias' => 45,
                'observacoes' => 'Regra especializada da SC Saúde com ciclo semanal.',
            ],
            [
                'convenio' => 'SC Saúde',
                'tipo_terapia' => 'convencional',
                'frequencia_lancamento' => 'mensal',
                'qtd_autorizada_por_ciclo' => 4,
                'validade_senha_dias' => 90,
                'observacoes' => 'Regra convencional da SC Saúde com senha mais longa.',
            ],
            [
                'convenio' => 'Celos',
                'tipo_terapia' => 'especializada',
                'frequencia_lancamento' => 'diaria',
                'qtd_autorizada_por_ciclo' => 2,
                'validade_senha_dias' => 15,
                'observacoes' => 'Regra especializada da Celos com duas autorizações por dia.',
            ],
            [
                'convenio' => 'Celos',
                'tipo_terapia' => 'convencional',
                'frequencia_lancamento' => 'semanal',
                'qtd_autorizada_por_ciclo' => 1,
                'validade_senha_dias' => 30,
                'observacoes' => 'Regra convencional da Celos com controle semanal.',
            ],
        ];

        foreach ($regras as $regra) {
            ConvenioRegra::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'convenio_id' => $convenios[$regra['convenio']]->id,
                    'tipo_terapia' => $regra['tipo_terapia'],
                ],
                [
                    'frequencia_lancamento' => $regra['frequencia_lancamento'],
                    'qtd_autorizada_por_ciclo' => $regra['qtd_autorizada_por_ciclo'],
                    'validade_senha_dias' => $regra['validade_senha_dias'],
                    'observacoes' => $regra['observacoes'],
                    'vigente_desde' => $hoje,
                    'vigente_ate' => null,
                ]
            );
        }
    }
}
