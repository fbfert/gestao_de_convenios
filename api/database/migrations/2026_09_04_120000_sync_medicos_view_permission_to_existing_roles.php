<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cria `medicos.view`, permissao de leitura separada de `medicos.manage`.
 *
 * Ate aqui so existia uma permissao para o recurso de medicos, cobrindo tanto
 * consulta quanto cadastro/edicao — diferente do padrao ja usado em
 * guias/antecipacoes/lancamentos/solicitacoes, que separam `.view`/`.viewOwn`
 * de `.manage`. Isso deixava GET /medicos preso a `medicos.manage`: um papel
 * customizado com acesso a Solicitacoes mas sem `medicos.manage` quebraria ao
 * carregar o campo de medico do formulario.
 *
 * Segue o mesmo formato de 2026_08_12_120000: a concessao e derivada de quem
 * ja tem `medicos.manage`, para nao dar visibilidade a um papel que o
 * administrador tirou de proposito na tela de Permissoes.
 */
return new class extends Migration
{
    private const NOVA = 'medicos.view';

    private const GATILHO = 'medicos.manage';

    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);

        Permission::findOrCreate(self::NOVA, 'web');

        foreach (Tenant::query()->get() as $tenant) {
            $registrar->setPermissionsTeamId($tenant->id);

            $papeis = Role::query()
                ->where('tenant_id', $tenant->id)
                ->where('guard_name', 'web')
                ->get();

            foreach ($papeis as $papel) {
                if ($papel->hasPermissionTo(self::GATILHO)) {
                    $papel->givePermissionTo(self::NOVA);
                }
            }
        }

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $permissao = Permission::query()
            ->where('name', self::NOVA)
            ->where('guard_name', 'web')
            ->first();

        if ($permissao === null) {
            return;
        }

        foreach (Tenant::query()->get() as $tenant) {
            $registrar->setPermissionsTeamId($tenant->id);

            Role::query()
                ->where('tenant_id', $tenant->id)
                ->where('guard_name', 'web')
                ->get()
                ->each(fn (Role $papel) => $papel->revokePermissionTo($permissao));
        }

        $registrar->setPermissionsTeamId(null);

        $permissao->delete();

        $registrar->forgetCachedPermissions();
    }
};
