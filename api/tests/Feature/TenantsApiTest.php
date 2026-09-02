<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
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
