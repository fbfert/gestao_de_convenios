<?php

namespace Database\Seeders;

use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\Tenant;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            TenantSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            EspecialidadeSeeder::class,
            CidSeeder::class,
            ProfissionalSeeder::class,
            MedicoSeeder::class,
            UserSeeder::class,
            ConvenioSeeder::class,
            ConvenioRegraSeeder::class,
            PacienteSeeder::class,
            TabelaValorSeeder::class,
            EmailTemplateSeeder::class,
        ]);

        if (app()->environment('testing')) {
            $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->first();
            $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->first();
            $profissional = Profissional::query()->where('especialidade_id', $especialidade?->id)->first();
            $medico = Medico::query()->where('nome', 'Dr. Carlos Almeida')->first();
            $convenio = \App\Models\Convenio::query()->where('nome', 'Unimed')->first();

            if ($tenant && $especialidade && $profissional && $medico && $convenio) {
                $paciente = Paciente::query()->create([
                    'tenant_id' => $tenant->id,
                    'nome' => 'Paciente Guia Popup',
                    'cpf' => '12345678901',
                    'carteirinha' => 'POP-0001',
                    'convenio_id' => $convenio->id,
                    'telefone' => '(11) 99999-0001',
                    'clinica_agil_id' => null,
                    'ativo' => true,
                ]);

                $solicitacao = Solicitacao::query()->create([
                    'tenant_id' => $tenant->id,
                    'paciente_id' => $paciente->id,
                    'profissional_id' => $profissional->id,
                    'especialidade_id' => $especialidade->id,
                    'convenio_id' => $convenio->id,
                    'medico_id' => $medico->id,
                    'status' => 'under_review',
                    'solicitado_em' => now()->toDateString(),
                    'observacoes' => 'Seed de popup de guia.',
                ]);

                $item = $solicitacao->itens()->create([
                    'tenant_id' => $tenant->id,
                    'especialidade_id' => $especialidade->id,
                    'profissional_id' => $profissional->id,
                    'quantidade' => 10,
                    'status_operacional' => 'pending',
                    'observacoes' => null,
                ]);

                Guia::query()->create([
                    'tenant_id' => $tenant->id,
                    'solicitacao_id' => $solicitacao->id,
                    'solicitacao_item_id' => $item->id,
                    'convenio_id' => $convenio->id,
                    'paciente_id' => $paciente->id,
                    'profissional_id' => $profissional->id,
                    'especialidade_id' => $especialidade->id,
                    'numero_guia' => 'GUIA-POPUP-SEED',
                    'tipo_terapia' => 'especializada',
                    'status' => 'under_review',
                    'data_solicitacao' => now()->toDateString(),
                    'data_finalizacao' => null,
                    'senha' => null,
                    'validade_senha' => null,
                    'observacoes' => null,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
