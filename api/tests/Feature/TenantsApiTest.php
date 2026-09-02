<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ConfiguracaoGlobal;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenantsApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_bloqueia_quem_nao_e_super_admin(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/tenants')->assertForbidden();
        $this->postJson('/api/tenants', [])->assertForbidden();
        $this->putJson('/api/tenants/1', [])->assertForbidden();
    }

    public function test_lista_todas_as_clinicas_para_super_admin(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->getJson('/api/tenants')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'clinica-exemplo')
            ->assertJsonPath('data.0.usuarios_count', User::query()->count());
    }

    public function test_cria_clinica_com_papeis_e_administrador(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/tenants', [
            'nome' => 'Clínica São Jorge',
            'slug' => 'clinica-sao-jorge',
            'cnpj' => '11.222.333/0001-44',
            'ativo' => true,
            'admin' => [
                'name' => 'Gestora São Jorge',
                'email' => 'gestora@sao-jorge.test',
                'password' => 'senha-forte-123',
            ],
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'clinica-sao-jorge')
            ->assertJsonPath('data.usuarios_count', 1);

        $tenant = Tenant::query()->where('slug', 'clinica-sao-jorge')->firstOrFail();

        // Os tres papeis nascem no tenant novo, com as permissoes do catalogo.
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $papeis = Role::query()->where('tenant_id', $tenant->id)->get();
        $this->assertEqualsCanonicalizing(RoleCatalog::nomes(), $papeis->pluck('name')->all());

        $admin = $papeis->firstWhere('name', 'admin');
        $this->assertEqualsCanonicalizing(
            RoleCatalog::permissoesDe('admin'),
            $admin->permissions->pluck('name')->all(),
        );

        // Nenhum papel global orfao: e o defeito que a migration
        // 2026_08_05_100000 teve que corrigir.
        $this->assertSame(0, Role::query()->whereNull('tenant_id')->count());

        $usuario = User::query()->where('email', 'gestora@sao-jorge.test')->firstOrFail();
        $this->assertSame($tenant->id, $usuario->tenant_id);
        $this->assertTrue($usuario->ativo);
        $this->assertFalse($usuario->super_admin);
        $this->assertTrue(Hash::check('senha-forte-123', $usuario->password));
        $this->assertTrue($usuario->hasRole('admin'));

        $registrar->setPermissionsTeamId(null);
    }

    public function test_recusa_slug_repetido_e_email_ja_usado(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->postJson('/api/tenants', $this->payload(['slug' => 'clinica-exemplo']))
            ->assertJsonValidationErrors('slug');

        $this->postJson('/api/tenants', $this->payload([
            'admin' => [
                'name' => 'Duplicada',
                'email' => 'admin@clinica-exemplo.test',
                'password' => 'senha-forte-123',
            ],
        ]))->assertJsonValidationErrors('admin.email');

        $this->postJson('/api/tenants', $this->payload(['slug' => 'Slug Invalido']))
            ->assertJsonValidationErrors('slug');
    }

    public function test_nao_deixa_clinica_orfa_quando_a_criacao_falha(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $antes = Tenant::query()->count();

        // E-mail ja usado: a validacao barra antes da transacao, entao nenhum
        // tenant pode sobrar no banco.
        $this->postJson('/api/tenants', $this->payload([
            'slug' => 'clinica-que-nao-nasce',
            'admin' => [
                'name' => 'Repetida',
                'email' => 'admin@clinica-exemplo.test',
                'password' => 'senha-forte-123',
            ],
        ]))->assertStatus(422);

        $this->assertSame($antes, Tenant::query()->count());
    }

    public function test_atualiza_e_desativa_outra_clinica(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $outra = Tenant::query()->create([
            'nome' => 'Clínica Vizinha',
            'slug' => 'clinica-vizinha',
            'cnpj' => null,
            'ativo' => true,
        ]);

        $this->putJson("/api/tenants/{$outra->id}", [
            'nome' => 'Clínica Vizinha Ltda',
            'slug' => 'clinica-vizinha-ltda',
            'cnpj' => '99.888.777/0001-66',
            'ativo' => false,
        ])->assertOk()->assertJsonPath('data.ativo', false)->assertJsonPath('data.slug', 'clinica-vizinha-ltda');

        $outra->refresh();
        $this->assertSame('Clínica Vizinha Ltda', $outra->nome);
        $this->assertFalse($outra->ativo);
        // O slug passou a ser editável (02/09/2026): não é mais imutável.
        $this->assertSame('clinica-vizinha-ltda', $outra->slug);
    }

    public function test_recusa_editar_slug_para_um_ja_usado_por_outra_clinica(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $outra = Tenant::query()->create([
            'nome' => 'Clínica Vizinha',
            'slug' => 'clinica-vizinha',
            'cnpj' => null,
            'ativo' => true,
        ]);

        $this->putJson("/api/tenants/{$outra->id}", [
            'nome' => 'Clínica Vizinha',
            'slug' => 'clinica-exemplo',
            'cnpj' => null,
            'ativo' => true,
        ])->assertJsonValidationErrors('slug');
    }

    public function test_impede_desativar_a_propria_clinica(): void
    {
        $super = $this->superAdmin();
        Sanctum::actingAs($super);

        $slugAtual = Tenant::query()->findOrFail($super->tenant_id)->slug;

        $this->putJson("/api/tenants/{$super->tenant_id}", [
            'nome' => 'Clínica Exemplo',
            'slug' => $slugAtual,
            'cnpj' => null,
            'ativo' => false,
        ])->assertJsonValidationErrors('ativo');

        $this->assertTrue(Tenant::query()->find($super->tenant_id)->ativo);
    }

    public function test_super_admin_acessa_outra_clinica_com_permissoes_totais_e_escopo_correto(): void
    {
        $superAdmin = $this->superAdmin();

        // Sanctum::actingAs() não serve aqui: uma vez chamado, ele passa a
        // ignorar qualquer token real mandado depois via withToken(), então
        // o token de acesso (gerado por baixo) nunca seria de fato usado.
        // Login de verdade é o único jeito de exercitar o token real.
        $tokenHome = $this->postJson('/api/login', [
            'email' => $superAdmin->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        $outra = Tenant::query()->create([
            'nome' => 'Clínica Vizinha',
            'slug' => 'clinica-vizinha',
            'cnpj' => null,
            'ativo' => true,
        ]);

        $resposta = $this->withToken($tokenHome)
            ->postJson("/api/tenants/{$outra->id}/acessar")
            ->assertOk()
            ->assertJsonPath('user.tenant.id', $outra->id)
            ->assertJsonPath('user.tenant.slug', 'clinica-vizinha')
            ->assertJsonPath('user.acesso_super_admin', true)
            ->assertJsonPath('user.role', 'admin');

        $permissoesRecebidas = $resposta->json('user.permissions');
        $this->assertEqualsCanonicalizing(PermissionCatalog::all(), $permissoesRecebidas);

        $tokenAcesso = $resposta->json('token');
        $this->assertNotEmpty($tokenAcesso);

        $this->app['auth']->forgetGuards();

        // Endpoint gated por `permission:configuracoes.manage` — o super admin
        // não tem NENHUM papel atribuído em "clinica-vizinha", então só o
        // bypass em User::hasPermissionTo() explica um 200 aqui.
        $respostaConfig = $this->withToken($tokenAcesso)
            ->getJson('/api/configuracoes/globais')
            ->assertOk();

        // E o dado devolvido é mesmo o da clínica-alvo, não da clínica de
        // origem do super admin — confirma que TenantContext seguiu o token,
        // não o tenant_id fixo do usuário.
        $configDaOutra = ConfiguracaoGlobal::query()->where('tenant_id', $outra->id)->firstOrFail();
        $this->assertSame($configDaOutra->itens_por_pagina, $respostaConfig->json('data.itens_por_pagina'));

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $outra->id,
            'user_id' => $superAdmin->id,
            'acao' => 'acesso.super_admin_entrar',
            'entidade' => 'tenants',
            'entidade_id' => $outra->id,
        ]);
    }

    public function test_sair_do_acesso_derruba_so_o_token_de_acesso_e_home_continua_valido(): void
    {
        $superAdmin = $this->superAdmin();

        $loginHome = $this->postJson('/api/login', [
            'email' => $superAdmin->email,
            'password' => 'password',
        ])->assertOk();
        $tokenHome = $loginHome->json('token');

        $outra = Tenant::query()->create([
            'nome' => 'Clínica Vizinha',
            'slug' => 'clinica-vizinha',
            'cnpj' => null,
            'ativo' => true,
        ]);

        $tokenAcesso = $this->withToken($tokenHome)
            ->postJson("/api/tenants/{$outra->id}/acessar")
            ->assertOk()
            ->json('token');

        $this->app['auth']->forgetGuards();

        $this->withToken($tokenAcesso)
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $outra->id,
            'user_id' => $superAdmin->id,
            'acao' => 'acesso.super_admin_sair',
        ]);

        $this->app['auth']->forgetGuards();

        // O token de acesso morreu, mas o token de origem (home) continua de pé.
        $this->withToken($tokenAcesso)
            ->getJson('/api/user')
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();

        $this->withToken($tokenHome)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('tenant.slug', 'clinica-exemplo')
            ->assertJsonPath('acesso_super_admin', false);
    }

    public function test_recusa_acessar_clinica_inativa(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $inativa = Tenant::query()->create([
            'nome' => 'Clínica Inativa',
            'slug' => 'clinica-inativa-acesso',
            'cnpj' => null,
            'ativo' => false,
        ]);

        $this->postJson("/api/tenants/{$inativa->id}/acessar")
            ->assertJsonValidationErrors('tenant');
    }

    public function test_bloqueia_acessar_para_quem_nao_e_super_admin(): void
    {
        Sanctum::actingAs($this->admin());

        $outra = Tenant::query()->create([
            'nome' => 'Clínica Vizinha',
            'slug' => 'clinica-vizinha',
            'cnpj' => null,
            'ativo' => true,
        ]);

        $this->postJson("/api/tenants/{$outra->id}/acessar")->assertForbidden();
    }

    private function payload(array $sobrescreve = []): array
    {
        return array_merge([
            'nome' => 'Clínica Nova',
            'slug' => 'clinica-nova',
            'cnpj' => null,
            'ativo' => true,
            'admin' => [
                'name' => 'Admin Nova',
                'email' => 'admin@clinica-nova.test',
                'password' => 'senha-forte-123',
            ],
        ], $sobrescreve);
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
    }

    private function superAdmin(): User
    {
        $user = $this->admin();
        $user->forceFill(['super_admin' => true])->save();

        return $user->refresh();
    }
}
