<?php

namespace Database\Seeders;

use App\Models\Medico;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class MedicoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();

        $medicos = [
            [
                'nome' => 'Dr. Carlos Almeida',
                'crm' => '123456',
                'crm_uf' => 'SP',
                'especialidade_medica' => 'Clínica Geral',
                'telefone' => '(11) 95555-0101',
                'email' => 'carlos.almeida@clinica-exemplo.test',
            ],
            [
                'nome' => 'Dra. Helena Soares',
                'crm' => '234567',
                'crm_uf' => 'SP',
                'especialidade_medica' => 'Neurologia',
                'telefone' => '(11) 95555-0102',
                'email' => 'helena.soares@clinica-exemplo.test',
            ],
            [
                'nome' => 'Dr. Pedro Nogueira',
                'crm' => '345678',
                'crm_uf' => 'SP',
                'especialidade_medica' => 'Pediatria',
                'telefone' => '(11) 95555-0103',
                'email' => 'pedro.nogueira@clinica-exemplo.test',
            ],
        ];

        foreach ($medicos as $medico) {
            Medico::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'crm' => $medico['crm'],
                    'crm_uf' => $medico['crm_uf'],
                ],
                [
                    'nome' => $medico['nome'],
                    'crm_uf' => $medico['crm_uf'],
                    'especialidade_medica' => $medico['especialidade_medica'],
                    'telefone' => $medico['telefone'],
                    'email' => $medico['email'],
                    'ativo' => true,
                ],
            );
        }
    }
}
