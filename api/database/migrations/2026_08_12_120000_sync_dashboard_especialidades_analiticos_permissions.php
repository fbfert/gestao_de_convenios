<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cria `dashboard.especialidades` e `dashboard.analiticos`, que passam a
 * alimentar os cartoes de metrica das telas de grupo (Cadastros e Operacao
 * Convenios). Sem elas o /dashboard nao tinha bloco algum para esses dois
 * itens e as telas novas apareceriam furadas.
 *
 * Em vez de fixar nomes de papel, a concessao e derivada de uma permissao
 * vizinha ja existente:
 *   - quem enxerga o bloco de Profissionais passa a enxergar Especialidades;
 *   - quem ve a conciliacao da clinica inteira passa a enxergar Analiticos.
 * Assim um papel ajustado a mao na tela de Permissoes nao ganha visibilidade
 * que o administrador tinha tirado de proposito.
 *
 * O gatilho de Analiticos e `conciliacoes.view`, e nao `dashboard.guias`: o
 * analitico e o demonstrativo de pagamento da operadora, dado financeiro do
 * consultorio todo. O papel `profissional` tem `dashboard.guias` mas so as
 * variantes `viewOwn`, entao derivar de guias daria a ele um numero agregado
 * que o RoleSeeder nunca lhe concede.
 *
 * Os papeis sao consultados por `tenant_id` explicito e o team id do Spatie e
 * definido a cada tenant. `Role::findOrCreate` sem team id foi justamente o
 * defeito corrigido na migration 2026_08_05_100000 (papel global com
 * tenant_id nulo sombreando o do tenant) e nao pode voltar aqui.
 */
return new class extends Migration
{
    /** permissao nova => permissao vizinha que serve de gatilho */
    private const CONCESSOES = [
        'dashboard.especialidades' => 'dashboard.profissionais',
        'dashboard.analiticos' => 'conciliacoes.view',
    ];

    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);

        foreach (array_keys(self::CONCESSOES) as $nova) {
            Permission::findOrCreate($nova, 'web');
        }

        foreach (Tenant::query()->get() as $tenant) {
            $registrar->setPermissionsTeamId($tenant->id);

            $papeis = Role::query()
                ->where('tenant_id', $tenant->id)
                ->where('guard_name', 'web')
                ->get();

            foreach ($papeis as $papel) {
                foreach (self::CONCESSOES as $nova => $gatilho) {
                    if ($papel->hasPermissionTo($gatilho)) {
                        $papel->givePermissionTo($nova);
                    }
                }
            }
        }

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $permissoes = Permission::query()
            ->whereIn('name', array_keys(self::CONCESSOES))
            ->where('guard_name', 'web')
            ->get();

        foreach (Tenant::query()->get() as $tenant) {
            $registrar->setPermissionsTeamId($tenant->id);

            Role::query()
                ->where('tenant_id', $tenant->id)
                ->where('guard_name', 'web')
                ->get()
                ->each(function (Role $papel) use ($permissoes) {
                    $permissoes->each(fn (Permission $p) => $papel->revokePermissionTo($p));
                });
        }

        $registrar->setPermissionsTeamId(null);

        $permissoes->each(fn (Permission $p) => $p->delete());

        $registrar->forgetCachedPermissions();
    }
};
