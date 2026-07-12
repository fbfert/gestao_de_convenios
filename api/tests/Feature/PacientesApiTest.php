<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PacientesApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lista_e_busca_funcionam(): void
    {
        $this->autenticar();

        $this->getJson('/api/pacientes')
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Ana Paula Ribeiro')
            ->assertJsonPath('data.0.convenio.nome', 'Unimed');

        $this->getJson('/api/pacientes?busca=Felipe')
            ->assertOk()
            ->assertJsonFragment(['nome' => 'Felipe Gomes Nogueira'])
            ->assertJsonMissing(['nome' => 'Ana Paula Ribeiro']);
    }

    public function test_cria_atualiza_e_desativa_paciente(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->postJson('/api/pacientes', [
            'nome' => 'Paciente CRUD Local',
            'cpf' => '99988877766',
            'carteirinha' => 'PAC-CRUD-0001',
            'convenio_id' => $convenio->id,
            'telefone' => '(11) 90000-0001',
            'clinica_agil_id' => 'CA-CRUD-0001',
        ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Paciente CRUD Local')
            ->assertJsonPath('data.convenio.id', $convenio->id)
            ->assertJsonPath('data.ativo', true);

        $paciente = Paciente::query()->where('carteirinha', 'PAC-CRUD-0001')->firstOrFail();

        $this->patchJson("/api/pacientes/{$paciente->id}", [
            'nome' => 'Paciente CRUD Atualizado',
            'telefone' => '(11) 90000-0099',
            'ativo' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.nome', 'Paciente CRUD Atualizado')
            ->assertJsonPath('data.telefone', '(11) 90000-0099')
            ->assertJsonPath('data.ativo', false);
    }

    public function test_nao_permite_convenio_de_outro_tenant_na_criacao(): void
    {
        $convenioExterno = $this->criarConvenioExterno();

        $this->autenticar();

        $this->postJson('/api/pacientes', [
            'nome' => 'Paciente Inválido',
            'cpf' => '11122233344',
            'carteirinha' => 'PAC-INV-0001',
            'convenio_id' => $convenioExterno->id,
            'telefone' => '(11) 90000-0002',
            'clinica_agil_id' => 'CA-INV-0001',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['convenio_id']);
    }

    public function test_isolamento_cross_tenant_em_detalhe_e_atualizacao(): void
    {
        $pacienteExterno = $this->criarPacienteExterno();

        $this->autenticar();

        $this->getJson("/api/pacientes/{$pacienteExterno->id}")
            ->assertNotFound();

        $this->patchJson("/api/pacientes/{$pacienteExterno->id}", [
            'ativo' => false,
        ])
            ->assertNotFound();
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function criarTenantExterno(): Tenant
    {
        return Tenant::query()->create([
            'nome' => 'Clínica Externa Pacientes',
            'slug' => 'clinica-externa-pacientes',
            'cnpj' => '22.222.222/0001-22',
            'ativo' => true,
        ]);
    }

    private function criarPacienteExterno(): Paciente
    {
        $convenio = $this->criarConvenioExterno();

        return Paciente::query()->create([
            'tenant_id' => $convenio->tenant_id,
            'nome' => 'Paciente Externo CRUD',
            'cpf' => '22233344455',
            'carteirinha' => 'PAC-EXT-0001',
            'convenio_id' => $convenio->id,
            'telefone' => '(11) 90000-0003',
            'clinica_agil_id' => 'CA-EXT-0001',
            'ativo' => true,
        ]);
    }

    private function criarConvenioExterno(): Convenio
    {
        $tenant = $this->criarTenantExterno();

        return Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Convênio Externo Pacientes',
            'connector_type' => 'manual',
            'connector_config' => null,
            'ativo' => true,
        ]);
    }
}
