<?php

namespace Database\Seeders;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class BrowserTenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'clinica-beta'],
            [
                'nome' => 'Clínica Beta',
                'cnpj' => '98.765.432/0001-10',
                'ativo' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@clinica-beta.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Admin Clínica Beta',
                'password' => 'password',
                'role' => 'admin',
                'ativo' => true,
            ],
        );

        $especialidade = Especialidade::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'nome' => 'Fisioterapia',
            ],
            [
                'ativo' => true,
            ],
        );

        $convenio = Convenio::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'nome' => 'Beta Saúde',
            ],
            [
                'connector_type' => 'manual',
                'connector_config' => null,
                'ativo' => true,
            ],
        );

        $profissional = Profissional::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'nome' => 'Dra. Leticia Azevedo',
            ],
            [
                'especialidade_id' => $especialidade->id,
                'conselho_registro' => 'CREFITO-9 99999',
                'ativo' => true,
            ],
        );

        $paciente = Paciente::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'carteirinha' => 'BET-2026-9001',
            ],
            [
                'nome' => 'Otavio Pereira Santos',
                'cpf' => '123.456.789-00',
                'convenio_id' => $convenio->id,
                'telefone' => '(11) 99999-9001',
                'clinica_agil_id' => null,
                'ativo' => true,
            ],
        );

        $solicitacao = Solicitacao::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'paciente_id' => $paciente->id,
                'profissional_id' => $profissional->id,
                'especialidade_id' => $especialidade->id,
                'convenio_id' => $convenio->id,
                'medico_solicitante' => 'Dr. Carlos Beta',
                'solicitado_em' => '2026-07-10',
            ],
            [
                'status' => 'approved',
                'observacoes' => 'Registro isolado do tenant beta.',
            ],
        );

        Guia::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'numero_guia' => 'GUIA-BETA-001',
            ],
            [
                'solicitacao_id' => $solicitacao->id,
                'convenio_id' => $convenio->id,
                'paciente_id' => $paciente->id,
                'profissional_id' => $profissional->id,
                'especialidade_id' => $especialidade->id,
                'tipo_terapia' => 'especializada',
                'status' => 'under_review',
                'data_solicitacao' => '2026-07-10',
                'data_finalizacao' => null,
                'senha' => null,
                'validade_senha' => null,
                'observacoes' => 'Guia exclusiva do tenant beta.',
            ],
        );
    }
}
