<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\PermissionCatalog;
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
            ->assertJsonPath('data.0.name', 'antecipacoes.view');
    }

    public function test_carrega_e_atualiza_permissoes_do_role(): void
    {
        $this->autenticar();

        $this->getJson('/api/roles/admin/permissions')
            ->assertOk()
            ->assertJsonPath('data.role.name', 'admin')
            ->assertJsonCount(count(PermissionCatalog::all()), 'data.permissions');

        $this->putJson('/api/roles/admin/permissions', [
            'permissions' => [
                'solicitacoes.view',
                'usuarios.manage',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.role.name', 'admin')
            ->assertJsonCount(2, 'data.permissions');

        $role = $this->roleAtual();

        $this->assertEquals(
            ['solicitacoes.view', 'usuarios.manage'],
            $role->permissions()->orderBy('name')->pluck('name')->all()
        );
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
