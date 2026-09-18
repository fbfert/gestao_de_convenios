<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * As quatro permissões de Relatórios chegando às clínicas que já existem.
 *
 * A migration roda antes de existir papel algum no banco, então aqui ela é
 * chamada à mão depois do arranjo — mesma técnica do
 * `SincronizaPermissaoConveniosTest`.
 */
class SincronizaPermissoesRelatoriosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const ARQUIVO = __DIR__.'/../../database/migrations/2026_09_18_120000_sync_relatorios_permissions_to_existing_roles.php';

    private const TODAS = [
        'relatorios.operacao',
        'relatorios.financeiro',
        'relatorios.automacoes',
        'relatorios.uso',
    ];

    public function test_admin_recebe_as_quatro(): void
    {
        $papel = $this->papel('admin');
        $papel->revokePermissionTo(self::TODAS);

        $this->migrar();

        foreach (self::TODAS as $permissao) {
            $this->assertTrue($this->papel('admin')->hasPermissionTo($permissao), $permissao);
        }
    }

    /** O financeiro é o recorte que motiva quatro permissões em vez de uma. */
    public function test_funcionario_recebe_operacao_e_automacoes_e_nao_o_resto(): void
    {
        $papel = $this->papel('funcionario');
        $papel->revokePermissionTo(self::TODAS);

        $this->migrar();

        $papel = $this->papel('funcionario');

        $this->assertTrue($papel->hasPermissionTo('relatorios.operacao'));
        $this->assertTrue($papel->hasPermissionTo('relatorios.automacoes'));
        $this->assertFalse($papel->hasPermissionTo('relatorios.financeiro'));
        $this->assertFalse($papel->hasPermissionTo('relatorios.uso'));
    }

    public function test_profissional_nao_recebe_nenhuma(): void
    {
        $this->migrar();

        $papel = $this->papel('profissional');

        foreach (self::TODAS as $permissao) {
            $this->assertFalse($papel->hasPermissionTo($permissao), $permissao);
        }
    }

    /** Papel criado pela clínica decide na tela de Perfis e Permissões, não aqui. */
    public function test_papel_customizado_nao_recebe_nada(): void
    {
        $tenantId = $this->tenantId();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        $recepcao = Role::query()->create([
            'name' => 'recepcao',
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);

        $this->migrar();

        foreach (self::TODAS as $permissao) {
            $this->assertFalse($recepcao->fresh()->hasPermissionTo($permissao), $permissao);
        }
    }

    public function test_sincronizacao_e_idempotente(): void
    {
        $papel = $this->papel('admin');
        $papel->revokePermissionTo(self::TODAS);

        $this->migrar();
        $this->migrar();

        $this->assertSame(
            1,
            $this->papel('admin')->permissions->where('name', 'relatorios.operacao')->count(),
        );
    }

    /**
     * O `admin` de uma clínica é papel por tenant. A migration não pode criar um
     * `admin` sem `tenant_id` para pendurar a permissão.
     */
    public function test_migracao_nao_cria_papel_sem_tenant(): void
    {
        $antes = Role::query()->whereNull('tenant_id')->count();

        $this->migrar();

        $this->assertSame($antes, Role::query()->whereNull('tenant_id')->count());
    }

    /** Instalação nova não depende da migration: o RoleCatalog já entrega. */
    public function test_instalacao_nova_ja_nasce_com_as_permissoes(): void
    {
        foreach (self::TODAS as $permissao) {
            $this->assertContains($permissao, PermissionCatalog::all(), $permissao);
            $this->assertContains($permissao, RoleCatalog::permissoesDe('admin'), $permissao);
            $this->assertTrue($this->papel('admin')->hasPermissionTo($permissao), $permissao);
        }

        $this->assertSame(
            ['relatorios.automacoes', 'relatorios.operacao'],
            collect(RoleCatalog::permissoesDe('funcionario'))
                ->filter(fn (string $p) => str_starts_with($p, 'relatorios.'))
                ->sort()
                ->values()
                ->all(),
        );
    }

    /** Toda permissão do catálogo tem rótulo legível — a tela mostra o rótulo, não o nome técnico. */
    public function test_as_permissoes_tem_rotulo_no_catalogo(): void
    {
        foreach (self::TODAS as $permissao) {
            $this->assertNotSame($permissao, PermissionCatalog::rotuloDe($permissao), $permissao);
        }
    }

    private function papel(string $nome): Role
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenantId());

        return Role::query()
            ->where('tenant_id', $this->tenantId())
            ->where('name', $nome)
            ->firstOrFail();
    }

    private function tenantId(): int
    {
        return (int) Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
    }

    private function migrar(): void
    {
        (require self::ARQUIVO)->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::TODAS as $permissao) {
            Permission::findOrCreate($permissao, 'web');
        }
    }
}
