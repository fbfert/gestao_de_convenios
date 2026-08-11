<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provisiona o administrador inicial (Felipe B. Fert) direto no schema.
 *
 * Por que uma migration e nao apenas o UserSeeder: `deploy/entrypoint.sh` roda
 * somente `php artisan migrate --force` no servidor, nunca `db:seed`. Sem isso
 * um servidor novo sobe sem nenhuma conta para o primeiro login.
 *
 * Idempotencia: se o e-mail ja existir, a senha NAO e sobrescrita — so garante
 * tenant, papel `admin` e `ativo`. Assim uma troca de senha feita pelo proprio
 * usuario nao e revertida por um `migrate` posterior.
 *
 * O papel `admin` e por tenant (Spatie em modo teams, team_foreign_key =
 * tenant_id). Os roles sao consultados por `tenant_id` explicito em vez de
 * `Role::findOrCreate` para nao reabrir o defeito corrigido na migration
 * 2026_08_05_100000 (role global com tenant_id nulo sombreando o do tenant).
 */
return new class extends Migration
{
    private const EMAIL = 'fbfert@gmail.com';

    private const NOME = 'Felipe B. Fert';

    private const SENHA_INICIAL = 'polenta22POLENTA@@';

    public function up(): void
    {
        $tenant = Tenant::query()->orderBy('id')->first();

        if (! $tenant) {
            // Base ainda sem tenant: nesse cenario a carga inicial vem do
            // `php artisan db:seed`, e o UserSeeder ja cria essa mesma conta.
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        foreach (PermissionCatalog::all() as $permissao) {
            Permission::findOrCreate($permissao, 'web');
        }

        $role = $this->papelAdmin($tenant);

        if ($role->permissions()->count() === 0) {
            // Papel recem-criado. Um `admin` que ja tem permissoes fica
            // intocado: pode ter sido ajustado de proposito na tela Permissoes.
            $role->syncPermissions(PermissionCatalog::all());
        }

        $user = User::query()->where('email', self::EMAIL)->first();

        if ($user) {
            $user->name = self::NOME;
            $user->ativo = true;
            $user->tenant_id = $user->tenant_id ?: $tenant->id;
            $user->save();
        } else {
            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => self::NOME,
                'email' => self::EMAIL,
                'password' => self::SENHA_INICIAL,
                'ativo' => true,
            ]);
        }

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        $user = User::query()->where('email', self::EMAIL)->first();

        if (! $user) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($user->tenant_id);

        $user->roles()->detach();
        $user->tokens()->delete();
        $user->delete();

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    private function papelAdmin(Tenant $tenant): Role
    {
        $role = Role::query()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->first();

        if ($role) {
            return $role;
        }

        return Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);
    }
};
