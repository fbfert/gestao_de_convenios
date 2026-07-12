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
                'crm' => 'CRM 123456',
                'especialidade_medica' => 'Clínica Geral',
                'telefone' => '(11) 95555-0101',
                'email' => 'carlos.almeida@clinica-exemplo.test',
            ],
            [
                'nome' => 'Dra. Helena Soares',
                'crm' => 'CRM 234567',
                'especialidade_medica' => 'Neurologia',
                'telefone' => '(11) 95555-0102',
                'email' => 'helena.soares@clinica-exemplo.test',
            ],
            [
                'nome' => 'Dr. Pedro Nogueira',
                'crm' => 'CRM 345678',
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
                ],
                [
                    'nome' => $medico['nome'],
                    'especialidade_medica' => $medico['especialidade_medica'],
                    'telefone' => $medico['telefone'],
                    'email' => $medico['email'],
                    'ativo' => true,
                ],
            );
        }
    }
}
