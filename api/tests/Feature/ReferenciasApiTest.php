<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferenciasApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lista_basica_de_referencias(): void
    {
        $this->autenticar();

        $this->getJson('/api/convenios')
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Celos');

        $this->getJson('/api/especialidades')
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Fisioterapia');

        $this->getJson('/api/pacientes')
            ->assertOk()
            ->assertJsonPath('data.0.convenio.nome', 'Unimed');

        $this->getJson('/api/profissionais')
            ->assertOk()
            ->assertJsonFragment(['nome' => 'Dr. Rafael Nascimento']);
    }

    public function test_filtros_por_busca_funcionam(): void
    {
        $this->autenticar();

        $this->getJson('/api/pacientes?busca=Ana')
            ->assertOk()
            ->assertJsonFragment(['nome' => 'Ana Paula Ribeiro'])
            ->assertJsonMissing(['nome' => 'Felipe Gomes Nogueira']);

        $this->getJson('/api/profissionais?busca=Paula')
            ->assertOk()
            ->assertJsonFragment(['nome' => 'Dra. Paula Menezes'])
            ->assertJsonMissing(['nome' => 'Dr. Rafael Nascimento']);

        $especialidade = Especialidade::query()->where('nome', 'Fonoaudiologia')->firstOrFail();

        $this->getJson('/api/profissionais?especialidade_id='.$especialidade->id)
            ->assertOk()
            ->assertJsonFragment(['especialidade_id' => $especialidade->id])
            ->assertJsonMissing(['especialidade_id' => 1, 'nome' => 'Dra. Marina Tavares']);
    }

    public function test_isolamento_cross_tenant_nas_listas_de_referencia(): void
    {
        $referencias = $this->criarReferenciaDeOutroTenant();

        $this->autenticar();

        $this->getJson('/api/convenios')
            ->assertOk()
            ->assertJsonMissing(['id' => $referencias['convenio']->id]);

        $this->getJson('/api/pacientes?busca=Externo')
            ->assertOk()
            ->assertJsonMissing(['id' => $referencias['paciente']->id]);

        $this->getJson('/api/profissionais?busca=Externo')
            ->assertOk()
            ->assertJsonMissing(['id' => $referencias['profissional']->id]);
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function criarReferenciaDeOutroTenant(): array
    {
        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Externa Ref',
            'slug' => 'clinica-externa-ref',
            'cnpj' => '11.111.111/0001-11',
            'ativo' => true,
        ]);

        $especialidade = Especialidade::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Especialidade Externa Ref',
            'ativo' => true,
        ]);

        $convenio = Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Convênio Externo Ref',
            'connector_type' => 'manual',
            'connector_config' => null,
            'ativo' => true,
        ]);

        $profissional = Profissional::query()->create([
            'tenant_id' => $tenant->id,
            'especialidade_id' => $especialidade->id,
            'nome' => 'Profissional Externo Ref',
            'conselho_registro' => 'CREFITO 123123-F',
            'ativo' => true,
        ]);

        $paciente = Paciente::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Paciente Externo Ref',
            'cpf' => '12345678901',
            'carteirinha' => 'EXT-R-0001',
            'convenio_id' => $convenio->id,
            'telefone' => '(11) 90000-0010',
            'clinica_agil_id' => null,
            'ativo' => true,
        ]);

        return [
            'convenio' => $convenio,
            'profissional' => $profissional,
            'paciente' => $paciente,
        ];
    }
}
