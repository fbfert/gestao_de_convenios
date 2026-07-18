<?php

namespace Tests\Feature;

use App\Models\Especialidade;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProfissionaisApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lista_profissionais_retorna_apenas_ativos_por_padrao(): void
    {
        $this->autenticar();
        $this->criarProfissionalInativoNoTenantAtual();

        $this->getJson('/api/profissionais')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissing(['nome' => 'Dra. Inativa']);
    }

    public function test_lista_profissionais_pode_incluir_inativos_para_administracao(): void
    {
        $this->autenticar();
        $this->criarProfissionalInativoNoTenantAtual();

        $this->getJson('/api/profissionais?incluir_inativos=1')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonFragment(['nome' => 'Dra. Inativa', 'ativo' => false]);
    }

    public function test_cria_profissional_para_o_tenant_autenticado(): void
    {
        $this->autenticar();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();

        $this->postJson('/api/profissionais', [
            'nome' => 'Dra. Laura Martins',
            'especialidade_id' => $especialidade->id,
            'conselho_registro' => 'CREFITO 987654-F',
            'percentual_repasse' => 42.5,
            'ativo' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Dra. Laura Martins')
            ->assertJsonPath('data.especialidade_id', $especialidade->id)
            ->assertJsonPath('data.conselho_registro', 'CREFITO 987654-F')
            ->assertJsonPath('data.percentual_repasse', '42.50')
            ->assertJsonPath('data.ativo', true);

        $this->assertDatabaseHas('profissionais', [
            'nome' => 'Dra. Laura Martins',
            'conselho_registro' => 'CREFITO 987654-F',
        ]);
    }

    public function test_atualiza_profissional_e_pode_desativar(): void
    {
        $this->autenticar();

        $profissional = Profissional::query()
            ->where('nome', 'Dra. Marina Tavares')
            ->firstOrFail();

        $this->patchJson("/api/profissionais/{$profissional->id}", [
            'conselho_registro' => 'CREFITO 111222-F',
            'percentual_repasse' => 37.5,
            'ativo' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $profissional->id)
            ->assertJsonPath('data.conselho_registro', 'CREFITO 111222-F')
            ->assertJsonPath('data.percentual_repasse', '37.50')
            ->assertJsonPath('data.ativo', false);

        $this->assertDatabaseHas('profissionais', [
            'id' => $profissional->id,
            'conselho_registro' => 'CREFITO 111222-F',
            'ativo' => false,
        ]);
    }

    public function test_profissional_de_outro_tenant_retorna_404_no_binding(): void
    {
        $profissional = Profissional::query()->where('nome', 'Dra. Marina Tavares')->firstOrFail();
        $user = $this->criarUsuarioAutorizadoDeOutroTenant();
        Sanctum::actingAs($user);

        $this->patchJson("/api/profissionais/{$profissional->id}", [
            'ativo' => false,
        ])->assertNotFound();
    }

    public function test_usuario_sem_permissao_nao_pode_gerenciar_profissionais(): void
    {
        $user = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $especialidadeId = Profissional::withoutGlobalScopes()
            ->where('nome', 'Dra. Marina Tavares')
            ->firstOrFail()
            ->especialidade_id;

        $this->postJson('/api/profissionais', [
            'nome' => 'Profissional Bloqueado',
            'especialidade_id' => $especialidadeId,
            'conselho_registro' => 'CREFITO 123123-F',
            'percentual_repasse' => 35,
        ])->assertForbidden();
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function criarProfissionalInativoNoTenantAtual(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $especialidade = Especialidade::query()->where('tenant_id', $tenant->id)->firstOrFail();

        Profissional::query()->create([
            'tenant_id' => $tenant->id,
            'especialidade_id' => $especialidade->id,
            'nome' => 'Dra. Inativa',
            'conselho_registro' => 'CREFITO 555555-F',
            'percentual_repasse' => 35,
            'ativo' => false,
        ]);
    }

    private function criarUsuarioAutorizadoDeOutroTenant(): User
    {
        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Externa Profissionais',
            'slug' => 'clinica-externa-profissionais',
            'cnpj' => '98.765.432/0001-10',
            'ativo' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Externo',
            'email' => 'admin@clinica-externa-profissionais.test',
            'password' => 'password',
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->givePermissionTo('profissionais.manage');

        return $user;
    }
}
