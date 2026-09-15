<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Quem já configurava a automação da Unimed passa a configurar as credenciais
 * de convênio.
 *
 * Concede a TODO papel que tem `configuracoes.unimed.manage`, e não só ao
 * `admin` como fez a migration de 2026_08_03: papéis criados depois daquela
 * data podem ter recebido a permissão antiga, e nenhum deles pode perder acesso
 * na virada da tela. É o sexto critério de aceite da change.
 *
 * A permissão antiga continua existindo e sendo aceita por um ciclo; sai no
 * change de limpeza.
 */
return new class extends Migration
{
    private const ANTIGA = 'configuracoes.unimed.manage';

    private const NOVA = 'configuracoes.convenios.manage';

    public function up(): void
    {
        $nova = Permission::findOrCreate(self::NOVA, 'web');

        $papeis = Role::query()
            ->whereHas('permissions', fn ($query) => $query
                ->where('name', self::ANTIGA)
                ->where('guard_name', 'web'))
            ->get();

        // Sem fallback para criar o `admin`: papel aqui é por tenant, e um
        // `Role::findOrCreate('admin', 'web')` nasceria sem `tenant_id`, colidindo
        // com os papéis que o RoleSeeder e a criação de clínica montam depois.
        // Instalação nova não precisa desta migration: o `admin` já recebe a
        // permissão pelo RoleCatalog.
        foreach ($papeis as $papel) {
            $papel->givePermissionTo($nova);
        }
    }

    public function down(): void
    {
        $nova = Permission::query()
            ->where('name', self::NOVA)
            ->where('guard_name', 'web')
            ->first();

        if (! $nova) {
            return;
        }

        foreach (Role::query()->get() as $papel) {
            $papel->revokePermissionTo($nova);
        }

        $nova->delete();
    }
};
