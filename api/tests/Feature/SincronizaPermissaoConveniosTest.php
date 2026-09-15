<?php

namespace Tests\Feature;

use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Sexto critério de aceite: quem já administrava a automação da Unimed não pode
 * perder acesso na virada para a tela de credenciais por convênio.
 *
 * A migration roda antes de existir papel algum no banco, então aqui ela é
 * chamada à mão depois do arranjo — mesma técnica do
 * `MigracaoCredenciaisPorConvenioTest`.
 */
class SincronizaPermissaoConveniosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const ARQUIVO = __DIR__.'/../../database/migrations/2026_09_15_100002_sync_convenios_manage_permission_to_existing_roles.php';

    private const ANTIGA = 'configuracoes.unimed.manage';

    private const NOVA = 'configuracoes.convenios.manage';

    public function test_papel_com_a_permissao_antiga_recebe_a_nova(): void
    {
        $papel = Role::query()->where('name', 'admin')->firstOrFail();
        $papel->revokePermissionTo(self::NOVA);

        $this->assertTrue($papel->fresh()->hasPermissionTo(self::ANTIGA));
        $this->assertFalse($papel->fresh()->hasPermissionTo(self::NOVA));

        $this->migrar();

        $this->assertTrue($papel->fresh()->hasPermissionTo(self::NOVA));
    }

    /**
     * Não é uma concessão em massa: papel que nunca configurou a automação
     * continua sem a permissão nova.
     */
    public function test_papel_sem_a_permissao_antiga_nao_ganha_a_nova(): void
    {
        $papel = Role::query()->where('name', 'funcionario')->firstOrFail();

        $this->assertFalse($papel->hasPermissionTo(self::ANTIGA));

        $this->migrar();

        $this->assertFalse($papel->fresh()->hasPermissionTo(self::NOVA));
    }

    /** Segunda passagem não duplica a concessão nem estoura. */
    public function test_sincronizacao_e_idempotente(): void
    {
        $papel = Role::query()->where('name', 'admin')->firstOrFail();
        $papel->revokePermissionTo(self::NOVA);

        $this->migrar();
        $this->migrar();

        $this->assertSame(
            1,
            $papel->fresh()->permissions->where('name', self::NOVA)->count(),
        );
    }

    /**
     * Instalação nova não depende da migration: o `admin` já nasce com a
     * permissão pelo `RoleCatalog`, que é o caminho do seeder e o da criação de
     * clínica pela tela de gestão.
     */
    public function test_instalacao_nova_ja_nasce_com_a_permissao(): void
    {
        $this->assertContains(self::NOVA, RoleCatalog::permissoesDe('admin'));
        $this->assertContains(self::NOVA, PermissionCatalog::all());
        $this->assertTrue(
            Role::query()->where('name', 'admin')->firstOrFail()->hasPermissionTo(self::NOVA),
        );
    }

    /**
     * O `admin` de uma clínica é papel por tenant. A migration não pode criar um
     * `admin` sem `tenant_id` para pendurar a permissão — foi o que quebrou
     * catorze testes no primeiro rascunho desta seção.
     */
    public function test_migracao_nao_cria_papel_sem_tenant(): void
    {
        $antes = Role::query()->whereNull('tenant_id')->count();

        $this->migrar();

        $this->assertSame($antes, Role::query()->whereNull('tenant_id')->count());
    }

    private function migrar(): void
    {
        (require self::ARQUIVO)->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate(self::NOVA, 'web');
    }
}
