<?php

namespace Tests\Feature;

use App\Models\Especialidade;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EspecialidadesApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lista_especialidades_retorna_apenas_ativas_por_padrao(): void
    {
        $this->autenticar();
        $this->criarEspecialidadeInativaNoTenantAtual();

        $this->getJson('/api/especialidades')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissing(['nome' => 'Especialidade Inativa'])
            ->assertJsonMissing(['ativo' => false]);
    }

    public function test_lista_administrativa_pode_incluir_inativas(): void
    {
        $this->autenticar();
        $this->criarEspecialidadeInativaNoTenantAtual();

        $this->getJson('/api/especialidades?incluir_inativos=1')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonFragment(['nome' => 'Especialidade Inativa', 'ativo' => false]);
    }

    public function test_cria_especialidade_para_o_tenant_autenticado(): void
    {
        $this->autenticar();

        $this->postJson('/api/especialidades', [
            'nome' => 'Musicoterapia',
            'ativo' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Musicoterapia')
            ->assertJsonPath('data.ativo', true);

        $this->assertDatabaseHas('especialidades', [
            'nome' => 'Musicoterapia',
            'ativo' => true,
        ]);
    }

    public function test_atualiza_especialidade_e_pode_inativar_e_reativar(): void
    {
        $this->autenticar();

        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();

        $this->patchJson("/api/especialidades/{$especialidade->id}", [
            'nome' => 'Fisioterapia Clínica',
            'ativo' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.nome', 'Fisioterapia Clínica')
            ->assertJsonPath('data.ativo', false);

        $this->patchJson("/api/especialidades/{$especialidade->id}", [
            'ativo' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.ativo', true);
    }

    public function test_nome_duplicado_e_bloqueado_na_criacao(): void
    {
        $this->autenticar();

        $this->postJson('/api/especialidades', [
            'nome' => 'Fisioterapia',
            'ativo' => true,
        ])->assertStatus(422);
    }

    public function test_nome_duplicado_e_bloqueado_na_edicao(): void
    {
        $this->autenticar();

        $especialidade = Especialidade::query()->create([
            'tenant_id' => $this->tenantAtual()->id,
            'nome' => 'Terapia Ocupacional',
            'ativo' => true,
        ]);

        $this->patchJson("/api/especialidades/{$especialidade->id}", [
            'nome' => 'Fonoaudiologia',
        ])->assertStatus(422);
    }

    public function test_usuario_sem_permissao_nao_pode_gerenciar_especialidades(): void
    {
        $user = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->postJson('/api/especialidades', [
            'nome' => 'Especialidade Bloqueada',
            'ativo' => true,
        ])->assertForbidden();
    }

    public function test_especialidade_de_outro_tenant_retorna_404_no_binding(): void
    {
        $this->autenticar();

        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Externa Especialidades',
            'slug' => 'clinica-externa-especialidades',
            'cnpj' => '10.010.010/0001-10',
            'ativo' => true,
        ]);

        $especialidade = Especialidade::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Especialidade Externa',
            'ativo' => true,
        ]);

        $this->patchJson("/api/especialidades/{$especialidade->id}", [
            'ativo' => false,
        ])->assertNotFound();
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function tenantAtual(): Tenant
    {
        return Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
    }

    private function criarEspecialidadeInativaNoTenantAtual(): void
    {
        Especialidade::query()->create([
            'tenant_id' => $this->tenantAtual()->id,
            'nome' => 'Especialidade Inativa',
            'ativo' => false,
        ]);
    }
}
