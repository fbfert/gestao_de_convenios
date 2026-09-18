<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * As quatro permissões de Relatórios nas clínicas que já existem.
 *
 * Por papel de sistema explícito, e não por derivação de outra permissão: não
 * há permissão anterior que signifique "pode ver relatório" — `dashboard.*` diz
 * respeito ao painel e ao menu, e herdar dela faria o financeiro vazar para
 * quem só precisa ver o bloco de conciliações. Então a concessão repete o
 * RoleCatalog: `admin` recebe as quatro, `funcionario` recebe operação e
 * automações, `profissional` não recebe nenhuma.
 *
 * Papel customizado do tenant fica de fora — quem criou papel próprio decide na
 * tela de Perfis e Permissões —, como as sincronizações anteriores já fazem.
 *
 * Instalação nova não depende desta migration: o RoleCatalog já entrega as
 * permissões pelo seeder e pela criação de clínica.
 */
return new class extends Migration
{
    private const TODAS = [
        'relatorios.operacao',
        'relatorios.financeiro',
        'relatorios.automacoes',
        'relatorios.uso',
    ];

    private const DO_FUNCIONARIO = [
        'relatorios.operacao',
        'relatorios.automacoes',
    ];

    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);

        foreach (self::TODAS as $nome) {
            Permission::findOrCreate($nome, 'web');
        }

        foreach (Tenant::query()->get() as $tenant) {
            // Papel aqui é por tenant (teams do Spatie): sem fixar o time, o
            // findOrCreate nasceria sem `tenant_id` e colidiria com os papéis
            // que o RoleSeeder e a criação de clínica montam.
            $registrar->setPermissionsTeamId($tenant->id);

            Role::findOrCreate('admin', 'web')->givePermissionTo(self::TODAS);
            Role::findOrCreate('funcionario', 'web')->givePermissionTo(self::DO_FUNCIONARIO);
        }

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $permissoes = Permission::query()
            ->whereIn('name', self::TODAS)
            ->where('guard_name', 'web')
            ->get();

        if ($permissoes->isEmpty()) {
            return;
        }

        foreach (Tenant::query()->get() as $tenant) {
            $registrar->setPermissionsTeamId($tenant->id);

            foreach (Role::query()->where('tenant_id', $tenant->id)->where('guard_name', 'web')->get() as $papel) {
                $papel->revokePermissionTo($permissoes);
            }
        }

        $registrar->setPermissionsTeamId(null);

        Permission::query()
            ->whereIn('name', self::TODAS)
            ->where('guard_name', 'web')
            ->delete();

        $registrar->forgetCachedPermissions();
    }
};
