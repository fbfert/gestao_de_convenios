<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lista_roles_e_catalogo_de_permissoes_do_tenant(): void
    {
        $this->autenticar();

        $this->getJson('/api/roles')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'admin');

        $this->getJson('/api/permissions')
            ->assertOk()
            ->assertJsonCount(count(PermissionCatalog::all()), 'data')
            ->assertJsonPath('data.0.name', 'antecipacoes.manage');
    }

    public function test_carrega_e_atualiza_permissoes_do_role(): void
    {
        $this->autenticar();

        $this->getJson('/api/roles/admin/permissions')
            ->assertOk()
            ->assertJsonPath('data.role.name', 'admin')
            ->assertJsonCount(count(PermissionCatalog::all()), 'data.permissions');

        // `permissoes.manage` fica na lista de proposito: e o papel de quem
        // esta autenticado, e a GuardaAdministracao recusa retirar dele.
        $this->putJson('/api/roles/admin/permissions', [
            'permissions' => [
                'permissoes.manage',
                'solicitacoes.view',
                'usuarios.manage',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.role.name', 'admin')
            ->assertJsonCount(3, 'data.permissions');

        $role = $this->roleAtual();

        $this->assertEquals(
            ['permissoes.manage', 'solicitacoes.view', 'usuarios.manage'],
            $role->permissions()->orderBy('name')->pluck('name')->all()
        );
    }

    public function test_catalogo_devolve_rotulo_legivel_da_permissao(): void
    {
        $this->autenticar();

        $this->getJson('/api/permissions')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'antecipacoes.manage')
            ->assertJsonPath('data.0.label', 'Editar dados de antecipações');
    }

    public function test_recusa_remover_administracao_do_proprio_papel(): void
    {
        $this->autenticar();

        $this->putJson('/api/roles/admin/permissions', [
            'permissions' => ['solicitacoes.view'],
        ])->assertStatus(422);

        $this->assertTrue($this->roleAtual()->hasPermissionTo('permissoes.manage'));
    }

    public function test_recusa_deixar_o_tenant_sem_nenhum_papel_que_administre(): void
    {
        $this->autenticar();

        // Quem tira a permissao e o `funcionario`, e nao o papel de quem esta
        // logado: aqui o que barra e ser o ultimo papel com administracao.
        $this->putJson('/api/roles/funcionario/permissions', [
            'permissions' => ['permissoes.manage'],
        ])->assertOk();

        $this->putJson('/api/roles/admin/permissions', [
            'permissions' => ['solicitacoes.view'],
        ])->assertStatus(422);
    }

    public function test_cria_papel_copiando_permissoes_de_outro(): void
    {
        $this->autenticar();

        $this->postJson('/api/roles', [
            'name' => 'recepcao',
            'copiar_de' => 'profissional',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'recepcao')
            ->assertJsonPath('data.sistema', false)
            ->assertJsonPath('data.users_count', 0);

        $this->getJson('/api/roles/recepcao/permissions')
            ->assertOk()
            ->assertJsonCount(count(RoleCatalog::permissoesDe('profissional')), 'data.permissions');
    }

    public function test_recusa_papel_com_nome_repetido(): void
    {
        $this->autenticar();

        $this->postJson('/api/roles', ['name' => 'admin'])->assertStatus(422);
    }

    public function test_papel_de_sistema_nao_pode_ser_renomeado_nem_excluido(): void
    {
        $this->autenticar();

        $this->patchJson('/api/roles/profissional', ['name' => 'terapeuta'])->assertStatus(422);
        $this->deleteJson('/api/roles/profissional')->assertStatus(422);

        $this->getJson('/api/roles')->assertOk()->assertJsonPath('data.2.name', 'profissional');
    }

    public function test_renomeia_e_exclui_papel_proprio(): void
    {
        $this->autenticar();

        $this->postJson('/api/roles', ['name' => 'recepcao'])->assertCreated();
        $this->patchJson('/api/roles/recepcao', ['name' => 'atendimento'])
            ->assertOk()
            ->assertJsonPath('data.name', 'atendimento');

        $this->deleteJson('/api/roles/atendimento')->assertOk();

        $this->getJson('/api/roles')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_recusa_excluir_papel_com_usuarios(): void
    {
        $this->autenticar();

        $this->postJson('/api/roles', ['name' => 'recepcao'])->assertCreated();

        $usuario = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        app(PermissionRegistrar::class)->setPermissionsTeamId($usuario->tenant_id);
        $usuario->assignRole('recepcao');

        $this->deleteJson('/api/roles/recepcao')
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Há 1 usuário com este papel. Troque o papel dele antes de excluir.');
    }

    public function test_rejeita_permissao_inexistente_na_atualizacao_do_role(): void
    {
        $this->autenticar();

        $this->putJson('/api/roles/admin/permissions', [
            'permissions' => [
                'solicitacoes.view',
                'nao.existe',
            ],
        ])->assertStatus(422);
    }

    public function test_role_de_outro_tenant_retorna_404_no_binding(): void
    {
        $this->criarRoleExterna();
        $this->autenticar();

        $this->getJson('/api/roles/gestor/permissions')
            ->assertNotFound();
    }

    public function test_usuario_sem_permissao_nao_acessa_crud_de_permissoes(): void
    {
        $user = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/roles')->assertForbidden();
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function roleAtual(): Role
    {
        $tenantId = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail()->tenant_id;

        return Role::query()
            ->where('tenant_id', $tenantId)
            ->where('name', 'admin')
            ->firstOrFail();
    }

    private function criarRoleExterna(): void
    {
        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Externa',
            'slug' => 'clinica-externa-permissoes',
            'cnpj' => '44.444.444/0001-44',
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        Role::findOrCreate('gestor', 'web');
    }
}
