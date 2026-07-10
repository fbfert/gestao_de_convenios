<?php

namespace Database\Seeders;

use App\Models\Convenio;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PacienteSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $convenios = Convenio::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('nome');

        $pacientes = [
            [
                'nome' => 'Ana Paula Ribeiro',
                'cpf' => '12345678901',
                'carteirinha' => 'UNI-2026-0001',
                'convenio' => 'Unimed',
                'telefone' => '(11) 98888-0001',
                'clinica_agil_id' => 'CA-0001',
            ],
            [
                'nome' => 'Bruno Henrique Lima',
                'cpf' => '12345678902',
                'carteirinha' => 'UNI-2026-0002',
                'convenio' => 'Unimed',
                'telefone' => '(11) 98888-0002',
                'clinica_agil_id' => 'CA-0002',
            ],
            [
                'nome' => 'Camila Santos Pereira',
                'cpf' => '12345678903',
                'carteirinha' => 'SCS-2026-0001',
                'convenio' => 'SC Saúde',
                'telefone' => '(47) 98888-0003',
                'clinica_agil_id' => 'CA-0003',
            ],
            [
                'nome' => 'Diego Alves Martins',
                'cpf' => '12345678904',
                'carteirinha' => 'SCS-2026-0002',
                'convenio' => 'SC Saúde',
                'telefone' => '(47) 98888-0004',
                'clinica_agil_id' => 'CA-0004',
            ],
            [
                'nome' => 'Elisa Fernandes Costa',
                'cpf' => '12345678905',
                'carteirinha' => 'CEL-2026-0001',
                'convenio' => 'Celos',
                'telefone' => '(61) 98888-0005',
                'clinica_agil_id' => 'CA-0005',
            ],
            [
                'nome' => 'Felipe Gomes Nogueira',
                'cpf' => '12345678906',
                'carteirinha' => 'CEL-2026-0002',
                'convenio' => 'Celos',
                'telefone' => '(61) 98888-0006',
                'clinica_agil_id' => 'CA-0006',
            ],
        ];

        foreach ($pacientes as $paciente) {
            Paciente::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'carteirinha' => $paciente['carteirinha'],
                ],
                [
                    'nome' => $paciente['nome'],
                    'cpf' => $paciente['cpf'],
                    'convenio_id' => $convenios[$paciente['convenio']]->id,
                    'telefone' => $paciente['telefone'],
                    'clinica_agil_id' => $paciente['clinica_agil_id'],
                    'ativo' => true,
                ]
            );
        }
    }
}
